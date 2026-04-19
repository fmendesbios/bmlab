<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class CampanhasProgramasController extends Controller {

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
            $where .= " AND (nome_campanha_programa LIKE '%$term%' OR codigo_campanha_programa LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM campanhas_programas $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT * FROM campanhas_programas $where";
        
        $sql .= " ORDER BY nome_campanha_programa ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $campanhas = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $campanhas[] = $row;
            }
        }

        $this->view('campanhas_programas/index', [
            'campanhas' => $campanhas,
            'busca' => $busca,
            'route' => 'campanhas_programas',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('campanhas_programas.novo')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM campanhas_programas";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            $nextId = ((int)$row['max_id']) + 1;
        }
        
        $code = str_pad((string)$nextId, 7, '0', STR_PAD_LEFT);
        
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        exit;
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=campanhas_programas');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $nome = trim($_POST['nome_campanha_programa'] ?? '');
        $status = isset($_POST['status']) && $_POST['status'] == 'I' ? 'I' : 'A';
        $codigo = trim($_POST['codigo_campanha_programa'] ?? '');
        
        $desconto_permitido = str_replace(',', '.', $_POST['desconto_permitido'] ?? '0');
        $desconto_permitido = (float)$desconto_permitido;

        // Validation
        if (empty($nome)) {
            $_SESSION['flash_error'] = 'O nome da campanha/programa é obrigatório.';
            header('Location: index.php?r=campanhas_programas');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('campanhas_programas.alterar')) {
                $_SESSION['flash_error'] = 'Sem permissão para editar campanhas/programas.';
                header('Location: index.php?r=campanhas_programas');
                exit;
            }

            // Generate code if empty
            if ($codigo === '') {
                $stmtC = $conn->prepare("SELECT codigo_campanha_programa FROM campanhas_programas WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_campanha_programa'])) {
                    $codigo = str_pad((string)$id, 7, '0', STR_PAD_LEFT);
                } else {
                    $codigo = $rowC['codigo_campanha_programa'];
                }
            }

            $sql = "UPDATE campanhas_programas SET status=?, nome_campanha_programa=?, codigo_campanha_programa=?, desconto_permitido=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdi", $status, $nome, $codigo, $desconto_permitido, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Campanha/Programa atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('campanhas_programas.novo')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar campanhas/programas.';
                header('Location: index.php?r=campanhas_programas');
                exit;
            }

            $sql = "INSERT INTO campanhas_programas (status, nome_campanha_programa, codigo_campanha_programa, desconto_permitido) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssd", $status, $nome, $codigo, $desconto_permitido);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo === '') {
                    $codigoAuto = str_pad((string)$novoId, 7, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE campanhas_programas SET codigo_campanha_programa = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Campanha/Programa criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=campanhas_programas');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=campanhas_programas');
            exit;
        }

        if (!temPermissao('campanhas_programas.deletar')) {
             $_SESSION['flash_error'] = 'Sem permissão para excluir campanhas/programas.';
             header('Location: index.php?r=campanhas_programas');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM campanhas_programas WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Campanha/Programa excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a esta campanha/programa.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=campanhas_programas');
        exit;
    }
}
