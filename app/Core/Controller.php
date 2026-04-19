<?php
namespace App\Core;

class Controller {
    protected $db;

    public function __construct() {
        global $conn;
        $this->db = $conn;
    }

    protected function view($viewName, $data = []) {
        // Auto-detect route for sidebar active state if not provided
        if (!isset($data['route'])) {
            $r = $_GET['r'] ?? 'home';
            // Extract base route (e.g. 'pedidos/create' -> 'pedidos')
            $parts = explode('/', $r);
            $data['route'] = $parts[0];
        }

        extract($data);
        // O layout principal será responsável por incluir a view específica
        // Passamos o caminho da view para o layout
        $contentView = __DIR__ . '/../Views/' . $viewName . '.php';
        
        $layoutFile = isset($layout) && is_string($layout) ? __DIR__ . '/../Views/layouts/' . $layout . '.php' : __DIR__ . '/../Views/layouts/main.php';

        if (file_exists($layoutFile)) {
            require_once $layoutFile;
        } else {
            // Fallback se não houver layout (ex: requisições AJAX que retornam HTML parcial)
            if (file_exists($contentView)) {
                require_once $contentView;
            } else {
                echo "View not found: " . $viewName;
            }
        }
    }

    protected function json($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Verify if user is logged in
     */
    protected function requireAuth() {
        if (function_exists('verificaLogin')) {
            verificaLogin();
        } else {
             if (empty($_SESSION['usuario_id'])) {
                header('Location: login.php');
                exit;
            }
        }
    }

    /**
     * Check if user has permission
     * @param string $chave_permissao
     * @return bool
     */
    protected function temPermissao($chave_permissao) {
        if (function_exists('temPermissao')) {
            return temPermissao($chave_permissao);
        }
        return false;
    }

    /**
     * Require permission or die/redirect
     * @param string $chave_permissao
     */
    protected function exigirPermissao($chave_permissao) {
        if (function_exists('exigirPermissao')) {
            exigirPermissao($chave_permissao);
        } else {
             if (!$this->temPermissao($chave_permissao)) {
                 die('<div class="alert alert-danger">Acesso Negado. Você não tem permissão para acessar este recurso (' . htmlspecialchars($chave_permissao) . ').</div>');
             }
        }
    }
}
