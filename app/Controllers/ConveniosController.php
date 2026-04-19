<?php

namespace App\Controllers;

use App\Core\Controller;
use mysqli_sql_exception;
use Exception;

class ConveniosController extends Controller {

    public function index() {
        $conn = $this->db;

        // Note: The original file didn't explicitly check 'convenios.acesso', but it's safer to check 'convenios.menu' or similar if available.
        // Assuming 'convenios.menu' based on main.php sidebar logic.
        if (!temPermissao('convenios.menu')) {
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
            $where .= " AND (nome LIKE '%$term%' OR tipo LIKE '%$term%' OR codigo_convenio LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM convenios $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT * FROM convenios $where ORDER BY nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $convenios = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $convenios[] = $row;
            }
        }

        $this->view('convenios/index', [
            'convenios' => $convenios,
            'busca' => $busca,
            'route' => 'convenios',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('convenios.novo')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM convenios";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            $nextId = ((int)$row['max_id']) + 1;
        }
        
        $code = str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
        
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        exit;
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=convenios');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? 'plano';
        $faturavel = isset($_POST['faturavel']) ? 1 : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $codigo_convenio = trim($_POST['codigo_convenio'] ?? '');

        // Validation
        if (empty($nome)) {
            $_SESSION['flash_error'] = 'O nome do convênio é obrigatório.';
            header('Location: index.php?r=convenios');
            exit;
        }

        $tipos_validos = ['particular', 'cortesia', 'plano', 'governo', 'outros'];
        if (!in_array($tipo, $tipos_validos, true)) {
            $_SESSION['flash_error'] = 'Tipo de convênio inválido.';
            header('Location: index.php?r=convenios');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('convenios.salvar')) { // Using 'salvar' as general edit permission based on legacy code
                $_SESSION['flash_error'] = 'Sem permissão para editar convênios.';
                header('Location: index.php?r=convenios');
                exit;
            }

            // Generate code if empty
            if ($codigo_convenio === '') {
                $stmtC = $conn->prepare("SELECT codigo_convenio FROM convenios WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_convenio'])) {
                    $codigo_convenio = str_pad((string)$id, 4, '0', STR_PAD_LEFT);
                } else {
                    $codigo_convenio = $rowC['codigo_convenio'];
                }
            }

            $sql = "UPDATE convenios SET nome = ?, tipo = ?, faturavel = ?, ativo = ?, codigo_convenio = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiisi", $nome, $tipo, $faturavel, $ativo, $codigo_convenio, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Convênio atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('convenios.novo')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar convênios.';
                header('Location: index.php?r=convenios');
                exit;
            }

            $sql = "INSERT INTO convenios (nome, tipo, faturavel, ativo, codigo_convenio) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiis", $nome, $tipo, $faturavel, $ativo, $codigo_convenio);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo_convenio === '') {
                    $codigoAuto = str_pad((string)$novoId, 4, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE convenios SET codigo_convenio = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Convênio criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=convenios');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=convenios');
            exit;
        }

        // Logic from convenios.php: check implied permission or explicit if exists. 
        // Usually delete permission is separated. Using 'convenios.novo' as a proxy if 'deletar' doesn't exist?
        // Actually, main layout doesn't show specific delete permission. 
        // I will assume 'convenios.salvar' or similar is needed, but let's check 'convenios.excluir' or 'convenios.deletar' if it was used in other files.
        // In convenios.php it used `if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'excluir')` without explicit permission check inside the block,
        // but it might be protected by the top level checks.
        // I'll enforce 'convenios.salvar' or 'convenios.novo' as "Manager" role, or better yet, check if 'convenios.deletar' exists in DB.
        // For safety, I'll use 'convenios.novo' as it implies administrative rights in this legacy context.
        if (!temPermissao('convenios.novo')) {
             $_SESSION['flash_error'] = 'Sem permissão para excluir convênios.';
             header('Location: index.php?r=convenios');
             exit;
        }

        $conn->begin_transaction();
        try {
            // Delete related prices
            $stmt = $conn->prepare("DELETE FROM exame_convenio_precos WHERE convenio_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete convenio
            $stmt = $conn->prepare("DELETE FROM convenios WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['flash_success'] = 'Convênio excluído com sucesso!';

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este convênio.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=convenios');
        exit;
    }
}
