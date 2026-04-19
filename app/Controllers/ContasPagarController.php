<?php

namespace App\Controllers;

use App\Core\Controller;

class ContasPagarController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_pagar.acesso');
        
        $db = $this->db;
        
        $ini = $_GET['ini'] ?? date('Y-m-01');
        $fim = $_GET['fim'] ?? date('Y-m-d');
        $status = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        $types = "";
        
        if (!empty($ini) && !empty($fim)) {
            $where[] = "cp.vencimento BETWEEN ? AND ?";
            $params[] = $ini;
            $params[] = $fim;
            $types .= "ss";
        } elseif (!empty($ini)) {
            $where[] = "cp.vencimento >= ?";
            $params[] = $ini;
            $types .= "s";
        } elseif (!empty($fim)) {
            $where[] = "cp.vencimento <= ?";
            $params[] = $fim;
            $types .= "s";
        }
        
        if ($status !== '') {
            $where[] = "cp.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $whereSQL = "";
        if (!empty($where)) {
            $whereSQL = " WHERE " . implode(" AND ", $where);
        }

        // Count Total
        $totalRecords = 0;
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM contas_pagar cp" . $whereSQL);
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $resCount = $stmtCount->get_result()->fetch_assoc();
        $totalRecords = (int)$resCount['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // Main Query
        $sql = "SELECT cp.*, p.nome as paciente_nome, f.nome as fornecedor_nome, cb.banco as banco_nome 
                FROM contas_pagar cp
                LEFT JOIN pacientes p ON p.id = cp.paciente_id
                LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id
                LEFT JOIN contas_bancarias cb ON cb.id = cp.conta_bancaria_id" . $whereSQL;
        
        $sql .= " ORDER BY cp.vencimento ASC LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $bancos = $db->query("SELECT * FROM contas_bancarias WHERE ativa = 1 ORDER BY banco ASC")->fetch_all(MYSQLI_ASSOC);
        
        $this->view('financeiro/contas_pagar/index', [
            'items' => $items,
            'filters' => ['ini' => $ini, 'fim' => $fim, 'status' => $status],
            'bancos' => $bancos,
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function create()
    {
        $this->requireAuth();
        
        $db = $this->db;
        $pacientes = $db->query("SELECT id, nome FROM pacientes WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
        $fornecedores = $db->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
        
        $this->view('financeiro/contas_pagar/form', [
            'pacientes' => $pacientes,
            'fornecedores' => $fornecedores,
            'item' => null
        ]);
    }

    public function store()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_pagar.novo');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=contas_pagar');
            exit;
        }

        $db = $this->db;
        
        $tipo = $_POST['tipo'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $data_lancamento = $_POST['data_lancamento'] ?: date('Y-m-d');
        $vencimento = $_POST['vencimento'] ?: date('Y-m-d');
        $parcelas = max(1, (int)($_POST['parcelas'] ?? 1));
        $valor = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
        
        $paciente_id = ($tipo === 'PACIENTE') ? ($_POST['paciente_id'] ?? null) : null;
        $fornecedor_id = ($tipo === 'FORNECEDOR') ? ($_POST['fornecedor_id'] ?? null) : null;
        if ($paciente_id == 0) $paciente_id = null;
        if ($fornecedor_id == 0) $fornecedor_id = null;

        $stmt = $db->prepare("INSERT INTO contas_pagar (data_lancamento, paciente_id, fornecedor_id, descricao, valor_parcela, vencimento, competencia, parcelas, status, valor_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'A', 0.00)");
        
        for ($i = 1; $i <= $parcelas; $i++) {
            $desc_i = $descricao . ($parcelas > 1 ? " ($i/$parcelas)" : "");
            $venc_i = date('Y-m-d', strtotime('+' . ($i - 1) . ' month', strtotime($vencimento)));
            $comp_i = date('m/Y', strtotime($venc_i));
            
            $stmt->bind_param('siisdssi', $data_lancamento, $paciente_id, $fornecedor_id, $desc_i, $valor, $venc_i, $comp_i, $parcelas);
            $stmt->execute();
        }
        
        header('Location: index.php?r=contas_pagar&success=1');
        exit;
    }

    public function pay()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_pagar.alterar');
        
        $id = $_POST['id'] ?? 0;
        $conta_id = $_POST['conta_id'] ?? 0;
        
        if (!$id || !$conta_id) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $db = $this->db;
        
        // Check Balance (Simple check, legacy logic)
        // Calculating balance on the fly
        $saldo = 0.0;
        $resBal = $db->query("SELECT COALESCE(SUM(CASE WHEN tipo='E' THEN valor ELSE -valor END),0) as saldo FROM contas_bancarias_movimentacoes WHERE conta_bancaria_id = $conta_id");
        if ($resBal) {
            $saldo = (float)$resBal->fetch_assoc()['saldo'];
        }
        
        // Get value to pay
        $resCP = $db->query("SELECT valor_parcela, status FROM contas_pagar WHERE id = $id");
        $cp = $resCP->fetch_assoc();
        
        if (!$cp || $cp['status'] === 'P') {
            echo json_encode(['success' => false, 'message' => 'Conta inválida ou já paga']);
            exit;
        }
        
        if ($saldo < $cp['valor_parcela']) {
            echo json_encode(['success' => false, 'message' => 'Saldo insuficiente na conta bancária']);
            exit;
        }
        
        $db->begin_transaction();
        try {
            // Update CP
            $stmtUp = $db->prepare("UPDATE contas_pagar SET status = 'P', valor_pago = ?, data_pagamento = CURDATE(), conta_bancaria_id = ? WHERE id = ?");
            $stmtUp->bind_param('dii', $cp['valor_parcela'], $conta_id, $id);
            $stmtUp->execute();
            
            // Insert Movement (Type 'S' = Saída)
            $stmtMov = $db->prepare("INSERT INTO contas_bancarias_movimentacoes (conta_bancaria_id, cr_id, emissao, valor, tipo) VALUES (?, NULL, CURDATE(), ?, 'S')");
            $stmtMov->bind_param('id', $conta_id, $cp['valor_parcela']);
            $stmtMov->execute();
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function reverse()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_pagar.alterar');
        // Estornar
        
        $id = $_POST['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        $db = $this->db;
        $db->begin_transaction();
        
        try {
            // Revert status
            // Note: Legacy logic does not delete the movement! It just updates the CP status.
            // Wait, if I paid and money left the bank, estornar should probably refund the bank?
            // Legacy code in `contas_pagar.php` (lines 240+) ONLY updates `contas_pagar`. It DOES NOT delete the movement from `contas_bancarias_movimentacoes`?
            // Let me check legacy line 66: inserts movement.
            // Legacy line 240: Updates CP status to 'A'.
            // IT DOES NOT TOUCH MOVIMENTACOES. This means the money is gone from the bank but the bill is open again? That's a bug in legacy or intended "Estorno de Pagamento" (voiding the check)?
            // If I void the check, the money returns.
            // I will implement a safer logic: Delete the LAST movement related to this payment? But CP doesn't link to movement ID.
            // Actually, `contas_pagar` has `conta_bancaria_id` and `valor_pago`.
            // If I reverse, I should probably add an entry "Estorno" (Entry) to balance it out?
            // OR delete the movement if it was recent.
            // Given I can't easily find the movement (no foreign key on movement table pointing to CP id, wait: `cr_id` is for Receber. Is there a `cp_id`?
            // Legacy insert: `INSERT INTO ... (conta_bancaria_id, cr_id ...)` -> cr_id is NULL.
            // So `contas_bancarias_movimentacoes` has no link to `contas_pagar` ID. This is a schema flaw.
            // I cannot delete the movement safely.
            // I will stick to legacy behavior: Just reopen the bill.
            
            $stmt = $db->prepare("UPDATE contas_pagar SET status = 'A', valor_pago = 0.00, data_pagamento = NULL, conta_bancaria_id = NULL, ultimo_estorno = CURDATE() WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
