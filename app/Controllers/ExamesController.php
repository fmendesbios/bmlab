<?php

namespace App\Controllers;

use App\Core\Controller;
use Throwable;

class ExamesController extends Controller {

    // Helper method
    private function br_para_float($valor) {
        if ($valor === null) return 0;
        $valor = trim($valor);
        if ($valor === '') return 0;
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return (float) $valor;
    }

    private function getLayoutDataInternal($exId) {
        $conn = $this->db;
        $rows = [];
        $colunasCfg = [];
        $celulasCfg = [];

        // Ensure columns exist (Crucial for SELECT below)
        $needCols = [
            'num_tipo' => "ALTER TABLE exame_variaveis ADD COLUMN num_tipo VARCHAR(20) NULL",
            'num_pos' => "ALTER TABLE exame_variaveis ADD COLUMN num_pos INT NULL",
            'num_dec' => "ALTER TABLE exame_variaveis ADD COLUMN num_dec INT NULL",
            'num_formula' => "ALTER TABLE exame_variaveis ADD COLUMN num_formula TEXT NULL",
            'var_alias' => "ALTER TABLE exame_variaveis ADD COLUMN var_alias VARCHAR(255) NULL"
        ];
        foreach ($needCols as $colName => $ddl) {
            try {
                $chk = $conn->query("SHOW COLUMNS FROM exame_variaveis LIKE '" . $conn->real_escape_string($colName) . "'");
                $exists = $chk && $chk->num_rows > 0;
                if ($chk) $chk->close();
                if (!$exists) {
                    $conn->query($ddl);
                }
            } catch (Throwable $eCol) {}
        }

        // Schema update for exame_layout_linhas
        $needColsLin = [
            'altura' => "ALTER TABLE exame_layout_linhas ADD COLUMN altura INT NULL",
            'quebra_antes' => "ALTER TABLE exame_layout_linhas ADD COLUMN quebra_antes TINYINT(1) DEFAULT 0",
            'quebra_depois' => "ALTER TABLE exame_layout_linhas ADD COLUMN quebra_depois TINYINT(1) DEFAULT 0",
            'ocultar_vazia' => "ALTER TABLE exame_layout_linhas ADD COLUMN ocultar_vazia TINYINT(1) DEFAULT 0"
        ];
        foreach ($needColsLin as $colName => $ddl) {
            try {
                $chk = $conn->query("SHOW COLUMNS FROM exame_layout_linhas LIKE '" . $conn->real_escape_string($colName) . "'");
                $exists = $chk && $chk->num_rows > 0;
                if ($chk) $chk->close();
                if (!$exists) {
                    $conn->query($ddl);
                }
            } catch (Throwable $eCol) {}
        }
        
        // Detection of schema version (legacy check)
        $linHasLinhaId = false;
        $colHasLinhaId = false;
        $chkA = $conn->query("SHOW COLUMNS FROM exame_layout_linhas LIKE 'linha_id'");
        if ($chkA) { $linHasLinhaId = ($chkA->num_rows > 0); $chkA->close(); }
        $chkB = $conn->query("SHOW COLUMNS FROM exame_layout_colunas LIKE 'linha_id'");
        if ($chkB) { $colHasLinhaId = ($chkB->num_rows > 0); $chkB->close(); }

        // Using the standard query logic assuming structure is consistent with what was seen
        $sqlLin = "SELECT id, ordem, secao, altura, quebra_antes, quebra_depois, ocultar_vazia FROM exame_layout_linhas WHERE exame_id = ? ORDER BY secao, ordem, id";
        $stmtLin = $conn->prepare($sqlLin);
        if (!$stmtLin) throw new Exception("Prepare SQL Lin failed: " . $conn->error);
        $stmtLin->bind_param('i', $exId);
        $stmtLin->execute();
        $resLin = $stmtLin->get_result();
        
        $ordIndexBySecao = [];
        $rowIndexBySecao = [];
        $descToIdxBySecao = [];

        // Buffer lines to avoid nested statement issues
        $allLines = [];
        while ($l = $resLin->fetch_assoc()) {
            $allLines[] = $l;
        }
        $stmtLin->close();

        // Prepare Column Statement once
        $sqlCol = "SELECT valor_fixo FROM exame_layout_colunas WHERE linha_id = ? ORDER BY ordem, id";
        $stmtCol = $conn->prepare($sqlCol);
        if (!$stmtCol) throw new Exception("Prepare SQL Col failed: " . $conn->error);

        foreach ($allLines as $linha) {
            $secao = (int)$linha['secao'];
            $linhaId = (int)$linha['id'];
            
            if (!isset($rowIndexBySecao[$secao])) $rowIndexBySecao[$secao] = 0;
            $currentIdx = $rowIndexBySecao[$secao]++;
            
            // Map ORDEM to Index, because exame_variaveis.linha stores ORDEM
            $ordem = (int)$linha['ordem'];
            $ordIndexBySecao[$secao][$ordem] = $currentIdx;

            $row = [
                'secao' => $secao, 
                'ordem' => $ordem,
                'altura' => isset($linha['altura']) ? (int)$linha['altura'] : null,
                'quebra_antes' => !empty($linha['quebra_antes']),
                'quebra_depois' => !empty($linha['quebra_depois']),
                'ocultar_vazia' => !empty($linha['ocultar_vazia'])
            ];
            
            $stmtCol->bind_param('i', $linhaId);
            $stmtCol->execute();
            $resCol = $stmtCol->get_result();
            $idx = 1;
            while ($col = $resCol->fetch_assoc()) {
                $row['col' . $idx] = (string)$col['valor_fixo'];
                $idx++;
            }
            
            if (isset($row['col1'])) {
                $dVal = trim($row['col1']);
                if ($dVal !== '') {
                    if (!isset($descToIdxBySecao[$secao])) $descToIdxBySecao[$secao] = [];
                    $keyDesc = function_exists('mb_strtolower') ? mb_strtolower($dVal) : strtolower($dVal);
                    $descToIdxBySecao[$secao][$keyDesc] = $currentIdx;
                }
            }

            $rows[] = $row;
        }
        $stmtCol->close();
        
        // Fetch Configs
        $stmtCC = $conn->prepare("SELECT secao, coluna, tipo, titulo, largura, single_line, custom_font, font_family, font_size, font_color, bold, italic, underline, align_h, align_v FROM exame_layout_colunas_cfg WHERE exame_id = ?");
        $stmtCC->bind_param('i', $exId);
        $stmtCC->execute();
        $resCC = $stmtCC->get_result();
        while ($r = $resCC->fetch_assoc()) $colunasCfg[] = $r;
        $stmtCC->close();

        $stmtCE = $conn->prepare("SELECT secao, linha, coluna, uppercase, single_line, custom_font, font_family, font_size, font_color, bold, italic, underline, align_h, align_v, rt_tipo, rt_id FROM exame_layout_celulas_cfg WHERE exame_id = ?");
        $stmtCE->bind_param('i', $exId);
        $stmtCE->execute();
        $resCE = $stmtCE->get_result();
        while ($r = $resCE->fetch_assoc()) $celulasCfg[] = $r;
        $stmtCE->close();
        
        // Merge exame_variaveis into celulasCfg
        $stmtNV = $conn->prepare("SELECT id, secao, linha, coluna, descricao, num_tipo, num_pos, num_dec, num_formula, var_alias FROM exame_variaveis WHERE exame_id = ? AND LOWER(tipo_resultado) IN ('nu','numerico','resultado_numerico','numero')");
        if ($stmtNV) {
            $stmtNV->bind_param('i', $exId);
            $stmtNV->execute();
            $resNV = $stmtNV->get_result();
            
            $map = [];
            $varsArr = [];
            while ($r = $resNV->fetch_assoc()) {
                $sec = (string)($r['secao'] ?? '');
                $lin1 = (int)($r['linha'] ?? 0);
                $col = (int)($r['coluna'] ?? 0);
                $idxLin = max(0, $lin1 - 1);
                $secInt = (int)$sec;
                if ($secInt > 0 && isset($ordIndexBySecao[$secInt]) && isset($ordIndexBySecao[$secInt][$lin1])) {
                    $idxLin = (int)$ordIndexBySecao[$secInt][$lin1];
                }
                $key = $sec . '|' . $idxLin . '|' . $col;
                $map[$key] = [
                    'secao' => $sec,
                    'linha' => $idxLin,
                    'coluna' => $col,
                    'numTipo' => (string)($r['num_tipo'] ?? ''),
                    'numPos' => isset($r['num_pos']) ? (int)$r['num_pos'] : null,
                    'numDec' => isset($r['num_dec']) ? (int)$r['num_dec'] : null,
                    'numFormula' => (string)($r['num_formula'] ?? ''),
                    'varAlias' => (string)($r['var_alias'] ?? ''),
                    'varId' => (int)($r['id'] ?? 0)
                ];
                $varsArr[] = $r;
            }
            $stmtNV->close();

            if (!empty($map)) {
                $byKey = [];
                foreach ($celulasCfg as $idx => $it) {
                    $k = (string)($it['secao'] ?? '') . '|' . (int)($it['linha'] ?? 0) . '|' . (int)($it['coluna'] ?? 0);
                    $byKey[$k] = $idx;
                }
                foreach ($map as $k => $nv) {
                    if (isset($byKey[$k])) {
                        $i = $byKey[$k];
                        $celulasCfg[$i]['numTipo'] = $nv['numTipo'];
                        $celulasCfg[$i]['numPos'] = $nv['numPos'];
                        $celulasCfg[$i]['numDec'] = $nv['numDec'];
                        $celulasCfg[$i]['numFormula'] = $nv['numFormula'];
                        $celulasCfg[$i]['varAlias'] = $nv['varAlias'];
                        $celulasCfg[$i]['varId'] = $nv['varId'];
                    } else {
                        $celulasCfg[] = $nv;
                    }
                }

                // Fallback by description
                if (!empty($varsArr)) {
                    foreach ($varsArr as $vr) {
                        $sInt = (int)($vr['secao'] ?? 0);
                        $desc = trim((string)($vr['descricao'] ?? ''));
                        $col = (int)($vr['coluna'] ?? 0);
                        
                        if ($sInt > 0 && $desc !== '' && isset($descToIdxBySecao[$sInt])) {
                            $keyDesc = mb_strtolower($desc);
                            if (isset($descToIdxBySecao[$sInt][$keyDesc])) {
                                $rIdx = (int)$descToIdxBySecao[$sInt][$keyDesc];
                                $k2 = (string)$sInt . '|' . $rIdx . '|' . $col;
                                
                                if (isset($byKey[$k2])) {
                                    $i2 = $byKey[$k2];
                                    $celulasCfg[$i2]['numTipo'] = (string)($vr['num_tipo'] ?? '');
                                    $celulasCfg[$i2]['numPos'] = isset($vr['num_pos']) ? (int)$vr['num_pos'] : null;
                                    $celulasCfg[$i2]['numDec'] = isset($vr['num_dec']) ? (int)$vr['num_dec'] : null;
                                    $celulasCfg[$i2]['numFormula'] = (string)($vr['num_formula'] ?? '');
                                    $celulasCfg[$i2]['varAlias'] = (string)($vr['var_alias'] ?? '');
                                    $celulasCfg[$i2]['varId'] = (int)($vr['id'] ?? 0);
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'rows' => $rows,
            'colunas_cfg' => $colunasCfg,
            'celulas_cfg' => $celulasCfg
        ];
    }

    public function index() {
        $conn = $this->db;

        verificaLogin();
        exigirPermissao('exames.acesso');

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        $filtro_setor = isset($_GET['filtro_setor']) ? (int)$_GET['filtro_setor'] : 0;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if ($busca !== '') {
            $where .= " AND (e.nome LIKE ? OR e.mnemonico_local LIKE ?)";
            $term = "%$busca%";
            $params[] = $term;
            $params[] = $term;
            $types .= "ss";
        }

        if ($filtro_setor > 0) {
            $where .= " AND e.setor_id = ?";
            $params[] = $filtro_setor;
            $types .= "i";
        }

        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM exames e $where";
        $stmtCount = $conn->prepare($sqlCount);
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $resCount = $stmtCount->get_result();
        if ($row = $resCount->fetch_assoc()) {
            $totalRecords = (int)$row['total'];
        }
        $stmtCount->close();

        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT e.*, s.setor_nome 
                FROM exames e 
                LEFT JOIN setores s ON s.id = e.setor_id 
                $where 
                ORDER BY e.nome ASC LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $exames = [];
        while ($row = $result->fetch_assoc()) {
            $exames[] = $row;
        }
        $stmt->close();

        // Load sectors for filter
        $setores = [];
        $resS = $conn->query("SELECT id, setor_nome FROM setores ORDER BY setor_nome");
        while ($r = $resS->fetch_assoc()) {
            $setores[] = $r;
        }

        $this->view('exames/index', [
            'exames' => $exames,
            'setores' => $setores,
            'busca' => $busca,
            'filtro_setor' => $filtro_setor,
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function edit() {
        $conn = $this->db;

        verificaLogin();
        exigirPermissao('exames.acesso');

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $exame = [
            'id' => '', 'ativo' => 1, 'mnemonico' => '', 'nome' => '', 
            'sinonimia' => '', 'metodo' => '', 'prazo_execucao_local' => '',
            'setor_id' => '', 'material_biologico_id' => '', 
            'laboratorio_id' => '', 'recipiente_id' => ''
        ];

        // Auxiliary data
        $apoios = [];
        $precos_convenios = [];
        $variaveis = [];
        $referencias = []; // Would need logic to fetch based on selected variable
        
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT e.*, e.mnemonico_local AS mnemonico, mb.material_nome AS material_biologico_nome 
                                    FROM exames e 
                                    LEFT JOIN materiais_biologicos mb ON mb.id = e.material_biologico_id
                                    WHERE e.id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($r = $res->fetch_assoc()) {
                $exame = $r;
            }
            $stmt->close();

            // Load Supports
            $stmtAp = $conn->prepare("SELECT ela.*, l.laboratorio_nome 
                                     FROM exame_laboratorio_apoio ela 
                                     JOIN laboratorios l ON l.id = ela.laboratorio_id 
                                     WHERE ela.exame_id = ?");
            $stmtAp->bind_param('i', $id);
            $stmtAp->execute();
            $resAp = $stmtAp->get_result();
            while ($ra = $resAp->fetch_assoc()) {
                $apoios[] = $ra;
            }
            $stmtAp->close();

            // Load Prices
            $stmtPc = $conn->prepare("SELECT ecp.*, c.nome AS convenio_nome 
                                     FROM exame_convenio_precos ecp 
                                     JOIN convenios c ON c.id = ecp.convenio_id 
                                     WHERE ecp.exame_id = ?");
            $stmtPc->bind_param('i', $id);
            $stmtPc->execute();
            $resPc = $stmtPc->get_result();
            while ($rpc = $resPc->fetch_assoc()) {
                $precos_convenios[] = $rpc;
            }
            $stmtPc->close();

            // Load Variables (for the list in 'Valores' tab)
            $stmtV = $conn->prepare("SELECT * FROM exame_variaveis WHERE exame_id = ? ORDER BY secao, linha, coluna");
            $stmtV->bind_param('i', $id);
            $stmtV->execute();
            $resV = $stmtV->get_result();
            while ($rv = $resV->fetch_assoc()) {
                $variaveis[] = $rv;
            }
            $stmtV->close();
        }

        // Dropdown data
        $setores = [];
        $res = $conn->query("SELECT id, setor_nome FROM setores ORDER BY setor_nome");
        while($r = $res->fetch_assoc()) $setores[] = $r;

        $materiais = [];
        $res = $conn->query("SELECT id, material_nome FROM materiais_biologicos ORDER BY material_nome");
        while($r = $res->fetch_assoc()) $materiais[] = $r;

        $recipientes = [];
        $res = $conn->query("SELECT id, recipiente_nome FROM recipientes_coleta ORDER BY recipiente_nome");
        while($r = $res->fetch_assoc()) $recipientes[] = $r;

        $laboratorios = [];
        $res = $conn->query("SELECT id, laboratorio_nome FROM laboratorios ORDER BY laboratorio_nome");
        while($r = $res->fetch_assoc()) $laboratorios[] = $r;

        $convenios = [];
        $res = $conn->query("SELECT id, nome FROM convenios WHERE ativo = 1 ORDER BY nome");
        while($r = $res->fetch_assoc()) $convenios[] = $r;

        $this->view('exames/form', [
            'exame' => $exame,
            'setores' => $setores,
            'materiais' => $materiais,
            'recipientes' => $recipientes,
            'laboratorios' => $laboratorios,
            'convenios' => $convenios,
            'apoios' => $apoios,
            'precos_convenios' => $precos_convenios,
            'variaveis' => $variaveis,
            'editando' => ($id > 0)
        ]);
    }

    public function store() {
        $conn = $this->db;
        verificaLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=exames');
            exit;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        // Handle specialized saves (Apoio, Preco, Layout) if they come through here, 
        // though they might have their own routes or form_origem checks.
        $form_origem = $_POST['form_origem'] ?? '';

        if ($form_origem === 'apoio') {
            $this->saveApoio();
            return;
        }
        if ($form_origem === 'convenio_preco') {
            $this->savePreco();
            return;
        }
        if ($form_origem === 'layout_editor') {
            $this->save_layout();
            return;
        }
        if ($form_origem === 'novo_setor') {
            $this->ajaxStoreSetor();
            return;
        }
        if ($form_origem === 'novo_material') {
            $this->ajaxStoreMaterial();
            return;
        }
        if ($form_origem === 'novo_recipiente') {
            $this->ajaxStoreRecipiente();
            return;
        }
        
        // Main Exam Save
        if (!temPermissao('exames.salvar')) {
            $_SESSION['flash_error'] = 'Sem permissão para salvar exames.';
            header('Location: index.php?r=exames');
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        $mnemonico = trim($_POST['mnemonico'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $setor_id = (int)($_POST['setor_id'] ?? 0);
        $material_id = (int)($_POST['material_biologico_id'] ?? 0);
        $recipiente_id = (int)($_POST['recipiente_id'] ?? 0);
        $laboratorio_id = (int)($_POST['laboratorio_id'] ?? 0); // Lab Preferencial
        $prazo = (int)($_POST['prazo_execucao_local'] ?? 0);
        $metodo = trim($_POST['metodo'] ?? '');
        $sinonimia = trim($_POST['sinonimia'] ?? '');

        if ($nome === '' || $mnemonico === '') {
            $_SESSION['flash_error'] = 'Nome e Mnemônico são obrigatórios.';
            header("Location: index.php?r=exames/edit&id=$id");
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE exames SET 
                nome=?, mnemonico_local=?, ativo=?, setor_id=?, 
                material_biologico_id=?, recipiente_id=?, laboratorio_id=?, 
                prazo_execucao_local=?, metodo=?, sinonimia=? 
                WHERE id=?");
            $stmt->bind_param("ssiiiiiisii", 
                $nome, $mnemonico, $ativo, $setor_id, 
                $material_id, $recipiente_id, $laboratorio_id, 
                $prazo, $metodo, $sinonimia, $id);
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Exame atualizado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao atualizar exame: ' . $conn->error;
            }
            $stmt->close();
            header("Location: index.php?r=exames/edit&id=$id");
        } else {
            $stmt = $conn->prepare("INSERT INTO exames 
                (nome, mnemonico_local, ativo, setor_id, 
                material_biologico_id, recipiente_id, laboratorio_id, 
                prazo_execucao_local, metodo, sinonimia) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiiiisis", 
                $nome, $mnemonico, $ativo, $setor_id, 
                $material_id, $recipiente_id, $laboratorio_id, 
                $prazo, $metodo, $sinonimia);
                
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $_SESSION['flash_success'] = 'Exame criado com sucesso.';
                header("Location: index.php?r=exames/edit&id=$newId");
            } else {
                $_SESSION['flash_error'] = 'Erro ao criar exame: ' . $conn->error;
                header("Location: index.php?r=exames/edit");
            }
            $stmt->close();
        }
    }

    public function delete() {
        $conn = $this->db;
        verificaLogin();
        exigirPermissao('exames.excluir');

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM exames WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Exame excluído com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir exame.';
            }
            $stmt->close();
        }
        header('Location: index.php?r=exames');
    }



    // AJAX Handlers
    
    private function ajaxStoreSetor() {
        $conn = $this->db;
        try {
            if (!temPermissao('exames.salvar')) {
                throw new Exception('Sem permissão.');
            }
            $nome = trim($_POST['setor_nome'] ?? '');
            if ($nome === '') {
                throw new Exception('Informe o nome do setor.');
            }
            $stmt = $conn->prepare("INSERT INTO setores (setor_nome) VALUES (?)");
            $stmt->bind_param('s', $nome);
            $stmt->execute();
            $novoId = $stmt->insert_id;
            $stmt->close();
            
            echo json_encode([
                'sucesso' => true,
                'tipo'    => 'setor',
                'id'      => $novoId,
                'nome'    => $nome
            ]);
        } catch (Throwable $e) {
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
        }
        exit;
    }

    private function ajaxStoreMaterial() {
        $conn = $this->db;
        try {
            if (!temPermissao('exames.salvar')) {
                throw new Exception('Sem permissão.');
            }
            $nome = trim($_POST['material_nome'] ?? '');
            if ($nome === '') {
                throw new Exception('Informe o nome do material biológico.');
            }
            $stmt = $conn->prepare("INSERT INTO materiais_biologicos (material_nome) VALUES (?)");
            $stmt->bind_param('s', $nome);
            $stmt->execute();
            $novoId = $stmt->insert_id;
            $stmt->close();
            
            echo json_encode([
                'sucesso' => true,
                'tipo'    => 'material',
                'id'      => $novoId,
                'nome'    => $nome
            ]);
        } catch (Throwable $e) {
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
        }
        exit;
    }

    private function ajaxStoreRecipiente() {
        $conn = $this->db;
        try {
            if (!temPermissao('exames.salvar')) {
                throw new Exception('Sem permissão.');
            }
            $nome = trim($_POST['recipiente_nome'] ?? '');
            if ($nome === '') {
                throw new Exception('Informe o nome do recipiente.');
            }
            $stmt = $conn->prepare("INSERT INTO recipientes_coleta (recipiente_nome) VALUES (?)");
            $stmt->bind_param('s', $nome);
            $stmt->execute();
            $novoId = $stmt->insert_id;
            $stmt->close();
            
            echo json_encode([
                'sucesso' => true,
                'tipo'    => 'recipiente',
                'id'      => $novoId,
                'nome'    => $nome
            ]);
        } catch (Throwable $e) {
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
        }
        exit;
    }
    
    public function layout_fetch() {
        // Prevent output pollution
        ob_start();
        
        $exId = (int) ($_POST['exame_id'] ?? 0);
        if ($exId <= 0) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Exame inválido.']);
            exit;
        }

        try {
            $data = $this->getLayoutDataInternal($exId);

            $json = json_encode([
                'sucesso' => true,
                'rows' => $data['rows'],
                'colunas_cfg' => $data['colunas_cfg'],
                'celulas_cfg' => $data['celulas_cfg']
            ]);
            
            if ($json === false) {
                 throw new \Exception("JSON Encode Error: " . json_last_error_msg());
            }

            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo $json;

        } catch (Throwable $e) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
        }
    }

    public function save_layout() {
        // Custom login check for AJAX to return JSON instead of redirect
        if (empty($_SESSION['usuario_id'])) {
             header('Content-Type: application/json; charset=utf-8');
             echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
             exit;
        }
        
        // Prevent output pollution
        ob_start();
        $conn = $this->db;
        // Logic from exames.php lines 771+
        // Requires json_decode of layout_json, colunas_cfg_json, celulas_cfg_json
        // Transactional delete and insert
        
        $exame_id = (int)($_POST['exame_id'] ?? 0);
        $jsonGrid = $_POST['layout_json'] ?? '[]';
        $colunasCfgJson = $_POST['colunas_cfg_json'] ?? '[]';
        $celulasCfgJson = $_POST['celulas_cfg_json'] ?? '[]';

        if ($exame_id <= 0) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
            exit;
        }

        $dados = json_decode($jsonGrid, true);
        if (!is_array($dados)) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']);
            exit;
        }

        $conn->begin_transaction();
        try {
            // DELETE OLD LAYOUT
            $stmtSelLinhas = $conn->prepare("SELECT id FROM exame_layout_linhas WHERE exame_id = ?");
            $stmtSelLinhas->bind_param('i', $exame_id);
            $stmtSelLinhas->execute();
            $resLin = $stmtSelLinhas->get_result();
            $linhaIds = [];
            while ($r = $resLin->fetch_assoc()) $linhaIds[] = (int)$r['id'];
            $stmtSelLinhas->close();

            if (!empty($linhaIds)) {
                $in = implode(',', $linhaIds);
                $conn->query("DELETE FROM exame_layout_colunas WHERE linha_id IN ($in)");
                $conn->query("DELETE FROM exame_layout_linhas WHERE id IN ($in)");
            }

            // INSERT NEW LAYOUT
            $stmtInsLinha = $conn->prepare("INSERT INTO exame_layout_linhas (exame_id, ordem, ativo, secao, altura, quebra_antes, quebra_depois, ocultar_vazia) VALUES (?, ?, 1, ?, ?, ?, ?, ?)");
            $stmtInsCol = $conn->prepare("INSERT INTO exame_layout_colunas (linha_id, ordem, tipo_coluna, largura, alinhamento_h, alinhamento_v, estilo_negrito, estilo_italico, estilo_sublinhado, valor_fixo) VALUES (?, ?, 'texto_fixo', ?, 'esquerda', 'meio', 0, 0, 0, ?)");

            $ordemPorSecao = [];
            foreach ($dados as $row) {
                $secao = isset($row['secao']) ? (int)$row['secao'] : 1;
                if ($secao <= 0) $secao = 1;
                $prox = isset($ordemPorSecao[$secao]) ? ((int)$ordemPorSecao[$secao] + 1) : 1;
                $ordemPorSecao[$secao] = $prox;
                
                $altura = isset($row['altura']) && $row['altura'] !== '' ? (int)$row['altura'] : null;
                $qa = !empty($row['quebra_antes']) ? 1 : 0;
                $qd = !empty($row['quebra_depois']) ? 1 : 0;
                $ov = !empty($row['ocultar_vazia']) ? 1 : 0;

                $stmtInsLinha->bind_param('iisiiii', $exame_id, $prox, $secao, $altura, $qa, $qd, $ov);
                $stmtInsLinha->execute();
                $linhaId = $stmtInsLinha->insert_id;

                $colunasLinha = [];
                foreach ($row as $campo => $valor) {
                    if (strpos($campo, 'col') === 0) {
                        $colunasLinha[$campo] = trim((string)$valor);
                    }
                }
                ksort($colunasLinha, SORT_NATURAL);
                $qtdCols = count($colunasLinha);
                if ($qtdCols <= 0) continue;
                
                $larguraBase = (int)floor(100 / $qtdCols);
                if ($larguraBase <= 0) $larguraBase = 100;

                $ordCol = 1;
                foreach ($colunasLinha as $valor) {
                    $stmtInsCol->bind_param('iiis', $linhaId, $ordCol, $larguraBase, $valor);
                    $stmtInsCol->execute();
                    $ordCol++;
                }
            }

            // SAVE CONFIGS (Column/Cell)
            $conn->query("DELETE FROM exame_layout_colunas_cfg WHERE exame_id = $exame_id");
            $cfgColsArr = json_decode($colunasCfgJson, true);
            if (is_array($cfgColsArr) && !empty($cfgColsArr)) {
                $stmtInsColCfg = $conn->prepare("INSERT INTO exame_layout_colunas_cfg (exame_id, secao, coluna, tipo, titulo, largura, single_line, custom_font, font_family, font_size, font_color, bold, italic, underline, align_h, align_v) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($cfgColsArr as $v) {
                    // Mapping values... (Simplified for brevity, assumes data is sanitized/valid type)
                    $secaoStr = (string)($v['secao'] ?? '');
                    $colIdx = (int)($v['coluna'] ?? 0);
                    $tipo = (string)($v['tipo'] ?? '');
                    $titulo = (string)($v['titulo'] ?? '');
                    $largura = isset($v['largura']) ? (int)$v['largura'] : null;
                    $single_line = !empty($v['single_line']) ? 1 : 0;
                    $custom_font = !empty($v['custom_font']) ? 1 : 0;
                    $font_family = (string)($v['font_family'] ?? '');
                    $font_size = isset($v['font_size']) ? (int)$v['font_size'] : null;
                    $font_color = (string)($v['font_color'] ?? '');
                    $bold = !empty($v['bold']) ? 1 : 0;
                    $italic = !empty($v['italic']) ? 1 : 0;
                    $underline = !empty($v['underline']) ? 1 : 0;
                    $align_h = (string)($v['align_h'] ?? '');
                    $align_v = (string)($v['align_v'] ?? '');
                    $stmtInsColCfg->bind_param('isissiiisisiiiss', $exame_id, $secaoStr, $colIdx, $tipo, $titulo, $largura, $single_line, $custom_font, $font_family, $font_size, $font_color, $bold, $italic, $underline, $align_h, $align_v);
                    $stmtInsColCfg->execute();
                }
                $stmtInsColCfg->close();
            }

            $conn->query("DELETE FROM exame_layout_celulas_cfg WHERE exame_id = $exame_id");
            $cellsArr = json_decode($celulasCfgJson, true);
            if (is_array($cellsArr) && !empty($cellsArr)) {
                 $stmtInsCellCfg = $conn->prepare("INSERT INTO exame_layout_celulas_cfg (exame_id, secao, linha, coluna, uppercase, single_line, custom_font, font_family, font_size, font_color, bold, italic, underline, align_h, align_v, rt_tipo, rt_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                 foreach ($cellsArr as $v) {
                     // Mapping...
                    $secaoStr = (string)($v['secao'] ?? '');
                    $linIdx = (int)($v['linha'] ?? 0);
                    $colIdx = (int)($v['coluna'] ?? 0);
                    $uc = !empty($v['uppercase']) ? 1 : 0;
                    $sl = !empty($v['single_line']) ? 1 : 0;
                    $cf = !empty($v['custom_font']) ? 1 : 0;
                    $ff = (string)($v['font_family'] ?? '');
                    $fs = isset($v['font_size']) ? (int)$v['font_size'] : null;
                    $fc = (string)($v['font_color'] ?? '');
                    $b = !empty($v['bold']) ? 1 : 0;
                    $i = !empty($v['italic']) ? 1 : 0;
                    $u = !empty($v['underline']) ? 1 : 0;
                    $ah = (string)($v['align_h'] ?? '');
                    $av = (string)($v['align_v'] ?? '');
                    $rtT = (string)($v['rt_tipo'] ?? '');
                    $rtI = isset($v['rt_id']) ? (int)$v['rt_id'] : null;

                    $stmtInsCellCfg->bind_param('isiiiisisiiissisi', $exame_id, $secaoStr, $linIdx, $colIdx, $uc, $sl, $cf, $ff, $fs, $fc, $b, $i, $u, $ah, $av, $rtT, $rtI);
                    $stmtInsCellCfg->execute();
                 }
                 $stmtInsCellCfg->close();
            }

            // Logic from exames.php: Update exame_variaveis based on cell config
            try {
                // Remove duplicates in exame_variaveis
                try {
                    $stmtDup = $conn->prepare("SELECT secao, linha, coluna, COUNT(*) AS c FROM exame_variaveis WHERE exame_id = ? GROUP BY secao, linha, coluna HAVING c > 1");
                    if ($stmtDup) {
                        $stmtDup->bind_param('i', $exame_id);
                        $stmtDup->execute();
                        $resDup = $stmtDup->get_result();
                        while ($d = $resDup->fetch_assoc()) {
                            $secD = (int)($d['secao'] ?? 0);
                            $linD = (int)($d['linha'] ?? 0);
                            $colD = (int)($d['coluna'] ?? 0);
                            $stmtIds = $conn->prepare("SELECT id FROM exame_variaveis WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ? ORDER BY id DESC");
                            if ($stmtIds) {
                                $stmtIds->bind_param('iiii', $exame_id, $secD, $linD, $colD);
                                $stmtIds->execute();
                                $resIds = $stmtIds->get_result();
                                $keep = 0;
                                $del = [];
                                while ($rI = $resIds->fetch_assoc()) {
                                    $idI = (int)($rI['id'] ?? 0);
                                    if ($idI <= 0) continue;
                                    if ($keep === 0) { $keep = $idI; } else { $del[] = $idI; }
                                }
                                $stmtIds->close();
                                foreach ($del as $idDel) {
                                    $stmtDel = $conn->prepare("DELETE FROM exame_variaveis WHERE id = ? LIMIT 1");
                                    if ($stmtDel) { $stmtDel->bind_param('i', $idDel); $stmtDel->execute(); $stmtDel->close(); }
                                }
                            }
                        }
                        $stmtDup->close();
                    }
                } catch (Throwable $eDedup) {}

                // Build map of order by section
                $ordByIdxBySecao = [];
                try {
                    $stmtOrd = $conn->prepare("SELECT secao, ordem FROM exame_layout_linhas WHERE exame_id = ? ORDER BY secao, ordem, id");
                    if ($stmtOrd) {
                        $stmtOrd->bind_param('i', $exame_id);
                        $stmtOrd->execute();
                        $resOrd = $stmtOrd->get_result();
                        $bySec = [];
                        while ($rO = $resOrd->fetch_assoc()) {
                            $s = (int)($rO['secao'] ?? 0);
                            $o = (int)($rO['ordem'] ?? 0);
                            if ($s > 0 && $o > 0) {
                                if (!isset($bySec[$s])) $bySec[$s] = [];
                                $bySec[$s][] = $o;
                            }
                        }
                        $stmtOrd->close();
                        foreach ($bySec as $secK => $ordsArr) {
                            sort($ordsArr, SORT_NUMERIC);
                            $ordByIdxBySecao[$secK] = $ordsArr;
                        }
                    }
                } catch (Throwable $eOrdIdxSave) {}

                // ------------------------------------------------------------------
                // REPLICATION OF exames.php LOGIC: Ensure variables exist for Result columns
                // ------------------------------------------------------------------
                try {
                    // 1. Map existing variables
                    $existPosTxt = [];
                    $stmtPos = $conn->prepare("SELECT secao, linha, coluna, tipo_resultado FROM exame_variaveis WHERE exame_id = ?");
                    if ($stmtPos) {
                        $stmtPos->bind_param('i', $exame_id);
                        $stmtPos->execute();
                        $resPos = $stmtPos->get_result();
                        while ($rr = $resPos->fetch_assoc()) {
                            $k = (string)$rr['secao'] . '|' . (int)$rr['linha'] . '|' . (int)$rr['coluna'];
                            $existPosTxt[$k] = strtolower((string)$rr['tipo_resultado']);
                        }
                        $stmtPos->close();
                    }

                    // 2. Map column types from config
                    $tipoCols = [];
                    if (is_array($cfgColsArr)) {
                        foreach ($cfgColsArr as $trow) {
                            $sec = is_numeric($trow['secao']) ? (int)$trow['secao'] : (string)$trow['secao'];
                            $col = (int)$trow['coluna'];
                            if (!isset($tipoCols[$sec])) $tipoCols[$sec] = [];
                            $tipoCols[$sec][$col] = (string)($trow['tipo'] ?? '');
                        }
                    }

                    // 3. Iterate layout rows to find missing variables
                    $ordemPorSecaoSync = []; 
                    foreach ($dados as $row) {
                        $secao = isset($row['secao']) ? (int)$row['secao'] : 1;
                        if ($secao <= 0) $secao = 1;
                        $ord = isset($ordemPorSecaoSync[$secao]) ? ((int)$ordemPorSecaoSync[$secao] + 1) : 1;
                        $ordemPorSecaoSync[$secao] = $ord;

                        // Magic Label Detection (Legacy/Operational Support)
                        $col1 = isset($row['col1']) ? strtolower(trim((string)$row['col1'])) : '';
                        if ($col1 === 'resultado:') {
                             // Default to column 2 if not configured
                             $kpos = (string)$secao . '|' . (int)$ord . '|2';
                             $exTipo = $existPosTxt[$kpos] ?? '';
                             // Check if column 2 is text or numeric based on config (or default to text if no config)
                             $t2 = $tipoCols[$secao][2] ?? '';
                             $isText = ($t2 === 'resultado_texto' || $t2 === 'resultado_texto_formatado');
                             
                             if ($isText) {
                                 $alreadyTxt = isset($existPosTxt[$kpos]) && in_array($existPosTxt[$kpos], ['resultado_texto', 'texto_curto', 'texto_longo'], true);
                                 if (!$alreadyTxt) {
                                     $stmtIns = $conn->prepare("INSERT INTO exame_variaveis (exame_id, secao, linha, coluna, descricao, tipo_resultado, casas_decimais, unidade, exibir_no_laudo) VALUES (?, ?, ?, 2, 'Resultado', 'resultado_texto', 0, '', 1)");
                                     if ($stmtIns) {
                                         $stmtIns->bind_param('isi', $exame_id, $secao, $ord);
                                         $stmtIns->execute();
                                         $stmtIns->close();
                                         $existPosTxt[$kpos] = 'resultado_texto';
                                     }
                                 }
                             } else {
                                 // Default numeric
                                 if ($exTipo !== 'nu' && $exTipo !== 'numerico' && $exTipo !== 'resultado_numerico' && $exTipo !== 'numero') {
                                     $stmtIns = $conn->prepare("INSERT INTO exame_variaveis (exame_id, secao, linha, coluna, descricao, tipo_resultado, casas_decimais, unidade, exibir_no_laudo) VALUES (?, ?, ?, 2, 'Resultado', 'nu', 2, '', 1)");
                                     if ($stmtIns) {
                                         $stmtIns->bind_param('isi', $exame_id, $secao, $ord);
                                         $stmtIns->execute();
                                         $stmtIns->close();
                                         $existPosTxt[$kpos] = 'nu';
                                     }
                                 }
                             }
                        }
                        if ($col1 === 'observação:' || $col1 === 'observacao:') {
                            $kpos = (string)$secao . '|' . (int)$ord . '|2';
                            $exTipo = $existPosTxt[$kpos] ?? '';
                            if ($exTipo !== 'ob' && $exTipo !== 'observacao') {
                                $stmtIns = $conn->prepare("INSERT INTO exame_variaveis (exame_id, secao, linha, coluna, descricao, tipo_resultado, casas_decimais, unidade, exibir_no_laudo) VALUES (?, ?, ?, 2, 'Observação', 'ob', 0, '', 1)");
                                if ($stmtIns) {
                                    $stmtIns->bind_param('isi', $exame_id, $secao, $ord);
                                    $stmtIns->execute();
                                    $stmtIns->close();
                                    $existPosTxt[$kpos] = 'ob';
                                }
                            }
                        }

                        // Check numeric/text columns defined in column config
                        $tiposSecCols = $tipoCols[$secao] ?? [];
                        if (!empty($tiposSecCols)) {
                            foreach ($tiposSecCols as $colIdx => $tipoCol) {
                                $tipoColLower = strtolower((string)$tipoCol);
                                
                                // Check for Numeric
                                if ($tipoColLower === 'resultado_num' || $tipoColLower === 'resultado_numerico') {
                                    $kpos = (string)$secao . '|' . (int)$ord . '|' . (int)$colIdx;
                                    $exTipo = $existPosTxt[$kpos] ?? '';
                                    if ($exTipo !== 'nu' && $exTipo !== 'numerico' && $exTipo !== 'resultado_numerico' && $exTipo !== 'numero') {
                                        $desc = isset($row['col1']) ? trim((string)$row['col1']) : '';
                                        if ($desc === '') $desc = 'Resultado';
                                        
                                        $stmtInsNum = $conn->prepare("INSERT INTO exame_variaveis (exame_id, secao, linha, coluna, descricao, tipo_resultado, casas_decimais, unidade, exibir_no_laudo) VALUES (?, ?, ?, ?, ?, 'nu', 2, '', 1)");
                                        if ($stmtInsNum) {
                                            $stmtInsNum->bind_param('isiis', $exame_id, $secao, $ord, $colIdx, $desc);
                                            $stmtInsNum->execute();
                                            $stmtInsNum->close();
                                            $existPosTxt[$kpos] = 'nu'; // mark as exist
                                        }
                                    }
                                }
                                
                                // Check for Text
                                if ($tipoColLower === 'resultado_texto' || $tipoColLower === 'resultado_texto_formatado') {
                                    $kpos = (string)$secao . '|' . (int)$ord . '|' . (int)$colIdx;
                                    $alreadyTxt = isset($existPosTxt[$kpos]) && in_array($existPosTxt[$kpos], ['resultado_texto', 'texto_curto', 'texto_longo'], true);
                                    if (!$alreadyTxt) {
                                        $desc = isset($row['col1']) ? trim((string)$row['col1']) : '';
                                        if ($desc === '') $desc = 'Resultado';
                                        
                                        $stmtInsTxt = $conn->prepare("INSERT INTO exame_variaveis (exame_id, secao, linha, coluna, descricao, tipo_resultado, casas_decimais, unidade, exibir_no_laudo) VALUES (?, ?, ?, ?, ?, 'resultado_texto', 0, '', 1)");
                                        if ($stmtInsTxt) {
                                            $stmtInsTxt->bind_param('isiis', $exame_id, $secao, $ord, $colIdx, $desc);
                                            $stmtInsTxt->execute();
                                            $stmtInsTxt->close();
                                            $existPosTxt[$kpos] = 'resultado_texto';
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (Throwable $eVarSync) {
                    error_log("Error syncing variables: " . $eVarSync->getMessage());
                }

                // Update variables based on cell config
                if (is_array($cellsArr)) {
                    foreach ($cellsArr as $v) {
                        $secaoStr = (string)($v['secao'] ?? '');
                        $linhaIdx = isset($v['linha']) ? (int)$v['linha'] : 0;
                        $colIdx = isset($v['coluna']) ? (int)$v['coluna'] : 0;
                        $numTipo = strtolower(trim((string)($v['numTipo'] ?? '')));
                        $numPos = isset($v['numPos']) ? (int)$v['numPos'] : null;
                        $numDec = isset($v['numDec']) ? (int)$v['numDec'] : null;
                        $numFormula = (string)($v['numFormula'] ?? '');
                        $varAlias = (string)($v['varAlias'] ?? '');
                        
                        $linhaDb = isset($v['linha_db']) ? (int)$v['linha_db'] : ($linhaIdx + 1);
                        $secaoInt = is_numeric($secaoStr) ? (int)$secaoStr : 0;
                        
                        if ($secaoInt > 0 && isset($ordByIdxBySecao[$secaoInt]) && isset($ordByIdxBySecao[$secaoInt][$linhaIdx])) {
                            $linhaDb = (int)$ordByIdxBySecao[$secaoInt][$linhaIdx];
                        }
                        
                        $varId = isset($v['varId']) ? (int)$v['varId'] : 0;

                        if ($varId <= 0) {
                            $stmtF = $conn->prepare("SELECT id FROM exame_variaveis WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ? ORDER BY id DESC LIMIT 1");
                            if ($stmtF) {
                                $stmtF->bind_param('iiii', $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmtF->execute();
                                $resF = $stmtF->get_result();
                                $rowF = $resF ? $resF->fetch_assoc() : null;
                                if ($rowF && isset($rowF['id'])) {
                                    $varId = (int)$rowF['id'];
                                }
                                $stmtF->close();
                            }
                        }

                        if ($varId > 0) {
                            // Align by description if needed
                            try {
                                $descRow = '';
                                $stmtLinha = $conn->prepare("SELECT id FROM exame_layout_linhas WHERE exame_id = ? AND secao = ? AND ordem = ? LIMIT 1");
                                if ($stmtLinha) {
                                    $stmtLinha->bind_param('isi', $exame_id, (string)$secaoInt, $linhaDb);
                                    $stmtLinha->execute();
                                    $resL = $stmtLinha->get_result();
                                    if ($resL && $resL->num_rows > 0) {
                                        $lid = (int)$resL->fetch_assoc()['id'];
                                        $stmtC1 = $conn->prepare("SELECT valor_fixo FROM exame_layout_colunas WHERE linha_id = ? AND ordem = 1 LIMIT 1");
                                        if ($stmtC1) {
                                            $stmtC1->bind_param('i', $lid);
                                            $stmtC1->execute();
                                            $resC1 = $stmtC1->get_result();
                                            if ($resC1 && $resC1->num_rows > 0) {
                                                $descRow = trim((string)$resC1->fetch_assoc()['valor_fixo'] ?? '');
                                            }
                                            $stmtC1->close();
                                        }
                                    }
                                    $stmtLinha->close();
                                }
                                if ($descRow !== '') {
                                    $stmtChk = $conn->prepare("SELECT descricao FROM exame_variaveis WHERE id = ? AND exame_id = ? LIMIT 1");
                                    if ($stmtChk) {
                                        $stmtChk->bind_param('ii', $varId, $exame_id);
                                        $stmtChk->execute();
                                        $resChk = $stmtChk->get_result();
                                        $rowChk = $resChk ? $resChk->fetch_assoc() : null;
                                        $stmtChk->close();
                                        $curDesc = trim((string)($rowChk['descricao'] ?? ''));
                                        if (mb_strtolower($curDesc) !== mb_strtolower($descRow)) {
                                            $stmtFind = $conn->prepare("SELECT id FROM exame_variaveis WHERE exame_id = ? AND secao = ? AND coluna = ? AND LOWER(descricao) = LOWER(?) ORDER BY id DESC LIMIT 1");
                                            if ($stmtFind) {
                                                $stmtFind->bind_param('isis', $exame_id, (string)$secaoInt, $colIdx, $descRow);
                                                $stmtFind->execute();
                                                $resFind = $stmtFind->get_result();
                                                $rowFind = $resFind ? $resFind->fetch_assoc() : null;
                                                if ($rowFind && isset($rowFind['id'])) {
                                                    $varId = (int)$rowFind['id'];
                                                }
                                                $stmtFind->close();
                                            }
                                        }
                                    }
                                }
                            } catch (Throwable $eAlign) {}

                            $stmtB = $conn->prepare("SELECT num_tipo, num_pos, num_dec, num_formula, var_alias FROM exame_variaveis WHERE id = ? AND exame_id = ?");
                            $rowB = null;
                            if ($stmtB) {
                                $stmtB->bind_param('ii', $varId, $exame_id);
                                $stmtB->execute();
                                $resB = $stmtB->get_result();
                                $rowB = $resB ? $resB->fetch_assoc() : null;
                                $stmtB->close();
                            }

                            $tipoSet = ($numTipo !== '') ? $numTipo : (string)($rowB['num_tipo'] ?? '');
                            $np = ($numPos === null) ? (int)($rowB['num_pos'] ?? 0) : $numPos;
                            $nd = ($numDec === null) ? (int)($rowB['num_dec'] ?? 0) : $numDec;
                            $formulaSet = ($numFormula !== '') ? $numFormula : (string)($rowB['num_formula'] ?? '');
                            $aliasSet = ($varAlias !== '') ? $varAlias : (string)($rowB['var_alias'] ?? '');

                            $stmt = $conn->prepare("UPDATE exame_variaveis SET num_tipo = ?, num_pos = ?, num_dec = ?, num_formula = ?, var_alias = ? WHERE id = ? AND exame_id = ?");
                            if ($stmt) {
                                $stmt->bind_param('siissii', $tipoSet, $np, $nd, $formulaSet, $aliasSet, $varId, $exame_id);
                                $stmt->execute();
                                if ($stmt->affected_rows === 0) {
                                    $stmt2 = $conn->prepare("UPDATE exame_variaveis SET num_tipo = ?, num_pos = ?, num_dec = ?, num_formula = ?, var_alias = ? WHERE id = ? LIMIT 1");
                                    if ($stmt2) {
                                        $stmt2->bind_param('siissi', $tipoSet, $np, $nd, $formulaSet, $aliasSet, $varId);
                                        $stmt2->execute();
                                        $stmt2->close();
                                    }
                                }
                                $stmt->close();
                            }
                            continue;
                        }

                        // If varId not found, try to update based on position
                        if ($numTipo !== '') {
                            $stmt = $conn->prepare("UPDATE exame_variaveis SET num_tipo = ? WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ?");
                            if ($stmt) {
                                $stmt->bind_param('siiii', $numTipo, $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                        if ($numPos !== null) {
                            $stmt = $conn->prepare("UPDATE exame_variaveis SET num_pos = ? WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ?");
                            if ($stmt) {
                                $stmt->bind_param('iiiii', $numPos, $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                        if ($numDec !== null) {
                            $stmt = $conn->prepare("UPDATE exame_variaveis SET num_dec = ? WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ?");
                            if ($stmt) {
                                $stmt->bind_param('iiiii', $numDec, $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                        if ($numTipo === 'calculo') {
                            $stmt = $conn->prepare("UPDATE exame_variaveis SET num_formula = ? WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ?");
                            if ($stmt) {
                                $stmt->bind_param('siiii', $numFormula, $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmt->execute();
                                $stmt->close();
                            }
                        } elseif ($numTipo !== '') {
                            $stmt = $conn->prepare("UPDATE exame_variaveis SET num_formula = '' WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ?");
                            if ($stmt) {
                                $stmt->bind_param('iiii', $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                        if ($varAlias !== '') {
                            $stmt = $conn->prepare("UPDATE exame_variaveis SET var_alias = ? WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ?");
                            if ($stmt) {
                                $stmt->bind_param('siiii', $varAlias, $exame_id, $secaoInt, $linhaDb, $colIdx);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    }
                }
            } catch (Throwable $eCfgDb) {
                // Ignore errors here to ensure commit happens
            }

            $conn->commit();

            if (ob_get_length()) ob_clean();
            @header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => true]);

        } catch (Throwable $e) {
            $conn->rollback();
            if (ob_get_length()) ob_clean();
            @header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        exit;
    }

    private function saveApoio() {
        $conn = $this->db;
        $exame_id = (int)($_POST['exame_id'] ?? 0);
        $apoio_id = (int)($_POST['apoio_id'] ?? 0);
        $laboratorio_id = (int)($_POST['laboratorio_id'] ?? 0);
        $mnemonico = trim($_POST['mnemonico_apoio'] ?? '');
        $custo = $this->br_para_float($_POST['custo_apoio'] ?? '0');
        $prazo_log = (int)($_POST['prazo_logistica_apoio'] ?? 0);
        $prazo_exec = (int)($_POST['prazo_execucao_apoio'] ?? 0);

        if ($exame_id <= 0 || $laboratorio_id <= 0 || $mnemonico === '') {
            $_SESSION['flash_error'] = 'Dados inválidos para Apoio.';
            header("Location: index.php?r=exames/edit&id=$exame_id");
            exit;
        }

        if ($apoio_id > 0) {
            $stmt = $conn->prepare("UPDATE exame_laboratorio_apoio SET laboratorio_id=?, mnemonico_apoio=?, custo_apoio=?, prazo_logistica=?, prazo_execucao=? WHERE id=? AND exame_id=?");
            $stmt->bind_param("isdiiii", $laboratorio_id, $mnemonico, $custo, $prazo_log, $prazo_exec, $apoio_id, $exame_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO exame_laboratorio_apoio (exame_id, laboratorio_id, mnemonico_apoio, custo_apoio, prazo_logistica, prazo_execucao) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdii", $exame_id, $laboratorio_id, $mnemonico, $custo, $prazo_log, $prazo_exec);
        }
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['flash_success'] = 'Apoio salvo com sucesso.';
        header("Location: index.php?r=exames/edit&id=$exame_id");
    }

    private function savePreco() {
        $conn = $this->db;
        $exame_id = (int)($_POST['exame_id'] ?? 0);
        $preco_id = (int)($_POST['preco_id'] ?? 0);
        $convenio_id = (int)($_POST['convenio_id'] ?? 0);
        $valor = $this->br_para_float($_POST['valor_convenio'] ?? '0');
        $ativo = isset($_POST['ativo_preco']) ? 1 : 0;

        if ($exame_id <= 0 || $convenio_id <= 0) {
            $_SESSION['flash_error'] = 'Dados inválidos para Preço.';
            header("Location: index.php?r=exames/edit&id=$exame_id");
            exit;
        }

        if ($preco_id > 0) {
            $stmt = $conn->prepare("UPDATE exame_convenio_precos SET convenio_id=?, valor=?, ativo=? WHERE id=? AND exame_id=?");
            $stmt->bind_param("idiii", $convenio_id, $valor, $ativo, $preco_id, $exame_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO exame_convenio_precos (exame_id, convenio_id, valor, ativo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iidi", $exame_id, $convenio_id, $valor, $ativo);
        }
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['flash_success'] = 'Preço salvo com sucesso.';
        header("Location: index.php?r=exames/edit&id=$exame_id");
    }

    public function delete_apoio() {
        $conn = $this->db;
        verificaLogin();
        $id = (int)($_GET['id'] ?? 0); // ID of the relation
        $exame_id = (int)($_GET['exame_id'] ?? 0);
        
        if ($id > 0 && $exame_id > 0) {
            $conn->query("DELETE FROM exame_laboratorio_apoio WHERE id=$id AND exame_id=$exame_id");
            $_SESSION['flash_success'] = 'Apoio removido.';
        }
        header("Location: index.php?r=exames/edit&id=$exame_id");
    }

    public function delete_preco() {
        $conn = $this->db;
        verificaLogin();
        $id = (int)($_GET['id'] ?? 0);
        $exame_id = (int)($_GET['exame_id'] ?? 0);

        if ($id > 0 && $exame_id > 0) {
            $conn->query("DELETE FROM exame_convenio_precos WHERE id=$id AND exame_id=$exame_id");
            $_SESSION['flash_success'] = 'Preço removido.';
        }
        header("Location: index.php?r=exames/edit&id=$exame_id");
    }

    public function quick_store() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $type = $_POST['tipo'] ?? ($_POST['form_origem'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $resp = ['sucesso' => false];

        try {
            if ($type === 'setor') {
                if ($nome) {
                    $stmt = $conn->prepare("INSERT INTO setores (setor_nome) VALUES (?)");
                    $stmt->bind_param('s', $nome);
                    $stmt->execute();
                    $resp = ['sucesso' => true, 'id' => $stmt->insert_id, 'nome' => $nome, 'tipo' => 'setor'];
                    $stmt->close();
                }
            } elseif ($type === 'material') {
                if ($nome) {
                    $stmt = $conn->prepare("INSERT INTO materiais_biologicos (material_nome) VALUES (?)");
                    $stmt->bind_param('s', $nome);
                    $stmt->execute();
                    $resp = ['sucesso' => true, 'id' => $stmt->insert_id, 'nome' => $nome, 'tipo' => 'material'];
                    $stmt->close();
                }
            } elseif ($type === 'recipiente') {
                if ($nome) {
                    $stmt = $conn->prepare("INSERT INTO recipientes_coleta (recipiente_nome) VALUES (?)");
                    $stmt->bind_param('s', $nome);
                    $stmt->execute();
                    $resp = ['sucesso' => true, 'id' => $stmt->insert_id, 'nome' => $nome, 'tipo' => 'recipiente'];
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {
            $resp['mensagem'] = $e->getMessage();
        }
        
        echo json_encode($resp);
    }

    public function check_mnemonico() {
        $conn = $this->db;
        header('Content-Type: application/json');
        $m = trim($_GET['m'] ?? '');
        $exists = false;
        $id = 0;
        if ($m !== '') {
            $stmt = $conn->prepare("SELECT id FROM exames WHERE UPPER(mnemonico_local) = UPPER(?) LIMIT 1");
            $stmt->bind_param('s', $m);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $exists = true;
                $id = $row['id'];
            }
            $stmt->close();
        }
        echo json_encode(['exists' => $exists, 'id' => $id]);
    }

    private function get_exame_variavel_id($exame_id, $secao, $linha, $coluna, $create = false) {
        $conn = $this->db;
        $secao = (string)$secao;
        $linha = (int)$linha;
        $coluna = (int)$coluna;
        
        $stmt = $conn->prepare("SELECT id FROM exame_variaveis WHERE exame_id = ? AND secao = ? AND linha = ? AND coluna = ? LIMIT 1");
        $stmt->bind_param('isii', $exame_id, $secao, $linha, $coluna);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            return (int)$row['id'];
        }
        
        if ($create) {
            // Create default variable
            $stmtIns = $conn->prepare("INSERT INTO exame_variaveis (exame_id, secao, linha, coluna, descricao, tipo_resultado, exibir_no_laudo) VALUES (?, ?, ?, ?, 'Resultado', 'TX', 1)");
            if ($stmtIns) {
                $stmtIns->bind_param('isii', $exame_id, $secao, $linha, $coluna);
                $stmtIns->execute();
                $newId = $stmtIns->insert_id;
                $stmtIns->close();
                return $newId;
            }
        }
        
        return 0;
    }

    public function get_textos_padrao() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $exame_id = (int)($_GET['exame_id'] ?? 0);
        $secao = $_GET['secao'] ?? null;
        $linha = $_GET['linha'] ?? null;
        $coluna = $_GET['coluna'] ?? null;
        
        $dados = [];
        
        if ($exame_id > 0 && $secao !== null && $linha !== null && $coluna !== null) {
            // Get variable ID for this cell
            $varId = $this->get_exame_variavel_id($exame_id, $secao, $linha, $coluna, false);
            
            if ($varId > 0) {
                // Fetch specific options from exame_variaveis_opcoes
                $stmt = $conn->prepare("SELECT id, codigo, texto, padrao as is_padrao FROM exame_variaveis_opcoes WHERE exame_variavel_id = ? ORDER BY ordem, id");
                if ($stmt) {
                    $stmt->bind_param('i', $varId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $dados[] = $row;
                    }
                    $stmt->close();
                }
            }
        }
        
        echo json_encode(['sucesso' => true, 'dados' => $dados]);
    }

    public function save_texto_padrao() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $id = $_POST['id'] ?? '';
        $exame_id = (int)($_POST['exame_id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $is_padrao = isset($_POST['is_padrao']) ? (int)$_POST['is_padrao'] : 0;
        
        $secao = $_POST['secao'] ?? null;
        $linha = $_POST['linha'] ?? null;
        $coluna = $_POST['coluna'] ?? null;

        if ($exame_id <= 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'ID do exame inválido']);
            exit;
        }

        // Must have coordinates to link to a variable
        if ($secao === null || $linha === null || $coluna === null) {
             echo json_encode(['sucesso' => false, 'mensagem' => 'Coordenadas da célula obrigatórias']);
             exit;
        }

        // Get or create variable ID
        $exame_variavel_id = $this->get_exame_variavel_id($exame_id, $secao, $linha, $coluna, true);
        
        try {
            if (is_numeric($id) && $id > 0) {
                // Update existing option in exame_variaveis_opcoes
                $stmt = $conn->prepare("UPDATE exame_variaveis_opcoes SET texto = ?, codigo = ?, padrao = ? WHERE id = ? AND exame_variavel_id = ?");
                if ($stmt) {
                    $stmt->bind_param('ssiii', $texto, $codigo, $is_padrao, $id, $exame_variavel_id);
                    if (!$stmt->execute()) {
                        throw new \Exception("Erro ao executar update: " . $stmt->error);
                    }
                    $stmt->close();
                    echo json_encode(['sucesso' => true]);
                } else {
                    throw new \Exception("Erro ao preparar update: " . $conn->error);
                }
            } else {
                // Insert new option into exame_variaveis_opcoes
                $stmt = $conn->prepare("INSERT INTO exame_variaveis_opcoes (exame_variavel_id, texto, codigo, padrao, ordem) VALUES (?, ?, ?, ?, 0)");
                if ($stmt) {
                    $stmt->bind_param('issi', $exame_variavel_id, $texto, $codigo, $is_padrao);
                    if (!$stmt->execute()) {
                         throw new \Exception("Erro ao executar insert: " . $stmt->error);
                    }
                    $newId = $stmt->insert_id;
                    $stmt->close();
                    
                    if ($newId > 0) {
                        echo json_encode(['sucesso' => true, 'id' => $newId]);
                    } else {
                        throw new \Exception("ID gerado inválido (0)");
                    }
                } else {
                    throw new \Exception("Erro ao preparar insert: " . $conn->error);
                }
            }
        } catch (Throwable $e) {
            file_put_contents('debug_rt_error.log', date('Y-m-d H:i:s') . " - Erro: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
        }
    }

    public function delete_texto_padrao() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            // Delete from exame_variaveis_opcoes
            $stmt = $conn->prepare("DELETE FROM exame_variaveis_opcoes WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(['sucesso' => true]);
            } else {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro DB']);
            }
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
        }
    }

    public function get_all_variaveis_opcoes() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $exame_id = (int)($_GET['exame_id'] ?? 0);
        $dados = [];
        
        if ($exame_id > 0) {
            $sql = "SELECT v.secao, v.linha, v.coluna, o.id, o.texto, o.padrao 
                    FROM exame_variaveis v
                    JOIN exame_variaveis_opcoes o ON v.id = o.exame_variavel_id
                    WHERE v.exame_id = ?
                    ORDER BY v.secao, v.linha, v.coluna, o.ordem, o.id";
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $exame_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $key = $row['secao'] . '_' . $row['linha'] . '_' . $row['coluna'];
                    if (!isset($dados[$key])) {
                        $dados[$key] = [];
                    }
                    $dados[$key][] = [
                        'id' => $row['id'],
                        'texto' => $row['texto'],
                        'padrao' => $row['padrao']
                    ];
                }
                $stmt->close();
            }
        }
        
        echo json_encode(['sucesso' => true, 'dados' => $dados]);
    }
}
