<?php

namespace App\Controllers;

use App\Core\Controller;

class UsuariosController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('usuarios.acesso')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        
        $sql = "SELECT u.id, u.nome, u.email, u.usuario, u.status, u.criado_em, u.id_grupo, u.desconto_maximo_permitido, g.nome as grupo_nome 
                FROM usuarios u
                LEFT JOIN grupos_usuarios g ON u.id_grupo = g.id 
                WHERE 1=1";
        
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $sql .= " AND (u.nome LIKE '%$term%' OR u.email LIKE '%$term%' OR u.usuario LIKE '%$term%')";
        }
        
        $sql .= " ORDER BY u.nome ASC";
        
        $result = $conn->query($sql);
        $usuarios = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $usuarios[] = $row;
            }
        }

        // Fetch Grupos for the modal
        $grupos = [];
        $resG = $conn->query("SELECT id, nome FROM grupos_usuarios ORDER BY nome ASC");
        if ($resG) {
            while ($row = $resG->fetch_assoc()) {
                $grupos[] = $row;
            }
        }

        $this->view('usuarios/index', [
            'usuarios' => $usuarios,
            'grupos' => $grupos,
            'busca' => $busca
        ]);
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=usuarios');
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $senha2 = $_POST['senha2'] ?? ''; // Confirm password
        $id_grupo = (int)($_POST['id_grupo'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        $desconto_max_input = trim((string)($_POST['desconto_maximo_permitido'] ?? '0'));
        $desconto_max_input = preg_replace('/[^0-9]/', '', $desconto_max_input);
        $desconto_max = (int)$desconto_max_input;
        if ($desconto_max < 0) $desconto_max = 0;
        if ($desconto_max > 100) $desconto_max = 100;

        // Validation
        if (empty($nome) || empty($email) || empty($usuario)) {
            $_SESSION['flash_error'] = 'Preencha todos os campos obrigatórios.';
            header('Location: index.php?r=usuarios');
            exit;
        }

        // Check Duplicate Username
        $sqlCheck = "SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param('si', $usuario, $id);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = 'Já existe um usuário com esse login.';
            header('Location: index.php?r=usuarios');
            exit;
        }

        if ($id > 0) {
            // UPDATE
            if (!temPermissao('usuarios.alterar')) {
                $_SESSION['flash_error'] = 'Você não tem permissão para alterar usuários.';
                header('Location: index.php?r=usuarios');
                exit;
            }

            // Check passwords if provided
            if (!empty($senha)) {
                if ($senha !== $senha2) {
                    $_SESSION['flash_error'] = 'As senhas não conferem.';
                    header('Location: index.php?r=usuarios');
                    exit;
                }
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                $sql = "UPDATE usuarios SET nome=?, email=?, usuario=?, id_grupo=?, status=?, desconto_maximo_permitido=?, senha=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssiidsi', $nome, $email, $usuario, $id_grupo, $status, $desconto_max, $senha_hash, $id);
            } else {
                // Update without password
                $sql = "UPDATE usuarios SET nome=?, email=?, usuario=?, id_grupo=?, status=?, desconto_maximo_permitido=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssiidi', $nome, $email, $usuario, $id_grupo, $status, $desconto_max, $id);
            }

            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Usuário atualizado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar usuário: ' . $conn->error;
            }

        } else {
            // CREATE
            // Usually 'usuarios.acesso' or specific 'usuarios.novo' if it existed, but legacy uses 'usuarios.acesso' for general access 
            // and logic implies creation is part of management. 
            // However, legacy 'usuarios.php' doesn't explicitly check 'usuarios.novo', but the store logic was inside the same file.
            // Let's assume 'usuarios.alterar' covers management or just check 'usuarios.acesso' is enough for entry, 
            // but for writing let's be safe. Legacy used `exigirPermissao('usuarios.alterar')` only for UPDATE.
            // For INSERT it didn't check extra permission in legacy code (lines 189+), just the top level `exigirPermissao('usuarios.acesso')`.
            // But let's check `usuarios.alterar` for consistency or just allow it if they have access. 
            // Actually, usually creating users is a sensitive action. I'll stick to 'usuarios.acesso' as minimum, maybe 'usuarios.alterar' is better?
            // Legacy code: line 5 `exigirPermissao('usuarios.acesso')`.
            // Line 106 `exigirPermissao('usuarios.alterar')` is inside `if ($id_form > 0)`.
            // So creating NEW user only required `usuarios.acesso` in legacy. I will stick to that to not break behavior.
            
            if (empty($senha)) {
                $_SESSION['flash_error'] = 'Informe a senha para o novo usuário.';
                header('Location: index.php?r=usuarios');
                exit;
            }
            if ($senha !== $senha2) {
                $_SESSION['flash_error'] = 'As senhas não conferem.';
                header('Location: index.php?r=usuarios');
                exit;
            }

            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nome, email, usuario, senha, id_grupo, status, desconto_maximo_permitido) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssiid', $nome, $email, $usuario, $senha_hash, $id_grupo, $status, $desconto_max);

            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Usuário criado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar usuário: ' . $conn->error;
            }
        }

        header('Location: index.php?r=usuarios');
        exit;
    }

    public function delete() {
        $conn = $this->db;

        if (!temPermissao('usuarios.deletar')) {
            $_SESSION['flash_error'] = 'Você não tem permissão para excluir usuários.';
            header('Location: index.php?r=usuarios');
            exit;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id > 0) {
            // Prevent deleting self? Legacy didn't check, but good practice.
            if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] == $id) {
                $_SESSION['flash_error'] = 'Você não pode excluir seu próprio usuário.';
                header('Location: index.php?r=usuarios');
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Usuário excluído com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir usuário.';
            }
        }
        
        header('Location: index.php?r=usuarios');
        exit;
    }

    public function alterar_senha() {
        $conn = $this->db;
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $senha_atual = $_POST['senha_atual'] ?? '';
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';
            $usuario_id = $_SESSION['usuario_id'];

            if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
                $_SESSION['flash_error'] = 'Preencha todos os campos.';
                header('Location: index.php?r=alterar_senha');
                exit;
            }

            if ($nova_senha !== $confirmar_senha) {
                $_SESSION['flash_error'] = 'A nova senha e a confirmação não conferem.';
                header('Location: index.php?r=alterar_senha');
                exit;
            }

            // Check current password
            $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ?");
            $stmt->bind_param('i', $usuario_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();

            if (!$user || !password_verify($senha_atual, $user['senha'])) {
                $_SESSION['flash_error'] = 'Senha atual incorreta.';
                header('Location: index.php?r=alterar_senha');
                exit;
            }

            // Update password
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmtUp = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmtUp->bind_param('si', $hash, $usuario_id);
            
            if ($stmtUp->execute()) {
                $_SESSION['flash_success'] = 'Senha alterada com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao alterar senha.';
            }

            header('Location: index.php?r=alterar_senha');
            exit;
        }

        $this->view('usuarios/alterar_senha');
    }
}
