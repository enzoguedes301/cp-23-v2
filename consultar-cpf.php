<?php
/**
 * Consulta CPF via API Gibson Corp
 * Retorna dados do contribuinte para preencher o formulário
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/credenciais.php';

$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CPF inválido']);
    exit;
}

// CACHE: verificar se já consultou este CPF
$cacheDir = sys_get_temp_dir() . '/cpf_cache';
@mkdir($cacheDir, 0777, true);
$cacheFile = $cacheDir . '/' . $cpf . '.json';
$cacheTime = 86400; // 24 horas

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if ($cached && $cached['ok']) {
        echo json_encode($cached);
        exit;
    }
}

// Credenciais vêm de credenciais.php (raiz do projeto)
$api_url = CPF_API_URL . $cpf . '?token=' . CPF_API_TOKEN;

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao consultar API: ' . $error]);
    exit;
}

$data = json_decode($response, true);

// Verifica se o CPF foi encontrado em qualquer base
$encontrado = false;
$nome = '';
$nasc = '';
$mae = '';
$telefones = [];
$poder_aquisitivo = '';
$renda = '';

if (isset($data['resultados']['serasa']['dados'])) {
    $serasa = $data['resultados']['serasa']['dados'];
    $encontrado = true;
    $nome = $serasa['NOME'] ?? '';
    $nasc = $serasa['NASC'] ?? '';
    $mae = $serasa['NOME_MAE'] ?? '';
    $telefones = $serasa['TELEFONES'] ?? [];
    $poder_aquisitivo = $serasa['PODER_AQUISITIVO'] ?? '';
    $renda = $serasa['RENDA'] ?? '';
}

// Se não encontrou em Serasa, procura em outras bases
if (!$encontrado && isset($data['resultados'])) {
    foreach ($data['resultados'] as $base => $resultado) {
        if (isset($resultado['dados']) && is_array($resultado['dados'])) {
            $encontrado = true;
            $nome = $resultado['dados']['NOME'] ?? '';
            if (!empty($resultado['dados']['TELEFONES'])) {
                $telefones = $resultado['dados']['TELEFONES'];
            }
            break;
        }
    }
}

if (!$encontrado) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'CPF não encontrado em nenhuma base']);
    exit;
}

// Formata telefone (pega o primeiro)
$telefone = '';
if (is_array($telefones) && count($telefones) > 0) {
    $telefone = preg_replace('/\D/', '', $telefones[0]);
}

$resultado = [
    'ok'    => true,
    'cpf'   => $cpf,
    'nome'  => $nome,
    'nasc'  => $nasc,
    'mae'   => $mae,
    'tel'   => $telefone,
    'poder_aquisitivo' => $poder_aquisitivo,
    'renda' => $renda,
    'tempo_ms' => $data['tempo_total_ms'] ?? 0,
];

// CACHE: salvar resultado
if (isset($cacheFile)) {
    @file_put_contents($cacheFile, json_encode($resultado));
}

echo json_encode($resultado, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
