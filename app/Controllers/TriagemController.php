<?php

namespace App\Controllers;

use App\Core\Controller;

class TriagemController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        
        // Load pedidos with pending triage items
        // Legacy: status_exame IN ('C', 'R') OR pendencia_material_triagem = 'S'
        // 'C' = Cadastrado, 'R' = Recebido/Realizando?, 'T' = Triado
        
        $db = $this->db;
        $sql = "
            SELECT 
                p.id,
                p.codigo_pedido,
                p.entrada,
                pa.nome        AS paciente_nome,
                pa.sexo        AS paciente_sexo,
                pa.nascimento  AS paciente_nascimento,
                (SELECT COUNT(*) FROM pedido_itens pi WHERE pi.pedido_id = p.id AND (pi.status_exame IN ('C', 'R') OR pi.pendencia_material_triagem = 'S')) as pending_count
            FROM pedidos p
            JOIN pacientes pa ON pa.id = p.paciente_id
            WHERE EXISTS (
                SELECT 1
                FROM pedido_itens pi
                WHERE pi.pedido_id = p.id
                AND (pi.status_exame IN ('C', 'R') OR pi.pendencia_material_triagem = 'S')
            )
            ORDER BY p.id DESC
        ";
        
        $pedidos = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
        
        $this->view('triagem/index', [
            'pedidos' => $pedidos
        ]);
    }

    public function show()
    {
        $this->requireAuth();
        $pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($pedido_id <= 0) {
            header('Location: index.php?r=triagem');
            exit;
        }

        $db = $this->db;
        
        // Get Pedido Info
        $stmt = $db->prepare("
            SELECT 
                p.codigo_pedido,
                p.id,
                p.entrada,
                pa.nome       AS paciente_nome,
                pa.sexo       AS paciente_sexo,
                pa.nascimento AS paciente_nascimento
            FROM pedidos p
            JOIN pacientes pa ON pa.id = p.paciente_id
            WHERE p.id = ?
        ");
        $stmt->bind_param('i', $pedido_id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        
        if (!$pedido) {
            header('Location: index.php?r=triagem');
            exit;
        }
        
        // Get Items
        $stmtItems = $db->prepare("
            SELECT 
                pi.id_amostra,
                pi.pedido_id,
                pi.status_exame,
                pi.pendencia_material_triagem,
                pi.data_pendencia_material_triagem,
                pi.data_entrega_material_triagem,
                pi.obs_pendencia_triagem,
                pi.data_triagem,
                pi.hora_triagem,
                e.mnemonico_local AS mnemonico,
                e.nome            AS exame_nome,
                mb.material_nome  AS material_biologico,
                l.laboratorio_nome AS laboratorio_nome
            FROM pedido_itens pi
            LEFT JOIN exames e              ON e.id = pi.exame_id
            LEFT JOIN materiais_biologicos mb ON mb.id = e.material_biologico_id
            LEFT JOIN laboratorios l        ON l.id = e.laboratorio_id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id_amostra ASC
        ");
        $stmtItems->bind_param('i', $pedido_id);
        $stmtItems->execute();
        $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $this->view('triagem/show', [
            'pedido' => $pedido,
            'items' => $items
        ]);
    }

    public function store()
    {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=triagem');
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        $item_ids = $_POST['item_ids'] ?? [];
        
        if ($pedido_id <= 0 || empty($item_ids) || !is_array($item_ids)) {
            // Error handling could be better (flash messages)
            header('Location: index.php?r=triagem/show&id=' . $pedido_id . '&error=1');
            exit;
        }
        
        $db = $this->db;
        
        if ($action === 'confirmar_triagem') {
            // Confirm Triage
            $this->exigirPermissao('triagem.salvar');
            
            $sql = "UPDATE pedido_itens 
                    SET status_exame = 'T', 
                        data_triagem = CURDATE(), 
                        hora_triagem = CURTIME(),
                        pendencia_material_triagem = 'N',
                        data_pendencia_material_triagem = NULL,
                        data_entrega_material_triagem = NULL
                    WHERE pedido_id = ? AND id_amostra = ?";
            
            $stmt = $db->prepare($sql);
            foreach ($item_ids as $id_amostra) {
                $id_amostra = (int)$id_amostra;
                $stmt->bind_param('ii', $pedido_id, $id_amostra);
                $stmt->execute();
            }
            $stmt->close();
            
        } elseif ($action === 'marcar_pendencia') {
            // Mark Pendency
            $sql = "UPDATE pedido_itens 
                    SET pendencia_material_triagem = 'S',
                        data_pendencia_material_triagem = CURDATE()
                    WHERE pedido_id = ? AND id_amostra = ?";
            
            $stmt = $db->prepare($sql);
            foreach ($item_ids as $id_amostra) {
                $id_amostra = (int)$id_amostra;
                $stmt->bind_param('ii', $pedido_id, $id_amostra);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        header('Location: index.php?r=triagem/show&id=' . $pedido_id . '&success=1');
        exit;
    }

    public function update_pendencia()
    {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=triagem');
            exit;
        }

        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        $id_amostra = (int)($_POST['id_amostra'] ?? 0);
        $data_prev = trim($_POST['data_entrega'] ?? '');
        $obs = trim($_POST['obs'] ?? '');
        
        $data_prev = ($data_prev !== '') ? $data_prev : null;

        if ($pedido_id > 0 && $id_amostra > 0) {
            $this->exigirPermissao('pendencias.salvar'); // Ensure permission
            
            $stmt = $this->db->prepare("UPDATE pedido_itens SET data_entrega_material_triagem = ?, obs_pendencia_triagem = ? WHERE pedido_id = ? AND id_amostra = ?");
            $stmt->bind_param('ssii', $data_prev, $obs, $pedido_id, $id_amostra);
            $stmt->execute();
            $stmt->close();
            
            header('Location: index.php?r=triagem/show&id=' . $pedido_id . '&success=1');
        } else {
            header('Location: index.php?r=triagem/show&id=' . $pedido_id . '&error=1');
        }
        exit;
    }
}
