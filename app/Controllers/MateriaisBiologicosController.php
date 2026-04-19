<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class MateriaisBiologicosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('materiais.acesso')) {
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
            $where .= " AND (material_nome LIKE '%$term%' OR codigo_material LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM materiais_biologicos $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT * FROM materiais_biologicos $where ORDER BY material_nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $materiais = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $materiais[] = $row;
            }
        }

        $this->view('materiais_biologicos/index', [
            'materiais' => $materiais,
            'busca' => $busca,
            'route' => 'materiais_biologicos',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('materiais.acesso')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM materiais_biologicos";
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
            header('Location: index.php?r=materiais_biologicos');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $material_nome = trim($_POST['material_nome'] ?? '');
        $codigo_material = trim($_POST['codigo_material'] ?? '');

        if (empty($material_nome)) {
            $_SESSION['flash_error'] = 'O nome do material biológico é obrigatório.';
            header('Location: index.php?r=materiais_biologicos');
            exit;
        }

        if ($id > 0) {
            if (!temPermissao('materiais.acesso')) { 
                $_SESSION['flash_error'] = 'Sem permissão para editar materiais biológicos.';
                header('Location: index.php?r=materiais_biologicos');
                exit;
            }

            // Generate code if empty
            if ($codigo_material === '') {
                $stmtC = $conn->prepare("SELECT codigo_material FROM materiais_biologicos WHERE id = ?");
                $stmtC->bind_param('i', $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();
                
                if (empty($rowC['codigo_material'])) {
                    $codigo_material = str_pad((string)$id, 5, '0', STR_PAD_LEFT);
                } else {
                    $codigo_material = $rowC['codigo_material'];
                }
            }

            $stmt = $conn->prepare("UPDATE materiais_biologicos SET material_nome = ?, codigo_material = ? WHERE id = ?");
            $stmt->bind_param("ssi", $material_nome, $codigo_material, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Material biológico atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            if (!temPermissao('materiais.acesso')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar materiais biológicos.';
                header('Location: index.php?r=materiais_biologicos');
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO materiais_biologicos (material_nome, codigo_material) VALUES (?, ?)");
            $stmt->bind_param("ss", $material_nome, $codigo_material);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;

                // Auto-generate code if empty
                if ($codigo_material === '') {
                    $codigoAuto = str_pad((string)$novoId, 5, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE materiais_biologicos SET codigo_material = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Material biológico criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=materiais_biologicos');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=materiais_biologicos');
            exit;
        }

        if (!temPermissao('materiais.deletar')) { 
             $_SESSION['flash_error'] = 'Sem permissão para excluir materiais biológicos.';
             header('Location: index.php?r=materiais_biologicos');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM materiais_biologicos WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Material biológico excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este material.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=materiais_biologicos');
        exit;
    }
}
