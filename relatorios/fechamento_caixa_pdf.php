<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_permissoes.php';
verificaLogin();

$loteId = isset($_GET['lote_id']) ? (int)$_GET['lote_id'] : 0;
if ($loteId <= 0) {
    http_response_code(400);
    echo 'Parâmetro lote_id inválido';
    exit;
}

$stL = $conn->prepare("SELECT lf.id, lf.posto_id, lf.caixa_id, lf.usuario_id, lf.inicio, lf.fim, lf.fechado_em, lf.validado_em, lf.validado_por_usuario_id, lf.remetido_em, lf.totais_por_metodo, lf.total_conferido, lf.diferenca, po.nome_posto AS posto_nome, us.nome AS usuario_nome, val.nome AS validado_por_nome FROM lotes_fechamento lf LEFT JOIN postos po ON po.id = lf.posto_id LEFT JOIN usuarios us ON us.id = lf.usuario_id LEFT JOIN usuarios val ON val.id = lf.validado_por_usuario_id WHERE lf.id = ? LIMIT 1");
$lote = null;
if ($stL) {
    $stL->bind_param('i', $loteId);
    $stL->execute();
    $rsL = $stL->get_result();
    if ($rsL && $rsL->num_rows > 0) {
        $lote = $rsL->fetch_assoc();
    }
    $stL->close();
}
if (!$lote) {
    http_response_code(404);
    echo 'Lote não encontrado';
    exit;
}

$metodos = [];
$raw = (string)($lote['totais_por_metodo'] ?? '');
if ($raw !== '') {
    $arr = json_decode($raw, true);
    if (is_array($arr)) {
        $metodos = $arr;
    }
}

$bgPath = null;
$bgExt = null;
$useFpdi = false;
$cfg = null;
$rc = $conn->query("SELECT * FROM configuracoes_sistema WHERE chave = 'papel_timbrado_pdf' LIMIT 1");
if ($rc && $rc->num_rows > 0) {
    $cfg = $rc->fetch_assoc();
}
if ($rc) {
    $rc->close();
}
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

$pdf->SetFont('Arial', 'B', 13);
$pdf->SetY(40);
$pdf->Cell(0, 10, utf8_decode('Relatório de Fechamento de Caixa'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(120, 6, utf8_decode('Posto de coleta: ' . (string)($lote['posto_nome'] ?? '')), 0, 0, 'L');
$pdf->Cell(0, 6, utf8_decode('Caixa fechado até ' . date('d/m/Y H:i', strtotime((string)$lote['fim']))), 0, 1, 'R');
$pdf->Cell(120, 6, utf8_decode('Lançamentos do caixa: ' . (string)($lote['usuario_nome'] ?? '')), 0, 0, 'L');
$pdf->Cell(0, 6, utf8_decode('Validado por ' . (string)($lote['validado_por_nome'] ?? '')), 0, 1, 'R');

// Pedidos do caixa no período
$colRecPor = false;
$colRecEm = false;
$resCol1 = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'recebido_por_usuario_id'");
if ($resCol1) {
    $colRecPor = ($resCol1->num_rows > 0);
    $resCol1->close();
}
$resCol2 = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'recebido_em'");
if ($resCol2) {
    $colRecEm = ($resCol2->num_rows > 0);
    $resCol2->close();
}

$sqlP = "SELECT p.id, p.entrada, p.total_bruto, p.desconto_percentual, p.desconto_valor, p.total_liquido, pa.nome AS paciente_nome, c.nome AS convenio_nome FROM pedidos p LEFT JOIN pacientes pa ON pa.id = p.paciente_id LEFT JOIN convenios c ON c.id = p.convenio_id WHERE 1=1";
$types = '';
$params = [];

if (!empty($lote['posto_id'])) {
    $sqlP .= " AND p.posto_id = ?";
    $types .= 'i';
    $params[] = (int)$lote['posto_id'];
}

if ($colRecPor) {
    $sqlP .= " AND p.recebido_por_usuario_id = ?";
    $types .= 'i';
    $params[] = (int)$lote['usuario_id'];
}
if ($colRecEm) {
    $sqlP .= " AND p.recebido_em BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = (string)$lote['inicio'];
    $params[] = (string)$lote['fim'];
} else {
    $sqlP .= " AND p.entrada BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = (string)$lote['inicio'];
    $params[] = (string)$lote['fim'];
}
$sqlP .= " ORDER BY p.id ASC";

$pedidos = [];
$stP = $conn->prepare($sqlP);
if ($stP) {
    $bind = [$types];
    foreach ($params as $k => $p) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stP, 'bind_param'], $bind);
    $stP->execute();
    $rsP = $stP->get_result();
    while ($rw = $rsP->fetch_assoc()) {
        $pedidos[] = $rw;
    }
    $stP->close();
}

if (!empty($pedidos)) {
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(20, 7, utf8_decode('Pedido'), 0, 0, 'C');
    $pdf->Cell(80, 7, utf8_decode('Paciente / Convênio'), 0, 0, 'C');
    $pdf->Cell(25, 7, utf8_decode('Data'), 0, 0, 'C');
    $pdf->Cell(20, 7, utf8_decode('Total Pedido'), 0, 0, 'C');
    $pdf->Cell(15, 7, utf8_decode('Desc %'), 0, 0, 'C');
    $pdf->Cell(15, 7, utf8_decode('Desc R$'), 0, 0, 'C');
    $pdf->Cell(15, 7, utf8_decode('Líquido'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $sumTB = 0.0;
    $sumDV = 0.0;
    $sumTL = 0.0;
    foreach ($pedidos as $p) {
        $pid = (int)$p['id'];
        $pac = (string)($p['paciente_nome'] ?? '');
        $conv = (string)($p['convenio_nome'] ?? '');
        $data = (string)($p['entrada'] ?? '');
        if ($colRecEm && !empty($p['recebido_em'])) {
            $data = (string)$p['recebido_em'];
        }
        $dataFmt = $data ? date('d/m/Y H:i', strtotime($data)) : '';
        $tb = (float)($p['total_bruto'] ?? 0);
        $dp = (float)($p['desconto_percentual'] ?? 0);
        $dv = (float)($p['desconto_valor'] ?? 0);
        $tl = (float)($p['total_liquido'] ?? max(0, $tb - $dv));
        $sumTB += $tb;
        $sumDV += $dv;
        $sumTL += $tl;
        $pdf->Cell(20, 6, utf8_decode((string)$pid), 0, 0, 'C');
        $pdf->Cell(80, 6, utf8_decode($pac . ($conv ? ' / ' . $conv : '')), 0, 0, 'C');
        $pdf->Cell(25, 6, utf8_decode($dataFmt), 0, 0, 'C');
        $pdf->Cell(20, 6, utf8_decode(number_format($tb, 2, ',', '.')), 0, 0, 'C');
        $pdf->Cell(15, 6, utf8_decode(number_format($dp, 2, ',', '.')), 0, 0, 'C');
        $pdf->Cell(15, 6, utf8_decode(number_format($dv, 2, ',', '.')), 0, 0, 'C');
        $pdf->Cell(15, 6, utf8_decode(number_format($tl, 2, ',', '.')), 0, 1, 'C');
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(125, 7, utf8_decode('Totais'), 0, 0, 'R');
    $pdf->Cell(20, 7, utf8_decode(number_format($sumTB, 2, ',', '.')), 0, 0, 'C');
    $pdf->Cell(15, 7, utf8_decode(''), 0, 0, 'C');
    $pdf->Cell(15, 7, utf8_decode(number_format($sumDV, 2, ',', '.')), 0, 0, 'C');
    $pdf->Cell(15, 7, utf8_decode(number_format($sumTL, 2, ',', '.')), 0, 1, 'C');
}

$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, utf8_decode('Valores recebidos'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$wNome = 120; $wValor = -70;
foreach ($metodos as $nome => $valor) {
    $pdf->Cell($wNome, 6, utf8_decode((string)$nome), 0, 0, 'L');
    $pdf->Cell($wValor, 6, utf8_decode(number_format((float)$valor, 2, ',', '.')), 0, 1, 'R');
}
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell($wNome, 7, utf8_decode('Total Caixa'), 0, 0, 'L');
$pdf->Cell($wValor, 7, utf8_decode(number_format((float)($lote['total_conferido'] ?? 0), 2, ',', '.')), 0, 1, 'R');

$pdf->Output('I', 'fechamento_caixa_' . (int)$loteId . '.pdf');
