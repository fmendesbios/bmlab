<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_permissoes.php';
verificaLogin();

$pedido_id = isset($_GET['pedido_id']) ? (int) $_GET['pedido_id'] : 0;
$pedido = null;
if ($pedido_id > 0) {
    $stmt = $conn->prepare("SELECT p.*, pa.nome AS paciente_nome, po.municipio_id, m.nome AS municipio_nome, m.uf AS municipio_uf, p.recebido_em, p.valor_recebido, p.total_liquido FROM pedidos p LEFT JOIN pacientes pa ON pa.id = p.paciente_id LEFT JOIN postos po ON po.id = p.posto_id LEFT JOIN municipios m ON m.id = po.municipio_id WHERE p.id = ? LIMIT 1");
    if ($stmt) { $stmt->bind_param('i', $pedido_id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $res->num_rows > 0) { $pedido = $res->fetch_assoc(); } $stmt->close(); }
}

$examesNomes = [];
if ($pedido_id > 0) {
    $stmtE = $conn->prepare("SELECT e.nome AS exame_nome FROM pedido_itens pi JOIN exames e ON e.id = pi.exame_id WHERE pi.pedido_id = ? ORDER BY e.nome ASC");
    if ($stmtE) {
        $stmtE->bind_param('i', $pedido_id);
        $stmtE->execute();
        $resE = $stmtE->get_result();
        while ($rowE = $resE->fetch_assoc()) {
            $nm = trim((string)($rowE['exame_nome'] ?? ''));
            if ($nm !== '' && !in_array($nm, $examesNomes, true)) { $examesNomes[] = $nm; }
        }
        $stmtE->close();
    }
}

$pacienteOverride = trim($_GET['paciente'] ?? '');
if (!$pedido && $pacienteOverride === '') { http_response_code(400); echo 'Informe paciente ou um pedido válido'; exit; }

function formatarDataBR($ts) { return date('d/m/Y', strtotime($ts)); }
function dataPorExtenso($dateYmd) {
    $t = strtotime($dateYmd);
    $dia = (int) date('d', $t);
    $mes = (int) date('m', $t);
    $ano = (int) date('Y', $t);
    $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $mesNome = $meses[$mes - 1] ?? '';
    return $dia . ' de ' . $mesNome . ' de ' . $ano;
}

function numero_extenso_pt($valor)
{
    $valor = round((float)$valor, 2);
    $int = (int)floor($valor);
    $cent = (int)round(($valor - $int) * 100);
    $u = ['zero','um','dois','três','quatro','cinco','seis','sete','oito','nove','dez','onze','doze','treze','quatorze','quinze','dezesseis','dezessete','dezoito','dezenove'];
    $d = ['','dez','vinte','trinta','quarenta','cinquenta','sessenta','setenta','oitenta','noventa'];
    $c = ['','cem','duzentos','trezentos','quatrocentos','quinhentos','seiscentos','setecentos','oitocentos','novecentos'];
    $milhar = function($n) use ($u,$d,$c) {
        if ($n === 0) return '';
        $out = '';
        $cent = intdiv($n,100);
        $rem = $n % 100;
        if ($cent > 0) {
            if ($cent === 1 && $rem === 0) $out .= 'cem'; else $out .= $c[$cent];
        }
        if ($rem > 0) {
            if ($out) $out .= ' e ';
            if ($rem < 20) { $out .= $u[$rem]; }
            else { $out .= $d[intdiv($rem,10)]; if ($rem % 10) { $out .= ' e ' . $u[$rem % 10]; } }
        }
        return $out;
    };
    $texto = '';
    if ($int >= 1000) {
        $mil = intdiv($int, 1000);
        $rest = $int % 1000;
        if ($mil === 1) { $texto .= 'mil'; } else { $texto .= $milhar($mil) . ' mil'; }
        if ($rest > 0) { $texto .= ' ' . $milhar($rest); }
    } else {
        $texto = $milhar($int);
    }
    if ($texto === '') $texto = 'zero';
    $texto .= ($int === 1 ? ' real' : ' reais');
    if ($cent > 0) {
        $texto .= ' e ';
        if ($cent < 20) { $texto .= $u[$cent]; }
        else { $texto .= $d[intdiv($cent,10)]; if ($cent % 10) { $texto .= ' e ' . $u[$cent % 10]; } }
        $texto .= ($cent === 1 ? ' centavo' : ' centavos');
    }
    return $texto;
}

$dataParam = trim($_GET['data'] ?? '');
$valorParam = isset($_GET['valor']) ? (float)$_GET['valor'] : null;
$descricao = trim($_GET['descricao'] ?? 'exames clínicos');

$dataYmd = $dataParam !== '' ? date('Y-m-d', strtotime($dataParam)) : ($pedido && !empty($pedido['recebido_em']) ? date('Y-m-d', strtotime($pedido['recebido_em'])) : date('Y-m-d'));
$pacienteNome = $pacienteOverride !== '' ? $pacienteOverride : (string) ($pedido['paciente_nome'] ?? '');
$valorRecibo = $valorParam !== null ? $valorParam : ($pedido ? ((float)($pedido['valor_recebido'] ?? 0) ?: (float)($pedido['total_liquido'] ?? 0)) : 0.00);
$valorRecibo = round(max(0.0, $valorRecibo), 2);

$municipio = (string) ($pedido['municipio_nome'] ?? '');
$uf = (string) ($pedido['municipio_uf'] ?? '');
if ($municipio === '') {
    $stmtM = $conn->query("SELECT m.nome AS nome, m.uf AS uf FROM postos p LEFT JOIN municipios m ON m.id = p.municipio_id WHERE p.ativo = 1 LIMIT 1");
    if ($stmtM && $rowM = $stmtM->fetch_assoc()) { $municipio = (string)($rowM['nome'] ?? ''); $uf = (string)($rowM['uf'] ?? ''); }
    if ($stmtM) { $stmtM->close(); }
}
$dataExtenso = dataPorExtenso($dataYmd);

$assinaturaRel = '../uploads/assinatura/assinatura.png';
$assinaturaAbs = __DIR__ . '/../uploads/assinatura/assinatura.png';
$temAssinatura = file_exists($assinaturaAbs);

$cfg = null;
$rc = $conn->query("SELECT * FROM configuracoes_sistema WHERE chave = 'papel_timbrado_pdf' LIMIT 1");
if ($rc && $rc->num_rows > 0) { $cfg = $rc->fetch_assoc(); }
if ($rc) { $rc->close(); }

$bgPath = null; $bgExt = null; $useFpdi = false;
if ($cfg && !empty($cfg['arquivo_path'])) { $bgPath = realpath(__DIR__ . '/../' . $cfg['arquivo_path']); }
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

if ($bgExt === 'pdf') {
    $autoloads = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../fpdi/src/autoload.php', __DIR__ . '/../fpdi/autoload.php'];
    foreach ($autoloads as $a) { if (is_file($a)) { require_once $a; } }
    require_once __DIR__ . '/../fpdf/fpdf.php';
    if (class_exists('setasign\Fpdi\Fpdi')) { $useFpdi = true; }
}
require_once __DIR__ . '/../fpdf/fpdf.php';
$pdf = ($useFpdi ? new setasign\Fpdi\Fpdi('P','mm','A4') : new FPDF('P','mm','A4'));
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 12);
if ($bgPath && $bgExt) {
    if ($useFpdi && $bgExt === 'pdf') { try { $pdf->setSourceFile($bgPath); $tpl = $pdf->importPage(1); $pdf->useTemplate($tpl, 0, 0, 210, 297, false); } catch (Exception $e) {} }
    elseif (in_array($bgExt, ['png','jpg','jpeg'])) { $pdf->Image($bgPath, 0, 0, 210, 297); }
}

$pdf->SetFont('Arial','B',16);
$pdf->SetY(50);
$pdf->Cell(0,10,utf8_decode('Recibo'),0,1,'C');
$pdf->SetFont('Arial','B',12);
$pdf->SetY(60);
$pdf->Cell(0,10,utf8_decode('R$ ' . number_format($valorRecibo, 2, ',', '.')),0,0,'R');

$pdf->SetFont('Arial','',12);
$pdf->SetY(70);
$label = 'Recebemos de ';
$wLabel = $pdf->GetStringWidth(utf8_decode($label));
$pdf->SetFont('Arial','B',12);
$wPaciente = $pdf->GetStringWidth(utf8_decode($pacienteNome));
$usableWidth = 190;
$startX = 10 + max(0, ($usableWidth - ($wLabel + $wPaciente)) / 2);
$pdf->SetX($startX);
$pdf->SetFont('Arial','',12);
$pdf->Cell($wLabel + 1,8,utf8_decode($label),0,0,'L');
$pdf->SetFont('Arial','B',12);
$pdf->Cell($wPaciente + 1,8,utf8_decode($pacienteNome),0,1,'L');
$pdf->SetFont('Arial','',12);
$texto = 'a quantia de ' . numero_extenso_pt($valorRecibo) . ' proveniente de ' . $descricao . '.';
$pdf->MultiCell(0,8,utf8_decode($texto),0,'C');

if (!empty($examesNomes)) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial','',11);
    foreach ($examesNomes as $nm) {
        $pdf->SetX(30);
        $pdf->MultiCell(0,7,utf8_decode('· ' . $nm),0,'L');
    }
}


$pdf->SetFont('Arial','',11);
$linhaCidade = ($municipio ? $municipio : '') . ($uf ? ', ' . $uf : '') . ', ' . $dataExtenso;
$pdf->SetY(170);
$pdf->Cell(0,8,utf8_decode($linhaCidade),0,1,'R');

if ($temAssinatura) { $pdf->Image($assinaturaAbs, 72, 180, 65); }

$pdf->Output('I','recibo_pagamento.pdf');
exit;
