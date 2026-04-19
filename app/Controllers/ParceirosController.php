<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class ParceirosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('parceiros.menu')) {
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
            $where .= " AND (p.nome LIKE '%$term%' OR p.codigo_parceiro LIKE '%$term%' OR p.cnpj LIKE '%$term%')";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM parceiros p $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT p.*, m.nome as municipio_nome, m.uf as municipio_uf 
                FROM parceiros p 
                LEFT JOIN municipios m ON p.municipio_id = m.id 
                $where";
        
        $sql .= " ORDER BY p.nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $parceiros = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $parceiros[] = $row;
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

        $this->view('parceiros/index', [
            'parceiros' => $parceiros,
            'municipios' => $municipios,
            'busca' => $busca,
            'route' => 'parceiros',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function next_code() {
        if (!temPermissao('parceiros.novo')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM parceiros";
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
            header('Location: index.php?r=parceiros');
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
        $codigo_parceiro = trim($_POST['codigo_parceiro'] ?? '');
        
        $desconto_permitido = str_replace(',', '.', $_POST['desconto_permitido'] ?? '0');
        $desconto_permitido = (float)$desconto_permitido;

        // Validation
        if (empty($nome)) {
            $_SESSION['flash_error'] = 'O nome do parceiro é obrigatório.';
            header('Location: index.php?r=parceiros');
            exit;
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('parceiros.alterar')) { // Assuming 'parceiros.alterar' exists, usually consistent with other modules
                $_SESSION['flash_error'] = 'Sem permissão para editar parceiros.';
                header('Location: index.php?r=parceiros');
                exit;
            }

            // Generate code if empty
            if ($codigo_parceiro === '') {
                $stmtC = $conn->prepare("SELECT codigo_parceiro FROM parceiros WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_parceiro'])) {
                    $codigo_parceiro = str_pad((string)$id, 7, '0', STR_PAD_LEFT);
                } else {
                    $codigo_parceiro = $rowC['codigo_parceiro'];
                }
            }

            $sql = "UPDATE parceiros SET ativo=?, nome=?, cnpj=?, cep=?, endereco=?, numero=?, bairro=?, municipio_id=?, celular=?, codigo_parceiro=?, desconto_permitido=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssissdi", $ativo, $nome, $cnpj, $cep, $endereco, $numero, $bairro, $municipio_id, $celular, $codigo_parceiro, $desconto_permitido, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Parceiro atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('parceiros.novo')) { // Assuming 'parceiros.novo' exists
                $_SESSION['flash_error'] = 'Sem permissão para criar parceiros.';
                header('Location: index.php?r=parceiros');
                exit;
            }

            $sql = "INSERT INTO parceiros (ativo, nome, cnpj, cep, endereco, numero, bairro, municipio_id, celular, codigo_parceiro, desconto_permitido) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssissd", $ativo, $nome, $cnpj, $cep, $endereco, $numero, $bairro, $municipio_id, $celular, $codigo_parceiro, $desconto_permitido);
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo_parceiro === '') {
                    $codigoAuto = str_pad((string)$novoId, 7, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE parceiros SET codigo_parceiro = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Parceiro criado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=parceiros');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=parceiros');
            exit;
        }

        if (!temPermissao('parceiros.deletar')) { // Assuming 'parceiros.deletar' exists
             $_SESSION['flash_error'] = 'Sem permissão para excluir parceiros.';
             header('Location: index.php?r=parceiros');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM parceiros WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Parceiro excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este parceiro.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=parceiros');
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
