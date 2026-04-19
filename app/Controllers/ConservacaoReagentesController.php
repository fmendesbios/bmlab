<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class ConservacaoReagentesController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('conservacao_reagentes.acesso')) {
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
            $where .= " AND (conservacao_nome LIKE '%$term%' OR codigo_conservacao_reagentes LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM conservacao_reagentes $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT * FROM conservacao_reagentes $where ORDER BY conservacao_nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $conservacao = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $conservacao[] = $row;
            }
        }

        $this->view('conservacao_reagentes/index', [
            'conservacao' => $conservacao,
            'busca' => $busca,
            'route' => 'conservacao_reagentes',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('conservacao_reagentes.acesso')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM conservacao_reagentes";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            $nextId = ((int)$row['max_id']) + 1;
        }
        
        $code = str_pad((string)$nextId, 2, '0', STR_PAD_LEFT);
        
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        exit;
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=conservacao_reagentes');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $conservacao_nome = trim($_POST['conservacao_nome'] ?? '');
        $codigo_conservacao = trim($_POST['codigo_conservacao_reagentes'] ?? '');

        if (empty($conservacao_nome)) {
            $_SESSION['flash_error'] = 'Informe a conservação de reagentes.';
            header('Location: index.php?r=conservacao_reagentes');
            exit;
        }

        if ($id > 0) {
            if (!temPermissao('conservacao_reagentes.salvar')) {
                $_SESSION['flash_error'] = 'Sem permissão para editar.';
                header('Location: index.php?r=conservacao_reagentes');
                exit;
            }

            // Generate code if empty
            if ($codigo_conservacao === '') {
                $stmtC = $conn->prepare("SELECT codigo_conservacao_reagentes FROM conservacao_reagentes WHERE id = ?");
                $stmtC->bind_param('i', $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();
                
                if (empty($rowC['codigo_conservacao_reagentes'])) {
                    $codigo_conservacao = str_pad((string)$id, 2, '0', STR_PAD_LEFT);
                } else {
                    $codigo_conservacao = $rowC['codigo_conservacao_reagentes'];
                }
            }

            $stmt = $conn->prepare("UPDATE conservacao_reagentes SET conservacao_nome = ?, codigo_conservacao_reagentes = ? WHERE id = ?");
            $stmt->bind_param("ssi", $conservacao_nome, $codigo_conservacao, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Registro atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            if (!temPermissao('conservacao_reagentes.salvar')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar.';
                header('Location: index.php?r=conservacao_reagentes');
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO conservacao_reagentes (conservacao_nome, codigo_conservacao_reagentes) VALUES (?, ?)");
            $stmt->bind_param("ss", $conservacao_nome, $codigo_conservacao);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;

                // Auto-generate code if empty
                if ($codigo_conservacao === '') {
                    $codigoAuto = str_pad((string)$novoId, 2, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE conservacao_reagentes SET codigo_conservacao_reagentes = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Registro cadastrado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao cadastrar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=conservacao_reagentes');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=conservacao_reagentes');
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM conservacao_reagentes WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Registro excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
             $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=conservacao_reagentes');
        exit;
    }
}
