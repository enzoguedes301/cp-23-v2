<?php
/**
 *  Gera a cobrança PIX.  Chamado pelo chat via POST (JSON).
 *  A lógica real está em lib-pix.php; as credenciais em config.php.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/lib-pix.php';

$cliente = json_decode(file_get_contents('php://input'), true);
if (!is_array($cliente)) {
    $cliente = [];
}

$res = pixCriarCobranca($cliente);

if (empty($res['ok'])) {
    http_response_code(200); // 200 para o front ler a mensagem; ok=false sinaliza erro
    echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Erro ao gerar PIX'], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok'     => true,
    'code'   => $res['code'],        // copia e cola (BR Code) — o front gera o QR a partir daqui
    'qrCode' => $res['qrCode'] ?? '', // imagem base64 (se o gateway fornecer)
    'txid'   => $res['txid'],        // id para o monitoramento consultar
], JSON_UNESCAPED_SLASHES);
