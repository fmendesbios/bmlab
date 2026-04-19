<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class MedicosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('medicos.acesso')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        // Base Condition
        $where = "WHERE 1=1";
        
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $where .= " AND (nome LIKE '%$term%' OR codigo_medico LIKE '%$term%' OR conselho_codigo LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM medicos $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        // Fetch Data
        $sql = "SELECT * FROM medicos $where ORDER BY nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $medicos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $medicos[] = $row;
            }
        }

        $opcoesConselho = ['CRM', 'CRV', 'CRAS', 'COREN', 'CRF', 'CRFA', 'CREFITO', 'CRN', 'CRO', 'CRP', 'CRBM', 'CRMV', 'RMS', 'CRBIO', 'CREF', 'CRTR', 'Outro'];

        $this->view('medicos/index', [
            'medicos' => $medicos,
            'busca' => $busca,
            'route' => 'medicos',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages,
            'opcoesConselho' => $opcoesConselho
        ]);
    }

    public function next_code() {
        if (!temPermissao('medicos.acesso')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM medicos";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            $nextId = ((int)$row['max_id']) + 1;
        }

        $code = '01' . str_pad((string)$nextId, 5, '0', STR_PAD_LEFT);
        
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        exit;
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=medicos');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $nome = trim($_POST['nome'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $conselho = trim($_POST['conselho'] ?? '');
        $conselho_codigo = trim($_POST['conselho_codigo'] ?? '');
        $conselho_uf = trim($_POST['conselho_uf'] ?? '');
        $codigo_medico = trim($_POST['codigo_medico'] ?? '');

        // Validation
        if (empty($nome) || empty($conselho) || empty($conselho_codigo) || empty($conselho_uf)) {
            $_SESSION['flash_error'] = 'Preencha todos os campos obrigatórios.';
            header('Location: index.php?r=medicos');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('medicos.acesso')) { // Using basic access permission or more specific if available
                $_SESSION['flash_error'] = 'Sem permissão para editar médicos.';
                header('Location: index.php?r=medicos');
                exit;
            }

            // Generate code if empty
            if ($codigo_medico === '') {
                $stmtC = $conn->prepare("SELECT codigo_medico FROM medicos WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_medico'])) {
                    $codigo_medico = '01' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
                } else {
                    $codigo_medico = $rowC['codigo_medico'];
                }
            }

            $sql = "UPDATE medicos SET ativo = ?, nome = ?, conselho = ?, conselho_codigo = ?, conselho_uf = ?, codigo_medico = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssis", $ativo, $nome, $conselho, $conselho_codigo, $conselho_uf, $codigo_medico, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Médico atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('medicos.acesso')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar médicos.';
                header('Location: index.php?r=medicos');
                exit;
            }

            $sql = "INSERT INTO medicos (ativo, nome, conselho, conselho_codigo, conselho_uf, codigo_medico) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $ativo, $nome, $conselho, $conselho_codigo, $conselho_uf, $codigo_medico);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo_medico === '') {
                    $codigoAuto = '01' . str_pad((string)$novoId, 5, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE medicos SET codigo_medico = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Médico criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=medicos');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=medicos');
            exit;
        }

        if (!temPermissao('medicos.acesso')) { 
             $_SESSION['flash_error'] = 'Sem permissão para excluir médicos.';
             header('Location: index.php?r=medicos');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM medicos WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Médico excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este médico.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=medicos');
        exit;
    }
}
