<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;

class TransferenciasBancariasController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        
        $db = $this->db;
        
        // Fetch transfers
        $lista = [];
        $stmt = $db->query("SELECT t.id, t.conta_bancaria_id, t.valor, t.data, t.status, t.protocolo, cb.banco, cb.agencia, cb.conta FROM transferencias_bancarias t LEFT JOIN contas_bancarias cb ON cb.id = t.conta_bancaria_id ORDER BY t.id DESC LIMIT 200");
        
        if ($stmt) {
            $grupos = [];
            while ($r = $stmt->fetch_assoc()) {
                $prot = (string)($r['protocolo'] ?? '');
                $base = preg_replace('/-(OUT|IN)$/','',$prot);
                
                if (!isset($grupos[$base])) { 
                    $grupos[$base] = [
                        'id' => null, // We'll use the minimum ID as the identifier
                        'origem' => null,
                        'destino' => null,
                        'valor' => null,
                        'data' => null,
                        'status' => null,
                        'base' => $base
                    ]; 
                }
                
                $accStr = trim((string)($r['banco'] ?? '')).' - '.trim((string)($r['agencia'] ?? '')).' / '.trim((string)($r['conta'] ?? ''));
                
                if (substr($prot,-4) === '-OUT' || preg_match('/-OUT$/', $prot)) { 
                    $grupos[$base]['origem'] = $accStr; 
                    $grupos[$base]['id'] = $grupos[$base]['id'] ? min($grupos[$base]['id'], (int)$r['id']) : (int)$r['id']; 
                } elseif (substr($prot,-3) === 'IN' || preg_match('/-IN$/', $prot)) { 
                    $grupos[$base]['destino'] = $accStr; 
                    $grupos[$base]['id'] = $grupos[$base]['id'] ? min($grupos[$base]['id'], (int)$r['id']) : (int)$r['id']; 
                }
                
                if ($grupos[$base]['valor'] === null) { $grupos[$base]['valor'] = (float)($r['valor'] ?? 0); }
                if ($grupos[$base]['data'] === null) { $grupos[$base]['data'] = (string)($r['data'] ?? ''); }
                if ($grupos[$base]['status'] === null) { $grupos[$base]['status'] = (string)($r['status'] ?? ''); }
            }
            foreach ($grupos as $g) { $lista[] = $g; }
        }

        $this->view('financeiro/transferencias/index', [
            'lista' => $lista
        ]);
    }

    public function create()
    {
        $this->requireAuth();
        
        $db = $this->db;
        $contas = [];
        $stmt = $db->query("SELECT id, banco, agencia, conta FROM contas_bancarias WHERE ativa = 1 ORDER BY banco ASC, agencia ASC");
        if ($stmt) {
            while($r = $stmt->fetch_assoc()) {
                $contas[] = $r;
            }
        }

        $this->view('financeiro/transferencias/form', [
            'contas' => $contas
        ]);
    }

    public function get_balance()
    {
        $this->requireAuth();
        
        $contaId = (int)($_GET['id'] ?? 0);
        if ($contaId <= 0) {
            echo json_encode(['balance' => 0]);
            exit;
        }

        $db = $this->db;
        $balance = 0.0;

        // Check if movement table exists (legacy check)
        $hasMov = false;
        $chkMov = $db->query("SHOW TABLES LIKE 'contas_bancarias_movimentacoes'");
        if ($chkMov && $chkMov->num_rows > 0) $hasMov = true;

        if ($hasMov) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='E' THEN valor ELSE -valor END),0) AS saldo FROM contas_bancarias_movimentacoes WHERE conta_bancaria_id = ?");
        } else {
            $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) AS saldo FROM contas_bancarias_entradas WHERE conta_bancaria_id = ?");
        }

        if ($stmt) {
            $stmt->bind_param('i', $contaId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $balance = (float)$row['saldo'];
            }
        }

        echo json_encode(['balance' => $balance]);
        exit;
    }

    public function store()
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=transferencias');
            exit;
        }

        $origem_id = (int)($_POST['origem_id'] ?? 0);
        $destino_id = (int)($_POST['destino_id'] ?? 0);
        $valor = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
        $data = $_POST['data'] ?? date('Y-m-d');

        if ($origem_id <= 0 || $destino_id <= 0 || $origem_id === $destino_id || $valor <= 0) {
            header('Location: index.php?r=transferencias/create&error=Dados inválidos');
            exit;
        }

        // Check balance
        $db = $this->db;
        // ... (Balance check logic same as get_balance)
        $hasMov = false;
        $chkMov = $db->query("SHOW TABLES LIKE 'contas_bancarias_movimentacoes'");
        if ($chkMov && $chkMov->num_rows > 0) $hasMov = true;

        $saldo_origem = 0.0;
        if ($hasMov) {
            $stSaldo = $db->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='E' THEN valor ELSE -valor END),0) AS saldo FROM contas_bancarias_movimentacoes WHERE conta_bancaria_id = ?");
        } else {
            $stSaldo = $db->prepare("SELECT COALESCE(SUM(valor),0) AS saldo FROM contas_bancarias_entradas WHERE conta_bancaria_id = ?");
        }
        $stSaldo->bind_param('i', $origem_id);
        $stSaldo->execute();
        $res = $stSaldo->get_result();
        if ($res && $rw = $res->fetch_assoc()) {
            $saldo_origem = (float)$rw['saldo'];
        }

        if ($saldo_origem < $valor) {
            header('Location: index.php?r=transferencias/create&error=Saldo insuficiente');
            exit;
        }

        // Execute transfer
        $protocolo = 'TRF-' . date('Ymd-His');
        $prot_out = $protocolo . '-OUT';
        $prot_in = $protocolo . '-IN';

        $db->begin_transaction();

        try {
            // Insert OUT
            $stmt1 = $db->prepare("INSERT INTO transferencias_bancarias (recebimento_id, conta_bancaria_id, valor, data, protocolo, status) VALUES (0, ?, ?, ?, ?, 'EFETIVADA')");
            $stmt1->bind_param('idss', $origem_id, $valor, $data, $prot_out);
            $stmt1->execute();
            $id_out = $db->insert_id;

            // Insert IN
            $stmt2 = $db->prepare("INSERT INTO transferencias_bancarias (recebimento_id, conta_bancaria_id, valor, data, protocolo, status) VALUES (0, ?, ?, ?, ?, 'EFETIVADA')");
            $stmt2->bind_param('idss', $destino_id, $valor, $data, $prot_in);
            $stmt2->execute();
            $id_in = $db->insert_id;

            if ($hasMov) {
                $loteId = min((int)$id_out, (int)$id_in);
                $crNull = null;

                // Movement S (Saída)
                $stmtS = $db->prepare("INSERT INTO contas_bancarias_movimentacoes (conta_bancaria_id, cr_id, emissao, valor, lote_id, tipo) VALUES (?, ?, ?, ?, ?, 'S')");
                $stmtS->bind_param('iisdi', $origem_id, $crNull, $data, $valor, $loteId);
                $stmtS->execute();

                // Movement E (Entrada)
                $stmtE = $db->prepare("INSERT INTO contas_bancarias_movimentacoes (conta_bancaria_id, cr_id, emissao, valor, lote_id, tipo) VALUES (?, ?, ?, ?, ?, 'E')");
                $stmtE->bind_param('iisdi', $destino_id, $crNull, $data, $valor, $loteId);
                $stmtE->execute();
            }

            $db->commit();
            header('Location: index.php?r=transferencias&success=1');
        } catch (Exception $e) {
            $db->rollback();
            header('Location: index.php?r=transferencias/create&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public function reverse()
    {
        $this->requireAuth();
        
        $base = $_POST['base'] ?? '';
        if (empty($base)) {
            echo json_encode(['ok' => false, 'erro' => 'Protocolo inválido']);
            exit;
        }

        $db = $this->db;
        $pout = $base . '-OUT';
        $pin = $base . '-IN';

        // Find IDs
        $id_out = 0; $id_in = 0; $origem_id = 0; $destino_id = 0; $valor = 0.0; $data = '';

        $stmtO = $db->prepare("SELECT id, conta_bancaria_id, valor, data FROM transferencias_bancarias WHERE protocolo = ? LIMIT 1");
        $stmtO->bind_param('s', $pout);
        $stmtO->execute();
        $resO = $stmtO->get_result();
        if ($resO && $rwO = $resO->fetch_assoc()) {
            $id_out = (int)$rwO['id'];
            $origem_id = (int)$rwO['conta_bancaria_id'];
            $valor = (float)$rwO['valor'];
            $data = (string)$rwO['data'];
        }

        $stmtI = $db->prepare("SELECT id, conta_bancaria_id, valor, data FROM transferencias_bancarias WHERE protocolo = ? LIMIT 1");
        $stmtI->bind_param('s', $pin);
        $stmtI->execute();
        $resI = $stmtI->get_result();
        if ($resI && $rwI = $resI->fetch_assoc()) {
            $id_in = (int)$rwI['id'];
            $destino_id = (int)$rwI['conta_bancaria_id'];
        }

        if (!$id_out || !$id_in) {
            echo json_encode(['ok' => false, 'erro' => 'Transferência não encontrada']);
            exit;
        }

        $db->begin_transaction();
        try {
            $hasMov = false;
            $chkMov = $db->query("SHOW TABLES LIKE 'contas_bancarias_movimentacoes'");
            if ($chkMov && $chkMov->num_rows > 0) $hasMov = true;

            if ($hasMov) {
                $loteId = min($id_out, $id_in);
                
                // Delete movements by lote_id
                $stmtDelL = $db->prepare("DELETE FROM contas_bancarias_movimentacoes WHERE lote_id = ?");
                $stmtDelL->bind_param('i', $loteId);
                $stmtDelL->execute();
                
                if ($stmtDelL->affected_rows === 0) {
                    // Fallback to specific IDs if lote_id didn't work (legacy issue?)
                    $db->query("DELETE FROM contas_bancarias_movimentacoes WHERE lote_id = $id_out");
                    $db->query("DELETE FROM contas_bancarias_movimentacoes WHERE lote_id = $id_in");
                }
                
                // Fallback specific match
                $stmtDelS = $db->prepare("DELETE FROM contas_bancarias_movimentacoes WHERE conta_bancaria_id = ? AND emissao = ? AND valor = ? AND tipo = 'S' LIMIT 1");
                $stmtDelS->bind_param('isd', $origem_id, $data, $valor);
                $stmtDelS->execute();
                
                $stmtDelE = $db->prepare("DELETE FROM contas_bancarias_movimentacoes WHERE conta_bancaria_id = ? AND emissao = ? AND valor = ? AND tipo = 'E' LIMIT 1");
                $stmtDelE->bind_param('isd', $destino_id, $data, $valor);
                $stmtDelE->execute();
            }

            // Delete transfers
            $stmtD = $db->prepare("DELETE FROM transferencias_bancarias WHERE id IN (?, ?)");
            $stmtD->bind_param('ii', $id_out, $id_in);
            $stmtD->execute();

            $db->commit();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }
}
