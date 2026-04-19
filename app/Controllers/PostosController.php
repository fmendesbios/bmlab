<?php

namespace App\Controllers;

use App\Core\Controller;
use mysqli_sql_exception;

class PostosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('postos.acesso') && !temPermissao('postos.menu')) {
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
            $where .= " AND (p.nome_posto LIKE '%$term%' OR p.codigo_posto LIKE '%$term%' OR p.cep LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM postos p $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT p.*, m.nome AS municipio_nome, m.uf AS municipio_uf 
                FROM postos p 
                LEFT JOIN municipios m ON m.id = p.municipio_id 
                $where";
        
        $sql .= " ORDER BY p.nome_posto ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $postos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $postos[] = $row;
            }
        }

        // Fetch Municipios for the modal
        $municipios = [];
        $res_m = $conn->query("SELECT id, nome, uf FROM municipios WHERE status = 1 ORDER BY nome ASC");
        if ($res_m) {
            while ($row = $res_m->fetch_assoc()) {
                $municipios[] = $row;
            }
        }

        $this->view('postos/index', [
            'postos' => $postos,
            'municipios' => $municipios,
            'busca' => $busca,
            'route' => 'postos',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('postos.novo')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM postos";
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
            header('Location: index.php?r=postos');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $nome_posto = trim($_POST['nome_posto'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $cep = trim($_POST['cep'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $municipio_id = !empty($_POST['municipio_id']) ? (int)$_POST['municipio_id'] : null;
        $codigo_posto = trim($_POST['codigo_posto'] ?? '');

        if (empty($nome_posto)) {
            $_SESSION['flash_error'] = 'O nome do posto é obrigatório.';
            header('Location: index.php?r=postos');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('postos.alterar')) {
                $_SESSION['flash_error'] = 'Sem permissão para editar postos.';
                header('Location: index.php?r=postos');
                exit;
            }

            // Generate code if empty
            if ($codigo_posto === '') {
                $stmtC = $conn->prepare("SELECT codigo_posto FROM postos WHERE id = ?");
                $stmtC->bind_param('i', $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();
                
                if (empty($rowC['codigo_posto'])) {
                    $codigo_posto = str_pad((string)$id, 2, '0', STR_PAD_LEFT);
                } else {
                    $codigo_posto = $rowC['codigo_posto'];
                }
            }

            $stmt = $conn->prepare("UPDATE postos SET ativo = ?, nome_posto = ?, cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, codigo_posto = ?, municipio_id = ? WHERE id = ?");
            $stmt->bind_param("isssssssii", $ativo, $nome_posto, $cep, $endereco, $numero, $complemento, $bairro, $codigo_posto, $municipio_id, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Posto atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('postos.novo')) {
                $_SESSION['flash_error'] = 'Sem permissão para criar postos.';
                header('Location: index.php?r=postos');
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO postos (ativo, nome_posto, cep, endereco, numero, complemento, bairro, municipio_id, codigo_posto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssis", $ativo, $nome_posto, $cep, $endereco, $numero, $complemento, $bairro, $municipio_id, $codigo_posto);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                // Auto-generate code if empty
                if ($codigo_posto === '') {
                    $codigoAuto = str_pad((string)$novoId, 2, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE postos SET codigo_posto = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Posto criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=postos');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=postos');
            exit;
        }

        if (!temPermissao('postos.deletar')) {
            $_SESSION['flash_error'] = 'Sem permissão para excluir postos.';
            header('Location: index.php?r=postos');
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM postos WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Posto excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1451) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este posto.';
            } else {
                $_SESSION['flash_error'] = 'Erro de banco de dados: ' . $e->getMessage();
            }
        }

        header('Location: index.php?r=postos');
        exit;
    }

    public function busca_municipio() {
        $conn = $this->db;
        $cep = isset($_GET['cep']) ? preg_replace('/[^0-9]/', '', $_GET['cep']) : '';

        if (strlen($cep) < 8) {
            echo json_encode(['error' => 'CEP inválido']);
            exit;
        }

        // Tenta buscar exato primeiro
        $cepFmt = substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        $sql = "SELECT id, nome, uf FROM municipios WHERE cep = ? OR REPLACE(cep, '-', '') = ? LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $cepFmt, $cep);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && $row = $res->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(null);
        }
        $stmt->close();
        exit;
    }
}
