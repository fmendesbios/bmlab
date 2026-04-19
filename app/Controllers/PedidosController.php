<?php
namespace App\Controllers;

use App\Controllers\AgendamentoController;
use App\Core\Controller;
use Exception;

class PedidosController extends Controller {
    public function index() {
        $conn = $this->db;
        $this->exigirPermissao('pedidos.acesso');

        $busca = trim($_GET['busca'] ?? '');
        $data_ini = trim($_GET['data_ini'] ?? '');
        $data_fim = trim($_GET['data_fim'] ?? '');
        $posto_filtro = isset($_GET['posto_filtro']) ? (int)$_GET['posto_filtro'] : 0;
        $convenio_filtro = isset($_GET['convenio_filtro']) ? (int)$_GET['convenio_filtro'] : 0;
        $status_pagamento = trim($_GET['status_pagamento'] ?? '');
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $params = [];
        $types = "";
        $where = "WHERE 1=1";

        if ($busca !== '') {
            $where .= " AND (pa.nome LIKE ? OR p.codigo_pedido LIKE ? OR p.id LIKE ?)";
            $b = "%$busca%";
            $params[] = $b; $params[] = $b; $params[] = $b;
            $types .= "sss";
        }

        if ($data_ini && $data_fim) {
            $where .= " AND p.entrada BETWEEN ? AND ?";
            $d1 = "$data_ini 00:00:00";
            $d2 = "$data_fim 23:59:59";
            $params[] = $d1; $params[] = $d2;
            $types .= "ss";
        } elseif ($data_ini) {
            $where .= " AND p.entrada >= ?";
            $d1 = "$data_ini 00:00:00";
            $params[] = $d1;
            $types .= "s";
        } elseif ($data_fim) {
            $where .= " AND p.entrada <= ?";
            $d2 = "$data_fim 23:59:59";
            $params[] = $d2;
            $types .= "s";
        }

        if ($posto_filtro > 0) {
            $where .= " AND p.posto_id = ?";
            $params[] = $posto_filtro;
            $types .= "i";
        }

        if ($convenio_filtro > 0) {
            $where .= " AND p.convenio_id = ?";
            $params[] = $convenio_filtro;
            $types .= "i";
        }

        if ($status_pagamento !== '') {
            if ($status_pagamento === 'aberto') {
                $where .= " AND p.recebido_status != 'R'";
            } elseif ($status_pagamento === 'pago') {
                $where .= " AND p.recebido_status = 'R'";
            }
        }

        // Count total
        $total = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM pedidos p LEFT JOIN pacientes pa ON pa.id = p.paciente_id $where";
        $stmt = $conn->prepare($sqlCount);
        if ($stmt) {
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) $total = $row['total'];
            $stmt->close();
        }

        // Fetch data
        $pedidos = [];
        $sql = "SELECT 
                    p.*,
            pa.nome AS paciente_nome,
            pa.celular AS paciente_celular,
            c.nome  AS convenio_nome,
                    c.tipo  AS convenio_tipo,
                    m.nome  AS medico_nome,
                    po.nome_posto
                FROM pedidos p
                LEFT JOIN pacientes pa ON pa.id = p.paciente_id
                LEFT JOIN convenios c  ON c.id  = p.convenio_id
                LEFT JOIN medicos m    ON m.id  = p.medico_id
                LEFT JOIN postos po    ON po.id = p.posto_id
                $where
                ORDER BY p.id DESC LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                // Fetch exams for this order (mnemonics) and calculate delivery prediction
                $mnemonicos = [];
                $max_entrega_ts = 0;
                $entrada_ts = strtotime($row['entrada']);

                $resMn = $conn->query("SELECT e.mnemonico_local, pi.status_exame, e.prazo_execucao_local, pi.data_coleta FROM pedido_itens pi JOIN exames e ON e.id = pi.exame_id WHERE pi.pedido_id = {$row['id']}");
                if ($resMn) {
                    while ($r = $resMn->fetch_assoc()) {
                        $mnemonicos[] = $r;

                        // Prediction Calculation
                        $prazo = (int)($r['prazo_execucao_local'] ?? 0);
                        $data_base_ts = $entrada_ts;
                        if (!empty($r['data_coleta']) && $r['data_coleta'] !== '0000-00-00') {
                             $data_base_ts = strtotime($r['data_coleta']);
                        }
                        
                        // Add days (simple calculation, could be business days in future if needed)
                        $entrega_ts = strtotime("+$prazo days", $data_base_ts);
                        if ($entrega_ts > $max_entrega_ts) {
                            $max_entrega_ts = $entrega_ts;
                        }
                    }
                }
                $row['itens'] = $mnemonicos;
                $row['previsao_entrega'] = $max_entrega_ts > 0 ? date('d/m/Y', $max_entrega_ts) : '-';
                $pedidos[] = $row;
            }
            $stmt->close();
        }

        // Fetch postos for filter
        $postos = [];
        $resP = $conn->query("SELECT id, nome_posto FROM postos WHERE ativo = 1 ORDER BY nome_posto");
        while ($r = $resP->fetch_assoc()) $postos[] = $r;

        // Fetch convenios for filter
        $convenios = [];
        $resC = $conn->query("SELECT id, nome FROM convenios WHERE ativo = 1 ORDER BY nome");
        while ($r = $resC->fetch_assoc()) $convenios[] = $r;
        
        // Fetch tipos de recebimento for modal
        $tipos_pagamento = [];
        $resTP = $conn->query("SELECT id, nome FROM tipos_recebimentos WHERE ativo = 1 ORDER BY nome");
        while ($r = $resTP->fetch_assoc()) $tipos_pagamento[] = $r;

        $this->view('pedidos/index', [
            'pedidos' => $pedidos,
            'postos' => $postos,
            'convenios' => $convenios,
            'tipos_pagamento' => $tipos_pagamento,
            'totalRecords' => $total,
            'limit' => $limit,
            'filters' => [
                'busca' => $busca,
                'data_ini' => $data_ini,
                'data_fim' => $data_fim,
                'posto_filtro' => $posto_filtro,
                'convenio_filtro' => $convenio_filtro,
                'status_pagamento' => $status_pagamento,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function create() {
        $this->form();
    }

    public function edit() {
        $id = (int)($_GET['id'] ?? 0);
        $this->form($id);
    }

    public function ver() {
        $id = (int)($_GET['id'] ?? 0);
        $this->form($id, true);
    }

    public function ver_modal() {
        $id = (int)($_GET['id'] ?? 0);
        $this->form($id, true, 'modal');
    }

    private function form($id = 0, $viewOnly = false, $layout = 'main') {
        $conn = $this->db;
        $permission = $viewOnly ? 'pedidos.acesso' : ($id > 0 ? 'pedidos.alterar' : 'pedidos.novo');
        $this->exigirPermissao($permission);

        $pedido = null;
        $itens = [];
        
        // Defaults
        $defaults = [
            'id' => 0,
            'paciente_id' => '',
            'medico_id' => '',
            'parceiro_id' => '',
            'campanha_programa_id' => '',
            'posto_id' => '',
            'convenio_id' => '',
            'entrada' => date('Y-m-d H:i:s'),
            'total_bruto' => 0,
            'desconto_percentual' => 0,
            'desconto_valor' => 0,
            'total_liquido' => 0,
            'bloqueio_desconto' => 'N'
        ];

        if ($id > 0) {
            $stmt = $conn->prepare("SELECT p.*, pa.nome as paciente_nome, m.nome as medico_nome 
                                    FROM pedidos p 
                                    LEFT JOIN pacientes pa ON pa.id = p.paciente_id 
                                    LEFT JOIN medicos m ON m.id = p.medico_id 
                                    WHERE p.id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $pedido = $res->fetch_assoc();
            $stmt->close();

            if ($pedido) {
                $sqlItens = "SELECT pi.*, e.nome as exame_nome, e.mnemonico_local, e.prazo_execucao_local, mb.material_nome, l.laboratorio_nome 
                             FROM pedido_itens pi 
                             JOIN exames e ON e.id = pi.exame_id 
                             LEFT JOIN materiais_biologicos mb ON mb.id = e.material_biologico_id 
                             LEFT JOIN laboratorios l ON l.id = e.laboratorio_id
                             WHERE pi.pedido_id = ?";
                $stmt = $conn->prepare($sqlItens);
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $itens[] = $row;
                }
                $stmt->close();
            }
        } else {
            $pedido = $defaults;
            
            // Prefill from Orcamento
            if (isset($_GET['prefill_orcamento_id'])) {
                $orcId = (int)$_GET['prefill_orcamento_id'];
                if ($orcId > 0) {
                    $stmtO = $conn->prepare("SELECT * FROM orcamentos WHERE id = ?");
                    $stmtO->bind_param('i', $orcId);
                    $stmtO->execute();
                    $resO = $stmtO->get_result();
                    if ($orc = $resO->fetch_assoc()) {
                        $pedido['orcamento_origem_id'] = $orc['id']; // Pass ID to form
                        $pedido['paciente_id'] = $orc['paciente_id'];
                        $pedido['medico_id'] = $orc['medico_id'];
                        $pedido['parceiro_id'] = $orc['parceiro_id'];
                        $pedido['campanha_programa_id'] = $orc['campanha_programa_id'];
                        $pedido['posto_id'] = $orc['posto_id'];
                        $pedido['convenio_id'] = $orc['convenio_id'];
                        $pedido['total_bruto'] = $orc['total_bruto'];
                        $pedido['desconto_percentual'] = $orc['desconto_percentual'];
                        $pedido['desconto_valor'] = $orc['desconto_valor'];
                        $pedido['total_liquido'] = $orc['total_liquido'];
                        
                        // Fetch items
                        $stmtI = $conn->prepare("SELECT oi.*, e.nome as exame_nome, e.mnemonico_local 
                                               FROM orcamento_itens oi 
                                               JOIN exames e ON e.id = oi.exame_id 
                                               WHERE oi.orcamento_id = ?");
                        $stmtI->bind_param('i', $orcId);
                        $stmtI->execute();
                        $resI = $stmtI->get_result();
                        while ($rowI = $resI->fetch_assoc()) {
                            // Map orcamento item to pedido item format
                            $itens[] = [
                                'exame_id' => $rowI['exame_id'],
                                'valor' => $rowI['valor'],
                                'exame_nome' => $rowI['exame_nome'],
                                'mnemonico_local' => $rowI['mnemonico_local'],
                                'data_coleta' => date('Y-m-d'),
                                'hora_coleta' => date('H:i'),
                                'laboratorio_id' => $rowI['laboratorio_id'] ?? null
                            ];
                        }
                        $stmtI->close();
                    }
                    $stmtO->close();
                }
            }
        }

        // Fetch auxiliary data
        $convenios = [];
        $res = $conn->query("SELECT id, nome, tipo, faturavel FROM convenios WHERE ativo = 1 ORDER BY nome");
        while($r = $res->fetch_assoc()) $convenios[] = $r;

        $medicos = [];
        $res = $conn->query("SELECT id, nome, conselho, conselho_codigo, conselho_uf FROM medicos WHERE ativo = 1 ORDER BY nome");
        while($r = $res->fetch_assoc()) $medicos[] = $r;

        $postos = [];
        $res = $conn->query("SELECT id, nome_posto FROM postos WHERE ativo = 1 ORDER BY nome_posto");
        while($r = $res->fetch_assoc()) $postos[] = $r;

        $parceiros = [];
        $res = $conn->query("SELECT id, nome, desconto_permitido FROM parceiros WHERE ativo = 1 ORDER BY nome");
        while($r = $res->fetch_assoc()) $parceiros[] = $r;

        $campanhas = [];
        $res = $conn->query("SELECT id, nome_campanha_programa, desconto_permitido FROM campanhas_programas WHERE status = 'A' ORDER BY nome_campanha_programa");
        while($r = $res->fetch_assoc()) $campanhas[] = $r;

        $pacientes = [];
        $sqlPac = "SELECT p.id, p.nome, p.sexo, p.nascimento, p.cpf,
                          (SELECT campanha_id FROM pacientes_campanhas pc 
                           JOIN campanhas_programas cp ON pc.campanha_id = cp.id 
                           WHERE pc.paciente_id = p.id AND cp.status = 'A' 
                           ORDER BY pc.criado_em DESC LIMIT 1) as campanha_padrao_id
                   FROM pacientes p 
                   WHERE p.ativo = 1 
                   ORDER BY p.nome";
        $res = $conn->query($sqlPac);
        while($r = $res->fetch_assoc()) $pacientes[] = $r;

        $proximoId = 1;
        if ($id == 0) {
             $res = $conn->query("SELECT MAX(id) as max_id FROM pedidos");
             if ($row = $res->fetch_assoc()) {
                 $proximoId = (int)$row['max_id'] + 1;
             }
        }

        $paciente = null;
        if (!empty($pedido['paciente_id'])) {
            $res = $conn->query("SELECT id, nome, sexo, nascimento, cpf FROM pacientes WHERE id = {$pedido['paciente_id']}");
            $paciente = $res->fetch_assoc();
        }

        $this->view('pedidos/form', [
            'pedido' => $pedido,
            'itens' => $itens,
            'convenios' => $convenios,
            'medicos' => $medicos,
            'postos' => $postos,
            'parceiros' => $parceiros,
            'campanhas' => $campanhas,
            'pacientes' => $pacientes,
            'proximoId' => $proximoId,
            'paciente' => $paciente,
            'viewOnly' => $viewOnly,
            'layout' => $layout
        ]);
    }

    public function search_exames() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $q = trim($_GET['q'] ?? '');
        $convenio_id = (int)($_GET['convenio_id'] ?? 0);
        
        if (strlen($q) < 2) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT e.id, e.nome, e.mnemonico_local, e.prazo_execucao_local, mb.material_nome, l.laboratorio_nome 
                FROM exames e 
                LEFT JOIN materiais_biologicos mb ON mb.id = e.material_biologico_id 
                LEFT JOIN laboratorios l ON l.id = e.laboratorio_id
                WHERE e.ativo = 1 AND (e.nome LIKE ? OR e.mnemonico_local LIKE ?) 
                LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        $like = "%$q%";
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $results = [];
        while ($row = $res->fetch_assoc()) {
            // Get price for this covenant
            $price = 0;
            if ($convenio_id > 0) {
                $resP = $conn->query("SELECT valor FROM exame_convenio_precos WHERE exame_id = {$row['id']} AND convenio_id = $convenio_id AND ativo = 1");
                if ($rP = $resP->fetch_assoc()) {
                    $price = (float)$rP['valor'];
                }
            }
            $row['preco'] = $price;
            
            // Get Support Labs
            $row['laboratorios_apoio'] = [];
            $resAp = $conn->query("SELECT ela.laboratorio_id, l.laboratorio_nome, ela.prazo_execucao as prazo, ela.custo_apoio as preco, ela.mnemonico_apoio as codigo_no_laboratorio 
                                   FROM exame_laboratorio_apoio ela 
                                   JOIN laboratorios l ON l.id = ela.laboratorio_id 
                                   WHERE ela.exame_id = {$row['id']}");
            if ($resAp) {
                while($ra = $resAp->fetch_assoc()) {
                    $row['laboratorios_apoio'][] = $ra;
                }
            }

            $results[] = $row;
        }
        
        echo json_encode($results);
        exit;
    }
    
    public function search_pacientes() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 3) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT id, nome, nascimento, sexo, cpf FROM pacientes WHERE ativo = 1 AND (nome LIKE ? OR cpf LIKE ?) LIMIT 20";
        $stmt = $conn->prepare($sql);
        $like = "%$q%";
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $results = [];
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
        echo json_encode($results);
        exit;
    }

    public function store() {
        $conn = $this->db;
        
        $id_editar = (int)($_POST['id'] ?? 0);
        $paciente_id = (int)($_POST['paciente_id'] ?? 0);
        $convenio_id = (int)($_POST['convenio_id'] ?? 0);
        $medico_id = (int)($_POST['medico_id'] ?? 0);
        $parceiro_id = (int)($_POST['parceiro_id'] ?? 0);
        $campanha_programa_id = (int)($_POST['campanha_programa_id'] ?? 0);
        $posto_id = (int)($_POST['posto_id'] ?? 0);
        
        if ($id_editar > 0) $this->exigirPermissao('pedidos.alterar');
        else $this->exigirPermissao('pedidos.novo');

        // Validation
        $erro = '';
        if ($paciente_id <= 0) $erro = "Selecione um paciente.";
        elseif ($convenio_id <= 0) $erro = "Selecione um convênio.";
        elseif ($posto_id <= 0) $erro = "Selecione o posto de coleta.";
        elseif ($medico_id <= 0) $erro = "Selecione o médico.";
        
        // Get user discount limit
        $desconto_maximo_usuario = 0;
        if (isset($_SESSION['usuario_id'])) {
            $uid = (int)$_SESSION['usuario_id'];
            $r = $conn->query("SELECT desconto_maximo_permitido FROM usuarios WHERE id = $uid");
            if ($row = $r->fetch_assoc()) {
                $desconto_maximo_usuario = (float)$row['desconto_maximo_permitido'];
            }
        }

        // Process Items
        $itens_post = $_POST['itens'] ?? [];
        if (is_string($itens_post)) $itens_post = json_decode($itens_post, true);
        
        // Check covenant type
        $isCortesia = false;
        if ($convenio_id > 0) {
            $r = $conn->query("SELECT tipo FROM convenios WHERE id = $convenio_id");
            if ($row = $r->fetch_assoc()) $isCortesia = ($row['tipo'] === 'cortesia');
        }

        $total_bruto = 0;
        $itens_validos = [];
        
        foreach($itens_post as $it) {
            $valStr = is_numeric($it['valor']) ? $it['valor'] : str_replace(',', '.', str_replace('.', '', $it['valor']));
            $val = (float)$valStr;
            
            if ($isCortesia) $val = 0;
            
            $total_bruto += $val;
            
            $data_coleta = !empty($it['data_coleta']) ? $it['data_coleta'] : null;
            $hora_coleta = !empty($it['hora_coleta']) ? $it['hora_coleta'] : null;
            $lab_id = isset($it['laboratorio_id']) && $it['laboratorio_id'] > 0 ? (int)$it['laboratorio_id'] : null;
            
            $itens_validos[] = [
                'exame_id' => $it['exame_id'],
                'valor' => $val,
                'data_coleta' => $data_coleta,
                'hora_coleta' => $hora_coleta,
                'laboratorio_id' => $lab_id
            ];
        }
        
        if (empty($itens_validos)) $erro = "Adicione ao menos um exame.";

        if ($erro) {
             if (isset($_POST['ajax'])) {
                 echo json_encode(['sucesso' => false, 'mensagem' => $erro]);
                 exit;
             }
             // Simple fallback for non-ajax
             header("Location: index.php?r=pedidos/create&erro=" . urlencode($erro));
             exit;
        }
        
        $desconto_percentual = (float)$_POST['desconto_percentual'];
        $desconto_valor = (float)$_POST['desconto_valor'];
        
        // Calculate Discount
        if ($desconto_percentual > 0) {
            $desconto_valor = round($total_bruto * $desconto_percentual / 100, 2);
        } elseif ($desconto_valor > 0 && $total_bruto > 0) {
            $desconto_percentual = round($desconto_valor * 100 / $total_bruto, 2);
        }
        
        if ($desconto_valor > $total_bruto) $desconto_valor = $total_bruto;
        
        $total_liquido = $total_bruto - $desconto_valor;
        
        // Check Block
        $dp_eff = $desconto_percentual > 0 ? $desconto_percentual : ($total_bruto > 0 ? round($desconto_valor * 100 / $total_bruto, 2) : 0);
        $bloqueio_desconto = ($dp_eff > $desconto_maximo_usuario) ? 'S' : 'N';
        
        // Prepare Entrada
        $entrada_ts = time();
        if (!empty($_POST['entrada_data']) && !empty($_POST['entrada_hora'])) {
             $entrada_ts = strtotime($_POST['entrada_data'] . ' ' . $_POST['entrada_hora']);
        }
        $entrada_db = date('Y-m-d H:i:s', $entrada_ts);

        // Save
        if ($id_editar > 0) {
            // Update
            $sql = "UPDATE pedidos SET paciente_id=?, medico_id=?, parceiro_id=?, campanha_programa_id=?, posto_id=?, convenio_id=?, total_bruto=?, desconto_percentual=?, desconto_valor=?, total_liquido=?, bloqueio_desconto=?, entrada=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $medico_id_null = $medico_id ?: null;
            $parceiro_id_null = $parceiro_id ?: null;
            $campanha_programa_id_null = $campanha_programa_id ?: null;
            $stmt->bind_param('iiiiiiddddssi', $paciente_id, $medico_id_null, $parceiro_id_null, $campanha_programa_id_null, $posto_id, $convenio_id, $total_bruto, $desconto_percentual, $desconto_valor, $total_liquido, $bloqueio_desconto, $entrada_db, $id_editar);
            $stmt->execute();
            $stmt->close();
            
            // Re-insert items
            $conn->query("DELETE FROM pedido_itens WHERE pedido_id = $id_editar");
            $stmtI = $conn->prepare("INSERT INTO pedido_itens (pedido_id, exame_id, valor, data_coleta, hora_coleta, status_exame, laboratorio_id) VALUES (?, ?, ?, ?, ?, 'C', ?)");
            foreach($itens_validos as $it) {
                $stmtI->bind_param('iidssi', $id_editar, $it['exame_id'], $it['valor'], $it['data_coleta'], $it['hora_coleta'], $it['laboratorio_id']);
                $stmtI->execute();
            }
            $stmtI->close();
            
            $pedido_id = $id_editar;
        } else {
            // Insert
            $sql = "INSERT INTO pedidos (paciente_id, medico_id, parceiro_id, campanha_programa_id, posto_id, convenio_id, entrada, total_bruto, desconto_percentual, desconto_valor, total_liquido, bloqueio_desconto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $medico_id_null = $medico_id ?: null;
            $parceiro_id_null = $parceiro_id ?: null;
            $campanha_programa_id_null = $campanha_programa_id ?: null;
            $stmt->bind_param('iiiiiisdddds', $paciente_id, $medico_id_null, $parceiro_id_null, $campanha_programa_id_null, $posto_id, $convenio_id, $entrada_db, $total_bruto, $desconto_percentual, $desconto_valor, $total_liquido, $bloqueio_desconto);
            $stmt->execute();
            $pedido_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert items
            $stmtI = $conn->prepare("INSERT INTO pedido_itens (pedido_id, exame_id, valor, data_coleta, hora_coleta, status_exame, laboratorio_id) VALUES (?, ?, ?, ?, ?, 'C', ?)");
            foreach($itens_validos as $it) {
                $stmtI->bind_param('iidssi', $pedido_id, $it['exame_id'], $it['valor'], $it['data_coleta'], $it['hora_coleta'], $it['laboratorio_id']);
                $stmtI->execute();
            }
            $stmtI->close();
            
            // Generate Code
             $codigoAuto = '01' . str_pad((string)$pedido_id, 7, '0', STR_PAD_LEFT);
             $conn->query("UPDATE pedidos SET codigo_pedido = '$codigoAuto' WHERE id = $pedido_id");
             
             // Update Orcamento with Pedido ID if provided
             if (!empty($_POST['orcamento_origem_id'])) {
                 $orcId = (int)$_POST['orcamento_origem_id'];
                 if ($orcId > 0) {
                     $conn->query("UPDATE orcamentos SET pedido_id = $pedido_id, status = 'G' WHERE id = $orcId");
                     
                     // Log CRM
                     $uid = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
                     $msg = "Orçamento convertido em Pedido #$pedido_id ($codigoAuto)";
                     $conn->query("INSERT INTO orcamentos_crm (orcamento_id, usuario_id, mensagem, tipo, criado_em) VALUES ($orcId, $uid, '$msg', 'S', NOW())");
                 }
             }
        }
        
        if (isset($_POST['ajax'])) {
             echo json_encode(['sucesso' => true, 'id' => $pedido_id]);
             exit;
        }
        
        header("Location: index.php?r=pedidos");
        exit;
    }

    public function delete() {
        $conn = $this->db;
        $this->exigirPermissao('pedidos.deletar');
        $id = (int)$_GET['id'];
        if ($id > 0) {
            $conn->query("DELETE FROM pedido_itens WHERE pedido_id = $id");
            $conn->query("DELETE FROM pedidos WHERE id = $id");
        }
        header("Location: index.php?r=pedidos");
        exit;
    }

    public function receber() {
        // Prevent any previous output or warnings
        ob_clean(); // Clean current buffer if any
        error_reporting(0);
        ini_set('display_errors', 0);
        
        $conn = $this->db;
        
        // Helper to output JSON and exit
        $jsonResponse = function($data) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data);
            exit;
        };

        try {
            $this->requireAuth();
    
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $jsonResponse(['ok' => false, 'msg' => 'Método inválido']);
            }
    
            if (!$this->temPermissao('pedidos.alterar')) {
                $jsonResponse(['ok' => false, 'msg' => 'Sem permissão.']);
            }
    
            $pid = (int)($_POST['pedido_id'] ?? 0);
            
            // Handle currency parsing safely
            $valStr = $_POST['valor'] ?? '0';
            // Remove thousands separator (.) and replace decimal separator (,) with (.)
            $valStr = str_replace('.', '', $valStr);
            $valStr = str_replace(',', '.', $valStr);
            $valor = (float)$valStr;
            
            $tipo_id = (int)($_POST['tipo_id'] ?? 0);
    
            if ($pid <= 0) {
                $jsonResponse(['ok' => false, 'msg' => 'Pedido inválido (ID 0)']);
            }
            if ($valor < 0) {
                $jsonResponse(['ok' => false, 'msg' => 'Valor inválido (negativo)']);
            }
            if ($tipo_id <= 0) {
                $jsonResponse(['ok' => false, 'msg' => 'Selecione a Forma de Pagamento']);
            }
    
            // Fetch current state
            $stmt = $conn->prepare("SELECT total_bruto, total_liquido, valor_recebido FROM pedidos WHERE id = ?");
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || !($row = $res->fetch_assoc())) {
                $stmt->close();
                $jsonResponse(['ok' => false, 'msg' => 'Pedido não encontrado']);
            }
            $stmt->close();
    
            $tb = (float)$row['total_bruto'];
            $tl = (float)$row['total_liquido'];
            $vrAnt = (float)$row['valor_recebido'];
            
            // Handle Discount Update
            $dpStr = $_POST['desconto_percentual'] ?? null;
            $dvStr = $_POST['desconto_valor'] ?? null;
            
            $dp = null;
            $dv = null;

            if ($dpStr !== null) {
                $dpStr = str_replace('.', '', $dpStr);
                $dpStr = str_replace(',', '.', $dpStr);
                $dp = (float)$dpStr;
            }
            if ($dvStr !== null) {
                $dvStr = str_replace('.', '', $dvStr);
                $dvStr = str_replace(',', '.', $dvStr);
                $dv = (float)$dvStr;
            }
            
            $bloqueio_desconto = 'N';
    
            if ($dp !== null || $dv !== null) {
                $dp = $dp ?? 0;
                $dv = $dv ?? 0;
    
                // Recalculate based on priority (Value > Percent usually, or just consistency)
                if ($dp > 0) {
                    $dv = round($tb * $dp / 100, 2);
                } elseif ($dv > 0 && $tb > 0) {
                    $dp = round($dv * 100 / $tb, 2);
                }
                
                if ($dv > $tb) $dv = $tb;
                $tl = $tb - $dv; // New Total Liquid
    
                // Check Discount Limit
                $desconto_maximo_usuario = 0;
                if (isset($_SESSION['usuario_id'])) {
                    $uid = (int)$_SESSION['usuario_id'];
                    $r = $conn->query("SELECT desconto_maximo_permitido FROM usuarios WHERE id = $uid");
                    if ($rowU = $r->fetch_assoc()) {
                        $desconto_maximo_usuario = (float)$rowU['desconto_maximo_permitido'];
                    }
                }
    
                $dp_eff = $dp > 0 ? $dp : ($tb > 0 ? round($dv * 100 / $tb, 2) : 0);
                if ($dp_eff > $desconto_maximo_usuario) {
                    $bloqueio_desconto = 'S';
                }
            } else {
                // Keep existing discount values if not provided
                 $dp = 0;
            }
    
            $novoValor = $vrAnt + $valor;
            $status = ($novoValor + 0.001 >= $tl) ? 'R' : 'A';
            $uid = $_SESSION['usuario_id'];
    
            // Prepare UPDATE
            if ($dp !== null || $dv !== null) {
                $stmtU = $conn->prepare("UPDATE pedidos SET valor_recebido = ?, recebido_status = ?, recebido_em = IFNULL(recebido_em, NOW()), recebido_por_usuario_id = ?, tipo_recebimento = ?, desconto_percentual = ?, desconto_valor = ?, total_liquido = ?, bloqueio_desconto = ? WHERE id = ?");
                $stmtU->bind_param('dsiidddsi', $novoValor, $status, $uid, $tipo_id, $dp, $dv, $tl, $bloqueio_desconto, $pid);
            } else {
                $stmtU = $conn->prepare("UPDATE pedidos SET valor_recebido = ?, recebido_status = ?, recebido_em = IFNULL(recebido_em, NOW()), recebido_por_usuario_id = ?, tipo_recebimento = ? WHERE id = ?");
                $stmtU->bind_param('dsiii', $novoValor, $status, $uid, $tipo_id, $pid);
            }
            
            $ok = $stmtU->execute();
            $stmtU->close();
    
            if ($ok) {
                // Update/Create Contas Receber
                // REMOVIDO: O contas a receber não deve ser gerado aqui, apenas no fechamento de caixa.
                /*
                $crId = 0;
                $resCr = $conn->query("SELECT id FROM contas_receber WHERE origem = 'P' AND referencia_id = $pid LIMIT 1");
                if ($rCr = $resCr->fetch_assoc()) $crId = $rCr['id'];
    
                if ($crId == 0) {
                    $em = date('Y-m-d');
                    $conn->query("INSERT INTO contas_receber (origem, referencia_id, emissao, vencimento, valor, status) VALUES ('P', $pid, '$em', '$em', $tl, 'N')");
                    $crId = $conn->insert_id;
                }
    
                if ($status === 'R' && $crId > 0) {
                    $conn->query("UPDATE contas_receber SET status = 'R' WHERE id = $crId");
                }
                */
            }
    
            $jsonResponse(['ok' => $ok, 'completo' => ($status === 'R')]);

        } catch (Exception $e) {
            $jsonResponse(['ok' => false, 'msg' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    public function desbloquear() {
        $conn = $this->db;
        $this->requireAuth();
        header('Content-Type: application/json');

        $pid = (int)($_POST['pedido_id'] ?? 0);
        $user = trim($_POST['usuario_login'] ?? '');
        $pass = trim($_POST['senha_login'] ?? '');

        if ($pid <= 0 || !$user || !$pass) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
            exit;
        }

        // Validate user
        $stmt = $conn->prepare("SELECT id, senha, desconto_maximo_permitido FROM usuarios WHERE usuario = ? AND status = 1");
        $stmt->bind_param('s', $user);
        $stmt->execute();
        $res = $stmt->get_result();
        $uRow = $res->fetch_assoc();
        $stmt->close();

        if (!$uRow || !password_verify($pass, $uRow['senha'])) {
            echo json_encode(['ok' => false, 'erro' => 'Credenciais inválidas']);
            exit;
        }

        // Check limits
        $stmt = $conn->prepare("SELECT total_bruto, desconto_percentual, desconto_valor FROM pedidos WHERE id = ?");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        $pRow = $res->fetch_assoc();
        $stmt->close();

        if (!$pRow) {
            echo json_encode(['ok' => false, 'erro' => 'Pedido não encontrado']);
            exit;
        }

        $tb = (float)$pRow['total_bruto'];
        $dp = (float)$pRow['desconto_percentual'];
        $dv = (float)$pRow['desconto_valor'];
        
        $dp_eff = $dp > 0 ? $dp : ($tb > 0 ? round($dv * 100 / $tb, 2) : 0);
        $limit = (float)$uRow['desconto_maximo_permitido'];

        if ($limit < $dp_eff) {
            echo json_encode(['ok' => false, 'erro' => "Limite excedido ($limit% vs $dp_eff%)"]);
            exit;
        }

        // Unlock
        $conn->query("UPDATE pedidos SET bloqueio_desconto = 'N' WHERE id = $pid");
        
        // Log
        $uidLog = $_SESSION['usuario_id'];
        $conn->query("CREATE TABLE IF NOT EXISTS logs_desbloqueio (id INT AUTO_INCREMENT PRIMARY KEY, tipo CHAR(1), referencia_id INT, usuario_id INT, criado_em DATETIME)");
        $conn->query("INSERT INTO logs_desbloqueio (tipo, referencia_id, usuario_id, criado_em) VALUES ('P', $pid, $uidLog, NOW())");

        echo json_encode(['ok' => true, 'msg' => 'Desbloqueado com sucesso']);
        exit;
    }

    public function etiquetas() {
        $this->exigirPermissao('pedidos.acesso');
        $conn = $this->db;
        require_once __DIR__ . '/../Libraries/PDF_Etiqueta.php';
        
        $pedido_id = 0;
        if (isset($_GET['id'])) {
            $pedido_id = (int) $_GET['id'];
        } elseif (isset($_GET['pedido_id'])) {
            $pedido_id = (int) $_GET['pedido_id'];
        }
        
        if ($pedido_id <= 0) {
            die('Pedido não informado.');
        }
        
        // Fetch Pedido
        $sqlPedido = "
            SELECT 
                p.id,
                p.codigo_pedido,
                p.paciente_id,
                p.entrada,
                pac.nome       AS paciente_nome,
                pac.nascimento AS paciente_nascimento
            FROM pedidos p
            INNER JOIN pacientes pac ON pac.id = p.paciente_id
            WHERE p.id = ?
        ";
        $stmt = $conn->prepare($sqlPedido);
        $stmt->bind_param('i', $pedido_id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$pedido) die('Pedido não encontrado.');
        
        // Fetch Itens
        $sqlItens = "
            SELECT 
                pi.exame_id,
                e.mnemonico_local,
                e.recipiente_id AS recipiente_id
            FROM pedido_itens pi
            INNER JOIN exames e ON e.id = pi.exame_id
            WHERE pi.pedido_id = ?
            ORDER BY e.recipiente_id, e.mnemonico_local
        ";
        $stmt = $conn->prepare($sqlItens);
        $stmt->bind_param('i', $pedido_id);
        $stmt->execute();
        $resultItens = $stmt->get_result();
        
        if ($resultItens->num_rows === 0) die('Nenhum exame encontrado.');
        
        $grupos = [];
        while ($row = $resultItens->fetch_assoc()) {
            $recipienteId = (int) ($row['recipiente_id'] ?? 0);
            if (!isset($grupos[$recipienteId])) {
                $grupos[$recipienteId] = ['mnemonicos' => []];
            }
            if (!in_array($row['mnemonico_local'], $grupos[$recipienteId]['mnemonicos'], true)) {
                $grupos[$recipienteId]['mnemonicos'][] = $row['mnemonico_local'];
            }
        }
        $stmt->close();
        
        // Generate PDF
        $pdf = new \App\Libraries\PDF_Etiqueta('L', 'mm', [50, 25]);
        $pdf->SetAutoPageBreak(false);
        
        $pacienteNome = $pedido['paciente_nome'] ?? '';
        $pacienteNascimento = $this->formataDataSimples($pedido['paciente_nascimento'] ?? '');
        $entrada = $this->formataDataHora($pedido['entrada'] ?? '');
        $codigoPedidoRaw = trim((string)($pedido['codigo_pedido'] ?? ''));
        $codigoPedido = ($codigoPedidoRaw !== '' ? $codigoPedidoRaw : ('01' . str_pad((string)$pedido['id'], 7, '0', STR_PAD_LEFT)));
        
        $nomeMaxLen = 32;
        if (mb_strlen($pacienteNome, 'UTF-8') > $nomeMaxLen) {
            $pacienteNome = mb_substr($pacienteNome, 0, $nomeMaxLen, 'UTF-8') . '...';
        }
        
        foreach ($grupos as $recipienteId => $grupo) {
            $listaMnemonicos = $grupo['mnemonicos'];
            $maxExibir = 4;
            $total = count($listaMnemonicos);
            
            if ($total > $maxExibir) {
                $exibir = array_slice($listaMnemonicos, 0, $maxExibir);
                $resto = $total - $maxExibir;
                $examesStr = implode(';', $exibir) . ' + ' . $resto;
            } else {
                $examesStr = implode(';', $listaMnemonicos);
            }
            
            $pdf->AddPage();
            
            $barcodeW = 32;
            $barcodeH = 7.0;
            $barcodeX = (50 - $barcodeW) / 2;
            $barcodeY = 2;
            
            $pdf->Code128($barcodeX, $barcodeY, $codigoPedido, $barcodeW, $barcodeH);
            
            $y = $barcodeY + $barcodeH + 1.0;
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY($barcodeX, $y);
            $pdf->Cell($barcodeW, 4, $codigoPedido, 0, 1, 'C');
            
            $y += 3.5;
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetXY(3, $y);
            $pdf->Cell(0, 3.5, mb_strtoupper($pacienteNome, 'UTF-8'), 0, 1, 'L');
            
            $y += 3.2;
            $pdf->SetFont('Arial', '', 5);
            $pdf->SetXY(3, $y);
            $pdf->Cell(0, 3, 'Dt de Nascimento: ' . $pacienteNascimento, 0, 1, 'L');
            
            if ($examesStr !== '') {
                $y += 3.0;
                $pdf->SetFont('Arial', 'B', 5);
                $pdf->SetXY(3, $y);
                $pdf->MultiCell(0, 2.8, $examesStr, 0, 'L');
            }
            
            $pdf->SetFont('Arial', '', 6.5);
            $xVertical = 46;
            $yVertical = 3.7;
            $pdf->RotatedText($xVertical, $yVertical, $entrada, 270);
        }
        
        $pdf->Output('I', 'etiquetas_pedido_' . $pedido_id . '.pdf');
        exit;
    }
    
    private function formataDataSimples(?string $data): string {
        if (!$data) return '';
        try {
            $dt = new \DateTime($data);
            return $dt->format('d/m/Y');
        } catch (Exception $e) {
            return $data;
        }
    }
    
    private function formataDataHora(?string $data): string {
        if (!$data) return '';
        try {
            $dt = new \DateTime($data);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return $data;
        }
    }
}
