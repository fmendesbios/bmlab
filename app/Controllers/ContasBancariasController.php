<?php

namespace App\Controllers;

use App\Core\Controller;

class ContasBancariasController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        
        $db = $this->db;
        $busca = trim($_GET['busca'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if ($busca !== '') {
            $where .= " AND (banco LIKE ? OR agencia LIKE ? OR conta LIKE ?)";
            $searchTerm = "%$busca%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }

        // Count total
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM contas_bancarias $where");
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result()->fetch_assoc();
        $totalRecords = $totalResult['total'];
        $totalPages = ceil($totalRecords / $perPage);

        // Fetch data
        $stmt = $db->prepare("SELECT * FROM contas_bancarias $where ORDER BY id DESC LIMIT ? OFFSET ?");
        $limitParams = $params;
        $limitParams[] = $perPage;
        $limitParams[] = $offset;
        $limitTypes = $types . "ii";
        
        $stmt->bind_param($limitTypes, ...$limitParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $contas = [];
        while ($row = $result->fetch_assoc()) {
            $contas[] = $row;
        }

        $this->view('financeiro/contas_bancarias/index', [
            'contas' => $contas,
            'busca' => $busca,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords
        ]);
    }

    public function create()
    {
        $this->requireAuth();
        
        $conta = [
            'id' => 0,
            'banco' => '',
            'agencia' => '',
            'conta' => '',
            'ativa' => 1
        ];

        // Get next ID for display
        $nextId = '';
        $sqlNext = $this->db->query("SHOW TABLE STATUS LIKE 'contas_bancarias'");
        if ($sqlNext && $rowNext = $sqlNext->fetch_assoc()) {
            $nextId = $rowNext['Auto_increment'];
        }

        $this->view('financeiro/contas_bancarias/form', [
            'conta' => $conta,
            'nextId' => $nextId,
            'isEdit' => false
        ]);
    }

    public function edit()
    {
        $this->requireAuth();
        
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            header('Location: index.php?r=contas_bancarias');
            exit;
        }

        $stmt = $this->db->prepare("SELECT * FROM contas_bancarias WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header('Location: index.php?r=contas_bancarias');
            exit;
        }

        $conta = $result->fetch_assoc();

        $this->view('financeiro/contas_bancarias/form', [
            'conta' => $conta,
            'nextId' => $id, // For edit, ID is the current ID
            'isEdit' => true
        ]);
    }

    public function store()
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=contas_bancarias');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $banco = trim($_POST['banco'] ?? '');
        $agencia = trim($_POST['agencia'] ?? '');
        $conta_num = trim($_POST['conta'] ?? '');
        $ativa = isset($_POST['ativa']) ? 1 : 0;

        if (empty($banco)) {
            // Handle validation error (redirect back or show error)
            header('Location: index.php?r=contas_bancarias&error=Banco obrigatório');
            exit;
        }

        $db = $this->db;

        if ($id > 0) {
            // Update
            $stmt = $db->prepare("UPDATE contas_bancarias SET banco = ?, agencia = ?, conta = ?, ativa = ? WHERE id = ?");
            $stmt->bind_param('sssii', $banco, $agencia, $conta_num, $ativa, $id);
            $stmt->execute();
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO contas_bancarias (banco, agencia, conta, ativa) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sssi', $banco, $agencia, $conta_num, $ativa);
            $stmt->execute();
        }

        header('Location: index.php?r=contas_bancarias&success=1');
        exit;
    }

    public function delete()
    {
        $this->requireAuth();
        
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare("DELETE FROM contas_bancarias WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }

        header('Location: index.php?r=contas_bancarias&success=3');
        exit;
    }
}
