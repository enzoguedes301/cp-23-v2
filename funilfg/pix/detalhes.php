<?php
/**
 *  Consulta o status de uma cobrança PIX.
 *  Chamado pelo chat a cada 5s:  detalhes.php?id={txid}
 *  Retorna: { "status": "pending" | "approved" | ... }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../lib-pix.php';

$txid = $_GET['id'] ?? '';
if ($txid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID da transação não informado']);
    exit;
}

$res = pixConsultarStatus($txid);

echo json_encode([
    'ok'     => true,
    'status' => $res['status'] ?? 'pending',
    'txid'   => $txid,
], JSON_UNESCAPED_SLASHES);
