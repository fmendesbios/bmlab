<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_permissoes.php';
verificaLogin();

$contaId = isset($_GET['conta_id']) ? (int)$_GET['conta_id'] : 0;
$ini = isset($_GET['ini']) ? (string)$_GET['ini'] : '';
$fim = isset($_GET['fim']) ? (string)$_GET['fim'] : '';
if ($contaId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
    http_response_code(400);
    echo 'Parâmetros inválidos';
    exit;
}

$conta = null;
$stC = $conn->prepare("SELECT banco, agencia, conta FROM contas_bancarias WHERE id = ? LIMIT 1");
if ($stC) {
    $stC->bind_param('i', $contaId);
    $stC->execute();
    $rsC = $stC->get_result();
    if ($rsC && $rsC->num_rows > 0) {
        $conta = $rsC->fetch_assoc();
    }
    $stC->close();
}
if (!$conta) {
    http_response_code(404);
    echo 'Conta não encontrada';
    exit;
}

$saldoAnt = 0.0;
$hasMov = false;
$chk = $conn->query("SHOW TABLES LIKE 'contas_bancarias_movimentacoes'");
if ($chk) {
    $hasMov = ($chk->num_rows > 0);
    $chk->close();
}
if ($hasMov) {
    $stSA = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='E' THEN valor ELSE -valor END),0) AS soma FROM contas_bancarias_movimentacoes WHERE conta_bancaria_id = ? AND emissao < ?");
} else {
    $stSA = $conn->prepare("SELECT COALESCE(SUM(valor),0) AS soma FROM contas_bancarias_entradas WHERE conta_bancaria_id = ? AND emissao < ?");
}
if ($stSA) {
    $stSA->bind_param('is', $contaId, $ini);
    $stSA->execute();
    $rsSA = $stSA->get_result();
    if ($rsSA && $rw = $rsSA->fetch_assoc()) {
        $saldoAnt = (float)$rw['soma'];
    }
    $stSA->close();
}

$items = [];
if ($hasMov) {
    $sql = "SELECT m.id, m.cr_id, m.emissao, m.valor, m.tipo, 
                   cr.descricao, p.nome AS paciente_nome, f.nome AS fornecedor_nome 
            FROM contas_bancarias_movimentacoes m
            LEFT JOIN contas_receber cr ON m.cr_id = cr.id
            LEFT JOIN pacientes p ON cr.paciente_id = p.id
            LEFT JOIN fornecedores f ON cr.fornecedor_id = f.id
            WHERE m.conta_bancaria_id = ? AND m.emissao BETWEEN ? AND ? 
            ORDER BY m.id ASC";
    $stI = $conn->prepare($sql);
} else {
    $stI = $conn->prepare("SELECT id, cr_id, emissao, valor, 'E' AS tipo FROM contas_bancarias_entradas WHERE conta_bancaria_id = ? AND emissao BETWEEN ? AND ? ORDER BY id ASC");
}
if ($stI) {
    $stI->bind_param('iss', $contaId, $ini, $fim);
    $stI->execute();
    $rsI = $stI->get_result();
    while ($r = $rsI->fetch_assoc()) {
        $items[] = $r;
    }
    $stI->close();
}

// Pagamentos de Contas a Pagar (para histórico e, se necessário, inclusão)
$hasCPDataPg = false; $hasCPContaId = false; $cpIndex = [];
$resC1 = $conn->query("SHOW COLUMNS FROM contas_pagar LIKE 'data_pagamento'");
if ($resC1) { $hasCPDataPg = ($resC1->num_rows > 0); $resC1->close(); }
$resC2 = $conn->query("SHOW COLUMNS FROM contas_pagar LIKE 'conta_bancaria_id'");
if ($resC2) { $hasCPContaId = ($resC2->num_rows > 0); $resC2->close(); }
if ($hasCPDataPg && $hasCPContaId) {
    $stCP = $conn->prepare("SELECT cp.id, cp.descricao, cp.data_pagamento, COALESCE(cp.valor_pago, cp.valor_parcela) AS valor_pago, p.nome AS paciente_nome, f.nome AS fornecedor_nome FROM contas_pagar cp LEFT JOIN pacientes p ON p.id = cp.paciente_id LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id WHERE cp.conta_bancaria_id = ? AND cp.status = 'P' AND cp.data_pagamento BETWEEN ? AND ? ORDER BY cp.data_pagamento ASC, cp.id ASC");
    if ($stCP) {
        $stCP->bind_param('iss', $contaId, $ini, $fim);
        $stCP->execute();
        $rsCP = $stCP->get_result();
        while ($cp = $rsCP->fetch_assoc()) {
            $nome = (string)($cp['fornecedor_nome'] ?? '');
            if ($nome === '') { $nome = (string)($cp['paciente_nome'] ?? ''); }
            $key = (string)$cp['data_pagamento'] . '|' . number_format((float)$cp['valor_pago'], 2, '.', '');
            if (!isset($cpIndex[$key])) { $cpIndex[$key] = []; }
            $cpIndex[$key][] = ['id' => (int)$cp['id'], 'nome' => $nome, 'descricao' => (string)$cp['descricao']];
            if (!$hasMov) {
                $items[] = [
                    'id' => (int)$cp['id'],
                    'cr_id' => null,
                    'emissao' => (string)$cp['data_pagamento'],
                    'valor' => (float)$cp['valor_pago'],
                    'tipo' => 'S',
                    'cp_id' => (int)$cp['id'],
                    'descricao' => (string)$cp['descricao'],
                    'nome' => $nome,
                    'source' => 'CP'
                ];
            }
        }
        $stCP->close();
    }
}

// Ordenação combinada
usort($items, function($a, $b){
    $da = strtotime($a['emissao']);
    $db = strtotime($b['emissao']);
    if ($da === $db) { return ($a['id'] ?? 0) <=> ($b['id'] ?? 0); }
    return $da <=> $db;
});

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
if ($useFpdi) {
    $pdf = new setasign\Fpdi\Fpdi('P', 'mm', 'A4');
} else {
    $pdf = new FPDF('P', 'mm', 'A4');
}

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

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetY(40);
$pdf->Cell(0, 10, utf8_decode('EXTRATO DE CONTA'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 6, utf8_decode('Banco: ' . ($conta['banco'] ?? '')), 0, 1, 'L');
$pdf->Cell(190, 6, utf8_decode('Agência: ' . ($conta['agencia'] ?? '')), 0, 1, 'L');
$pdf->Cell(190, 6, utf8_decode('Conta Nº: ' . ($conta['conta'] ?? '')), 0, 1, 'L');

$pdf->Cell(100, 6, utf8_decode('Período de ' . date('d/m/Y', strtotime($ini)) . ' à ' . date('d/m/Y', strtotime($fim))), 0, 0, 'L');
$pdf->Cell(90, 6, utf8_decode('Saldo Anterior: R$ ' . number_format($saldoAnt, 2, ',', '.')), 0, 1, 'R');

$pdf->Ln(1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 7, utf8_decode('Data'), 0, 0, 'C');
$pdf->Cell(90, 7, utf8_decode('Histórico'), 0, 0, 'C');
$pdf->Cell(25, 7, utf8_decode('Crédito'), 0, 0, 'C');
$pdf->Cell(25, 7, utf8_decode('Débito'), 0, 0, 'C');
$pdf->Cell(25, 7, utf8_decode('Saldo'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);

$saldo = $saldoAnt;
$n = 0;
foreach ($items as $it) {
    $data = date('d/m/Y', strtotime($it['emissao']));
    $isCredito = (string)($it['tipo'] ?? 'E') === 'E';
    $hist = '';
    if ($isCredito) {
        $crId = (int)($it['cr_id'] ?? 0);
        $hist = 'Recebimento de Título CR #' . $crId;
        
        $desc = $it['descricao'] ?? '';
        $nome = $it['paciente_nome'] ?? ($it['fornecedor_nome'] ?? '');
        
        if (!empty($desc)) {
            $hist = $desc;
        }
        if (!empty($nome)) {
            $hist = $nome . ' - ' . $hist;
        }
    } else {
        if (isset($it['source']) && $it['source'] === 'CP') {
            $nm = isset($it['nome']) ? (string)$it['nome'] : '';
            $ds = isset($it['descricao']) ? (string)$it['descricao'] : '';
            $hist = ($nm !== '' ? $nm : 'Pagamento') . ($ds !== '' ? ' - ' . $ds : '');
        } else {
            $key = date('Y-m-d', strtotime($it['emissao'])) . '|' . number_format((float)$it['valor'], 2, '.', '');
            if (isset($cpIndex[$key]) && !empty($cpIndex[$key])) {
                $m = array_shift($cpIndex[$key]);
                $nm = (string)($m['nome'] ?? '');
                $ds = (string)($m['descricao'] ?? '');
                $hist = ($nm !== '' ? $nm : 'Pagamento') . ($ds !== '' ? ' - ' . $ds : '');
            } else {
                $hist = 'Transferência entre Contas';
            }
        }
    }
    $credito = $isCredito ? (float)$it['valor'] : 0.0;
    $debito = $isCredito ? 0.0 : (float)$it['valor'];
    $saldo = $saldo + $credito - $debito;
    $pdf->Cell(25, 6, utf8_decode($data), 0, 0, 'C');
    $pdf->Cell(90, 6, utf8_decode($hist), 0, 0, 'C');
    $pdf->Cell(25, 6, utf8_decode(number_format($credito, 2, ',', '.')), 0, 0, 'C');
    $pdf->Cell(25, 6, utf8_decode(number_format($debito, 2, ',', '.')), 0, 0, 'C');
    $pdf->Cell(25, 6, utf8_decode(number_format($saldo, 2, ',', '.')), 0, 1, 'C');
    $n++;
}

$pdf->Output('I', 'extrato_conta.pdf');
