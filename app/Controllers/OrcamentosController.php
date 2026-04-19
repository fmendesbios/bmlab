<?php

namespace App\Controllers;

use App\Core\Controller;

class OrcamentosController extends Controller
{
    public function index()
    {
        $conn = $this->db;

        if (!temPermissao('orcamentos.acesso')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $mensagem = '';
        $erro = '';
        
        // Handle Action Messages
        if (isset($_SESSION['flash_success'])) {
            $mensagem = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
        } elseif (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
            $mensagem = 'Operação realizada com sucesso.';
        }

        if (isset($_SESSION['flash_error'])) {
            $erro = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        }

        // --- Logic for Desconto Maximo ---
        $desconto_maximo_usuario = 0;
        if (isset($_SESSION['usuario_id'])) {
            $uid_logado = (int)$_SESSION['usuario_id'];
            if ($uid_logado > 0) {
                $stDesc = $conn->prepare("SELECT desconto_maximo_permitido FROM usuarios WHERE id = ? LIMIT 1");
                if ($stDesc) {
                    $stDesc->bind_param('i', $uid_logado);
                    $stDesc->execute();
                    $rsDesc = $stDesc->get_result();
                    if ($rsDesc && $rsDesc->num_rows === 1) {
                        $rowD = $rsDesc->fetch_assoc();
                        $val = isset($rowD['desconto_maximo_permitido']) ? (float)$rowD['desconto_maximo_permitido'] : 0;
                        $val = (int)round($val);
                        if ($val < 0) $val = 0;
                        if ($val > 100) $val = 100;
                        $desconto_maximo_usuario = $val;
                    }
                    $stDesc->close();
                }
            }
        }

        // --- Load Dependencies for Form (Modals) ---
        $convenios = [];
        $res_conv = $conn->query("SELECT id, nome FROM convenios WHERE ativo = 1 ORDER BY nome ASC");
        if ($res_conv) {
            while ($row = $res_conv->fetch_assoc()) $convenios[] = $row;
            $res_conv->close();
        }

        $postos = [];
        $res_postos = $conn->query("SELECT id, nome_posto FROM postos WHERE ativo = 1 ORDER BY nome_posto ASC");
        if ($res_postos) {
            while ($row = $res_postos->fetch_assoc()) $postos[] = $row;
            $res_postos->close();
        }

        $medicos = [];
        $res_med = $conn->query("SELECT id, nome, conselho, conselho_codigo, conselho_uf FROM medicos WHERE ativo = 1 ORDER BY nome ASC");
        if ($res_med) {
            while ($row = $res_med->fetch_assoc()) $medicos[] = $row;
            $res_med->close();
        }

        $parceiros = [];
        $res_parc = $conn->query("SELECT id, nome, desconto_permitido FROM parceiros WHERE ativo = 1 ORDER BY nome ASC");
        if ($res_parc) {
            while ($row = $res_parc->fetch_assoc()) $parceiros[] = $row;
            $res_parc->close();
        }

        $campanhas = [];
        $res_camp = $conn->query("SELECT id, nome_campanha_programa, desconto_permitido FROM campanhas_programas WHERE status = 'A' ORDER BY nome_campanha_programa ASC");
        if ($res_camp) {
            while ($row = $res_camp->fetch_assoc()) $campanhas[] = $row;
            $res_camp->close();
        }

        $pacientes = [];
        $sqlPac = "SELECT p.id, p.nome, p.nascimento, p.sexo, p.celular,
                          (SELECT campanha_id FROM pacientes_campanhas pc 
                           JOIN campanhas_programas cp ON pc.campanha_id = cp.id 
                           WHERE pc.paciente_id = p.id AND cp.status = 'A' 
                           ORDER BY pc.criado_em DESC LIMIT 1) as campanha_padrao_id
                   FROM pacientes p 
                   WHERE p.ativo = 1 
                   ORDER BY p.nome ASC";
        $res_pac = $conn->query($sqlPac);
        if ($res_pac) {
            while ($row = $res_pac->fetch_assoc()) $pacientes[] = $row;
            $res_pac->close();
        }

        // Exames and Prices
        $exames = [];
        $sql_ex = "SELECT e.id, e.nome, e.mnemonico_local AS mnemonico, e.prazo_execucao_local, mb.material_nome, l.laboratorio_nome, e.laboratorio_id
                   FROM exames e
                   LEFT JOIN materiais_biologicos mb ON mb.id = e.material_biologico_id
                   LEFT JOIN laboratorios l ON l.id = e.laboratorio_id
                   WHERE e.ativo = 1
                   ORDER BY e.nome ASC";
        $res_ex = $conn->query($sql_ex);
        if ($res_ex) {
            while ($row = $res_ex->fetch_assoc()) $exames[] = $row;
            $res_ex->close();
        }
        
        $examesMap = [];
        foreach ($exames as $ex) {
            $examesMap[(int)$ex['id']] = $ex;
        }

        // Support Labs
        $apoios = [];
        $resAp = $conn->query("SELECT ela.exame_id, ela.laboratorio_id, l.laboratorio_nome, ela.prazo_execucao as prazo, ela.custo_apoio as preco, ela.mnemonico_apoio as codigo_no_laboratorio 
                               FROM exame_laboratorio_apoio ela 
                               JOIN laboratorios l ON l.id = ela.laboratorio_id");
        if ($resAp) {
            while($r = $resAp->fetch_assoc()) {
                $apoios[(int)$r['exame_id']][] = $r;
            }
            $resAp->close();
        }

        $mapa_precos = [];
        $res_precos = $conn->query("SELECT ep.exame_id, ep.convenio_id, ep.valor FROM exame_convenio_precos ep JOIN exames e ON e.id = ep.exame_id WHERE ep.ativo = 1 AND e.ativo = 1");
        if ($res_precos) {
            while ($row = $res_precos->fetch_assoc()) {
                $eid = (int)$row['exame_id'];
                $cid = (int)$row['convenio_id'];
                $val = (float)$row['valor'];
                if (!isset($mapa_precos[$eid])) $mapa_precos[$eid] = [];
                $mapa_precos[$eid][$cid] = $val;
            }
            $res_precos->close();
        }

        // --- List Filtering ---
        $busca = trim($_GET['busca'] ?? '');
        $data_ini = trim($_GET['data_ini'] ?? '');
        $data_fim = trim($_GET['data_fim'] ?? '');
        $posto_filtro = isset($_GET['posto_filtro']) ? (int)$_GET['posto_filtro'] : 0;
        
        $tem_ini = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ini);
        $tem_fim = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim);
        
        $por_pagina = 10;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($page - 1) * $por_pagina;
        
        $sql_lista = "SELECT o.*, pa.nome AS paciente_nome, c.nome AS convenio_nome, u.nome AS usuario_nome, pe.codigo_pedido
                      FROM orcamentos o 
                      LEFT JOIN pacientes pa ON pa.id = o.paciente_id 
                      LEFT JOIN convenios c ON c.id = o.convenio_id 
                      LEFT JOIN usuarios u ON u.id = o.criado_por_usuario_id 
                      LEFT JOIN pedidos pe ON pe.id = o.pedido_id
                      WHERE 1";
        
        $condicoes = '';
        if ($busca !== '') {
            $buscaEsc = '%' . $conn->real_escape_string($busca) . '%';
            $sql_lista .= " AND ((pa.nome LIKE '{$buscaEsc}') OR (o.paciente_avulso LIKE '{$buscaEsc}') OR (o.codigo_orcamento LIKE '{$buscaEsc}') OR (pe.codigo_pedido LIKE '{$buscaEsc}'))";
            $condicoes .= " AND ((pa.nome LIKE '{$buscaEsc}') OR (o.paciente_avulso LIKE '{$buscaEsc}') OR (o.codigo_orcamento LIKE '{$buscaEsc}') OR (pe.codigo_pedido LIKE '{$buscaEsc}'))";
        }
        if ($tem_ini && $tem_fim) {
            $sql_lista .= " AND o.criado_em BETWEEN '{$data_ini} 00:00:00' AND '{$data_fim} 23:59:59'";
            $condicoes .= " AND o.criado_em BETWEEN '{$data_ini} 00:00:00' AND '{$data_fim} 23:59:59'";
        } elseif ($tem_ini) {
            $sql_lista .= " AND o.criado_em >= '{$data_ini} 00:00:00'";
            $condicoes .= " AND o.criado_em >= '{$data_ini} 00:00:00'";
        } elseif ($tem_fim) {
            $sql_lista .= " AND o.criado_em <= '{$data_fim} 23:59:59'";
            $condicoes .= " AND o.criado_em <= '{$data_fim} 23:59:59'";
        }
        if ($posto_filtro > 0) {
            $sql_lista .= " AND o.posto_id = {$posto_filtro}";
            $condicoes .= " AND o.posto_id = {$posto_filtro}";
        }
        
        $sql_lista .= " ORDER BY o.id DESC LIMIT {$por_pagina} OFFSET {$offset}";
        
        $sql_count = "SELECT COUNT(*) AS total 
                      FROM orcamentos o 
                      LEFT JOIN pacientes pa ON pa.id = o.paciente_id 
                      LEFT JOIN convenios c ON c.id = o.convenio_id 
                      LEFT JOIN usuarios u ON u.id = o.criado_por_usuario_id 
                      LEFT JOIN pedidos pe ON pe.id = o.pedido_id
                      WHERE 1" . $condicoes;
        
        $total_registros = 0;
        $res_count = $conn->query($sql_count);
        if ($res_count && $row_count = $res_count->fetch_assoc()) {
            $total_registros = (int)$row_count['total'];
        }
        
        $orcamentos = [];
        $result_lista = $conn->query($sql_lista);
        if ($result_lista) {
            while ($row = $result_lista->fetch_assoc()) {
                $orcamentos[] = $row;
            }
        }

        $total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

        // --- Prepare Edit/View Data if needed ---
        $orcamento_atual = [
            'id' => '',
            'medico_id' => '',
            'parceiro_id' => '',
            'campanha_programa_id' => '',
            'posto_id' => '',
            'convenio_id' => '',
            'validade' => '',
            'contato_celular' => '',
            'total_bruto' => 0,
            'desconto_percentual' => 0,
            'desconto_valor' => 0,
            'total_liquido' => 0,
            'paciente_id' => '',
            'paciente_avulso' => '',
            'bloqueio_desconto' => 'N',
            'codigo_orcamento' => ''
        ];
        $itens_existentes = [];
        $editando = false;
        $visualizando = false;
        $proximoId = 1;

        if (isset($_GET['acao']) && ($_GET['acao'] === 'editar' || $_GET['acao'] === 'ver') && isset($_GET['id'])) {
            $id_editar = (int)$_GET['id'];
            if ($id_editar > 0) {
                $st = $conn->prepare("SELECT * FROM orcamentos WHERE id = ? LIMIT 1");
                if ($st) {
                    $st->bind_param('i', $id_editar);
                    $st->execute();
                    $rs = $st->get_result();
                    if ($rs && $rs->num_rows > 0) {
                        $orcamento_atual = $rs->fetch_assoc();
                        $editando = ($_GET['acao'] === 'editar');
                        $visualizando = ($_GET['acao'] === 'ver');
                    }
                    $st->close();
                }
                
                $stI = $conn->prepare("SELECT * FROM orcamento_itens WHERE orcamento_id = ?");
                if ($stI) {
                    $stI->bind_param('i', $id_editar);
                    $stI->execute();
                    $rsI = $stI->get_result();
                    while ($row = $rsI->fetch_assoc()) {
                        $itens_existentes[(int)$row['exame_id']] = $row;
                    }
                    $stI->close();
                }
            }
        } else {
             // Calculate Next ID for New
            $sqlNext = $conn->query("SELECT MAX(id) as max_id FROM orcamentos");
            if ($sqlNext && $rowNext = $sqlNext->fetch_assoc()) {
                $proximoId = (int)$rowNext['max_id'] + 1;
            }
            if (!$proximoId) $proximoId = 1;
        }

        $this->view('orcamentos/index', [
            'orcamentos' => $orcamentos,
            'orcamento' => $orcamento_atual, // For modal
            'itens_existentes' => $itens_existentes,
            'convenios' => $convenios,
            'postos' => $postos,
            'medicos' => $medicos,
            'parceiros' => $parceiros,
            'campanhas' => $campanhas,
            'pacientes' => $pacientes,
            'exames' => $exames,
            'examesMap' => $examesMap,
            'apoios' => $apoios,
            'mapa_precos' => $mapa_precos,
            'busca' => $busca,
            'data_ini' => $data_ini,
            'data_fim' => $data_fim,
            'posto_filtro' => $posto_filtro,
            'page' => $page,
            'total_paginas' => $total_paginas,
            'totalRecords' => $total_registros,
            'limit' => $por_pagina,
            'mensagem' => $mensagem,
            'erro' => $erro,
            'editando' => $editando,
            'visualizando' => $visualizando,
            'proximoId' => $proximoId,
            'desconto_maximo_usuario' => $desconto_maximo_usuario
        ]);
    }

    public function store()
    {
        $conn = $this->db;
        
        if (!temPermissao('orcamentos.novo') && !temPermissao('orcamentos.alterar')) {
            $_SESSION['flash_error'] = 'Permissão negada.';
            header('Location: index.php?r=orcamentos');
            exit;
        }

        $erro = '';
        $id_editar = (int)($_POST['id'] ?? 0);
        $modo_paciente = $_POST['modo_paciente'] ?? '';
        $paciente_id = (int)($_POST['paciente_id'] ?? 0);
        $paciente_avulso = trim($_POST['paciente_avulso'] ?? '');
        $medico_id = (int)($_POST['medico_id'] ?? 0);
        $parceiro_id = (int)($_POST['parceiro_id'] ?? 0);
        $campanha_programa_id = (int)($_POST['campanha_programa_id'] ?? 0);
        $posto_id = (int)($_POST['posto_id'] ?? 0);
        $convenio_id = (int)($_POST['convenio_id'] ?? 0);
        $validade = trim($_POST['validade'] ?? '');
        $contato_pais = trim($_POST['contato_celular_pais'] ?? '+55');
        $contato_celular = trim($_POST['contato_celular'] ?? '');
        $desconto_percentual = (float)str_replace(',', '.', $_POST['desconto_percentual'] ?? '0');
        $desconto_valor_in = $_POST['desconto_valor'] ?? '0';
        $itens_post = $_POST['itens'] ?? [];

        function br_para_float_orc_ctrl($valor) {
            if ($valor === null) return 0;
            $valor = trim($valor);
            if ($valor === '') return 0;
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return (float)$valor;
        }

        $total_bruto = 0.0;
        $itens_validos = [];
        foreach ($itens_post as $key => $item) {
            $exame_id = isset($item['exame_id']) ? (int)$item['exame_id'] : (int)$key;
            if ($exame_id <= 0) continue;
            $valor_br = $item['valor'] ?? '0';
            $valor = br_para_float_orc_ctrl($valor_br);
            if ($valor <= 0) continue;
            $total_bruto += $valor;
            $lab_id = isset($item['laboratorio_id']) && $item['laboratorio_id'] > 0 ? (int)$item['laboratorio_id'] : null;
            $itens_validos[] = ['exame_id' => $exame_id, 'valor' => $valor, 'laboratorio_id' => $lab_id];
        }

        if ($convenio_id <= 0) $erro = 'Selecione um convênio.';
        elseif ($posto_id <= 0) $erro = 'Selecione um posto de coleta.';
        elseif ($medico_id <= 0) $erro = 'Selecione um médico.';
        elseif ($parceiro_id <= 0) $erro = 'Selecione um parceiro.';
        elseif ($validade === '') $erro = 'Informe a validade.';
        elseif ($contato_celular === '') $erro = 'Informe o celular de contato.';
        elseif (($modo_paciente === 'cadastrado' && $paciente_id <= 0) || ($modo_paciente === 'avulso' && $paciente_avulso === '')) $erro = 'Informe o paciente.';
        elseif ($total_bruto <= 0 || empty($itens_validos)) $erro = 'Adicione ao menos um exame com valor.';

        if ($erro) {
            $_SESSION['flash_error'] = $erro;
            header('Location: index.php?r=orcamentos'); // Should ideally preserve input, but simplifying
            exit;
        }

        $desconto_valor = br_para_float_orc_ctrl($desconto_valor_in);
        if ($desconto_percentual > 0) {
            $desconto_valor = round($total_bruto * $desconto_percentual / 100, 2);
        } elseif ($desconto_valor > 0 && $total_bruto > 0) {
            $desconto_percentual = round($desconto_valor * 100 / $total_bruto, 2);
        }
        if ($desconto_valor > $total_bruto) {
            $desconto_valor = $total_bruto;
        }
        $total_liquido = $total_bruto - $desconto_valor;
        
        // Get user discount limit
        $desconto_maximo_usuario = 0;
        if (isset($_SESSION['usuario_id'])) {
            $uid_logado = (int)$_SESSION['usuario_id'];
            $stDesc = $conn->prepare("SELECT desconto_maximo_permitido FROM usuarios WHERE id = ? LIMIT 1");
            if ($stDesc) {
                $stDesc->bind_param('i', $uid_logado);
                $stDesc->execute();
                $rsDesc = $stDesc->get_result();
                if ($rsDesc && $rsDesc->num_rows === 1) {
                    $rowD = $rsDesc->fetch_assoc();
                    $val = isset($rowD['desconto_maximo_permitido']) ? (float)$rowD['desconto_maximo_permitido'] : 0;
                    $desconto_maximo_usuario = $val;
                }
                $stDesc->close();
            }
        }

        $dp_eff = $desconto_percentual > 0 ? $desconto_percentual : ($total_bruto > 0 ? round($desconto_valor * 100 / $total_bruto, 2) : 0);
        $bloqueio_desconto = ($dp_eff > $desconto_maximo_usuario) ? 'S' : 'N';

        $celDigits = preg_replace('/\D+/', '', $contato_celular);
        $paisDigits = preg_replace('/\D+/', '', $contato_pais);
        $contato_celular_e164 = ($celDigits !== '' ? '+' . $paisDigits . $celDigits : '');
        
        $validadeSql = '';
        if ($validade !== '') {
             $validadeSql = date('Y-m-d', strtotime($validade));
        }

        if ($id_editar > 0) {
            if (!temPermissao('orcamentos.alterar')) {
                $_SESSION['flash_error'] = 'Você não tem permissão para alterar orçamentos.';
                header('Location: index.php?r=orcamentos');
                exit;
            }
            $stmt = $conn->prepare("UPDATE orcamentos
                SET paciente_id = NULLIF(?,0), paciente_avulso = NULLIF(?,''), medico_id = NULLIF(?,0), parceiro_id = NULLIF(?,0), campanha_programa_id = NULLIF(?,0), posto_id = NULLIF(?,0), convenio_id = ?,
                    validade = NULLIF(?,''), contato_celular = NULLIF(?,''), total_bruto = ?, desconto_percentual = ?, desconto_valor = ?, total_liquido = ?, bloqueio_desconto = ?,
                    status = CASE WHEN status = 'N' THEN 'NE' ELSE status END
                WHERE id = ?");
            if ($stmt) {
                $pidSave = ($modo_paciente === 'cadastrado' && $paciente_id > 0) ? $paciente_id : 0;
                $stmt->bind_param(
                    'isiiiiissddddsi',
                    $pidSave,
                    $paciente_avulso,
                    $medico_id,
                    $parceiro_id,
                    $campanha_programa_id,
                    $posto_id,
                    $convenio_id,
                    $validadeSql,
                    $contato_celular_e164,
                    $total_bruto,
                    $desconto_percentual,
                    $desconto_valor,
                    $total_liquido,
                    $bloqueio_desconto,
                    $id_editar
                );
                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // Generate code if missing
                    $codStmt = $conn->prepare("SELECT codigo_orcamento FROM orcamentos WHERE id = ? LIMIT 1");
                    $codStmt->bind_param('i', $id_editar);
                    $codStmt->execute();
                    $codRes = $codStmt->get_result();
                    $codRow = $codRes ? $codRes->fetch_assoc() : null;
                    $codStmt->close();
                    $codigoAtual = $codRow ? trim((string)$codRow['codigo_orcamento']) : '';
                    if ($codigoAtual === '') {
                        $novoCodigo = '01' . str_pad((string)$id_editar, 9, '0', STR_PAD_LEFT);
                        $upCod = $conn->prepare("UPDATE orcamentos SET codigo_orcamento = ? WHERE id = ?");
                        $upCod->bind_param('si', $novoCodigo, $id_editar);
                        $upCod->execute();
                        $upCod->close();
                    }

                    // Update items
                    $stDel = $conn->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = ?");
                    $stDel->bind_param('i', $id_editar);
                    $stDel->execute();
                    $stDel->close();

                    $stItem = $conn->prepare("INSERT INTO orcamento_itens (orcamento_id, exame_id, valor, laboratorio_id) VALUES (?, ?, ?, ?)");
                    if ($stItem) {
                        foreach ($itens_validos as $it) {
                            $stItem->bind_param('iidi', $id_editar, $it['exame_id'], $it['valor'], $it['laboratorio_id']);
                            $stItem->execute();
                        }
                        $stItem->close();
                    }
                    header("Location: index.php?r=orcamentos&sucesso=1");
                    exit;
                }
            }
        } else {
            if (!temPermissao('orcamentos.novo')) {
                 $_SESSION['flash_error'] = 'Você não tem permissão para cadastrar orçamentos.';
                 header('Location: index.php?r=orcamentos');
                 exit;
            }
            $stmt = $conn->prepare("INSERT INTO orcamentos
                (paciente_id, paciente_avulso, medico_id, parceiro_id, campanha_programa_id, posto_id, convenio_id, validade, contato_celular, total_bruto, desconto_percentual, desconto_valor, total_liquido, bloqueio_desconto, criado_por_usuario_id)
                VALUES (NULLIF(?,0), ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, NULLIF(?,''), NULLIF(?,''), ?, ?, ?, ?, ?, NULLIF(?,0))");
            if ($stmt) {
                $pidSave = ($modo_paciente === 'cadastrado' && $paciente_id > 0) ? $paciente_id : 0;
                $uid = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
                $stmt->bind_param(
                    'isiiiiissddddsi',
                    $pidSave,
                    $paciente_avulso,
                    $medico_id,
                    $parceiro_id,
                    $campanha_programa_id,
                    $posto_id,
                    $convenio_id,
                    $validadeSql,
                    $contato_celular_e164,
                    $total_bruto,
                    $desconto_percentual,
                    $desconto_valor,
                    $total_liquido,
                    $bloqueio_desconto,
                    $uid
                );
                if ($stmt->execute()) {
                    $orcamento_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Generate code
                    if ($orcamento_id > 0) {
                         $novoCodigo = '01' . str_pad((string)$orcamento_id, 9, '0', STR_PAD_LEFT);
                         $upCod = $conn->prepare("UPDATE orcamentos SET codigo_orcamento = ? WHERE id = ?");
                         $upCod->bind_param('si', $novoCodigo, $orcamento_id);
                         $upCod->execute();
                         $upCod->close();
                    }

                    // Insert items
                    $stItem = $conn->prepare("INSERT INTO orcamento_itens (orcamento_id, exame_id, valor, laboratorio_id) VALUES (?, ?, ?, ?)");
                    if ($stItem) {
                        foreach ($itens_validos as $it) {
                            $stItem->bind_param('iidi', $orcamento_id, $it['exame_id'], $it['valor'], $it['laboratorio_id']);
                            $stItem->execute();
                        }
                        $stItem->close();
                    }
                    header("Location: index.php?r=orcamentos&sucesso=1");
                    exit;
                }
            }
        }
        $_SESSION['flash_error'] = 'Erro ao salvar orçamento: ' . $conn->error;
        header('Location: index.php?r=orcamentos');
    }

    public function delete()
    {
        $conn = $this->db;

        if (!temPermissao('orcamentos.deletar')) {
            $_SESSION['flash_error'] = 'Permissão negada.';
            header('Location: index.php?r=orcamentos');
            exit;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: index.php?r=orcamentos');
            exit;
        }

        // Check if exists
        $stmt = $conn->prepare("SELECT id, pedido_id FROM orcamentos WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $orc = $res->fetch_assoc();
        $stmt->close();

        if (!$orc) {
            $_SESSION['flash_error'] = 'Orçamento não encontrado.';
            header('Location: index.php?r=orcamentos');
            exit;
        }

        if (!empty($orc['pedido_id'])) {
            $_SESSION['flash_error'] = 'Não é possível excluir um orçamento que já gerou pedido.';
            header('Location: index.php?r=orcamentos');
            exit;
        }

        // Delete items first
        $delItens = $conn->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = ?");
        $delItens->bind_param('i', $id);
        $delItens->execute();
        $delItens->close();

        // Delete orcamento
        $delOrc = $conn->prepare("DELETE FROM orcamentos WHERE id = ?");
        $delOrc->bind_param('i', $id);
        if ($delOrc->execute()) {
            $_SESSION['flash_success'] = 'Orçamento excluído com sucesso.'; 
            header('Location: index.php?r=orcamentos&sucesso=1');
        } else {
            $_SESSION['flash_error'] = 'Erro ao excluir orçamento: ' . $conn->error;
            header('Location: index.php?r=orcamentos');
        }
        $delOrc->close();
        exit;
    }

    public function desbloquear()
    {
        $conn = $this->db;
        $orc_id = (int)($_POST['orcamento_id'] ?? 0);
        $usuario_login = trim($_POST['usuario_login'] ?? '');
        $senha_login = trim($_POST['senha_login'] ?? '');

        if ($orc_id > 0 && $usuario_login !== '' && $senha_login !== '') {
            $sqlU = "SELECT id, senha, desconto_maximo_permitido FROM usuarios WHERE usuario = ? AND status = 1 LIMIT 1";
            $stU = $conn->prepare($sqlU);
            if ($stU) {
                $stU->bind_param('s', $usuario_login);
                $stU->execute();
                $rsU = $stU->get_result();
                if ($rsU && $rsU->num_rows === 1) {
                    $urow = $rsU->fetch_assoc();
                    if (password_verify($senha_login, $urow['senha'])) {
                        $stOrc = $conn->prepare("SELECT total_bruto, desconto_percentual, desconto_valor FROM orcamentos WHERE id = ? LIMIT 1");
                        if ($stOrc) {
                            $stOrc->bind_param('i', $orc_id);
                            $stOrc->execute();
                            $rsOrc = $stOrc->get_result();
                            $stOrc->close();
                            if ($rsOrc && $rsOrc->num_rows === 1) {
                                $orc = $rsOrc->fetch_assoc();
                                $total_bruto = (float)($orc['total_bruto'] ?? 0);
                                $dp = (float)($orc['desconto_percentual'] ?? 0);
                                $dv = (float)($orc['desconto_valor'] ?? 0);
                                $dp_eff = $dp > 0 ? $dp : ($total_bruto > 0 ? round($dv * 100 / $total_bruto, 2) : 0);
                                $limite_user = isset($urow['desconto_maximo_permitido']) ? (float)$urow['desconto_maximo_permitido'] : 0;
                                
                                if ($limite_user < $dp_eff) {
                                    $_SESSION['flash_error'] = 'Desconto Máximo Menor que o Permitido para este usuário.';
                                } else {
                                    $stUp = $conn->prepare("UPDATE orcamentos SET bloqueio_desconto = 'N' WHERE id = ?");
                                    if ($stUp) { $stUp->bind_param('i', $orc_id); $stUp->execute(); $stUp->close(); }
                                    $_SESSION['flash_success'] = 'Orçamento desbloqueado.';
                                }
                            }
                        }
                    } else {
                        $_SESSION['flash_error'] = 'Credenciais inválidas.';
                    }
                } else {
                    $_SESSION['flash_error'] = 'Usuário não encontrado.';
                }
                $stU->close();
            }
        } else {
            $_SESSION['flash_error'] = 'Dados incompletos.';
        }
        header("Location: index.php?r=orcamentos");
    }

    public function exportar_pedido()
    {
        $conn = $this->db;
        if (!temPermissao('pedidos.novo')) {
             $_SESSION['flash_error'] = 'Você não tem permissão para gerar pedidos.';
             header('Location: index.php?r=orcamentos');
             exit;
        }

        $orc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($orc_id <= 0) {
             header('Location: index.php?r=orcamentos');
             exit;
        }

        $stmtO = $conn->prepare("SELECT * FROM orcamentos WHERE id = ? LIMIT 1");
        $stmtO->bind_param('i', $orc_id);
        $stmtO->execute();
        $resO = $stmtO->get_result();
        $o = $resO && $resO->num_rows > 0 ? $resO->fetch_assoc() : null;
        $stmtO->close();

        if (!$o) {
            $_SESSION['flash_error'] = 'Orçamento não encontrado.';
        } elseif (!empty($o['pedido_id'])) {
             // Already exported
             header("Location: index.php?r=pedidos/edit&id=" . (int)$o['pedido_id']);
             exit;
        } else {
            $pacId = (int)($o['paciente_id'] ?? 0);
            $convId = (int)($o['convenio_id'] ?? 0);
            
            if ($pacId <= 0) {
                 $_SESSION['flash_error'] = 'Orçamento avulso não pode ser exportado para pedido. Cadastre o paciente primeiro.';
            } elseif ($convId <= 0) {
                 $_SESSION['flash_error'] = 'Convênio inválido.';
            } else {
                $itens = [];
                $stmtI = $conn->prepare("SELECT exame_id, valor FROM orcamento_itens WHERE orcamento_id = ?");
                $stmtI->bind_param('i', $orc_id);
                $stmtI->execute();
                $resI = $stmtI->get_result();
                while ($rw = $resI->fetch_assoc()) $itens[] = $rw;
                $stmtI->close();

                if (empty($itens)) {
                    $_SESSION['flash_error'] = 'Orçamento sem itens.';
                } else {
                    $entrada_atual = date('Y-m-d H:i:s');
                    $medId = (int)($o['medico_id'] ?? 0);
                    $postoId = (int)($o['posto_id'] ?? 0);
                    $tb = (float)($o['total_bruto'] ?? 0);
                    $dp = (float)($o['desconto_percentual'] ?? 0);
                    $dv = (float)($o['desconto_valor'] ?? 0);
                    $tl = (float)($o['total_liquido'] ?? 0);

                    $stmtP = $conn->prepare("INSERT INTO pedidos
                        (paciente_id, medico_id, posto_id, convenio_id, entrada, total_bruto, desconto_percentual, desconto_valor, total_liquido)
                        VALUES (?, NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?)");
                    
                    $stmtP->bind_param('iiiisdddd', $pacId, $medId, $postoId, $convId, $entrada_atual, $tb, $dp, $dv, $tl);
                    if ($stmtP->execute()) {
                        $pedido_id_new = (int)$stmtP->insert_id;
                        $stmtP->close();
                        
                        // Items
                        $stmtPI = $conn->prepare("INSERT INTO pedido_itens (pedido_id, exame_id, valor, data_coleta, hora_coleta, status_exame) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($itens as $it) {
                            $exid = (int)($it['exame_id'] ?? 0);
                            $val = (float)($it['valor'] ?? 0);
                            $dc = '';
                            $hc = '';
                            $st = 'C';
                            $stmtPI->bind_param('iidsss', $pedido_id_new, $exid, $val, $dc, $hc, $st);
                            $stmtPI->execute();
                        }
                        $stmtPI->close();

                        // Link back
                        $stmtUp = $conn->prepare("UPDATE orcamentos SET pedido_id = ?, status = 'G' WHERE id = ?");
                        $stmtUp->bind_param('ii', $pedido_id_new, $orc_id);
                        $stmtUp->execute();
                        $stmtUp->close();

                        header("Location: index.php?r=pedidos/edit&id=" . $pedido_id_new);
                        exit;
                    } else {
                        $_SESSION['flash_error'] = 'Falha ao gerar pedido.';
                    }
                }
            }
        }
        header("Location: index.php?r=orcamentos");
    }

    public function crm_history()
    {
        $conn = $this->db;
        $this->requireAuth();
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $history = [];
        $orcamento = null;

        if ($id > 0) {
            // Get Orcamento Status
            $resO = $conn->query("SELECT id, status, codigo_orcamento FROM orcamentos WHERE id = $id");
            if ($resO) $orcamento = $resO->fetch_assoc();

            // Get History
            $sql = "SELECT c.*, u.nome as usuario_nome 
                    FROM orcamentos_crm c 
                    LEFT JOIN usuarios u ON u.id = c.usuario_id 
                    WHERE c.orcamento_id = ? 
                    ORDER BY c.criado_em DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                while($row = $res->fetch_assoc()) {
                    $history[] = $row;
                }
                $stmt->close();
            }
        }
        
        // Get Motivos Perdemos
        $motivos = [];
        $resM = $conn->query("SELECT * FROM crm_motivos_perdemos WHERE ativo = 1 ORDER BY motivo ASC");
        if ($resM) {
            while ($row = $resM->fetch_assoc()) {
                $motivos[] = $row;
            }
        }

        // Use 'ajax' layout name to trigger fallback to content view only (since layouts/ajax.php likely doesn't exist)
        $this->view('orcamentos/crm_content', [
            'history' => $history, 
            'orcamento' => $orcamento,
            'motivos' => $motivos,
            'layout' => 'ajax'
        ]); 
    }

    public function crm_save()
    {
        $conn = $this->db;
        $this->requireAuth();

        $orc_id = (int)($_POST['orcamento_id'] ?? 0);
        $mensagem = trim($_POST['mensagem'] ?? '');
        $acao = $_POST['acao'] ?? ''; // 'comentario' or 'perdemos'
        $motivo_id = (int)($_POST['motivo_id'] ?? 0);
        $uid = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

        if ($orc_id > 0 && $uid > 0) {
            if ($acao === 'perdemos') {
                 $conn->query("UPDATE orcamentos SET status = 'P' WHERE id = $orc_id");
                 
                 $prefix = "[Marcado como Perdemos]";
                 if ($motivo_id > 0) {
                     $res = $conn->query("SELECT motivo FROM crm_motivos_perdemos WHERE id = $motivo_id");
                     if ($res && $row = $res->fetch_assoc()) {
                         $prefix .= " [Motivo: " . $row['motivo'] . "]";
                     }
                 }
                 
                 $msg_full = $prefix . ($mensagem !== '' ? " " . $mensagem : "");
                 
                 // Insert history
                 $stmt = $conn->prepare("INSERT INTO orcamentos_crm (orcamento_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'S')");
                 $stmt->bind_param('iis', $orc_id, $uid, $msg_full);
                 $stmt->execute();
            } elseif ($acao === 'continuar') {
                 $conn->query("UPDATE orcamentos SET status = 'NE' WHERE id = $orc_id");
                 
                 $msg_full = "[Retomou a Negociação]" . ($mensagem !== '' ? " " . $mensagem : "");
                 
                 $stmt = $conn->prepare("INSERT INTO orcamentos_crm (orcamento_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'S')");
                 $stmt->bind_param('iis', $orc_id, $uid, $msg_full);
                 $stmt->execute();
            } else {
                 if ($mensagem !== '') {
                     $stmt = $conn->prepare("INSERT INTO orcamentos_crm (orcamento_id, usuario_id, mensagem, tipo) VALUES (?, ?, ?, 'M')");
                     $stmt->bind_param('iis', $orc_id, $uid, $mensagem);
                     $stmt->execute();
                     
                     // If adding a note and status is 'N', upgrade to 'NE'
                     $conn->query("UPDATE orcamentos SET status = 'NE' WHERE id = $orc_id AND status = 'N'");
                 }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }
}
