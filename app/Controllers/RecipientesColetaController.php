<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class RecipientesColetaController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('recipientes.acesso')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $where .= " AND (recipiente_nome LIKE '%$term%' OR codigo_recipiente LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM recipientes_coleta $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT * FROM recipientes_coleta $where ORDER BY recipiente_nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $recipientes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recipientes[] = $row;
            }
        }

        $this->view('recipientes_coleta/index', [
            'recipientes' => $recipientes,
            'busca' => $busca,
            'route' => 'recipientes_coleta',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('recipientes.acesso')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM recipientes_coleta";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            $nextId = ((int)$row['max_id']) + 1;
        }
        
        $code = str_pad((string)$nextId, 5, '0', STR_PAD_LEFT);
        
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        exit;
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=recipientes_coleta');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $recipiente_nome = trim($_POST['recipiente_nome'] ?? '');
        $codigo_recipiente = trim($_POST['codigo_recipiente'] ?? '');

        if (empty($recipiente_nome)) {
            $_SESSION['flash_error'] = 'O nome do recipiente é obrigatório.';
            header('Location: index.php?r=recipientes_coleta');
            exit;
        }

        if ($id > 0) {
            if (!temPermissao('recipientes.acesso')) { 
                $_SESSION['flash_error'] = 'Sem permissão para editar recipientes.';
                header('Location: index.php?r=recipientes_coleta');
                exit;
            }

            // Generate code if empty
            if ($codigo_recipiente === '') {
                $stmtC = $conn->prepare("SELECT codigo_recipiente FROM recipientes_coleta WHERE id = ?");
                $stmtC->bind_param('i', $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();
                
                if (empty($rowC['codigo_recipiente'])) {
                    $codigo_recipiente = str_pad((string)$id, 5, '0', STR_PAD_LEFT);
                } else {
                    $codigo_recipiente = $rowC['codigo_recipiente'];
                }
            }

            $stmt = $conn->prepare("UPDATE recipientes_coleta SET recipiente_nome = ?, codigo_recipiente = ? WHERE id = ?");
            $stmt->bind_param("ssi", $recipiente_nome, $codigo_recipiente, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Recipiente atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            if (!temPermissao('recipientes.acesso')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar recipientes.';
                header('Location: index.php?r=recipientes_coleta');
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO recipientes_coleta (recipiente_nome, codigo_recipiente) VALUES (?, ?)");
            $stmt->bind_param("ss", $recipiente_nome, $codigo_recipiente);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;

                // Auto-generate code if empty
                if ($codigo_recipiente === '') {
                    $codigoAuto = str_pad((string)$novoId, 5, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE recipientes_coleta SET codigo_recipiente = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Recipiente criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=recipientes_coleta');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=recipientes_coleta');
            exit;
        }

        if (!temPermissao('recipientes.deletar')) { 
             $_SESSION['flash_error'] = 'Sem permissão para excluir recipientes.';
             header('Location: index.php?r=recipientes_coleta');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM recipientes_coleta WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Recipiente excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este recipiente.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=recipientes_coleta');
        exit;
    }
}
