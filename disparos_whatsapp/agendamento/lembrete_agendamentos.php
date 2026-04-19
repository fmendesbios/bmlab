<?php
require_once '../../config.php';
require_once '../../auth_permissoes.php';
verificaLogin();
header('Content-Type: application/json; charset=utf-8');

// TZ local para alinhar PHP x MySQL
date_default_timezone_set('America/Belem');

// Configuração da API ChatMix
$chatmix_token = "DB-3EA-24AF3-9076";
$chatmix_key   = "CRMLAB-FA5C3";

$dados = json_decode(file_get_contents("php://input"), true);

// Se não houver POST, roda em modo automático (cron)
if (!isset($dados['agendamentos'])) {
    // Janela de 55 a 65 minutos à frente (1h ±5min)
    $start = date('Y-m-d H:i:s', strtotime('+55 minutes'));
    $end   = date('Y-m-d H:i:s', strtotime('+65 minutes'));

    // Normalização de telefone: usamos o que está disponível (paciente ou avulso)
    // e evitamos duplicar se já houver log recente (2h) do tipo 'lembrete'
    $agendamentos = $conn->query("
        SELECT 
            a.id,
            a.data_hora,
            COALESCE(p.nome, a.nome_avulso)      AS nome,
            COALESCE(p.celular, a.whatsapp_avulso)     AS whatsapp
        FROM agendamentos a
        LEFT JOIN pacientes p ON p.id = a.paciente_id
        LEFT JOIN logs_envio le
          ON le.tipo = 'lembrete'
         AND le.criado_em >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
         AND le.telefone = CONCAT(
               '55',
               REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.celular, a.whatsapp_avulso),'(',''),')',''),'-',''),' ',''),'.','')
             )
        WHERE a.data_hora BETWEEN '$start' AND '$end'
          AND le.id IS NULL
    ")->fetch_all(MYSQLI_ASSOC);

    $dados['agendamentos'] = array_map(function ($a) {
        return [
            'nome'      => $a['nome'],
            'telefone'  => $a['whatsapp'],
            'data_hora' => $a['data_hora']
        ];
    }, $agendamentos);
}

// Se não achou nada, responda vazio (para cron isso é normal)
if (empty($dados['agendamentos'])) {
    echo json_encode(['sucesso' => true, 'resultados' => []]);
    exit;
}

$resultados = [];

foreach ($dados['agendamentos'] as $item) {
    $nome      = $item['nome'] ?? '';
    $telefone  = preg_replace('/\D/', '', $item['telefone'] ?? '');
    $data_hora = $item['data_hora'] ?? '';

    if (!$nome || !$telefone || !$data_hora) {
        $resultados[] = [
            'nome' => $nome,
            'telefone' => $telefone,
            'status' => 'falha',
            'erro' => 'Dados incompletos'
        ];
        continue;
    }

    // Padroniza com 55 se vier com 11 dígitos (DDD+8/9)
    if (strlen($telefone) === 11) {
        $telefone = '55' . $telefone;
    }

    $data = date('d/m/Y', strtotime($data_hora));
    $hora = date('H:i', strtotime($data_hora));

    $mensagem = "🔔 Olá *{$nome}*, lembramos que sua coleta está agendada para:\n\n"
        . "📅 *{$data}*\n"
        . "🕗 *{$hora}h*\n\n"
        . "📍 Rua Presidente Vargas, 625\n"
        . "(Atrás do América Hall) – Rondon do Pará/PA\n\n"
        . "💙 *Braga Mendes Laboratório* – Resultados precisos e confiáveis.";

    $payload = [
        'token'       => $chatmix_token,
        'key'         => $chatmix_key,
        'numero'      => $telefone,
        'mensagem'    => $mensagem,
        'agendamento' => 'nao'
    ];

    $ch = curl_init("https://api.chatmix.com.br");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resposta   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resposta_decodificada = json_decode($resposta, true);
    $mensagem_resposta = is_array($resposta_decodificada) ? json_encode($resposta_decodificada) : $resposta;

    $sucesso = ($http_code == 200);
    $status  = $sucesso ? 'sucesso' : 'falha';

    // Log
    $tipo = 'lembrete';
    $stmt_log = $conn->prepare("INSERT INTO logs_envio (tipo, nome, telefone, status, retorno) VALUES (?, ?, ?, ?, ?)");
    $stmt_log->bind_param("sssss", $tipo, $nome, $telefone, $status, $mensagem_resposta);
    $stmt_log->execute();

    $resultados[] = [
        'nome'     => $nome,
        'telefone' => $telefone,
        'status'   => $status,
        'resposta' => $mensagem_resposta
    ];
}

echo json_encode([
    'sucesso'   => true,
    'resultados' => $resultados
]);
