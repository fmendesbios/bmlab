<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_permissoes.php';
verificaLogin();

$ini = isset($_GET['ini']) ? (string)$_GET['ini'] : date('Y-m-d');
$fim = isset($_GET['fim']) ? (string)$_GET['fim'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
    http_response_code(400);
    echo 'Parâmetros inválidos';
    exit;
}

$sql = "SELECT p.id, p.codigo_pedido, p.entrada, p.total_bruto, p.desconto_valor, p.total_liquido, p.valor_recebido, p.recebido_status,
               pa.nome AS paciente_nome, c.nome AS convenio_nome
        FROM pedidos p
        LEFT JOIN pacientes pa ON pa.id = p.paciente_id
        LEFT JOIN convenios c ON c.id = p.convenio_id
        WHERE p.entrada BETWEEN ? AND ?
        ORDER BY p.entrada ASC, p.id ASC";
$pedidos = [];
$st = $conn->prepare($sql);
if ($st) {
    $iniFull = $ini . ' 00:00:00';
    $fimFull = $fim . ' 23:59:59';
    $st->bind_param('ss', $iniFull, $fimFull);
    $st->execute();
    $rs = $st->get_result();
    while ($rw = $rs->fetch_assoc()) {
        $pedidos[] = $rw;
    }
    $st->close();
}

$mnemsPorPedido = [];
if (!empty($pedidos)) {
    $ids = [];
    foreach ($pedidos as $p) {
        $ids[] = (int)$p['id'];
    }
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $typesM = str_repeat('i', count($ids));
        $sqlM = "SELECT pi.pedido_id, e.mnemonico_local
                 FROM pedido_itens pi
                 JOIN exames e ON e.id = pi.exame_id
                 WHERE pi.pedido_id IN ($ph)
                 ORDER BY e.mnemonico_local ASC";
        $stM = $conn->prepare($sqlM);
        if ($stM) {
            $bind = [$typesM];
            foreach ($ids as $k => $v) {
                $bind[] = &$ids[$k];
            }
            call_user_func_array([$stM, 'bind_param'], $bind);
            $stM->execute();
            $rsM = $stM->get_result();
            while ($rm = $rsM->fetch_assoc()) {
                $pid = (int)$rm['pedido_id'];
                $mn = trim((string)($rm['mnemonico_local'] ?? ''));
                if ($mn !== '') {
                    if (!isset($mnemsPorPedido[$pid])) {
                        $mnemsPorPedido[$pid] = [];
                    }
                    if (!in_array($mn, $mnemsPorPedido[$pid], true)) {
                        $mnemsPorPedido[$pid][] = $mn;
                    }
                }
            }
            $stM->close();
        }
        foreach ($mnemsPorPedido as $pid => $arr) {
            $mnemsPorPedido[$pid] = implode(', ', $arr);
        }
    }
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

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetY(40);
$pdf->Cell(0, 10, utf8_decode('Relatório de Situação dos Pedidos'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->SetY(60);
$pdf->Cell(190, 6, utf8_decode('Período de entrada: ' . date('d/m/Y', strtotime($ini)) . ' até ' . date('d/m/Y', strtotime($fim)) . '.'), 0, 1, 'L');

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
// Larguras: total 190 mm
$wData = 20;
$wId = 20;
$wNome = 62;
$wTB = 20;
$wDV = 17;
$wTL = 20;
$wRec = 17;
$wSal = 14;

$pdf->Cell($wData, 7, utf8_decode('Data entrada'), 0, 0, 'C');
$pdf->Cell($wId, 7, utf8_decode('Pedido'), 0, 0, 'C');
$pdf->Cell($wNome, 7, utf8_decode('Paciente'), 0, 0, 'L');
$pdf->Cell($wTB, 7, utf8_decode('Total exames'), 0, 0, 'C');
$pdf->Cell($wDV, 7, utf8_decode('Desconto'), 0, 0, 'C');
$pdf->Cell($wTL, 7, utf8_decode('Total líquido'), 0, 0, 'C');
$pdf->Cell($wRec, 7, utf8_decode('Recebido'), 0, 0, 'C');
$pdf->Cell($wSal, 7, utf8_decode('Saldo'), 0, 1, 'C');
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);
$y = $pdf->GetY() + 1;
$pdf->Line(10, $y, 200, $y);
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 9);

$sumTB = 0.0;
$sumDV = 0.0;
$sumTL = 0.0;
$sumRec = 0.0;
$sumSal = 0.0;
$rowGap = 3.0;

foreach ($pedidos as $p) {
    $data = !empty($p['entrada']) ? date('d/m/Y', strtotime($p['entrada'])) : '';
    $pid = (int)$p['id'];
    $nome = (string)($p['paciente_nome'] ?? '');
    $conv = (string)($p['convenio_nome'] ?? '');
    $mns = isset($mnemsPorPedido[$pid]) ? (string)$mnemsPorPedido[$pid] : '';
    $tb = (float)($p['total_bruto'] ?? 0);
    $dv = (float)($p['desconto_valor'] ?? 0);
    $tl = (float)($p['total_liquido'] ?? max(0, $tb - $dv));
    $vr = (float)($p['valor_recebido'] ?? 0);
    $fat = $tl; // no sistema atual, faturado segue o total líquido
    $saldo = max(0.0, $fat - $vr);

    $sumTB += $tb;
    $sumDV += $dv;
    $sumTL += $tl;
    $sumRec += $vr;
    $sumSal += $saldo;

    $xStart = $pdf->GetX();
    $yStart = $pdf->GetY();
    $xNome = $xStart + $wData + $wId;
    $pdf->SetXY($xNome, $yStart);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->MultiCell($wNome, 4, utf8_decode($nome), 0, 'L');
    if ($mns !== '') {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY($xNome, $pdf->GetY());
        $pdf->MultiCell($wNome, 4, utf8_decode($mns), 0, 'L');
    }
    if ($conv !== '') {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY($xNome, $pdf->GetY());
        $pdf->MultiCell($wNome, 4, utf8_decode($conv), 0, 'L');
    }
    $rowH = $pdf->GetY() - $yStart;
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY($xStart, $yStart);
    $pdf->Cell($wData, $rowH, utf8_decode($data), 0, 0, 'C');
    $codigoExibicao = !empty($p['codigo_pedido']) ? $p['codigo_pedido'] : '01' . str_pad((string)$pid, 7, '0', STR_PAD_LEFT);
    $pdf->Cell($wId, $rowH, utf8_decode($codigoExibicao), 0, 0, 'C');
    $pdf->SetXY($xNome + $wNome, $yStart);
    $pdf->Cell($wTB, $rowH, utf8_decode(number_format($tb, 2, ',', '.')), 0, 0, 'R');
    $pdf->Cell($wDV, $rowH, utf8_decode(number_format($dv, 2, ',', '.')), 0, 0, 'R');
    $pdf->Cell($wTL, $rowH, utf8_decode(number_format($tl, 2, ',', '.')), 0, 0, 'R');
    $pdf->Cell($wRec, $rowH, utf8_decode(number_format($vr, 2, ',', '.')), 0, 0, 'R');
    $pdf->Cell($wSal, $rowH, utf8_decode(number_format($saldo, 2, ',', '.')), 0, 0, 'R');
    $pdf->SetXY($xStart, $yStart + $rowH + $rowGap);
}

$y = $pdf->GetY() + 1;
$pdf->Line(10, $y, 200, $y);
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($wData + $wId + $wNome, 6, utf8_decode('Total do relatório'), 0, 0, 'L');
$pdf->Cell($wTB, 6, utf8_decode(number_format($sumTB, 2, ',', '.')), 0, 0, 'R');
$pdf->Cell($wDV, 6, utf8_decode(number_format($sumDV, 2, ',', '.')), 0, 0, 'R');
$pdf->Cell($wTL, 6, utf8_decode(number_format($sumTL, 2, ',', '.')), 0, 0, 'R');
$pdf->Cell($wRec, 6, utf8_decode(number_format($sumRec, 2, ',', '.')), 0, 0, 'R');
$pdf->Cell($wSal, 6, utf8_decode(number_format($sumSal, 2, ',', '.')), 0, 1, 'R');

$pdf->Output('I', 'pedidos_situacao.pdf');
