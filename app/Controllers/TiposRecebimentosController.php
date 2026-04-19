<?php

namespace App\Controllers;

use App\Core\Controller;

class TiposRecebimentosController extends Controller {

    public function index() {
        $conn = $this->db;
        $this->requireAuth();

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'tipos';
        
        // Ensure classification table exists (Legacy behavior)
        $conn->query("CREATE TABLE IF NOT EXISTS tipos_recebimentos_classificacao ( id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uk_nome (nome) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Ensure column exists
        $resColClass = $conn->query("SHOW COLUMNS FROM tipos_recebimentos LIKE 'classificacao_id'");
        if ($resColClass && $resColClass->num_rows === 0) {
            $conn->query("ALTER TABLE tipos_recebimentos ADD COLUMN classificacao_id INT NULL");
        }

        // Fetch Classifications for Select
        $classificacoes = [];
        $resClass = $conn->query("SELECT id, nome FROM tipos_recebimentos_classificacao ORDER BY nome ASC");
        while ($row = $resClass->fetch_assoc()) $classificacoes[] = $row;

        // Fetch Tipos
        $sql = "SELECT tr.id, tr.nome, tr.ativo, tr.classificacao_id, cr.nome AS classificacao_nome 
                FROM tipos_recebimentos tr 
                LEFT JOIN tipos_recebimentos_classificacao cr ON cr.id = tr.classificacao_id 
                WHERE 1=1";
        
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $sql .= " AND (tr.nome LIKE '%$term%' OR cr.nome LIKE '%$term%')";
        }
        
        $sql .= " ORDER BY tr.nome ASC";
        
        $result = $conn->query($sql);
        $tipos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tipos[] = $row;
            }
        }

        $this->view('financeiro/tipos_recebimentos/index', [
            'tipos' => $tipos,
            'classificacoes' => $classificacoes,
            'busca' => $busca,
            'tab' => $tab
        ]);
    }

    public function store() {
        $conn = $this->db;
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=tipos_recebimentos');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $classificacao_id = isset($_POST['classificacao_id']) ? (int)$_POST['classificacao_id'] : null;
        if ($classificacao_id <= 0) $classificacao_id = null;

        if (empty($nome)) {
            $_SESSION['flash_error'] = 'Informe o nome.';
            header('Location: index.php?r=tipos_recebimentos');
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE tipos_recebimentos SET nome=?, ativo=?, classificacao_id=? WHERE id=?");
            $stmt->bind_param('siii', $nome, $ativo, $classificacao_id, $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Atualizado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar.';
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO tipos_recebimentos (nome, ativo, classificacao_id) VALUES (?, ?, ?)");
            $stmt->bind_param('sii', $nome, $ativo, $classificacao_id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Criado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar.';
            }
        }

        header('Location: index.php?r=tipos_recebimentos');
        exit;
    }

    public function delete() {
        $conn = $this->db;
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM tipos_recebimentos WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Excluído com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
        }
        header('Location: index.php?r=tipos_recebimentos');
        exit;
    }

    // Classifications Management

    public function classificacoes() {
        $conn = $this->db;
        $this->requireAuth();

        $classificacoes = [];
        $res = $conn->query("SELECT * FROM tipos_recebimentos_classificacao ORDER BY nome ASC");
        while ($row = $res->fetch_assoc()) $classificacoes[] = $row;

        // Reuse index view but with tab active
        // But index() prepares 'tipos', I should probably separate or just use index logic with tab param
        // To save code duplication, I'll redirect to index with tab param or just render view with empty types if needed,
        // but it's better to have both available.
        // Let's call index() but override tab? No, Controller actions are distinct.
        // I will just fetch what's needed for the view or separate view.
        // To keep it simple: Use same view, but ensure data is there.
        // I'll copy the data fetching logic for Types/Classes.
        
        // Actually, let's just make 'index' handle the tab logic via GET param.
        // So this method might not be needed if I just use index.php?r=tipos_recebimentos&tab=classificacoes
        
        // But for clarity, let's keep separate route if we want `tipos_recebimentos/classificacoes`.
        // I'll just redirect to index with tab param.
        header('Location: index.php?r=tipos_recebimentos&tab=classificacoes');
        exit;
    }

    public function store_classificacao() {
        $conn = $this->db;
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');

        if (empty($nome)) {
            $_SESSION['flash_error'] = 'Informe o nome.';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tipos_recebimentos_classificacao SET nome=? WHERE id=?");
                $stmt->bind_param('si', $nome, $id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO tipos_recebimentos_classificacao (nome) VALUES (?)");
                $stmt->bind_param('s', $nome);
                $stmt->execute();
            }
            $_SESSION['flash_success'] = 'Salvo com sucesso.';
        }
        
        header('Location: index.php?r=tipos_recebimentos&tab=classificacoes');
        exit;
    }

    public function delete_classificacao() {
        $conn = $this->db;
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            // Check usage? Legacy didn't check.
            $stmt = $conn->prepare("DELETE FROM tipos_recebimentos_classificacao WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Excluído com sucesso.';
        }
        header('Location: index.php?r=tipos_recebimentos&tab=classificacoes');
        exit;
    }
}
