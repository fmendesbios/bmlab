<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;

class PacientesCampanhasController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('campanhas_programas.menu')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        // Base query conditions
        $where = "WHERE 1=1";
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $where .= " AND (p.nome LIKE '%$term%' OR cp.nome_campanha_programa LIKE '%$term%')";
        }
        
        // Count Total
        $sqlCount = "SELECT COUNT(*) as total 
                     FROM pacientes_campanhas pc
                     JOIN pacientes p ON pc.paciente_id = p.id
                     JOIN campanhas_programas cp ON pc.campanha_id = cp.id
                     $where";
        $resCount = $conn->query($sqlCount);
        $totalRecords = 0;
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT pc.id, pc.criado_em, 
                       p.nome as paciente_nome, p.codigo_paciente,
                       cp.nome_campanha_programa, cp.codigo_campanha_programa
                FROM pacientes_campanhas pc
                JOIN pacientes p ON pc.paciente_id = p.id
                JOIN campanhas_programas cp ON pc.campanha_id = cp.id
                $where
                ORDER BY pc.criado_em DESC, p.nome ASC 
                LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $associacoes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $associacoes[] = $row;
            }
        }

        // Fetch Campanhas for the modal select
        $campanhas = [];
        $resC = $conn->query("SELECT id, nome_campanha_programa FROM campanhas_programas WHERE status = 'A' ORDER BY nome_campanha_programa ASC");
        if ($resC) {
            while ($row = $resC->fetch_assoc()) {
                $campanhas[] = $row;
            }
        }

        $this->view('pacientes_campanhas/index', [
            'associacoes' => $associacoes,
            'campanhas' => $campanhas,
            'busca' => $busca,
            'route' => 'pacientes_campanhas',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=pacientes_campanhas');
            exit;
        }

        header('Content-Type: application/json');
        
        if (!temPermissao('campanhas_programas.novo')) {
             echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
             exit;
        }

        $paciente_id = isset($_POST['paciente_id']) ? (int)$_POST['paciente_id'] : 0;
        $campanha_id = isset($_POST['campanha_id']) ? (int)$_POST['campanha_id'] : 0;
        
        if ($paciente_id <= 0 || $campanha_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Selecione o paciente e a campanha/programa.']);
            exit;
        }

        // Check duplicate
        $stmtCheck = $conn->prepare("SELECT id FROM pacientes_campanhas WHERE paciente_id = ? AND campanha_id = ?");
        $stmtCheck->bind_param("ii", $paciente_id, $campanha_id);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            echo json_encode(['success' => false, 'message' => 'Este paciente já está associado a esta campanha/programa.']);
            exit;
        }
        $stmtCheck->close();

        // Insert
        $stmt = $conn->prepare("INSERT INTO pacientes_campanhas (paciente_id, campanha_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $paciente_id, $campanha_id);
        
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Associação criada com sucesso!';
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    public function delete() {
        $conn = $this->db;
        
        if (!temPermissao('campanhas_programas.excluir')) {
            $_SESSION['flash_error'] = 'Sem permissão para excluir.';
            header('Location: index.php?r=pacientes_campanhas');
            exit;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM pacientes_campanhas WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Associação removida com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao remover: ' . $conn->error;
            }
            $stmt->close();
        }
        
        header('Location: index.php?r=pacientes_campanhas');
        exit;
    }

    public function search_pacientes() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 3) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT id, nome, nascimento, cpf, codigo_paciente FROM pacientes WHERE ativo = 1 AND (nome LIKE ? OR cpf LIKE ? OR codigo_paciente LIKE ?) LIMIT 20";
        $stmt = $conn->prepare($sql);
        $like = "%$q%";
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $results = [];
        while ($row = $res->fetch_assoc()) {
            $text = $row['nome'];
            if ($row['cpf']) $text .= " (CPF: " . $row['cpf'] . ")";
            $results[] = [
                'id' => $row['id'],
                'text' => $text,
                'data' => $row
            ];
        }
        echo json_encode($results);
        exit;
    }
}
