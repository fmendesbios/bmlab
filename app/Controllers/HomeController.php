<?php

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller {

    public function index() {
        $conn = $this->db;
        $this->requireAuth();

        // Load Pendências de Material
        $pendMateriais = [];
        $sqlPend = "
            SELECT 
                pi.id_amostra,
                pi.pedido_id,
                pi.data_coleta,
                pi.status_exame,
                pa.nome AS paciente_nome,
                e.mnemonico_local AS mnemonico,
                e.nome AS exame_nome,
                mb.material_nome AS material_biologico,
                pi.pendencia_material_triagem,
                pi.data_entrega_material_triagem,
                pi.obs_pendencia_triagem
            FROM pedido_itens pi
            JOIN pedidos p ON p.id = pi.pedido_id
            JOIN pacientes pa ON pa.id = p.paciente_id
            LEFT JOIN exames e ON e.id = pi.exame_id
            LEFT JOIN materiais_biologicos mb ON mb.id = e.material_biologico_id
            WHERE pi.pendencia_material_triagem = 'S' OR pi.status_exame = 'R'
            ORDER BY p.id DESC, pi.id_amostra ASC
        ";
        $resPend = $conn->query($sqlPend);
        if ($resPend) {
            while ($r = $resPend->fetch_assoc()) $pendMateriais[] = $r;
        }

        // Load Bloqueios de Desconto (Pedidos)
        $bloqPedidos = [];
        $resBloqP = $conn->query("
            SELECT 
                p.id,
                p.entrada,
                p.total_bruto,
                p.total_liquido,
                p.desconto_percentual,
                p.desconto_valor,
                pa.nome AS paciente_nome
            FROM pedidos p
            LEFT JOIN pacientes pa ON pa.id = p.paciente_id
            WHERE p.bloqueio_desconto = 'S'
            ORDER BY p.id DESC
            LIMIT 50
        ");
        if ($resBloqP) {
            while ($r = $resBloqP->fetch_assoc()) $bloqPedidos[] = $r;
        }

        // Load Bloqueios de Desconto (Orçamentos)
        $bloqOrcamentos = [];
        $resBloqO = $conn->query("
            SELECT 
                o.id,
                o.criado_em,
                o.total_bruto,
                o.total_liquido,
                o.desconto_percentual,
                o.desconto_valor,
                pa.nome AS paciente_nome,
                o.paciente_avulso
            FROM orcamentos o
            LEFT JOIN pacientes pa ON pa.id = o.paciente_id
            WHERE o.bloqueio_desconto = 'S'
            ORDER BY o.id DESC
            LIMIT 50
        ");
        if ($resBloqO) {
            while ($r = $resBloqO->fetch_assoc()) $bloqOrcamentos[] = $r;
        }

        // --- Cards Logic (from cards.php) ---

        // 1. Aniversariantes do mês
        $pag_aniv = isset($_GET['pag_aniv']) ? max(1, intval($_GET['pag_aniv'])) : 1;
        $limit_aniv = 5;
        $offset_aniv = ($pag_aniv - 1) * $limit_aniv;
        
        $total_aniv = 0;
        $resTA = $conn->query("SELECT COUNT(*) FROM pacientes WHERE MONTH(nascimento) = MONTH(CURDATE())");
        if ($resTA) $total_aniv = $resTA->fetch_row()[0];
        
        $total_paginas_aniv = ceil($total_aniv / $limit_aniv);
        
        $aniversariantes = [];
        $resAniv = $conn->query("
            SELECT nome, celular AS whatsapp, DATE_FORMAT(nascimento, '%d/%m') AS nascimento_formatado 
            FROM pacientes 
            WHERE MONTH(nascimento) = MONTH(CURDATE())
            ORDER BY DAY(nascimento)
            LIMIT $offset_aniv, $limit_aniv
        ");
        if ($resAniv) $aniversariantes = $resAniv->fetch_all(MYSQLI_ASSOC);

        // 2. Check-up 90 dias
        $pag_check = isset($_GET['pag_check']) ? max(1, intval($_GET['pag_check'])) : 1;
        $limit_check = 5;
        $offset_check = ($pag_check - 1) * $limit_check;
        
        $hasLogsEnvio = false;
        $chk = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'logs_envio'");
        if ($chk) { 
            $row = $chk->fetch_row(); 
            $hasLogsEnvio = ($row && (int)$row[0] > 0); 
            $chk->close(); 
        }
        
        $checkups_query = "SELECT p.id, p.nome, p.celular AS whatsapp, MAX(pe.id) AS pedido_id, MAX(pe.entrada) AS ultimo_pedido, DATE_FORMAT(MAX(pe.entrada), '%d/%m/%Y') AS data_formatada FROM pacientes p INNER JOIN pedidos pe ON pe.paciente_id = p.id ";
        if ($hasLogsEnvio) {
            $checkups_query .= "WHERE NOT EXISTS (SELECT 1 FROM logs_envio le WHERE le.tipo = 'checkup' AND le.telefone COLLATE utf8mb4_unicode_ci = CONCAT('55', REPLACE(REPLACE(REPLACE(p.celular, '(', ''), ')', ''), '-', '')) COLLATE utf8mb4_unicode_ci) ";
        } else {
            $checkups_query .= "WHERE 1=1 ";
        }
        $checkups_query .= "GROUP BY p.id HAVING DATE(MAX(pe.entrada)) = DATE_SUB(CURDATE(), INTERVAL 90 DAY) ORDER BY p.id";
        
        // Count total for pagination
        $total_check = 0;
        $resTotalCheck = $conn->query($checkups_query);
        if ($resTotalCheck) $total_check = $resTotalCheck->num_rows;
        
        $total_paginas_check = ceil($total_check / $limit_check);
        
        $checkups = [];
        $resCheck = $conn->query($checkups_query . " LIMIT $offset_check, $limit_check");
        if ($resCheck) $checkups = $resCheck->fetch_all(MYSQLI_ASSOC);

        // 3. Top 10 pacientes
        $top_clientes = [];
        $resTop = $conn->query("
            SELECT 
                p.id,
                p.nome,
                p.celular AS whatsapp,
                COUNT(pe.id) AS total_pedidos,
                DATE_FORMAT(MAX(pe.entrada), '%d/%m/%Y') AS ultimo_pedido
            FROM pacientes p
            INNER JOIN pedidos pe ON pe.paciente_id = p.id
            GROUP BY p.id, p.nome, p.celular
            ORDER BY total_pedidos DESC, MAX(pe.entrada) DESC
            LIMIT 10
        ");
        if ($resTop) $top_clientes = $resTop->fetch_all(MYSQLI_ASSOC);

        // 4. Orçamentos (Novo / Negociação)
        $pag_orc = isset($_GET['pag_orc']) ? max(1, intval($_GET['pag_orc'])) : 1;
        $limit_orc = 5;
        $offset_orc = ($pag_orc - 1) * $limit_orc;
        
        $total_orc = 0;
        $resTO = $conn->query("SELECT COUNT(*) FROM orcamentos WHERE status IN ('N', 'NE')");
        if ($resTO) $total_orc = $resTO->fetch_row()[0];
        
        $total_paginas_orc = ceil($total_orc / $limit_orc);

        // Count separately for "Novo" (N) and "Negociação" (NE)
        $total_novo = 0;
        $total_negociacao = 0;
        $resCounts = $conn->query("
            SELECT 
                SUM(CASE WHEN status = 'N' THEN 1 ELSE 0 END) AS qtd_novo,
                SUM(CASE WHEN status = 'NE' THEN 1 ELSE 0 END) AS qtd_negociacao
            FROM orcamentos 
            WHERE status IN ('N', 'NE')
        ");
        if ($resCounts) {
            $rowCounts = $resCounts->fetch_assoc();
            $total_novo = (int)($rowCounts['qtd_novo'] ?? 0);
            $total_negociacao = (int)($rowCounts['qtd_negociacao'] ?? 0);
        }
        
        $orcamentos = [];
        $resOrc = $conn->query("
            SELECT o.id, o.codigo_orcamento, COALESCE(p.nome, o.paciente_avulso) AS paciente, o.status, u.usuario
            FROM orcamentos o
            LEFT JOIN pacientes p ON p.id = o.paciente_id
            LEFT JOIN usuarios u ON u.id = o.criado_por_usuario_id
            WHERE o.status IN ('N', 'NE')
            ORDER BY o.id DESC
            LIMIT $offset_orc, $limit_orc
        ");
        if ($resOrc) $orcamentos = $resOrc->fetch_all(MYSQLI_ASSOC);

        $nome_busca = isset($_GET['busca_nome']) ? trim($_GET['busca_nome']) : '';
        $resultados_busca = [];
        if ($nome_busca !== '') {
            if ($stmt = $conn->prepare("
                SELECT 
                    p.id, p.nome, p.celular AS whatsapp,
                    COUNT(pe.id) AS total_pedidos,
                    DATE_FORMAT(MAX(pe.entrada), '%d/%m/%Y') AS ultimo_pedido
                FROM pacientes p
                LEFT JOIN pedidos pe ON pe.paciente_id = p.id
                WHERE p.nome LIKE ?
                GROUP BY p.id, p.nome, p.celular
                ORDER BY total_pedidos DESC, MAX(pe.entrada) DESC
                LIMIT 5
            ")) {
                $like = "%{$nome_busca}%";
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $resultados_busca = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }

        $this->view('home/index', [
            'pendMateriais' => $pendMateriais,
            'bloqPedidos' => $bloqPedidos,
            'bloqOrcamentos' => $bloqOrcamentos,
            // Cards Data
            'aniversariantes' => $aniversariantes,
            'pag_aniv' => $pag_aniv,
            'total_paginas_aniv' => $total_paginas_aniv,
            'checkups' => $checkups,
            'pag_check' => $pag_check,
            'total_paginas_check' => $total_paginas_check,
            'top_clientes' => $top_clientes,
            'orcamentos' => $orcamentos,
            'total_novo' => $total_novo,
            'total_negociacao' => $total_negociacao,
            'pag_orc' => $pag_orc,
            'total_paginas_orc' => $total_paginas_orc,
            'nome_busca' => $nome_busca,
            'resultados_busca' => $resultados_busca
        ]);
    }

    public function update_pendencia() {
        $conn = $this->db;
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pedidoId = isset($_POST['pedido_id']) ? (int) $_POST['pedido_id'] : 0;
            $idAmostra = isset($_POST['id_amostra']) ? (int) $_POST['id_amostra'] : 0;
            $dataPrev = trim($_POST['data_entrega_material_triagem'] ?? '');
            $obsPend = trim($_POST['obs_pendencia_triagem'] ?? '');
            $dataPrev = ($dataPrev !== '') ? $dataPrev : null;

            $stmt = $conn->prepare("UPDATE pedido_itens SET data_entrega_material_triagem = ?, obs_pendencia_triagem = ? WHERE pedido_id = ? AND id_amostra = ?");
            $stmt->bind_param('ssii', $dataPrev, $obsPend, $pedidoId, $idAmostra);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Pendência atualizada.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar pendência.';
            }
        }
        header('Location: index.php?r=home');
        exit;
    }

    public function desbloquear() {
        $conn = $this->db;
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'erro' => 'Método inválido']);
            exit;
        }

        $tipo = strtoupper(trim($_POST['tipo'] ?? ''));
        $idRef = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $usuario_login = trim($_POST['usuario_login'] ?? '');
        $senha_login = trim($_POST['senha_login'] ?? '');

        if (!in_array($tipo, ['P', 'O'], true) || $idRef <= 0 || $usuario_login === '' || $senha_login === '') {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
            exit;
        }

        // Validate User
        $stU = $conn->prepare("SELECT id, senha, desconto_maximo_permitido FROM usuarios WHERE usuario = ? AND status = 1 LIMIT 1");
        $stU->bind_param('s', $usuario_login);
        $stU->execute();
        $rsU = $stU->get_result();
        
        if (!$rsU || $rsU->num_rows !== 1) {
            echo json_encode(['ok' => false, 'erro' => 'Usuário não encontrado.']);
            exit;
        }
        
        $urow = $rsU->fetch_assoc();
        if (!password_verify($senha_login, $urow['senha'])) {
            echo json_encode(['ok' => false, 'erro' => 'Credenciais inválidas']);
            exit;
        }

        $limite_user = isset($urow['desconto_maximo_permitido']) ? (float)$urow['desconto_maximo_permitido'] : 0.0;
        $limite_user = max(0.0, min(100.0, $limite_user));

        // Logic for Pedido vs Orcamento
        $table = ($tipo === 'P') ? 'pedidos' : 'orcamentos';
        
        $stSel = $conn->prepare("SELECT total_bruto, desconto_percentual, desconto_valor FROM $table WHERE id = ? LIMIT 1");
        $stSel->bind_param('i', $idRef);
        $stSel->execute();
        $rsSel = $stSel->get_result();
        
        if (!$rsSel || !($row = $rsSel->fetch_assoc())) {
            echo json_encode(['ok' => false, 'erro' => 'Registro não encontrado.']);
            exit;
        }

        $tb = (float)($row['total_bruto'] ?? 0);
        $dp = (float)($row['desconto_percentual'] ?? 0);
        $dv = (float)($row['desconto_valor'] ?? 0);
        $dp_eff = $dp > 0 ? $dp : ($tb > 0 ? round($dv * 100 / $tb, 2) : 0);

        if ($limite_user < $dp_eff) {
            echo json_encode(['ok' => false, 'erro' => 'Desconto Máximo Menor que o Permitido (' . number_format($limite_user, 2) . '% vs ' . number_format($dp_eff, 2) . '%)']);
            exit;
        }

        // Unlock
        $conn->query("UPDATE $table SET bloqueio_desconto = 'N' WHERE id = $idRef");

        // Log
        $uidLog = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
        
        // Ensure log table exists (Legacy check)
        $conn->query("CREATE TABLE IF NOT EXISTS logs_desbloqueio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo CHAR(1) NOT NULL,
            referencia_id INT NOT NULL,
            usuario_id INT NOT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $stLog = $conn->prepare("INSERT INTO logs_desbloqueio (tipo, referencia_id, usuario_id, criado_em) VALUES (?, ?, ?, NOW())");
        $stLog->bind_param('sii', $tipo, $idRef, $uidLog);
        $stLog->execute();

        echo json_encode(['ok' => true, 'msg' => 'Desbloqueado com sucesso.']);
        exit;
    }
}
