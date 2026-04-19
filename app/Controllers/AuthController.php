<?php

namespace App\Controllers;

use App\Core\Controller;

class AuthController extends Controller {

    public function login() {
        if (!empty($_SESSION['usuario_id'])) {
            header('Location: index.php?r=home');
            exit;
        }

        $erro = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']); // Clear error after displaying

        // We don't use the main layout for login, so we include the view directly
        // Note: The View class usually extracts variables. We can do that manually here or use the view method if it supports no layout.
        // Assuming view() method uses a layout by default or we can bypass it.
        // Since login is unique, let's just include it.
        
        include __DIR__ . '/../Views/auth/login.php';
    }

    public function authenticate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=login');
            exit;
        }

        $usuario = trim($_POST['usuario'] ?? '');
        $senha   = trim($_POST['senha'] ?? '');

        if ($usuario === '' || $senha === '') {
            $_SESSION['login_error'] = 'Informe usuário e senha.';
            header('Location: index.php?r=login');
            exit;
        }

        $db = $this->db;
        $sql = "SELECT u.id, u.nome, u.senha, u.id_grupo, g.nome as grupo_nome 
                FROM usuarios u
                LEFT JOIN grupos_usuarios g ON u.id_grupo = g.id
                WHERE u.usuario = ? AND u.status = 1 LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $usuario_bd = $resultado->fetch_assoc();

            if (password_verify($senha, $usuario_bd['senha'])) {
                $_SESSION['usuario_id'] = $usuario_bd['id'];
                $_SESSION['nome']       = $usuario_bd['nome'];
                $_SESSION['id_grupo']   = $usuario_bd['id_grupo'];
                $_SESSION['grupo_nome'] = $usuario_bd['grupo_nome'];

                // Load permissions
                // Assuming carregarPermissoesNaSessao is available globally (it is in auth_permissoes.php which is included in index.php)
                if (function_exists('carregarPermissoesNaSessao')) {
                    carregarPermissoesNaSessao();
                }

                header('Location: index.php?r=home');
                exit;
            } else {
                $_SESSION['login_error'] = 'Usuário ou senha inválidos.';
            }
        } else {
            $_SESSION['login_error'] = 'Usuário ou senha inválidos.';
        }

        $stmt->close();
        header('Location: index.php?r=login');
        exit;
    }

    public function logout() {
        session_unset();
        session_destroy();
        header('Location: index.php?r=login');
        exit;
    }
}
