<?php

namespace App\Controllers;

use App\Core\Controller;

class CrmMotivosController extends Controller
{
    public function index()
    {
        $conn = $this->db;

        if (!temPermissao('campanhas_programas.menu')) { // Using same permission as parent menu for simplicity, or create new one
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $mensagem = '';
        $erro = '';

        if (isset($_SESSION['flash_success'])) {
            $mensagem = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $erro = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        $motivos = [];
        $res = $conn->query("SELECT * FROM crm_motivos_perdemos ORDER BY motivo ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $motivos[] = $row;
            }
            $res->close();
        }

        $this->view('crm_motivos/index', [
            'motivos' => $motivos,
            'mensagem' => $mensagem,
            'erro' => $erro
        ]);
    }

    public function store()
    {
        $conn = $this->db;
        
        if (!temPermissao('campanhas_programas.menu')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=crm_motivos');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($motivo === '') {
            $_SESSION['flash_error'] = 'O motivo é obrigatório.';
            header('Location: index.php?r=crm_motivos');
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE crm_motivos_perdemos SET motivo = ?, ativo = ? WHERE id = ?");
            $stmt->bind_param('sii', $motivo, $ativo, $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Motivo atualizado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar: ' . $conn->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO crm_motivos_perdemos (motivo, ativo) VALUES (?, ?)");
            $stmt->bind_param('si', $motivo, $ativo);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Motivo criado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar: ' . $conn->error;
            }
        }

        header('Location: index.php?r=crm_motivos');
        exit;
    }

    public function delete()
    {
        $conn = $this->db;
        
        if (!temPermissao('campanhas_programas.menu')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=crm_motivos');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM crm_motivos_perdemos WHERE id = $id");
            $_SESSION['flash_success'] = 'Motivo excluído.';
        }

        header('Location: index.php?r=crm_motivos');
        exit;
    }
}
