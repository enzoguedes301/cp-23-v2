<?php
/**
 *  WEBHOOK  —  endpoint que o gateway chama quando o pagamento é confirmado.
 *  Configure a URL deste arquivo no painel do seu gateway (e em PIX_WEBHOOK_URL).
 *
 *  Ele apenas marca a transação como 'approved' no storage; o chat, que
 *  consulta detalhes.php a cada 5s, percebe na hora e redireciona.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib-pix.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

// Cada gateway manda o id/status num campo diferente — cobrimos os comuns.
$txid = $body['id']
      ?? $body['txid']
      ?? $body['data']['id']            // Mercado Pago
      ?? $body['transaction']['id']
      ?? '';

$status = strtolower(
    $body['status']
    ?? $body['transaction']['status']
    ?? $body['data']['status']
    ?? ''
);

$pago = in_array($status, ['approved', 'paid', 'confirmed', 'completed'], true);

// Mercado Pago avisa por tipo de evento; confirme consultando a API.
if (!$pago && ($body['type'] ?? '') === 'payment' && $txid !== '') {
    $check = pixConsultarStatus((string) $txid);
    $pago  = (strtolower($check['status'] ?? '') === 'approved');
}

if ($txid !== '' && $pago) {
    pixAtualizarStatus((string) $txid, 'approved');
}

// Responde 200 para o gateway não ficar reenviando.
http_response_code(200);
echo json_encode(['ok' => true, 'received' => $txid !== '']);
