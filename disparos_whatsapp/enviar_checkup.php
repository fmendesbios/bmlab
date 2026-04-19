<?php
require_once '../config.php';
require_once '../auth_permissoes.php';
header('Content-Type: application/json; charset=utf-8');
verificaLogin();

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

if (!isset($dados['pacientes'])) {
    $q = "
        SELECT p.id, p.nome, p.celular AS telefone, MAX(pe.id) AS pedido_id
        FROM pacientes p
        INNER JOIN pedidos pe ON pe.paciente_id = p.id
        WHERE NOT EXISTS (
            SELECT 1 FROM logs_envio le
            WHERE le.tipo = 'checkup'
            AND le.telefone COLLATE utf8mb4_unicode_ci = CONCAT('55', REPLACE(REPLACE(REPLACE(p.celular, '(', ''), ')', ''), '-', '')) COLLATE utf8mb4_unicode_ci
        )
        GROUP BY p.id
        HAVING DATE(MAX(pe.entrada)) = DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ";
    $pacientes = $conn->query($q)->fetch_all(MYSQLI_ASSOC);
    $dados['pacientes'] = array_map(function ($p) {
        return ['id' => $p['id'], 'nome' => $p['nome'], 'telefone' => $p['telefone'], 'pedido_id' => $p['pedido_id']];
    }, $pacientes);
}

if (!is_array($dados['pacientes']) || count($dados['pacientes']) === 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Nenhum paciente para envio.']);
    exit;
}

$resultados = [];

foreach ($dados['pacientes'] as $paciente) {
    $nome = trim($paciente['nome'] ?? '');
    $telefone = normalizarTelefone($paciente['telefone'] ?? '');
    $pedido_id = (int)($paciente['pedido_id'] ?? 0);

    if ($nome === '' || $telefone === '' || !$pedido_id) {
        $resultados[] = ['nome' => $nome, 'telefone' => $telefone, 'status' => 'falha', 'erro' => 'Dados incompletos'];
        continue;
    }

    $data_exame = '';
    $exames = '';
    if ($stmt = $conn->prepare("
        SELECT pe.entrada AS data_pedido, GROUP_CONCAT(e.nome ORDER BY e.nome SEPARATOR ', ') AS exames
        FROM pedidos pe
        LEFT JOIN pedido_itens pi ON pi.pedido_id = pe.id
        LEFT JOIN exames e ON e.id = pi.exame_id
        WHERE pe.id = ?
        GROUP BY pe.id
    ")) {
        $stmt->bind_param('i', $pedido_id);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($info) {
            $data_exame = date('d/m/Y', strtotime($info['data_pedido']));
            $exames = (string)$info['exames'];
        }
    }

    $mensagem = "Olá *{$nome}*, tudo bem? 👋\n\n";
    $mensagem .= "Notamos que já se passaram *90 dias* desde seus últimos exames aqui conosco. 🧪\n\n";
    if ($data_exame !== '') $mensagem .= "*Data do último exame:* {$data_exame}\n";
    if ($exames !== '') $mensagem .= "*Exames realizados:* {$exames}\n\n";
    $mensagem .= "Que tal fazer um *check-up preventivo* e continuar cuidando da sua saúde com a gente? 💙\n\n";
    $mensagem .= "*Braga Mendes Laboratório – Resultados precisos e confiáveis.*";

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
        'payload' => ['numero' => $telefone, 'pedido_id' => $pedido_id],
        'api_resposta' => $mensagem_resposta
    ];
    $retorno_log_json = json_encode($retorno_log, JSON_UNESCAPED_UNICODE);

    try {
        $tipo = 'checkup';
        if ($stmt_log = $conn->prepare("INSERT INTO logs_envio (tipo, nome, telefone, status, retorno) VALUES (?, ?, ?, ?, ?)")) {
            $stmt_log->bind_param("sssss", $tipo, $nome, $telefone, $status, $retorno_log_json);
            $stmt_log->execute();
            $stmt_log->close();
        }
    } catch (\Throwable $e) {
        error_log('Erro ao logar envio checkup: ' . $e->getMessage());
    }

    $resultados[] = ['nome' => $nome, 'telefone' => $telefone, 'status' => $status, 'resposta' => $retorno_log];
}

echo json_encode(['sucesso' => true, 'resultados' => $resultados], JSON_UNESCAPED_UNICODE);
