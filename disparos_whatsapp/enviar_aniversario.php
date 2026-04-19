<?php
require_once '../config.php';
require_once '../auth_permissoes.php';
header('Content-Type: application/json; charset=utf-8');
verificaLogin();

// Configuração da API ChatMix (mantidas conforme ambiente)
$chatmix_token = "DB-3EA-24AF3-9076";
$chatmix_key = "CRMLAB-FA5C3";

function normalizarTelefone($raw) {
    $t = preg_replace('/\D/', '', (string)$raw);
    if ($t === '') return '';
    if (strpos($t, '55') === 0 && strlen($t) >= 13) return $t;
    if (strlen($t) >= 11) return '55' . $t;
    return (strlen($t) >= 9) ? '55' . $t : '';
}

$json = file_get_contents("php://input");
$dados = json_decode($json, true);

// Se não veio POST, assume modo automático (cron)
if (!isset($dados['aniversariantes'])) {
    $hoje = date('m-d');
    $aniversariantes = $conn->query("
        SELECT nome, celular 
        FROM pacientes 
        WHERE DATE_FORMAT(nascimento, '%m-%d') = '$hoje'
    ")->fetch_all(MYSQLI_ASSOC);

    $dados['aniversariantes'] = array_map(function ($p) {
        return ['nome' => ($p['nome'] ?? ''), 'telefone' => ($p['celular'] ?? '')];
    }, $aniversariantes);
}

if (!is_array($dados['aniversariantes']) || count($dados['aniversariantes']) === 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Nenhum aniversariante recebido.']);
    exit;
}

$resultados = [];

foreach ($dados['aniversariantes'] as $pessoa) {
    $nome = trim($pessoa['nome'] ?? '');
    $telefone = normalizarTelefone($pessoa['telefone'] ?? '');

    if ($nome === '' || $telefone === '') {
        $resultados[] = ['nome' => $nome, 'telefone' => $telefone, 'status' => 'falha', 'erro' => 'Nome ou telefone inválido'];
        continue;
    }

    $mensagem = "🎉 Olá *{$nome}*, o Braga Mendes Laboratório deseja a você um FELIZ ANIVERSÁRIO! 🎂\n\nQue seu dia seja repleto de saúde, alegria e muitas realizações!\n\nConte com a gente para cuidar de você em todos os momentos!\n\n*💙 Braga Mendes Laboratório – Resultados precisos e confiáveis.*";

    $payload = [
        'token' => $chatmix_token,
        'key' => $chatmix_key,
        'numero' => $telefone,
        'mensagem' => $mensagem,
        'agendamento' => 'nao'
    ];

    $ch = curl_init("https://api.chatmix.com.br");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    $resposta_decodificada = json_decode($resposta ?? '', true);
    $mensagem_resposta = is_array($resposta_decodificada) ? json_encode($resposta_decodificada, JSON_UNESCAPED_UNICODE) : (string)$resposta;

    $sucesso = ($http_code >= 200 && $http_code < 300) && !$curl_err;
    $status = $sucesso ? 'sucesso' : 'falha';

    $retorno_log = [
        'http_code' => $http_code,
        'curl_error' => $curl_err,
        'payload' => ['numero' => $telefone],
        'api_resposta' => $mensagem_resposta
    ];
    $retorno_log_json = json_encode($retorno_log, JSON_UNESCAPED_UNICODE);

    try {
        $tipo = 'aniversario';
        if ($stmt_log = $conn->prepare("INSERT INTO logs_envio (tipo, nome, telefone, status, retorno) VALUES (?, ?, ?, ?, ?)")) {
            $stmt_log->bind_param("sssss", $tipo, $nome, $telefone, $status, $retorno_log_json);
            $stmt_log->execute();
            $stmt_log->close();
        }
    } catch (\Throwable $e) {
        error_log('Erro ao logar envio aniversario: ' . $e->getMessage());
    }

    $resultados[] = [
        'nome' => $nome,
        'telefone' => $telefone,
        'status' => $status,
        'resposta' => $retorno_log
    ];
}

echo json_encode(['sucesso' => true, 'resultados' => $resultados], JSON_UNESCAPED_UNICODE);
