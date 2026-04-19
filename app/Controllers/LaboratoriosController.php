<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class LaboratoriosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('laboratorios.menu')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        
        $sql = "SELECT * FROM laboratorios WHERE 1=1";
        
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $sql .= " AND (laboratorio_nome LIKE '%$term%' OR codigo_laboratorio LIKE '%$term%')";
        }
        
        $sql .= " ORDER BY laboratorio_nome ASC";
        
        $result = $conn->query($sql);
        $laboratorios = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $laboratorios[] = $row;
            }
        }

        $this->view('laboratorios/index', [
            'laboratorios' => $laboratorios,
            'busca' => $busca,
            'route' => 'laboratorios'
        ]);
    }

    public function next_code() {
        if (!temPermissao('laboratorios.novo')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM laboratorios";
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
            header('Location: index.php?r=laboratorios');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $laboratorio_nome = trim($_POST['laboratorio_nome'] ?? '');
        $tipo = $_POST['tipo'] ?? 'apoio';
        $codigo_laboratorio = trim($_POST['codigo_laboratorio'] ?? '');

        // Validation
        if ($tipo !== 'local' && $tipo !== 'apoio') {
            $tipo = 'apoio';
        }

        if (empty($laboratorio_nome)) {
            $_SESSION['flash_error'] = 'O nome do laboratório é obrigatório.';
            header('Location: index.php?r=laboratorios');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('laboratorios.alterar')) { // Assuming permission
                $_SESSION['flash_error'] = 'Sem permissão para editar laboratórios.';
                header('Location: index.php?r=laboratorios');
                exit;
            }

            // Generate code if empty
            if ($codigo_laboratorio === '') {
                $stmtC = $conn->prepare("SELECT codigo_laboratorio FROM laboratorios WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_laboratorio'])) {
                    $codigo_laboratorio = str_pad((string)$id, 5, '0', STR_PAD_LEFT);
                } else {
                    $codigo_laboratorio = $rowC['codigo_laboratorio'];
                }
            }

            $sql = "UPDATE laboratorios SET laboratorio_nome = ?, tipo = ?, codigo_laboratorio = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $laboratorio_nome, $tipo, $codigo_laboratorio, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Laboratório atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('laboratorios.novo')) { // Assuming permission
                $_SESSION['flash_error'] = 'Sem permissão para criar laboratórios.';
                header('Location: index.php?r=laboratorios');
                exit;
            }

            $sql = "INSERT INTO laboratorios (laboratorio_nome, tipo, codigo_laboratorio) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $laboratorio_nome, $tipo, $codigo_laboratorio);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo_laboratorio === '') {
                    $codigoAuto = str_pad((string)$novoId, 5, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE laboratorios SET codigo_laboratorio = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Laboratório criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=laboratorios');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=laboratorios');
            exit;
        }

        if (!temPermissao('laboratorios.deletar')) { // Assuming permission
             $_SESSION['flash_error'] = 'Sem permissão para excluir laboratórios.';
             header('Location: index.php?r=laboratorios');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM laboratorios WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Laboratório excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este laboratório.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=laboratorios');
        exit;
    }
}
