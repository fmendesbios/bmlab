<?php
// config.php - BMLAB
// Ajuste usuário e senha do MySQL conforme seu ambiente (XAMPP, hospedagem, etc.)

$host       = 'localhost';
$usuario_db = 'root';
$senha_db   = '';
$banco      = 'bmlab';

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
} else {
    $envHost = getenv('BMLAB_DB_HOST');
    $envUser = getenv('BMLAB_DB_USER');
    $envPass = getenv('BMLAB_DB_PASS');
    $envName = getenv('BMLAB_DB_NAME');

    if ($envHost !== false && $envHost !== '') $host = $envHost;
    if ($envUser !== false && $envUser !== '') $usuario_db = $envUser;
    if ($envPass !== false) $senha_db = $envPass;
    if ($envName !== false && $envName !== '') $banco = $envName;
}

try {
    $conn = new mysqli($host, $usuario_db, $senha_db, $banco);
} catch (Throwable $e) {
    if ($host === 'localhost') {
        $conn = new mysqli('127.0.0.1', $usuario_db, $senha_db, $banco);
    } else {
        throw $e;
    }
}

if ($conn->connect_error) {
    die('Erro na conexão com o banco de dados: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Inicia a sessão (caso ainda não tenha sido iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define o fuso horário padrão do sistema
date_default_timezone_set('America/Belem');

// Função simples para verificar se o usuário está logado
// (Movido para auth_permissoes.php)
/*
function verificaLogin()
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}
*/

// Função que retorna true se usuário é admin, false caso contrário
function usuarioEhAdmin()
{
    global $conn;
    if (empty($_SESSION['usuario_id'])) {
        return false;
    }

    // ID 1 é sempre admin
    if ($_SESSION['usuario_id'] == 1) {
        return true;
    }

    // Verifica se o grupo do usuário é 'Administradores'
    $uid = (int)$_SESSION['usuario_id'];
    $sql = "SELECT g.nome FROM usuarios u JOIN grupos_usuarios g ON u.id_grupo = g.id WHERE u.id = $uid LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['nome'] === 'Administradores') {
            return true;
        }
    }

    return false;
}

// Função para verificar se o usuário logado é administrador (com redirect)
function verificaAdmin()
{
    if (!usuarioEhAdmin()) {
        header('Location: home.php');
        exit;
    }
}
