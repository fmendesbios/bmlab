<?php
require_once __DIR__ . '/../config.php';
verificaLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Parâmetros inválidos';
    exit;
}

$orc = null;
$st = $conn->prepare("SELECT o.*, pa.nome AS paciente_nome, c.nome AS convenio_nome, m.nome AS medico_nome, m.conselho, m.conselho_codigo, m.conselho_uf, pe.codigo_pedido FROM orcamentos o LEFT JOIN pacientes pa ON pa.id = o.paciente_id LEFT JOIN convenios c ON c.id = o.convenio_id LEFT JOIN medicos m ON m.id = o.medico_id LEFT JOIN pedidos pe ON pe.id = o.pedido_id WHERE o.id = ? LIMIT 1");
if ($st) {
    $st->bind_param('i', $id);
    $st->execute();
    $rs = $st->get_result();
    if ($rs && $rs->num_rows > 0) {
        $orc = $rs->fetch_assoc();
    }
    $st->close();
}
if (!$orc) {
    http_response_code(404);
    echo 'Orçamento não encontrado';
    exit;
}

$itens = [];
$maxPrazo = null;
$stI = $conn->prepare("SELECT oi.exame_id, oi.valor, e.nome AS exame_nome, e.mnemonico_local AS mnemonico, e.prazo_execucao_local FROM orcamento_itens oi JOIN exames e ON e.id = oi.exame_id WHERE oi.orcamento_id = ?");
if ($stI) {
    $stI->bind_param('i', $id);
    $stI->execute();
    $rsI = $stI->get_result();
    while ($r = $rsI->fetch_assoc()) {
        $itens[] = $r;
        $pz = isset($r['prazo_execucao_local']) ? (int)$r['prazo_execucao_local'] : null;
        if ($pz !== null) {
            if ($maxPrazo === null || $pz > $maxPrazo) $maxPrazo = $pz;
        }
    }
    $stI->close();
}

$cfg = null;
$rc = $conn->query("SELECT * FROM configuracoes_sistema WHERE chave = 'papel_timbrado_pdf' LIMIT 1");
if ($rc && $rc->num_rows > 0) {
    $cfg = $rc->fetch_assoc();
}
if ($rc) {
    $rc->close();
}

$bgPath = null;
$bgExt = null;
$useFpdi = false;
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
    foreach ($candidates as $cand) {
        if (is_file($cand)) {
            $bgPath = realpath($cand);
            break;
        }
    }
}
if ($bgPath && is_file($bgPath)) {
    $bgExt = strtolower(pathinfo($bgPath, PATHINFO_EXTENSION));
}

if ($bgExt === 'pdf') {
    $autoloads = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../fpdi/src/autoload.php', __DIR__ . '/../fpdi/autoload.php'];
    foreach ($autoloads as $a) {
        if (is_file($a)) {
            require_once $a;
        }
    }
    require_once __DIR__ . '/../fpdf/fpdf.php';
    if (class_exists('setasign\\Fpdi\\Fpdi')) {
        $useFpdi = true;
    }
}
require_once __DIR__ . '/../fpdf/fpdf.php';
$pdf = $useFpdi ? new setasign\Fpdi\Fpdi('P', 'mm', 'A4') : new FPDF('P', 'mm', 'A4');

$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 12);
if ($bgPath && $bgExt) {
    if ($useFpdi && $bgExt === 'pdf') {
        try {
            $pdf->setSourceFile($bgPath);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, 0, 0, 210, 297, false);
        } catch (Exception $e) {
        }
    } elseif (in_array($bgExt, ['png', 'jpg', 'jpeg'])) {
        $pdf->Image($bgPath, 0, 0, 210, 297);
    }
}

$pdf->SetFont('Arial', 'B', 15);
$pdf->SetY(40);
$pdf->Cell(0, 10, utf8_decode('ORÇAMENTO'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetY(60);
$pdf->Cell(30, 6, utf8_decode('Orçamento:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$codigoOrcamento = ($orc['codigo_orcamento'] ?? '') !== '' ? (string)$orc['codigo_orcamento'] : ('01' . str_pad((string)$orc['id'], 9, '0', STR_PAD_LEFT));
$pdf->Cell(80, 6, utf8_decode(' ' . $codigoOrcamento), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(32, 6, utf8_decode('Data Inclusão:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(48, 6, utf8_decode(' ' . (!empty($orc['criado_em']) ? date('d/m/Y H:i', strtotime($orc['criado_em'])) : '')), 0, 1, 'L');

if (!empty($orc['pedido_id'])) {
    $codigoPedido = ($orc['codigo_pedido'] ?? '') !== '' ? (string)$orc['codigo_pedido'] : ('01' . str_pad((string)$orc['pedido_id'], 7, '0', STR_PAD_LEFT));
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(30, 6, utf8_decode('Pedido:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(160, 6, utf8_decode(' ' . $codigoPedido), 0, 1, 'L');
}

$nomePac = (string)($orc['paciente_nome'] ?? '');
if ($nomePac === '') $nomePac = (string)($orc['paciente_avulso'] ?? '');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 6, utf8_decode('Paciente:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(80, 6, utf8_decode(' ' . $nomePac), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(32, 6, utf8_decode('Contato:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(48, 6, utf8_decode(' ' . (string)($orc['contato_celular'] ?? '')), 0, 1, 'L');
$medLabel = (string)($orc['medico_nome'] ?? '');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 6, utf8_decode('Médico:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(160, 6, utf8_decode(' ' . $medLabel), 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 6, utf8_decode('Convênio:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(160, 6, utf8_decode(' ' . (string)($orc['convenio_nome'] ?? '')), 0, 1, 'L');
$valStr = !empty($orc['validade']) ? date('d/m/Y', strtotime($orc['validade'])) : '';
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 6, utf8_decode('Validade:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(80, 6, utf8_decode(' ' . $valStr), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(32, 6, utf8_decode('Dias Entrega:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(48, 6, utf8_decode(' ' . ($maxPrazo !== null ? (int)$maxPrazo : '')), 0, 1, 'L');

$pdf->Ln(1);
$pdf->SetY(100);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 7, utf8_decode('Mnemônico'), 0, 0, 'C');
$pdf->Cell(160, 7, utf8_decode('Exame'), 0, 1, 'L');
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);
$y = $pdf->GetY() + 1;
$pdf->Line(10, $y, 200, $y);
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 12);
foreach ($itens as $it) {
    $mn = (string)($it['mnemonico'] ?? '');
    $en = (string)($it['exame_nome'] ?? '');
    $pdf->Cell(30, 6, utf8_decode($mn), 0, 0, 'C');
    $pdf->Cell(160, 6, utf8_decode($en), 0, 1, 'L');
}

$y = $pdf->GetY() + 1;
$pdf->Line(10, $y, 200, $y);
$pdf->Ln(3);
$pdf->SetY(140);
$tb = (float)($orc['total_bruto'] ?? 0);
$dv = (float)($orc['desconto_valor'] ?? 0);
$tl = (float)($orc['total_liquido'] ?? max(0, $tb - $dv));
$dp = (float)($orc['desconto_percentual'] ?? 0);
$dpLabel = $dp > 0 ? rtrim(rtrim(number_format($dp, 2, ',', '.'), '0'), ',') . '%' : '0%';
$pdf->Cell(190, 6, utf8_decode('Total dos exames: R$ ' . number_format($tb, 2, ',', '.')), 0, 1, 'R');
$pdf->Cell(190, 6, utf8_decode('Desconto (' . $dpLabel . '): R$ ' . number_format($dv, 2, ',', '.')), 0, 1, 'R');
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(190, 7, utf8_decode('Total Líquido: R$ ' . number_format($tl, 2, ',', '.')), 0, 1, 'R');

$cleanNome = preg_replace('/[^a-zA-Z0-9]/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nomePac));
$filename = 'orc-' . $codigoOrcamento . '-' . $cleanNome . '.pdf';
$pdf->Output('I', $filename);
