<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_permissoes.php';
verificaLogin();

$pedido_id = isset($_GET['pedido_id']) ? (int) $_GET['pedido_id'] : 0;
if ($pedido_id <= 0) { http_response_code(400); echo 'Pedido inválido'; exit; }

$stmt = $conn->prepare("SELECT p.*, pa.nome AS paciente_nome, pa.nascimento AS paciente_nascimento, m.nome AS medico_nome, c.nome AS convenio_nome FROM pedidos p LEFT JOIN pacientes pa ON pa.id = p.paciente_id LEFT JOIN medicos m ON m.id = p.medico_id LEFT JOIN convenios c ON c.id = p.convenio_id WHERE p.id = ? LIMIT 1");
$pedido = null;
if ($stmt) { $stmt->bind_param('i', $pedido_id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $res->num_rows > 0) { $pedido = $res->fetch_assoc(); } $stmt->close(); }
if (!$pedido) { http_response_code(404); echo 'Pedido não encontrado'; exit; }

$items = [];
$stmtI = $conn->prepare("SELECT pi.*, e.mnemonico_local AS mnemonico, e.nome AS exame_nome, l.laboratorio_nome, r.recipiente_nome, cr.conservacao_nome, e.prazo_execucao_local FROM pedido_itens pi JOIN exames e ON e.id = pi.exame_id LEFT JOIN laboratorios l ON l.id = e.laboratorio_id LEFT JOIN recipientes_coleta r ON r.id = e.recipiente_id LEFT JOIN conservacao_reagentes cr ON cr.id = e.conservacao_reagente_id WHERE pi.pedido_id = ? ORDER BY e.nome ASC");
if ($stmtI) { $stmtI->bind_param('i', $pedido_id); $stmtI->execute(); $resI = $stmtI->get_result(); while ($row = $resI->fetch_assoc()) { $items[] = $row; } $stmtI->close(); }

$idade = '';
if (!empty($pedido['paciente_nascimento'])) { $n = new DateTime($pedido['paciente_nascimento']); $h = new DateTime(); $diff = $n->diff($h); $idade = $diff->y . 'A ' . $diff->m . 'M ' . $diff->d . 'D'; }

$dataColeta = '';
if (!empty($items)) { $datas = array_filter(array_map(function($it){ return $it['data_coleta']; }, $items)); if (!empty($datas)) { $dataColeta = date('d/m/Y', strtotime(min($datas))); } }

$prevEntrega = '';
if (!empty($items)) { $base = null; if (!empty($datas)) { $base = new DateTime(min($datas)); } else { $base = !empty($pedido['entrada']) ? new DateTime($pedido['entrada']) : new DateTime(); } $maxDias = 0; foreach ($items as $it) { $dias = (int) ($it['prazo_execucao_local'] ?? 0); if ($dias > $maxDias) $maxDias = $dias; } if ($base) { if ($maxDias > 0) { $base->modify('+' . $maxDias . ' days'); } $prevEntrega = $base->format('d/m/Y'); } }

$cfg = null;
$rc = $conn->query("SELECT * FROM configuracoes_sistema WHERE chave = 'papel_timbrado_pdf' LIMIT 1");
if ($rc && $rc->num_rows > 0) { $cfg = $rc->fetch_assoc(); }
if ($rc) { $rc->close(); }

// Detecta timbrado e disponibilidade de FPDI
$bgPath = null; $bgExt = null; $useFpdi = false;
if ($cfg && !empty($cfg['arquivo_path'])) {
    $bgPath = realpath(__DIR__ . '/../' . $cfg['arquivo_path']);
}
if (!$bgPath || !is_file($bgPath)) {
    $candidates = [
        __DIR__ . '/../uploads/papel_timbrado.pdf',
        __DIR__ . '/../uploads/papel_timbrado/papel_timbrado.pdf',
        __DIR__ . '/../uploads/papel_timbrado.png',
        __DIR__ . '/../uploads/papel_timbrado/papel_timbrado.png',
        __DIR__ . '/../uploads/papel_timbrado.jpg',
        __DIR__ . '/../uploads/papel_timbrado/papel_timbrado.jpg'
    ];
    foreach ($candidates as $cand) { if (is_file($cand)) { $bgPath = realpath($cand); break; } }
}
if ($bgPath && is_file($bgPath)) { $bgExt = strtolower(pathinfo($bgPath, PATHINFO_EXTENSION)); }

// Carrega FPDI autoloader se timbrado for PDF
if ($bgExt === 'pdf') {
    $autoloads = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../fpdi/src/autoload.php', __DIR__ . '/../fpdi/autoload.php'];
    foreach ($autoloads as $a) { if (is_file($a)) { require_once $a; } }
    // FPDF deve estar disponível antes de instanciar FPDI
    require_once __DIR__ . '/../fpdf/fpdf.php';
    if (class_exists('setasign\\Fpdi\\Fpdi')) { $useFpdi = true; }
}

// Instância do gerador
require_once __DIR__ . '/../fpdf/fpdf.php';
if ($useFpdi) {
    $pdf = new setasign\Fpdi\Fpdi('P','mm','A4');
} else {
    $pdf = new FPDF('P','mm','A4');
}

$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 12);
if ($bgPath && $bgExt) {
    if ($useFpdi && $bgExt === 'pdf') {
        try {
            $pdf->setSourceFile($bgPath);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, 0, 0, 210, 297, false);
        } catch (Exception $e) { /* sem fundo se falhar */ }
    } elseif (in_array($bgExt, ['png','jpg','jpeg'])) {
        $pdf->Image($bgPath, 0, 0, 210, 297);
    }
}

$pdf->SetFont('Arial','B',15);
$pdf->Cell(0,70,utf8_decode('Guia de Coleta e Produção'),0,1,'C');
$pdf->Ln(-25);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,6,utf8_decode('Pedido:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(160,6,(string)($pedido['codigo_pedido'] ?? $pedido_id),0,1,'L');

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,6,utf8_decode('Paciente:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(110,6,utf8_decode($pedido['paciente_nome'] ?? ''),0,0,'L');
$pdf->SetFont('Arial','B',10);
$pdf->Cell(20,6,utf8_decode('Idade:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(30,6,utf8_decode($idade),0,1,'L');

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,6,utf8_decode('Médico:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(160,6,utf8_decode($pedido['medico_nome'] ?? ''),0,1,'L');

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,6,utf8_decode('Convênio:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(60,6,utf8_decode($pedido['convenio_nome'] ?? ''),0,0,'L');
$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,6,utf8_decode('Data Coleta:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(70,6,utf8_decode($dataColeta),0,1,'L');

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,6,utf8_decode('Previsão Entrega:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(160,6,utf8_decode($prevEntrega),0,1,'L');

$pdf->Ln(3);
// Agrupar por laboratório
$grupos = [];
foreach ($items as $it) {
    $lab = trim((string)($it['laboratorio_nome'] ?? ''));
    if ($lab === '') { $lab = 'Laboratório Local'; }
    if (!isset($grupos[$lab])) { $grupos[$lab] = []; }
    $grupos[$lab][] = $it;
}

// Larguras ajustadas para ~190mm (sem coluna Laboratório)
$pdf->SetFont('Arial','',10);
$wMn = 25; $wEx = 55; $wRec = 60; $wCons = 30; $wRes = 20;

foreach ($grupos as $labNome => $lista) {
    // Cabeçalho do grupo
    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,7,utf8_decode('Laboratório: ' . $labNome),0,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($wMn,7,utf8_decode('Mnemônico'),1,0,'C');
    $pdf->Cell($wEx,7,utf8_decode('Exame'),1,0,'C');
    $pdf->Cell($wRec,7,utf8_decode('Recipiente'),1,0,'C');
    $pdf->Cell($wCons,7,utf8_decode('Cons. Reagente'),1,0,'C');
    $pdf->Cell($wRes,7,utf8_decode('Resultado'),1,1,'C');
    $pdf->SetFont('Arial','',10);

    foreach ($lista as $it) {
        $pdf->Cell($wMn,6,utf8_decode($it['mnemonico'] ?? ''),1,0,'C');
        $pdf->Cell($wEx,6,utf8_decode($it['exame_nome'] ?? ''),1,0,'C');
        $pdf->Cell($wRec,6,utf8_decode($it['recipiente_nome'] ?? ''),1,0,'C');
        $pdf->Cell($wCons,6,utf8_decode($it['conservacao_nome'] ?? ''),1,0,'C');
        $pdf->Cell($wRes,6,'',1,1,'C');
    }

    $pdf->Ln(1);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,utf8_decode('Quantidade de exames: ' . count($lista)),0,1,'L');
}

$pdf->Ln(2);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,7,utf8_decode('Total de exames: ' . count($items)),0,1,'L');

$pdf->Output('I','guia_coleta_producao.pdf');
exit;
