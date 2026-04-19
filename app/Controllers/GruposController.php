<?php

namespace App\Controllers;

use App\Core\Controller;

class GruposController extends Controller {

    public function index() {
        $conn = $this->db;
        $this->requireAuth();

        if (!usuarioEhAdmin()) {
            $_SESSION['flash_error'] = 'Acesso Negado. Apenas administradores.';
            header('Location: index.php?r=home');
            exit;
        }

        $id_grupo_selecionado = isset($_GET['id_grupo']) ? (int)$_GET['id_grupo'] : 0;
        $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
        if ($page < 1) $page = 1;
        $limit = 10;

        // 1. Build Permission Matrix (Sorted)
        $matriz_permissoes = $this->getPermissionsMatrix();

        // Pagination
        $total_rows = count($matriz_permissoes);
        $total_pages = ceil($total_rows / $limit);
        $offset = ($page - 1) * $limit;
        $matriz_paginada = array_slice($matriz_permissoes, $offset, $limit, true);

        // 2. Fetch Groups
        $grupos = [];
        $res = $conn->query("SELECT * FROM grupos_usuarios ORDER BY nome");
        while ($row = $res->fetch_assoc()) $grupos[] = $row;

        // 3. Fetch Selected Group Data & Permissions
        $grupo_atual = null;
        $permissoes_grupo = [];
        if ($id_grupo_selecionado > 0) {
            foreach ($grupos as $g) {
                if ($g['id'] == $id_grupo_selecionado) {
                    $grupo_atual = $g;
                    break;
                }
            }
            if ($grupo_atual) {
                $res = $conn->query("SELECT id_permissao FROM grupos_permissoes WHERE id_grupo = $id_grupo_selecionado");
                while ($row = $res->fetch_assoc()) {
                    $permissoes_grupo[] = $row['id_permissao'];
                }
            }
        }

        $this->view('grupos/index', [
            'grupos' => $grupos,
            'grupo_atual' => $grupo_atual,
            'matriz_paginada' => $matriz_paginada,
            'permissoes_grupo' => $permissoes_grupo,
            'id_grupo_selecionado' => $id_grupo_selecionado,
            'page' => $page,
            'total_pages' => $total_pages
        ]);
    }

    public function store() {
        $conn = $this->db;
        $this->requireAuth();
        if (!usuarioEhAdmin()) {
            header('Location: index.php?r=home');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id_grupo'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if ($nome) {
                if ($id > 0) {
                    $stmt = $conn->prepare("UPDATE grupos_usuarios SET nome = ?, descricao = ? WHERE id = ?");
                    $stmt->bind_param('ssi', $nome, $descricao, $id);
                    if ($stmt->execute()) {
                        $_SESSION['flash_success'] = "Grupo atualizado com sucesso.";
                    } else {
                        $_SESSION['flash_error'] = "Erro ao atualizar grupo.";
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO grupos_usuarios (nome, descricao) VALUES (?, ?)");
                    $stmt->bind_param('ss', $nome, $descricao);
                    if ($stmt->execute()) {
                        $_SESSION['flash_success'] = "Grupo criado com sucesso.";
                        $id = $stmt->insert_id;
                    } else {
                        $_SESSION['flash_error'] = "Erro ao criar grupo.";
                    }
                }
            } else {
                $_SESSION['flash_error'] = "Nome do grupo é obrigatório.";
            }
            
            header("Location: index.php?r=grupos&id_grupo=$id");
            exit;
        }
    }

    public function delete() {
        $conn = $this->db;
        $this->requireAuth();
        if (!usuarioEhAdmin()) {
            header('Location: index.php?r=home');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM grupos_permissoes WHERE id_grupo = $id");
            $conn->query("UPDATE usuarios SET id_grupo = NULL WHERE id_grupo = $id");
            $conn->query("DELETE FROM grupos_usuarios WHERE id = $id");
            $_SESSION['flash_success'] = "Grupo excluído.";
        }
        header("Location: index.php?r=grupos");
        exit;
    }

    public function save_permissions() {
        $conn = $this->db;
        $this->requireAuth();
        if (!usuarioEhAdmin()) {
            header('Location: index.php?r=home');
            exit;
        }

        $id_grupo = (int)($_POST['id_grupo'] ?? 0);
        $page = (int)($_POST['page'] ?? 1);
        
        if ($id_grupo > 0) {
            // Re-build matrix to know what to clear for this page
            $matriz_permissoes = $this->getPermissionsMatrix();

            $limit = 10;
            $offset = ($page - 1) * $limit;
            $slice_atual = array_slice($matriz_permissoes, $offset, $limit, true);
            
            $ids_na_pagina = [];
            foreach ($slice_atual as $cat => $acoes) {
                foreach ($acoes as $perm) {
                    $ids_na_pagina[] = $perm['id'];
                }
            }

            if (!empty($ids_na_pagina)) {
                $ids_str = implode(',', $ids_na_pagina);
                $conn->query("DELETE FROM grupos_permissoes WHERE id_grupo = $id_grupo AND id_permissao IN ($ids_str)");
            }

            if (isset($_POST['perms']) && is_array($_POST['perms'])) {
                $stmt = $conn->prepare("INSERT IGNORE INTO grupos_permissoes (id_grupo, id_permissao) VALUES (?, ?)");
                foreach ($_POST['perms'] as $id_permissao) {
                    $stmt->bind_param('ii', $id_grupo, $id_permissao);
                    $stmt->execute();
                }
            }
            $_SESSION['flash_success'] = "Permissões atualizadas.";
        }
        
        header("Location: index.php?r=grupos&id_grupo=$id_grupo&page=$page");
        exit;
    }

    private function getPermissionsMatrix() {
        $conn = $this->db;
        $matriz_permissoes = [];
        $res = $conn->query("SELECT * FROM permissoes");
        while ($row = $res->fetch_assoc()) {
            $parts = explode('.', $row['chave']);
            $acao = end($parts);
            if (count($parts) < 2) $acao = 'geral';
            $matriz_permissoes[$row['categoria']][$acao] = $row;
        }

        // Remove idle categories
        $categoriasOciosas = [
            'Sistema > Cadastros > Clientes',
            'Sistema > Cadastros > Produtos',
            'Sistema > Comercial > Vendas',
            'Sistema > Configurações > Grupos',
            'Sistema > Configurações > Usuários'
        ];
        foreach ($categoriasOciosas as $catRemover) {
            if (isset($matriz_permissoes[$catRemover])) {
                unset($matriz_permissoes[$catRemover]);
            }
        }

        // Custom Sort Order
        $order = [
            // 1. Cadastros
            '01. Cadastros',
            '01. Cadastros > 01. Municípios',
            '01. Cadastros > 02. Postos de Coleta',
            '01. Cadastros > 03. Convênios',
            '01. Cadastros > 04. Parceiros',
            '01. Cadastros > 05. Laboratórios',
            '01. Cadastros > 06. Médicos',
            '01. Cadastros > 07. Pacientes',
            '01. Cadastros > 08. Fornecedores',
            '01. Cadastros > 09. Exames > 01. Exame',
            '01. Cadastros > 09. Exames > 02. Setor',
            '01. Cadastros > 09. Exames > 03. Material Biológico',
            '01. Cadastros > 09. Exames > 04. Recipiente / Tipo de Coleta',
            '01. Cadastros > 09. Exames > 05. Conservação Reagentes',
            '01. Cadastros > 10. Pendências',
            '01. Cadastros > 11. Resultado Texto',

            // 2. Movimentação
            '02. Movimentação',
            '02. Movimentação > 01. Orçamentos',
            '02. Movimentação > 02. Pedidos',
            '02. Movimentação > 03. Triagem',
            '02. Movimentação > 04. Resultados',
            '02. Movimentação > 05. Fechamento de Caixa',

            // 3. Financeiro
            '03. Financeiro',
            '03. Financeiro > 01. Contas a Receber',
            '03. Financeiro > 02. Contas a Pagar',
            '03. Financeiro > 03. Transferências',
            '03. Financeiro > 04. Contas Bancárias',
            '03. Financeiro > 05. Tipos de Recebimentos',
            '03. Financeiro > 06. Classificação Recebimentos',

            // 4. Relatórios
            '04. Relatórios',
            '04. Relatórios > 01. Situação dos Pedidos',
            '04. Relatórios > 02. Extrato Conta',

            // 5. Configurações
            '05. Configurações',
            '05. Configurações > 01. Config. Geral',
            '05. Configurações > 02. Usuários > 01. Gerenciar Usuários',
            '05. Configurações > 02. Usuários > 02. Grupos e Permissões',
            
            // 6. Campanhas
            '06. Campanhas',
            '06. Campanhas > 01. Campanhas/Programas',
            '06. Campanhas > 02. Associar Pacientes',
        ];

        uksort($matriz_permissoes, function($a, $b) use ($order) {
            $posA = array_search($a, $order);
            $posB = array_search($b, $order);

            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;

            if ($posA == $posB) return strcasecmp($a, $b);
            return $posA - $posB;
        });

        return $matriz_permissoes;
    }
}
