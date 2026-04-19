<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class FornecedoresController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('fornecedores.menu')) { 
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
            $where .= " AND (f.nome LIKE '%$term%' OR f.codigo_fornecedor LIKE '%$term%' OR f.cnpj LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM fornecedores f $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT f.*, m.nome as municipio_nome, m.uf as municipio_uf 
                FROM fornecedores f 
                LEFT JOIN municipios m ON f.municipio_id = m.id 
                $where";
        
        $sql .= " ORDER BY f.nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $fornecedores = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $fornecedores[] = $row;
            }
        }

        // Fetch Municipios for the modal
        $municipios = [];
        $resM = $conn->query("SELECT id, nome, uf FROM municipios ORDER BY nome ASC");
        if ($resM) {
            while ($row = $resM->fetch_assoc()) {
                $municipios[] = $row;
            }
        }

        $this->view('fornecedores/index', [
            'fornecedores' => $fornecedores,
            'municipios' => $municipios,
            'busca' => $busca,
            'route' => 'fornecedores',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('fornecedores.novo')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM fornecedores";
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

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=fornecedores');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $nome = trim($_POST['nome'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $cnpj = trim($_POST['cnpj'] ?? '');
        $cep = trim($_POST['cep'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $municipio_id = isset($_POST['municipio_id']) ? (int)$_POST['municipio_id'] : null;
        if ($municipio_id <= 0) $municipio_id = null;
        
        $celular = trim($_POST['celular'] ?? '');
        $codigo_fornecedor = trim($_POST['codigo_fornecedor'] ?? '');
        
        // Validation
        if (empty($nome)) {
            $_SESSION['flash_error'] = 'O nome do fornecedor é obrigatório.';
            header('Location: index.php?r=fornecedores');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('fornecedores.alterar')) { // Assuming permission
                $_SESSION['flash_error'] = 'Sem permissão para editar fornecedores.';
                header('Location: index.php?r=fornecedores');
                exit;
            }

            // Generate code if empty
            if ($codigo_fornecedor === '') {
                $stmtC = $conn->prepare("SELECT codigo_fornecedor FROM fornecedores WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_fornecedor'])) {
                    $codigo_fornecedor = str_pad((string)$id, 7, '0', STR_PAD_LEFT);
                } else {
                    $codigo_fornecedor = $rowC['codigo_fornecedor'];
                }
            }

            $sql = "UPDATE fornecedores SET ativo=?, nome=?, cnpj=?, cep=?, endereco=?, numero=?, bairro=?, municipio_id=?, celular=?, codigo_fornecedor=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssissi", $ativo, $nome, $cnpj, $cep, $endereco, $numero, $bairro, $municipio_id, $celular, $codigo_fornecedor, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Fornecedor atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('fornecedores.novo')) { // Assuming permission
                $_SESSION['flash_error'] = 'Sem permissão para criar fornecedores.';
                header('Location: index.php?r=fornecedores');
                exit;
            }

            $sql = "INSERT INTO fornecedores (ativo, nome, cnpj, cep, endereco, numero, bairro, municipio_id, celular, codigo_fornecedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssiss", $ativo, $nome, $cnpj, $cep, $endereco, $numero, $bairro, $municipio_id, $celular, $codigo_fornecedor);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo_fornecedor === '') {
                    $codigoAuto = str_pad((string)$novoId, 7, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE fornecedores SET codigo_fornecedor = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Fornecedor criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=fornecedores');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=fornecedores');
            exit;
        }

        if (!temPermissao('fornecedores.deletar')) { 
             $_SESSION['flash_error'] = 'Sem permissão para excluir fornecedores.';
             header('Location: index.php?r=fornecedores');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM fornecedores WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Fornecedor excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este fornecedor.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=fornecedores');
        exit;
    }
}
