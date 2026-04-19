<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_permissoes.php';
verificaLogin();

$pedido_id = isset($_GET['pedido_id']) ? (int) $_GET['pedido_id'] : 0;

$pedido = null;
if ($pedido_id > 0) {
    $stmt = $conn->prepare("SELECT p.*, pa.nome AS paciente_nome, pa.nascimento AS paciente_nascimento, m.nome AS medico_nome, c.nome AS convenio_nome, po.nome_posto, po.municipio_id, mu.nome AS municipio_nome, mu.uf AS municipio_uf FROM pedidos p LEFT JOIN pacientes pa ON pa.id = p.paciente_id LEFT JOIN medicos m ON m.id = p.medico_id LEFT JOIN convenios c ON c.id = p.convenio_id LEFT JOIN postos po ON po.id = p.posto_id LEFT JOIN municipios mu ON mu.id = po.municipio_id WHERE p.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $pedido_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $pedido = $res->fetch_assoc();
        }
        $stmt->close();
    }
}
// Para modo avulso, exige ao menos paciente via GET
$pacienteOverride = trim($_GET['paciente'] ?? '');
if (!$pedido && $pacienteOverride === '') {
    http_response_code(400);
    echo 'Informe paciente ou um pedido válido';
    exit;
}

function formatarHora($ts)
{
    return date('H:i', strtotime($ts));
}
function formatarDataBR($ts)
{
    return date('d/m/Y', strtotime($ts));
}
function dataPorExtenso($dateYmd)
{
    $t = strtotime($dateYmd);
    $dia = (int) date('d', $t);
    $mes = (int) date('m', $t);
    $ano = (int) date('Y', $t);
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $mesNome = $meses[$mes - 1] ?? '';
    return $dia . ' de ' . $mesNome . ' de ' . $ano;
}

$entradaTs = $pedido['entrada'] ?? date('Y-m-d H:i:s');
$dataParam = trim($_GET['data'] ?? '');
$horaIniParam = trim($_GET['hora_ini'] ?? '');
$horaFimParam = trim($_GET['hora_fim'] ?? '');

$dataYmd = $dataParam !== '' ? date('Y-m-d', strtotime($dataParam)) : date('Y-m-d', strtotime($entradaTs));
$horaIni = $horaIniParam !== '' ? $horaIniParam : formatarHora($entradaTs);
$horaFim = $horaFimParam !== '' ? $horaFimParam : $horaIni;

$pacienteNome = $pacienteOverride !== '' ? $pacienteOverride : (string) ($pedido['paciente_nome'] ?? '');
$municipio = (string) ($pedido['municipio_nome'] ?? '');
$uf = (string) ($pedido['municipio_uf'] ?? '');
$cidadeLinha = $municipio ? ($municipio . ($uf ? ', ' . $uf : '')) : '';
$dataExtenso = dataPorExtenso($dataYmd);

$assinaturaRel = '../uploads/assinatura/assinatura.png';
$assinaturaAbs = __DIR__ . '/../uploads/assinatura/assinatura.png';
$temAssinatura = file_exists($assinaturaAbs);

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
$pdf = ($useFpdi ? new setasign\Fpdi\Fpdi('P', 'mm', 'A4') : new FPDF('P', 'mm', 'A4'));
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

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetY(60);
$pdf->Cell(0, 10, utf8_decode('Atestado de Comparecimento'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
function pdfJustifySegments($pdf, $x, $y, $w, $h, $segments)
{
    $pdf->SetXY($x, $y);
    $fn = ['Arial','',12];
    $fb = ['Arial','B',12];
    $tokens = [];
    foreach ($segments as $seg) {
        $parts = preg_split('/(\s+)/u', (string)$seg[0], -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $p) { if ($p !== '') { $tokens[] = ['t'=>$p,'b'=>(bool)$seg[1]]; } }
    }
    $i = 0;
    while ($i < count($tokens)) {
        $line = [];
        $total = 0.0;
        $spaces = 0;
        $j = $i;
        while ($j < count($tokens)) {
            $tk = $tokens[$j];
            $pdf->SetFont($tk['b'] ? $fb[0] : $fn[0], $tk['b'] ? $fb[1] : $fn[1], $tk['b'] ? $fb[2] : $fn[2]);
            $wTok = $pdf->GetStringWidth(utf8_decode($tk['t']));
            if ($total + $wTok > $w && !empty($line)) { break; }
            $line[] = ['t'=>$tk['t'],'b'=>$tk['b'],'w'=>$wTok];
            $total += $wTok;
            if (preg_match('/^\s+$/u', $tk['t'])) { $spaces++; }
            $j++;
        }
        $i = $j;
        $justify = ($spaces > 0 && $i < count($tokens));
        $extra = $justify ? (($w - $total) / $spaces) : 0.0;
        foreach ($line as $lk) {
            $pdf->SetFont($lk['b'] ? $fb[0] : $fn[0], $lk['b'] ? $fb[1] : $fn[1], $lk['b'] ? $fb[2] : $fn[2]);
            if (preg_match('/^\s+$/u', $lk['t'])) {
                $pdf->Cell($lk['w'] + ($justify ? $extra : 0.0), $h, '', 0, 0);
            } else {
                $pdf->Cell($lk['w'], $h, utf8_decode($lk['t']), 0, 0);
            }
        }
        $pdf->Ln($h);
    }
}
$wTexto = 190;
pdfJustifySegments($pdf, $pdf->GetX(), 80, $wTexto, 8, [
    ['Atesto para os devidos fins que ', false],
    [$pacienteNome, true],
    [' esteve presente em nosso estabelecimento no horário de ', false],
    [$horaIni, true],
    [' às ', false],
    [$horaFim, true],
    [' do dia ', false],
    [formatarDataBR($dataYmd), true],
    [' para realização de exames.', false],
]);

$linhaCidade = ($municipio ? $municipio : '');
if ($linhaCidade === '') {
    $stmtM = $conn->query("SELECT m.nome AS nome, m.uf AS uf FROM postos p LEFT JOIN municipios m ON m.id = p.municipio_id WHERE p.ativo = 1 LIMIT 1");
    if ($stmtM && $rowM = $stmtM->fetch_assoc()) {
        $municipio = (string)($rowM['nome'] ?? '');
        $uf = (string)($rowM['uf'] ?? '');
    }
    if ($stmtM) { $stmtM->close(); }
}
$linhaCidade = ($municipio ? $municipio : '') . ($uf ? ', ' . $uf : '') . ', ' . $dataExtenso;
$pdf->SetFont('Arial', '', 11);
$pdf->SetY(150);
$pdf->Cell(0, 8, utf8_decode($linhaCidade), 0, 1, 'R');

if ($temAssinatura) {
    $pdf->Image($assinaturaAbs, 72, 180, 65);
}

$pdf->Output('I', 'atestado_comparecimento.pdf');
exit;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>BMLAB - Atestado de Comparecimento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            margin: 25mm 20mm 20mm 20mm;
        }

        body {
            background: #fff;
            color: #000;
        }

        .doc-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .titulo {
            font-size: 1.6rem;
            font-weight: 600;
            text-align: center;
            margin-top: 1.5rem;
        }

        .texto {
            font-size: 1rem;
            margin-top: 2rem;
            line-height: 1.7;
        }

        .rodape {
            margin-top: 3rem;
        }

        .assinatura-bloco {
            text-align: right;
            margin-top: 2rem;
        }

        .assinatura-img {
            width: 160px;
            height: auto;
            opacity: 0.9;
        }

        .assinatura-legenda {
            font-size: 0.9rem;
            margin-top: .25rem;
        }

        .marca {
            text-align: center;
            margin-top: 3rem;
            font-weight: 600;
            letter-spacing: .5px;
        }

        .cabecalho {
            text-align: center;
            margin-top: .5rem;
        }

        .cabecalho .logo-texto {
            font-size: 1rem;
            font-weight: 600;
            color: #00B298;
        }
    </style>
</head>

<body>
    <div class="doc-container">
        <div class="cabecalho">
            <div class="logo-texto">BRAGA MENDES LABORATÓRIO</div>
        </div>

        <div class="titulo">Atestado de Comparecimento</div>

        <div class="texto">
            Atesto para os devidos fins que <strong><?= htmlspecialchars($pacienteNome) ?></strong>
            esteve presente em nosso estabelecimento no horário de <strong><?= htmlspecialchars($horaIni) ?></strong>
            às <strong><?= htmlspecialchars($horaFim) ?></strong> do dia <strong><?= htmlspecialchars(formatarDataBR($dataYmd)) ?></strong>
            para realização de exames.
        </div>

        <div class="rodape">
            <div class="assinatura-bloco">
                <div class="mb-2"><?= htmlspecialchars($municipio) ?><?= $municipio && $uf ? ', ' . htmlspecialchars($uf) : '' ?>, <?= htmlspecialchars($dataExtenso) ?></div>
                <?php if ($temAssinatura): ?>
                    <img src="<?= htmlspecialchars($assinaturaRel) ?>" alt="Assinatura" class="assinatura-img">
                <?php endif; ?>
            </div>

            <div class="marca">BRAGA MENDES LABORATÓRIO</div>
        </div>
    </div>
</body>

</html>
