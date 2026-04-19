<?php
// auth_permissoes.php
// Sistema de verificação de permissões

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário logado possui uma determinada permissão.
 * 
 * @param string $chave_permissao A chave da permissão (ex: 'pacientes.excluir')
 * @return bool True se permitido, False caso contrário.
 */
function temPermissao($chave_permissao) {
    global $conn;

    // 1. Se for super-admin (id=1 ou perfil admin antigo), libera tudo?
    // Vamos preferir usar a lógica de grupos, mas manter um fallback para o admin principal é seguro.
    if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] == 1) {
        return true; 
    }

    // 1.1 Se for do Grupo de Administradores (id_grupo=1), também libera tudo
    if (isset($_SESSION['id_grupo']) && $_SESSION['id_grupo'] == 1) {
        return true;
    }

    // 2. Verifica se as permissões já estão na sessão (cache de sessão)
    if (!isset($_SESSION['permissoes_usuario'])) {
        carregarPermissoesNaSessao();
    }

    // 3. Checa a permissão no array
    if (in_array($chave_permissao, $_SESSION['permissoes_usuario'] ?? [])) {
        return true;
    }

    return false;
}

/**
 * Carrega as permissões do usuário do banco para a sessão.
 * Deve ser chamado no login ou quando recarregar permissões.
 */
function carregarPermissoesNaSessao() {
    global $conn;

    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['permissoes_usuario'] = [];
        return;
    }

    $user_id = $_SESSION['usuario_id'];

    // Busca o grupo do usuário
    $sqlGrupo = "SELECT id_grupo FROM usuarios WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sqlGrupo);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuario = $res->fetch_assoc();
    $stmt->close();

    if (!$usuario || empty($usuario['id_grupo'])) {
        $_SESSION['permissoes_usuario'] = [];
        return;
    }

    $id_grupo = $usuario['id_grupo'];
    $_SESSION['id_grupo'] = $id_grupo; // Garante que o grupo esteja na sessão

    // Busca todas as chaves de permissão desse grupo
    $sqlPerms = "
        SELECT p.chave 
        FROM permissoes p
        INNER JOIN grupos_permissoes gp ON p.id = gp.id_permissao
        WHERE gp.id_grupo = ?
    ";
    
    $stmt2 = $conn->prepare($sqlPerms);
    $stmt2->bind_param('i', $id_grupo);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $permissoes = [];
    while ($row = $res2->fetch_assoc()) {
        $permissoes[] = $row['chave'];
    }
    $stmt2->close();

    $_SESSION['permissoes_usuario'] = $permissoes;
}

/**
 * Função auxiliar para verificar permissão e redirecionar/parar se não tiver.
 */
function exigirPermissao($chave_permissao) {
    if (!temPermissao($chave_permissao)) {
        die('<div class="alert alert-danger">Acesso Negado. Você não tem permissão para acessar este recurso (' . htmlspecialchars($chave_permissao) . ').</div>');
    }
}

/**
 * Verifica se o usuário está logado.
 * Se não estiver, redireciona para o login.
 */
function verificaLogin() {
    // Se a rota atual for login ou autenticação, não verifica
    $rota = $_GET['r'] ?? '';
    if ($rota === 'login' || $rota === 'auth/authenticate') {
        return;
    }

    if (empty($_SESSION['usuario_id'])) {
        header('Location: index.php?r=login');
        exit;
    }
}

