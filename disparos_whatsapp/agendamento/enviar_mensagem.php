<?php
require_once '../../config.php';
require_once '../../auth_permissoes.php';
verificaLogin();
header('Content-Type: application/json; charset=utf-8');

// Recebe os dados via JSON
$dados = json_decode(file_get_contents("php://input"), true);

// Validação
if (empty($dados['telefone']) || empty($dados['mensagem'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Telefone ou mensagem ausentes.']);
    exit;
}

$telefone = preg_replace('/\D/', '', $dados['telefone']);
if (strlen($telefone) === 11) {
    $telefone = '55' . $telefone;
}

$nome = $dados['nome'] ?? ''; // <- Nome opcional, se enviado

$chatmix_token = "DB-3EA-24AF3-9076";
$chatmix_key   = "CRMLAB-FA5C3";

$payload = [
    'token'     => $chatmix_token,
    'key'       => $chatmix_key,
    'numero'    => $telefone,
    'mensagem'  => $dados['mensagem'],
    'agendamento' => 'nao',
    'arquivos'  => []
];

$ch = curl_init("https://api.chatmix.com.br");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$resposta = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resposta_decodificada = json_decode($resposta, true);
$mensagem_resposta = is_array($resposta_decodificada) ? json_encode($resposta_decodificada) : $resposta;

$sucesso_envio = ($http_code == 200);

// Log no banco
$tipo = 'mensagem';
$status = $sucesso_envio ? 'sucesso' : 'falha';
$retorno = $mensagem_resposta;

$stmt_log = $conn->prepare("INSERT INTO logs_envio (tipo, nome, telefone, status, retorno) VALUES (?, ?, ?, ?, ?)");
$stmt_log->bind_param("sssss", $tipo, $nome, $telefone, $status, $retorno);
$stmt_log->execute();

// Retorno
if ($http_code == 200) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'erro' => $resposta]);
}
