<?php
/**
 * =====================================================================
 *  BIBLIOTECA PIX  —  storage + adaptadores de gateway
 * =====================================================================
 *  Você normalmente NÃO precisa editar este arquivo.
 *  As credenciais ficam em config.php.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

/* =====================================================================
 *  STORAGE  —  guarda cada transação num arquivo JSON
 * ================================================================== */

function pixStorageDir(): string {
    $dir = PIX_STORAGE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function pixSalvarTransacao(string $txid, array $dados): void {
    $dados['txid']       = $txid;
    $dados['updated_at'] = time();
    if (!isset($dados['created_at'])) {
        $dados['created_at'] = time();
    }
    $arquivo = pixStorageDir() . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '', $txid) . '.json';
    file_put_contents($arquivo, json_encode($dados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function pixLerTransacao(string $txid): ?array {
    $arquivo = pixStorageDir() . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '', $txid) . '.json';
    if (!is_file($arquivo)) {
        return null;
    }
    $json = json_decode(file_get_contents($arquivo), true);
    return is_array($json) ? $json : null;
}

function pixAtualizarStatus(string $txid, string $status): void {
    $t = pixLerTransacao($txid);
    if ($t === null) {
        $t = [];
    }
    $t['status'] = $status;
    pixSalvarTransacao($txid, $t);
}

/* =====================================================================
 *  HELPERS
 * ================================================================== */

/** Converte "R$ 337,29" | "337,29" | "337.29" | 337.29  ->  337.29 (float) */
function pixParsearValor($valor): float {
    if (is_numeric($valor)) {
        return round((float) $valor, 2);
    }
    $s = (string) $valor;
    $s = preg_replace('/[^0-9,\.]/', '', $s);   // tira "R$", espaços, etc.
    if (strpos($s, ',') !== false) {
        // formato brasileiro: ponto = milhar, vírgula = decimal
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    $f = (float) $s;
    return $f > 0 ? round($f, 2) : (float) PIX_VALOR_PADRAO;
}

/** Faz uma requisição HTTP e devolve [httpCode, bodyDecodedArray, rawBody] */
function pixHttp(string $metodo, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [0, ['_curl_error' => $err], ''];
    }
    $decoded = json_decode($raw, true);
    return [$code, is_array($decoded) ? $decoded : [], $raw];
}

/* =====================================================================
 *  CRIAR COBRANÇA  —  roteia para o gateway configurado
 *  Retorno padronizado:
 *    [ 'ok'=>bool, 'code'=>'copia e cola', 'txid'=>'id', 'qrCode'=>'base64?', 'error'=>'?' ]
 * ================================================================== */

function pixCriarCobranca(array $cliente): array {
    $valor = pixParsearValor($cliente['valorTotal'] ?? $cliente['valor'] ?? '');
    $cpf   = preg_replace('/\D/', '', (string) ($cliente['cpf'] ?? ''));
    $nome  = trim((string) ($cliente['name'] ?? $cliente['nome'] ?? 'Cliente'));
    $email = trim((string) ($cliente['email'] ?? ''));

    if (PIX_MODO === 'mock') {
        return pixGatewayMock($valor, $cpf, $nome, $email);
    }

    switch (PIX_GATEWAY) {
        case 'mercadopago': return pixGatewayMercadoPago($valor, $cpf, $nome, $email);
        case 'pushinpay':   return pixGatewayPushinPay($valor, $cpf, $nome, $email);
        case 'custom':      return pixGatewayCustom($valor, $cpf, $nome, $email);
        default:
            return ['ok' => false, 'error' => 'Gateway desconhecido em config.php: ' . PIX_GATEWAY];
    }
}

/* =====================================================================
 *  CONSULTAR STATUS  —  roteia para o gateway configurado
 *  Retorno padronizado:  [ 'status' => 'pending'|'approved'|... ]
 * ================================================================== */

function pixConsultarStatus(string $txid): array {
    $t = pixLerTransacao($txid);

    // Se o webhook já marcou como pago, responde na hora.
    if ($t && in_array(strtolower($t['status'] ?? ''), ['approved', 'paid'], true)) {
        return ['status' => 'approved'];
    }

    if (PIX_MODO === 'mock') {
        return pixStatusMock($txid, $t);
    }

    switch (PIX_GATEWAY) {
        case 'mercadopago': return pixStatusMercadoPago($txid, $t);
        case 'pushinpay':   return pixStatusPushinPay($txid, $t);
        case 'custom':      return pixStatusCustom($txid, $t);
        default:            return ['status' => 'pending'];
    }
}

/* =====================================================================
 *  ADAPTADOR: MOCK  (teste local)
 * ================================================================== */

function pixGatewayMock(float $valor, string $cpf, string $nome, string $email): array {
    $txid = 'MOCK' . time() . rand(1000, 9999);
    // BR Code de exemplo (NÃO é pagável — só para exibir QR e copia-e-cola)
    $code = '00020126580014br.gov.bcb.pix0136' . strtolower($txid)
          . '520400005303986540' . number_format($valor, 2, '', '')
          . '5802BR5913TESTE LOCAL6009LOCALHOST62070503***6304ABCD';

    pixSalvarTransacao($txid, [
        'status'   => 'pending',
        'valor'    => $valor,
        'cpf'      => $cpf,
        'nome'     => $nome,
        'gateway'  => 'mock',
        'attempts' => 0,
    ]);

    return ['ok' => true, 'code' => $code, 'txid' => $txid, 'qrCode' => ''];
}

function pixStatusMock(string $txid, ?array $t): array {
    // Mock NÃO auto-aprova: a tela de PIX só avança com pagamento real
    // (via gateway real + webhook). Sem redirect antes do pagamento.
    return ['status' => 'pending'];
}

/* =====================================================================
 *  ADAPTADOR: MERCADO PAGO
 *  Docs: https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
 * ================================================================== */

function pixGatewayMercadoPago(float $valor, string $cpf, string $nome, string $email): array {
    if (MP_ACCESS_TOKEN === '') {
        return ['ok' => false, 'error' => 'MP_ACCESS_TOKEN vazio em config.php'];
    }

    $partes = explode(' ', $nome, 2);
    $payload = [
        'transaction_amount' => $valor,
        'description'        => PIX_DESCRICAO,
        'payment_method_id'  => 'pix',
        'payer' => [
            'email'      => $email !== '' ? $email : ('cliente' . substr($cpf, 0, 6) . '@email.com'),
            'first_name' => $partes[0] ?? 'Cliente',
            'last_name'  => $partes[1] ?? '.',
            'identification' => ['type' => 'CPF', 'number' => $cpf],
        ],
    ];

    [$code, $resp] = pixHttp('POST', 'https://api.mercadopago.com/v1/payments', [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . uniqid('pix_', true),
    ], $payload);

    $trans = $resp['point_of_interaction']['transaction_data'] ?? null;
    if ($code === 201 && $trans && !empty($trans['qr_code'])) {
        $txid = (string) $resp['id'];
        pixSalvarTransacao($txid, [
            'status'  => 'pending',
            'valor'   => $valor,
            'cpf'     => $cpf,
            'gateway' => 'mercadopago',
        ]);
        return [
            'ok'     => true,
            'code'   => $trans['qr_code'],                  // copia e cola
            'qrCode' => $trans['qr_code_base64'] ?? '',     // imagem base64 (opcional)
            'txid'   => $txid,
        ];
    }

    $msg = $resp['message'] ?? ($resp['_curl_error'] ?? 'Falha ao criar cobrança no Mercado Pago');
    return ['ok' => false, 'error' => $msg];
}

function pixStatusMercadoPago(string $txid, ?array $t): array {
    if (MP_ACCESS_TOKEN === '') {
        return ['status' => 'pending'];
    }
    [$code, $resp] = pixHttp('GET', 'https://api.mercadopago.com/v1/payments/' . $txid, [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
    ]);
    // status do MP: pending | approved | authorized | in_process | rejected | cancelled
    $status = strtolower($resp['status'] ?? 'pending');
    if ($status === 'approved') {
        pixAtualizarStatus($txid, 'approved');
    }
    return ['status' => $status];
}

/* =====================================================================
 *  ADAPTADOR: PUSHINPAY
 *  Docs: https://docs.pushinpay.com.br  (valor em CENTAVOS)
 * ================================================================== */

function pixGatewayPushinPay(float $valor, string $cpf, string $nome, string $email): array {
    if (PUSHINPAY_TOKEN === '') {
        return ['ok' => false, 'error' => 'PUSHINPAY_TOKEN vazio em config.php'];
    }

    $payload = ['value' => (int) round($valor * 100)];   // centavos
    if (PIX_WEBHOOK_URL !== '') {
        $payload['webhook_url'] = PIX_WEBHOOK_URL;
    }

    [$code, $resp] = pixHttp('POST', 'https://api.pushinpay.com.br/api/pix/cashIn', [
        'Authorization: Bearer ' . PUSHINPAY_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ], $payload);

    if (($code === 200 || $code === 201) && !empty($resp['qr_code'])) {
        $txid = (string) ($resp['id'] ?? uniqid('pp_'));
        pixSalvarTransacao($txid, [
            'status'  => 'pending',
            'valor'   => $valor,
            'cpf'     => $cpf,
            'gateway' => 'pushinpay',
        ]);
        return [
            'ok'     => true,
            'code'   => $resp['qr_code'],                    // copia e cola
            'qrCode' => $resp['qr_code_base64'] ?? '',       // imagem base64 (opcional)
            'txid'   => $txid,
        ];
    }

    $msg = $resp['message'] ?? ($resp['_curl_error'] ?? 'Falha ao criar cobrança na PushinPay');
    return ['ok' => false, 'error' => $msg];
}

function pixStatusPushinPay(string $txid, ?array $t): array {
    if (PUSHINPAY_TOKEN === '') {
        return ['status' => 'pending'];
    }
    [$code, $resp] = pixHttp('GET', 'https://api.pushinpay.com.br/api/transactions/' . $txid, [
        'Authorization: Bearer ' . PUSHINPAY_TOKEN,
        'Accept: application/json',
    ]);
    // status PushinPay: created | paid | expired
    $raw = strtolower($resp['status'] ?? 'pending');
    $status = ($raw === 'paid') ? 'approved' : 'pending';
    if ($status === 'approved') {
        pixAtualizarStatus($txid, 'approved');
    }
    return ['status' => $status];
}

/* =====================================================================
 *  ADAPTADOR: CUSTOM  (template para qualquer outro gateway)
 *  Ajuste os campos conforme a documentação da sua API.
 * ================================================================== */

function pixGatewayCustom(float $valor, string $cpf, string $nome, string $email): array {
    if (CUSTOM_API_URL === '' || CUSTOM_API_TOKEN === '') {
        return ['ok' => false, 'error' => 'CUSTOM_API_URL/TOKEN vazios em config.php'];
    }

    // >>> AJUSTE o payload conforme sua API <<<
    $payload = [
        'amount'      => $valor,
        'document'    => $cpf,
        'name'        => $nome,
        'email'       => $email,
        'description' => PIX_DESCRICAO,
    ];

    [$code, $resp] = pixHttp('POST', CUSTOM_API_URL, [
        'Authorization: Bearer ' . CUSTOM_API_TOKEN,
        'Content-Type: application/json',
    ], $payload);

    // >>> AJUSTE os nomes dos campos conforme a resposta da sua API <<<
    $copiaECola = $resp['qr_code'] ?? $resp['pix_code'] ?? $resp['emv'] ?? '';
    $id         = $resp['id'] ?? $resp['txid'] ?? '';

    if (($code === 200 || $code === 201) && $copiaECola !== '') {
        pixSalvarTransacao((string) $id, [
            'status'  => 'pending',
            'valor'   => $valor,
            'cpf'     => $cpf,
            'gateway' => 'custom',
        ]);
        return [
            'ok'     => true,
            'code'   => $copiaECola,
            'qrCode' => $resp['qr_code_base64'] ?? '',
            'txid'   => (string) $id,
        ];
    }

    $msg = $resp['message'] ?? ($resp['_curl_error'] ?? 'Falha ao criar cobrança (custom)');
    return ['ok' => false, 'error' => $msg];
}

function pixStatusCustom(string $txid, ?array $t): array {
    if (CUSTOM_API_URL === '' || CUSTOM_API_TOKEN === '') {
        return ['status' => 'pending'];
    }
    // >>> AJUSTE a URL de consulta conforme sua API <<<
    [$code, $resp] = pixHttp('GET', rtrim(CUSTOM_API_URL, '/') . '/' . $txid, [
        'Authorization: Bearer ' . CUSTOM_API_TOKEN,
    ]);
    // >>> AJUSTE: mapeie o status da sua API para 'approved' quando pago <<<
    $raw = strtolower($resp['status'] ?? 'pending');
    $status = in_array($raw, ['paid', 'approved', 'confirmed', 'completed'], true) ? 'approved' : 'pending';
    if ($status === 'approved') {
        pixAtualizarStatus($txid, 'approved');
    }
    return ['status' => $status];
}
