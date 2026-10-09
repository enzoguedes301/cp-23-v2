<?php
// Servir como JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../credenciais.php';

$cpf = $_GET['cpf'] ?? '';
$cpfLimpo = preg_replace('/\D/', '', $cpf);

if (!$cpfLimpo || strlen($cpfLimpo) !== 11) {
    echo json_encode(['nome' => 'Não disponível', 'cpf' => $cpfLimpo]);
    exit;
}

// CACHE: verificar se já consultou este CPF
$cacheDir = sys_get_temp_dir() . '/cpf_cache';
@mkdir($cacheDir, 0777, true);
$cacheFile = $cacheDir . '/' . $cpfLimpo . '.json';
$cacheTime = 86400; // 24 horas

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if ($cached && isset($cached['nome'])) {
        echo json_encode([
            'nome' => $cached['nome'],
            'cpf' => $cpfLimpo,
            'email' => $cached['email'] ?? '',
            'telefone' => $cached['tel'] ?? '',
            'nascimento' => $cached['nasc'] ?? ''
        ]);
        exit;
    }
}

// Consultar API (credenciais em credenciais.php)
$api_url = CPF_API_URL . $cpfLimpo . '?token=' . CPF_API_TOKEN;

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// Extrair dados da Serasa se disponível
if (isset($data['resultados']['serasa']['dados'])) {
    $serasa = $data['resultados']['serasa']['dados'];
    $resultado = [
        'nome' => $serasa['NOME'] ?? 'Não disponível',
        'cpf' => $cpfLimpo,
        'email' => '',
        'telefone' => (!empty($serasa['TELEFONES']) && is_array($serasa['TELEFONES']))
                     ? preg_replace('/\D/', '', $serasa['TELEFONES'][0])
                     : '',
        'nascimento' => $serasa['NASC'] ?? ''
    ];

    // CACHE: salvar resultado
    if (isset($cacheFile)) {
        @file_put_contents($cacheFile, json_encode($resultado));
    }

    echo json_encode($resultado);
} else {
    echo json_encode(['nome' => 'Não disponível', 'cpf' => $cpfLimpo]);
}
