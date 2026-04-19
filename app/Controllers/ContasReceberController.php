<?php

namespace App\Controllers;

use App\Core\Controller;

class ContasReceberController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_receber.acesso');
        
        $db = $this->db;
        
        $ini = isset($_GET['ini']) ? $_GET['ini'] : date('Y-m-01');
        $fim = isset($_GET['fim']) ? $_GET['fim'] : date('Y-m-d');
        $status = $_GET['status'] ?? '';
        $origem = $_GET['origem'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        $types = "";
        
        if (!empty($ini) && !empty($fim)) {
            $where[] = "DATE(cr.emissao) BETWEEN ? AND ?";
            $params[] = $ini;
            $params[] = $fim;
            $types .= "ss";
        } elseif (!empty($ini)) {
            $where[] = "DATE(cr.emissao) >= ?";
            $params[] = $ini;
            $types .= "s";
        } elseif (!empty($fim)) {
            $where[] = "DATE(cr.emissao) <= ?";
            $params[] = $fim;
            $types .= "s";
        }
        
        if ($status !== '') {
            $where[] = "cr.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($origem !== '') {
            $where[] = "cr.origem = ?";
            $params[] = $origem;
            $types .= "s";
        }
        
        $whereSQL = "";
        if (!empty($where)) {
            $whereSQL = " WHERE " . implode(" AND ", $where);
        }
        
        // Count Total
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM contas_receber cr" . $whereSQL);
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $totalRecords = $stmtCount->get_result()->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // Main Query
        $sql = "SELECT cr.*, p.nome as paciente_nome, f.nome as fornecedor_nome 
                FROM contas_receber cr
                LEFT JOIN pacientes p ON p.id = cr.paciente_id
                LEFT JOIN fornecedores f ON f.id = cr.fornecedor_id" . $whereSQL;
        
        $sql .= " ORDER BY cr.emissao DESC, cr.id DESC LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get Banks for Transfer Modal
        $bancos = $db->query("SELECT * FROM contas_bancarias WHERE ativa = 1 ORDER BY banco ASC")->fetch_all(MYSQLI_ASSOC);
        
        $this->view('financeiro/contas_receber/index', [
            'items' => $items,
            'filters' => ['ini' => $ini, 'fim' => $fim, 'status' => $status, 'origem' => $origem],
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
        $this->exigirPermissao('financeiro_receber.novo');
        
        // Data for dropdowns
        $db = $this->db;
        $pacientes = $db->query("SELECT id, nome FROM pacientes WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
        $fornecedores = $db->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
        
        $this->view('financeiro/contas_receber/form', [
            'pacientes' => $pacientes,
            'fornecedores' => $fornecedores,
            'item' => null
        ]);
    }

    public function store()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_receber.novo');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=contas_receber');
            exit;
        }

        $db = $this->db;
        
        $origem = 'A'; // Avulso
        $tipo = $_POST['tipo'] ?? ''; // PACIENTE or FORNECEDOR
        $descricao = $_POST['descricao'] ?? '';
        $emissao = $_POST['emissao'] ?: date('Y-m-d');
        $vencimento = $_POST['vencimento'] ?: date('Y-m-d');
        $valor = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
        
        $paciente_id = ($tipo === 'PACIENTE') ? ($_POST['paciente_id'] ?? null) : null;
        $fornecedor_id = ($tipo === 'FORNECEDOR') ? ($_POST['fornecedor_id'] ?? null) : null;
        
        if ($paciente_id == 0) $paciente_id = null;
        if ($fornecedor_id == 0) $fornecedor_id = null;

        $stmt = $db->prepare("INSERT INTO contas_receber (origem, paciente_id, fornecedor_id, descricao, emissao, vencimento, valor, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'A')");
        $stmt->bind_param('siisssd', $origem, $paciente_id, $fornecedor_id, $descricao, $emissao, $vencimento, $valor);
        
        if ($stmt->execute()) {
            header('Location: index.php?r=contas_receber&success=1');
        } else {
            header('Location: index.php?r=contas_receber/create&error=1');
        }
        exit;
    }

    public function receive()
    {
        $this->requireAuth();
        
        $ids = $_POST['ids'] ?? [];
        $conta_id = $_POST['conta_id'] ?? 0;
        
        if (empty($ids) || !$conta_id) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $db = $this->db;
        $db->begin_transaction();
        
        try {
            foreach ($ids as $id) {
                // Get info
                $stmtGet = $db->prepare("SELECT valor, emissao, lote_id FROM contas_receber WHERE id = ?");
                $stmtGet->bind_param('i', $id);
                $stmtGet->execute();
                $res = $stmtGet->get_result();
                $cr = $res->fetch_assoc();
                
                if (!$cr) continue;
                
                // Insert Movement
                $stmtMov = $db->prepare("INSERT INTO contas_bancarias_movimentacoes (conta_bancaria_id, cr_id, emissao, valor, lote_id, tipo) VALUES (?, ?, ?, ?, ?, 'E')");
                $lote = $cr['lote_id'] ?: null;
                $stmtMov->bind_param('iisdi', $conta_id, $id, $cr['emissao'], $cr['valor'], $lote);
                $stmtMov->execute();
                
                // Update Status to 'R' (Recebido)
                $stmtUp = $db->prepare("UPDATE contas_receber SET status = 'R' WHERE id = ?");
                $stmtUp->bind_param('i', $id);
                $stmtUp->execute();
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function transfer()
    {
        $this->requireAuth();
        
        $ids = $_POST['ids'] ?? [];
        $conta_id = $_POST['conta_id'] ?? 0;
        
        if (empty($ids) || !$conta_id) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $db = $this->db;
        
        // Use Transaction
        $db->begin_transaction();
        
        try {
            foreach ($ids as $id) {
                // Get info
                $stmtGet = $db->prepare("SELECT valor, emissao, lote_id FROM contas_receber WHERE id = ?");
                $stmtGet->bind_param('i', $id);
                $stmtGet->execute();
                $res = $stmtGet->get_result();
                $cr = $res->fetch_assoc();
                
                if (!$cr) continue;
                
                // Insert Movement
                $stmtMov = $db->prepare("INSERT INTO contas_bancarias_movimentacoes (conta_bancaria_id, cr_id, emissao, valor, lote_id, tipo) VALUES (?, ?, ?, ?, ?, 'E')");
                $lote = $cr['lote_id'] ?: null;
                $stmtMov->bind_param('iisdi', $conta_id, $id, $cr['emissao'], $cr['valor'], $lote);
                $stmtMov->execute();
                
                // Update Status
                $stmtUp = $db->prepare("UPDATE contas_receber SET status = 'T' WHERE id = ?");
                $stmtUp->bind_param('i', $id);
                $stmtUp->execute();
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function delete()
    {
        $this->requireAuth();
        $this->exigirPermissao('financeiro_receber.deletar');
        
        $id = $_POST['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        $db = $this->db;
        $db->begin_transaction();
        
        try {
            // Delete movements
            $stmtDelMov = $db->prepare("DELETE FROM contas_bancarias_movimentacoes WHERE cr_id = ?");
            $stmtDelMov->bind_param('i', $id);
            $stmtDelMov->execute();
            
            // Delete CR
            $stmtDel = $db->prepare("DELETE FROM contas_receber WHERE id = ?");
            $stmtDel->bind_param('i', $id);
            $stmtDel->execute();
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
