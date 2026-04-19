<?php

namespace App\Controllers;

use App\Core\Controller;
use mysqli_sql_exception;

class MunicipiosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('municipios.menu')) {
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
            // Search logic from old file: nome, uf, cep, or cep digits
            $buscaNum = preg_replace('/[^0-9]/', '', $busca);
            $buscaNumEsc = $conn->real_escape_string($buscaNum);
            
            $where .= " AND (nome LIKE '%$term%' OR uf LIKE '%$term%' OR cep LIKE '%$term%'";
            if (!empty($buscaNum)) {
                $where .= " OR REPLACE(cep, '-', '') LIKE '%$buscaNumEsc%'";
            }
            $where .= ")";
        }
        
        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM municipios $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT * FROM municipios $where ORDER BY nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $municipios = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $municipios[] = $row;
            }
        }

        // UFs List
        $ufs = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
            'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
            'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
        ];

        // Next ID for "Novo" modal
        $proximoId = '';
        $sqlNext = $conn->query("SHOW TABLE STATUS LIKE 'municipios'");
        if ($sqlNext && $rowNext = $sqlNext->fetch_assoc()) {
            $proximoId = $rowNext['Auto_increment'];
        }

        $this->view('municipios/index', [
            'municipios' => $municipios,
            'busca' => $busca,
            'ufs' => $ufs,
            'proximoId' => $proximoId,
            'route' => 'municipios',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=municipios');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        
        // Permissions
        if ($id > 0 && !temPermissao('municipios.alterar')) {
             $_SESSION['flash_error'] = 'Você não tem permissão para alterar.';
             header("Location: index.php?r=municipios");
             exit;
        }
        if ($id === 0 && !temPermissao('municipios.novo')) {
             $_SESSION['flash_error'] = 'Você não tem permissão para cadastrar.';
             header("Location: index.php?r=municipios");
             exit;
        }

        $nome   = trim($_POST['nome'] ?? '');
        $uf     = strtoupper(trim($_POST['uf'] ?? ''));
        $cep    = trim($_POST['cep'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $codigo_municipio = trim($_POST['codigo_municipio'] ?? '');

        if (empty($nome) || empty($uf)) {
            $_SESSION['flash_error'] = 'Preencha todos os campos obrigatórios.';
            header('Location: index.php?r=municipios');
            exit;
        }

        if ($id > 0) {
            // Logic to preserve or generate codigo_municipio if empty
            if ($codigo_municipio === '') {
                $stmtC = $conn->prepare("SELECT codigo_municipio FROM municipios WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC ? $resC->fetch_assoc() : null;
                $stmtC->close();
                
                if (!$rowC || trim((string)$rowC['codigo_municipio']) === '') {
                    $codigo_municipio = str_pad((string)$id, 6, '0', STR_PAD_LEFT);
                } else {
                    $codigo_municipio = (string)$rowC['codigo_municipio'];
                }
            }

            $stmt = $conn->prepare("UPDATE municipios SET nome = ?, uf = ?, cep = ?, codigo_municipio = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $nome, $uf, $cep, $codigo_municipio, $status, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Município atualizado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
            $stmt->close();

        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO municipios (nome, uf, cep, status, codigo_municipio) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $nome, $uf, $cep, $status, $codigo_municipio);
            
            if ($stmt->execute()) {
                $novoId = (int)$conn->insert_id;
                // Auto-generate code if empty
                if ($codigo_municipio === '') {
                    $codigoAuto = str_pad((string)$novoId, 6, '0', STR_PAD_LEFT);
                    $stmtUpd = $conn->prepare("UPDATE municipios SET codigo_municipio = ? WHERE id = ?");
                    if ($stmtUpd) {
                        $stmtUpd->bind_param("si", $codigoAuto, $novoId);
                        $stmtUpd->execute();
                        $stmtUpd->close();
                    }
                }
                $_SESSION['flash_success'] = 'Município cadastrado com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao cadastrar: ' . $conn->error;
            }
            $stmt->close();
        }

        header('Location: index.php?r=municipios');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=municipios');
            exit;
        }

        if (!temPermissao('municipios.deletar')) {
            $_SESSION['flash_error'] = 'Sem permissão para excluir.';
            header('Location: index.php?r=municipios');
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM municipios WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = 'Município excluído com sucesso!';
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1451) {
                $_SESSION['flash_error'] = 'Não é possível excluir este município porque existem pacientes vinculados.';
            } else {
                $_SESSION['flash_error'] = 'Não foi possível excluir o município: ' . $e->getMessage();
            }
        }

        header('Location: index.php?r=municipios');
        exit;
    }
}
