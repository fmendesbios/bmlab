<?php
require_once '../../config.php';
require_once '../../auth_permissoes.php';
verificaLogin();
header('Content-Type: application/json; charset=utf-8');

$dados = json_decode(file_get_contents("php://input"), true);

if (empty($dados['telefone']) || empty($dados['nome']) || empty($dados['data_hora'])) {
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Dados incompletos.',
        'dados_recebidos' => $dados
    ]);
    exit;
}

$telefone = preg_replace('/\D/', '', $dados['telefone']);
$nome = $dados['nome'];
$data_hora = new DateTime($dados['data_hora']);

if (strlen($telefone) === 11) {
    $telefone = '55' . $telefone;
}

$mensagem = "Olá *{$nome}*, 👋

Informamos que sua coleta foi *Reagendada* para:

📅 *" . $data_hora->format('d/m/Y') . "*
🕗 *" . $data_hora->format('H:i') . "h*

📍 *Local:* Rua Presidente Vargas, 625
(Atrás do América Hall) – *Rondon do Pará/PA*

🕒 *Horários de Atendimento:*
- Segunda à Sexta: *07h00 às 17h00*
- Sábados: *07h00 às 11h00*
*Não fechamos para o almoço.*

💙 *Braga Mendes Laboratório*";

// API ChatMix
$chatmix_token = "DB-3EA-24AF3-9076";
$chatmix_key = "CRMLAB-FA5C3";

$payload = [
    'token' => $chatmix_token,
    'key' => $chatmix_key,
    'numero' => $telefone,
    'mensagem' => $mensagem,
    'agendamento' => 'nao'
];

$ch = curl_init("https://api.chatmix.com.br");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$resposta = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Preparar log
$sucesso_envio = ($codigo_http == 200);
$resposta_decodificada = json_decode($resposta, true);
$mensagem_resposta = is_array($resposta_decodificada) ? json_encode($resposta_decodificada) : $resposta;

// Gravar log
$tipo = 'reagendamento';
$status = $sucesso_envio ? 'sucesso' : 'falha';
$retorno = $mensagem_resposta;

$stmt_log = $conn->prepare("INSERT INTO logs_envio (tipo, nome, telefone, status, retorno) VALUES (?, ?, ?, ?, ?)");
$stmt_log->bind_param("sssss", $tipo, $nome, $telefone, $status, $retorno);
$stmt_log->execute();

// Retorno para o frontend
if ($sucesso_envio) {
    echo json_encode([
        'sucesso' => true,
        'mensagem_confirmacao' => "✅ Mensagem enviada com sucesso para {$nome}",
        'debug' => [
            'dados_enviados' => $payload,
            'resposta_api' => $resposta,
            'http_code' => $codigo_http
        ]
    ]);
} else {
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao enviar mensagem',
        'debug' => [
            'dados_enviados' => $payload,
            'resposta_api' => $resposta,
            'http_code' => $codigo_http
        ]
    ]);
}
