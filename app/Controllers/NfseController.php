<?php

namespace App\Controllers;

require_once __DIR__ . '/../Core/Controller.php';

$libPath = __DIR__ . '/../Libraries/Nfse/SefinNacionalClient.php';
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($libPath, true);
}
require_once $libPath;

use App\Core\Controller;
use App\Libraries\Nfse\SefinNacionalClient;
use Exception;

class NfseController extends Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
    }

    private function getNfseConfig()
    {
        $config = [];

        // Valores padrão
        $defaults = [
            'ambiente' => 'producao',
            'serie_rps' => '1',
            'tipo_rps' => '1',
            'ultimo_rps' => 0,
            'cnpj_prestador' => '',
            'codigo_municipio_prestador' => '',
            'cert_arquivo' => '',
            'cert_senha' => ''
        ];

        // Busca do Banco
        $sql = "SELECT * FROM nfse_config WHERE id = 1";
        $res = $this->db->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            $config = array_merge($defaults, $row);
        } else {
            $config = $defaults;
        }

        return $config;
    }

    private function _getSefinClient()
    {
        $nfseConfig = $this->getNfseConfig();
        $certArquivo = $nfseConfig['cert_arquivo'];
        $possiblePaths = [
            $certArquivo,
            __DIR__ . '/../../' . $certArquivo,
            $_SERVER['DOCUMENT_ROOT'] . '/' . $certArquivo,
            $_SERVER['DOCUMENT_ROOT'] . '/bmlab/' . $certArquivo
        ];
        $certPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $certPath = $path;
                break;
            }
        }

        if (!$certPath) {
            // Tenta usar o caminho configurado direto se não achou nos relativos
            if (file_exists($certArquivo)) {
                 $certPath = $certArquivo;
            } else {
                 throw new Exception("Certificado não encontrado: " . $certArquivo);
            }
        }

        $client = new SefinNacionalClient($certPath, $nfseConfig['cert_senha'], 'producao');

        if (!empty($nfseConfig['p_url_service'])) {
            $client->setCustomUrl($nfseConfig['p_url_service']);
        }
        
        // Configurar Auth Basic se disponível (Apenas Produção)
        $usuario = $nfseConfig['p_usuario'] ?? '';
        $senha = $nfseConfig['p_senha'] ?? '';

        if ($usuario && $senha) {
            $client->setAuth($usuario, $senha);
        }

        return $client;
    }

    public function notas_expedidas()
    {
        // Filtros (Pedidos)
        $data_inicio = !empty($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
        $data_fim = !empty($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');

        // Filtros (Emitidas) - Default: 30 days window (Last month to today)
        $data_inicio_emitidas = !empty($_GET['data_inicio_emitidas']) ? $_GET['data_inicio_emitidas'] : date('Y-m-d', strtotime('-30 days'));
        $data_fim_emitidas = !empty($_GET['data_fim_emitidas']) ? $_GET['data_fim_emitidas'] : date('Y-m-d');

        $active_tab = $_GET['active_tab'] ?? 'pedidos';

        // Paginação
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        if ($page < 1) $page = 1;

        // Define a página para cada aba
        $pagePedidos = ($active_tab === 'pedidos') ? $page : 1;
        $pageEmitidas = ($active_tab === 'emitidas') ? $page : 1;

        $offsetPedidos = ($pagePedidos - 1) * $limit;
        $offsetEmitidas = ($pageEmitidas - 1) * $limit;

        // Debug Filtros
        file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt', "Filtro Notas Expedidas: Inicio=$data_inicio, Fim=$data_fim, InicioEmit=$data_inicio_emitidas, FimEmit=$data_fim_emitidas, Tab=$active_tab, Page=$page\n", FILE_APPEND);

        // Query Pedidos (Legado/Local)
        $hasNacionalDocs = false;
        $checkTableLocal = $this->db->query("SHOW TABLES LIKE 'nfse_nacional_docs'");
        if ($checkTableLocal && $checkTableLocal->num_rows > 0) {
            $hasNacionalDocs = true;
        }

        $totalPedidos = 0;
        $notas = [];

        if ($hasNacionalDocs) {
            $nfseConfig = $this->getNfseConfig();
            $cnpjSistema = preg_replace('/[^0-9]/', '', $nfseConfig['cnpj_prestador'] ?? '');

            // Base conditions
            $whereConditions = "WHERE (
                        (p.nfse_status IS NOT NULL AND p.nfse_status != '') 
                        OR 
                        (p.nfse_numero IS NOT NULL AND p.nfse_numero != '')
                      )
                      AND (
                        (p.nfse_data_emissao IS NOT NULL AND DATE(p.nfse_data_emissao) BETWEEN ? AND ?)
                        OR 
                        (p.nfse_data_emissao IS NULL AND DATE(p.entrada) BETWEEN ? AND ?)
                      )";

            // Count Query
            $sqlCount = "SELECT COUNT(*) as total FROM pedidos p $whereConditions";
            
            $stmtCount = $this->db->prepare($sqlCount);
            if ($stmtCount) {
                $stmtCount->bind_param('ssss', $data_inicio, $data_fim, $data_inicio, $data_fim);
                $stmtCount->execute();
                $resCount = $stmtCount->get_result();
                $totalPedidos = $resCount->fetch_assoc()['total'];
                $stmtCount->close();
            }

            // Data Query
            $sql = "SELECT p.id, p.codigo_pedido, p.entrada AS data_pedido, p.total_liquido, p.nfse_numero, p.nfse_data_emissao,
                           COALESCE(
                               (SELECT nd.status 
                                FROM nfse_nacional_docs nd 
                                WHERE nd.numero_dps = p.nfse_numero
                                  AND nd.tipo_documento = 'NFSE' 
                                  AND nd.tipo_fluxo = 'PRESTADO'
                                  AND nd.prestador_doc = ?
                                  AND (
                                      nd.tomador_doc = REPLACE(REPLACE(REPLACE(pac.cpf, '.', ''), '-', ''), '/', '')
                                      OR pac.cpf IS NULL OR pac.cpf = ''
                                  )
                                ORDER BY nd.id DESC LIMIT 1
                               ), 
                               p.nfse_status
                           ) AS nfse_status,
                           (SELECT nd.chave_acesso 
                                FROM nfse_nacional_docs nd 
                                WHERE nd.numero_dps = p.nfse_numero
                                  AND nd.tipo_documento = 'NFSE' 
                                  AND nd.tipo_fluxo = 'PRESTADO'
                                  AND nd.prestador_doc = ?
                                  AND (
                                      nd.tomador_doc = REPLACE(REPLACE(REPLACE(pac.cpf, '.', ''), '-', ''), '/', '')
                                      OR pac.cpf IS NULL OR pac.cpf = ''
                                  )
                                ORDER BY nd.id DESC LIMIT 1
                           ) AS nfse_chave_acesso,
                           (SELECT nd.data_emissao 
                                FROM nfse_nacional_docs nd 
                                WHERE nd.numero_dps = p.nfse_numero
                                  AND nd.tipo_documento = 'NFSE' 
                                  AND nd.tipo_fluxo = 'PRESTADO'
                                  AND nd.prestador_doc = ?
                                  AND (
                                      nd.tomador_doc = REPLACE(REPLACE(REPLACE(pac.cpf, '.', ''), '-', ''), '/', '')
                                      OR pac.cpf IS NULL OR pac.cpf = ''
                                  )
                                ORDER BY nd.id DESC LIMIT 1
                           ) AS nfse_data_emissao_oficial,
                           (SELECT nd.status 
                                FROM nfse_nacional_docs nd 
                                WHERE nd.numero_dps = p.nfse_numero
                                  AND nd.tipo_documento = 'NFSE' 
                                  AND nd.tipo_fluxo = 'PRESTADO'
                                  AND nd.prestador_doc = ?
                                  AND (
                                      nd.tomador_doc = REPLACE(REPLACE(REPLACE(pac.cpf, '.', ''), '-', ''), '/', '')
                                      OR pac.cpf IS NULL OR pac.cpf = ''
                                  )
                                ORDER BY nd.id DESC LIMIT 1
                           ) AS nfse_status_oficial,
                           p.nfse_xml_retorno,
                           pac.nome AS paciente_nome, pac.nf_responsavel_nome, pac.cpf AS paciente_cpf
                    FROM pedidos p
                    LEFT JOIN pacientes pac ON p.paciente_id = pac.id
                    $whereConditions
                    GROUP BY p.id
                    ORDER BY COALESCE(p.nfse_data_emissao, p.entrada) DESC, p.id DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssssssssii', $cnpjSistema, $cnpjSistema, $cnpjSistema, $cnpjSistema, $data_inicio, $data_fim, $data_inicio, $data_fim, $limit, $offsetPedidos);
                $stmt->execute();
                $res = $stmt->get_result();
                
                while ($row = $res->fetch_assoc()) {
                    if (!empty($row['nf_responsavel_nome'])) {
                        $row['paciente_nome'] = $row['nf_responsavel_nome'];
                    }
                    
                    // Prioritize Official Emission Date from National Docs if available
                    if (!empty($row['nfse_data_emissao_oficial'])) {
                        $row['nfse_data_emissao'] = $row['nfse_data_emissao_oficial'];
                    }
                    
                    // Prioritize Official Status from National Docs if available
                    if (!empty($row['nfse_status_oficial'])) {
                        $row['nfse_status'] = $row['nfse_status_oficial'];
                    }

                    $row['protocolo'] = null;
                    // Fallback para Chave de Acesso se não vier do JOIN (ou se for NULL)
                    if (empty($row['nfse_chave_acesso']) && !empty($row['nfse_xml_retorno'])) {
                        if (preg_match('/<chaveAcesso>([0-9]{50})<\/chaveAcesso>/', $row['nfse_xml_retorno'], $m)) {
                            $row['nfse_chave_acesso'] = $m[1];
                        } elseif (preg_match('/Id="NFS([0-9]{50})"/', $row['nfse_xml_retorno'], $m)) {
                             $row['nfse_chave_acesso'] = $m[1];
                        }
                    }
                    
                    if ($row['nfse_xml_retorno']) {
                        if (preg_match('/\[[Pp]rotocolo\]\s*=>\s*(\d+)/', $row['nfse_xml_retorno'], $m)) {
                            $row['protocolo'] = $m[1];
                        }
                    }
                    $notas[] = $row;
                }
                $stmt->close();
            }

        } else {
            // Base conditions
            $whereConditions = "WHERE (
                        (p.nfse_status IS NOT NULL AND p.nfse_status != '') 
                        OR 
                        (p.nfse_numero IS NOT NULL AND p.nfse_numero != '')
                      )
                      AND (
                        (p.nfse_data_emissao IS NOT NULL AND DATE(p.nfse_data_emissao) BETWEEN ? AND ?)
                        OR 
                        (p.nfse_data_emissao IS NULL AND DATE(p.entrada) BETWEEN ? AND ?)
                      )";

            // Count Query
            $sqlCount = "SELECT COUNT(*) as total FROM pedidos p $whereConditions";
            $stmtCount = $this->db->prepare($sqlCount);
            if ($stmtCount) {
                $stmtCount->bind_param('ssss', $data_inicio, $data_fim, $data_inicio, $data_fim);
                $stmtCount->execute();
                $resCount = $stmtCount->get_result();
                $totalPedidos = $resCount->fetch_assoc()['total'];
                $stmtCount->close();
            }

            // Data Query
            $sql = "SELECT p.id, p.codigo_pedido, p.entrada AS data_pedido, p.total_liquido, p.nfse_numero, p.nfse_data_emissao, p.nfse_status, p.nfse_xml_retorno,
                           pac.nome AS paciente_nome, pac.nf_responsavel_nome
                    FROM pedidos p
                    LEFT JOIN pacientes pac ON p.paciente_id = pac.id
                    $whereConditions
                    ORDER BY COALESCE(p.nfse_data_emissao, p.entrada) DESC, p.id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssssii', $data_inicio, $data_fim, $data_inicio, $data_fim, $limit, $offsetPedidos);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    if (!empty($row['nf_responsavel_nome'])) {
                        $row['paciente_nome'] = $row['nf_responsavel_nome'];
                    }
                    $row['protocolo'] = null;
                    
                    // Fallback para Chave de Acesso (sem tabela nacional)
                    $row['nfse_chave_acesso'] = null;
                    if (!empty($row['nfse_xml_retorno'])) {
                        if (preg_match('/<chaveAcesso>([0-9]{50})<\/chaveAcesso>/', $row['nfse_xml_retorno'], $m)) {
                            $row['nfse_chave_acesso'] = $m[1];
                        } elseif (preg_match('/Id="NFS([0-9]{50})"/', $row['nfse_xml_retorno'], $m)) {
                             $row['nfse_chave_acesso'] = $m[1];
                        }
                    }

                    if ($row['nfse_xml_retorno']) {
                        if (preg_match('/\[[Pp]rotocolo\]\s*=>\s*(\d+)/', $row['nfse_xml_retorno'], $m)) {
                            $row['protocolo'] = $m[1];
                        }
                    }
                    $notas[] = $row;
                }
                $stmt->close();
            }
        }

        // Query Nacional (Importadas)
        $notasNacionalEmitidas = [];
        $totalEmitidas = 0;

        if ($hasNacionalDocs) {
            // Count Emitidas (Unique NFSe)
            $sqlCountEmit = "SELECT COUNT(DISTINCT numero_nfse) as total FROM nfse_nacional_docs WHERE tipo_fluxo = 'PRESTADO' AND tipo_documento = 'NFSE' AND (DATE(data_emissao) BETWEEN ? AND ?)";
            $stmtCountEmit = $this->db->prepare($sqlCountEmit);
            if ($stmtCountEmit) {
                $stmtCountEmit->bind_param('ss', $data_inicio_emitidas, $data_fim_emitidas);
                $stmtCountEmit->execute();
                $resCountEmit = $stmtCountEmit->get_result();
                $totalEmitidas = $resCountEmit->fetch_assoc()['total'];
                $stmtCountEmit->close();
            }

            // Data Emitidas (Latest Version per NFSe)
            $sqlEmit = "SELECT t1.* 
                        FROM nfse_nacional_docs t1
                        INNER JOIN (
                            SELECT MAX(id) as max_id
                            FROM nfse_nacional_docs
                            WHERE tipo_fluxo = 'PRESTADO' AND tipo_documento = 'NFSE'
                            GROUP BY numero_nfse
                        ) t2 ON t1.id = t2.max_id
                        WHERE (DATE(t1.data_emissao) BETWEEN ? AND ?)
                        ORDER BY t1.data_emissao DESC 
                        LIMIT ? OFFSET ?";
            $stmtEmit = $this->db->prepare($sqlEmit);
            if ($stmtEmit) {
                $stmtEmit->bind_param('ssii', $data_inicio_emitidas, $data_fim_emitidas, $limit, $offsetEmitidas);
                $stmtEmit->execute();
                $resEmit = $stmtEmit->get_result();
                if ($resEmit) while ($row = $resEmit->fetch_assoc()) $notasNacionalEmitidas[] = $row;
                $stmtEmit->close();
            }
        }

        // Calculate Total Pages
        $totalPagesPedidos = ceil($totalPedidos / $limit);
        $totalPagesEmitidas = ceil($totalEmitidas / $limit);

        $this->view('nfse/notas_expedidas', [
            'notas' => $notas,
            'notasNacionalEmitidas' => $notasNacionalEmitidas,
            'notasNacionalRecebidas' => [],
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'data_inicio_emitidas' => $data_inicio_emitidas,
            'data_fim_emitidas' => $data_fim_emitidas,
            'active_tab' => $active_tab,
            'titulo_pagina' => 'Notas Fiscais Expedidas',
            'route' => 'nfse/notas_expedidas',
            // Pagination
            'limit' => $limit,
            'pagePedidos' => $pagePedidos,
            'totalPedidos' => $totalPedidos,
            'totalPagesPedidos' => $totalPagesPedidos,
            'pageEmitidas' => $pageEmitidas,
            'totalEmitidas' => $totalEmitidas,
            'totalPagesEmitidas' => $totalPagesEmitidas
        ]);
    }

    public function painel()
    {
        // Verifica permissão (Admin ou Config)
        if (!$this->temPermissao('configuracoes.menu') && (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin')) {
            die("Acesso negado.");
        }

        $nfseConfig = $this->getNfseConfig();

        $msg = $_GET['msg'] ?? '';
        $mensagem = ($msg === 'success') ? 'Configurações salvas com sucesso!' : '';
        $erro = ($msg === 'error') ? 'Erro ao salvar configurações.' : '';

        $this->view('nfse/painel', [
            'dados' => $nfseConfig,
            'mensagem' => $mensagem,
            'erro' => $erro,
            'titulo_pagina' => 'Painel NFSe Nacional',
            'route' => 'nfse/painel'
        ]);
    }

    public function salvar_config()
    {
        // Verifica permissão
        if (!$this->temPermissao('configuracoes.menu') && (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin')) {
            die("Acesso negado.");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ambiente = $_POST['ambiente'];
            $cnpj = $_POST['cnpj_prestador'];
            $codMun = $_POST['codigo_municipio_prestador'];
            $ultimoRps = (int)$_POST['ultimo_rps'];

            $cert_arquivo = $_POST['cert_arquivo'] ?? '';
            $cert_senha = $_POST['cert_senha'] ?? '';

            $h_url_service = $_POST['h_url_service'] ?? '';
            $p_url_service = $_POST['p_url_service'] ?? '';

            // Verifica se existe registro 1
            $check = $this->db->query("SELECT id FROM nfse_config WHERE id = 1");
            if ($check->num_rows > 0) {
                $sql = "UPDATE nfse_config SET 
                        ambiente=?, cnpj_prestador=?, codigo_municipio_prestador=?, ultimo_rps=?,
                        cert_arquivo=?, cert_senha=?,
                        h_url_service=?, p_url_service=?
                        WHERE id=1";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param(
                    'sssissss',
                    $ambiente,
                    $cnpj,
                    $codMun,
                    $ultimoRps,
                    $cert_arquivo,
                    $cert_senha,
                    $h_url_service,
                    $p_url_service
                );
            } else {
                $sql = "INSERT INTO nfse_config 
                        (id, ambiente, cnpj_prestador, codigo_municipio_prestador, ultimo_rps,
                        cert_arquivo, cert_senha, h_url_service, p_url_service) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param(
                    'sssissss',
                    $ambiente,
                    $cnpj,
                    $codMun,
                    $ultimoRps,
                    $cert_arquivo,
                    $cert_senha,
                    $h_url_service,
                    $p_url_service
                );
            }

            if ($stmt->execute()) {
                // Redireciona com sucesso
                header("Location: index.php?r=nfse/painel&msg=success");
            } else {
                header("Location: index.php?r=nfse/painel&msg=error");
            }
            exit;
        }
    }

    public function importar()
    {
        set_time_limit(0); // Evitar timeout em sync longo

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método inválido']);
            return;
        }

        // Limpa buffer de saída para garantir JSON puro
        if (ob_get_length()) ob_clean();

        try {
            $nfseConfig = $this->getNfseConfig();
            $client = $this->_getSefinClient();

            // Pega o maior NSU já importado
            $checkTable = $this->db->query("SHOW TABLES LIKE 'nfse_nacional_docs'");
            if (!$checkTable || $checkTable->num_rows == 0) {
                // Cria tabela se não existir (fallback)
                $sqlCreate = "CREATE TABLE IF NOT EXISTS nfse_nacional_docs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nsu BIGINT NOT NULL UNIQUE,
                    chave_acesso VARCHAR(100),
                    numero_nfse VARCHAR(20),
                    numero_dps VARCHAR(20),
                    data_emissao DATETIME,
                    valor_liquido DECIMAL(15,2),
                    valor_servico DECIMAL(15,2),
                    xml_conteudo MEDIUMTEXT,
                    pdf_conteudo MEDIUMBLOB,
                    status VARCHAR(50),
                    tipo_documento VARCHAR(20),
                    tipo_fluxo VARCHAR(20),
                    tomador_nome VARCHAR(255),
                    tomador_doc VARCHAR(20),
                    prestador_nome VARCHAR(255),
                    prestador_doc VARCHAR(20),
                    municipio_emissao_descricao VARCHAR(100),
                    competencia DATE,
                    pedido_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (numero_nfse),
                    INDEX (data_emissao),
                    INDEX (chave_acesso),
                    INDEX (prestador_doc),
                    INDEX (tomador_doc)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                $this->db->query($sqlCreate);
                $lastNsu = 0;
            } else {
                $resetNsu = isset($_POST['reset_nsu']) && $_POST['reset_nsu'] === 'true';

                if ($resetNsu) {
                    $lastNsu = 0;
                } else {
                    $result = $this->db->query("SELECT MAX(nsu) as max_nsu FROM nfse_nacional_docs");
                    $row = $result->fetch_assoc();
                    $dbMaxNsu = $row['max_nsu'] ?? 0;
                    // Recuar 1000 NSUs para garantir que notas perdidas ou gaps sejam recuperados
                    // A API suporta até 50 docs por página, então 1000 cobre ~20 páginas de histórico.
                    $lastNsu = ($dbMaxNsu > 1000) ? $dbMaxNsu - 1000 : 0;
                }
            }

            // Loop para buscar TODAS as páginas disponíveis
            $totalImported = 0;
            $loopLimit = 50; // Limite de segurança para evitar loop infinito
            $currentLoop = 0;
            $moreDocsAvailable = true;

            while ($moreDocsAvailable && $currentLoop < $loopLimit) {
                $currentLoop++;
                
                // Consulta
                $response = $client->distribuirDfe($lastNsu);

                // Tratamento especial para 404 (Nenhum documento localizado)
                if (!$response['success']) {
                    if (isset($response['http_code']) && $response['http_code'] == 404) {
                        $moreDocsAvailable = false; // Parar loop
                        break;
                    }
                    throw new Exception("Erro na consulta API (Loop $currentLoop): " . $response['message']);
                }

                $data = $response['data'];
                $docs = $data['LoteDFe'] ?? [];
                
                if (empty($docs)) {
                    $moreDocsAvailable = false;
                    break;
                }

                // Pegar CNPJ do sistema para comparação
                $cnpjSistema = preg_replace('/[^0-9]/', '', $nfseConfig['cnpj_prestador'] ?? '');
                
                $maxNsuInBatch = $lastNsu;
                $importedInBatch = 0;

                foreach ($docs as $doc) {
                    $nsu = (int)$doc['NSU'];
                    if ($nsu > $maxNsuInBatch) $maxNsuInBatch = $nsu;

                    $chave = $doc['ChaveAcesso'] ?? '';
                    $arquivoXmlEnc = $doc['ArquivoXml'] ?? '';

                    if (!$arquivoXmlEnc) continue;

                    $zipContent = base64_decode($arquivoXmlEnc, true);
                    $xmlContent = @gzdecode($zipContent);
                    if (!$xmlContent) $xmlContent = @gzuncompress($zipContent);

                    if (!$xmlContent) continue;

                    // Processar e Salvar (usando método unificado)
                    if ($this->_processarXmlDfe($xmlContent, $nsu, $chave)) {
                        $importedInBatch++;
                    }
                }
                
                $totalImported += $importedInBatch;

                // Se o maior NSU deste lote for igual ao lastNsu, não avançamos.
                // Isso evita loop infinito se a API retornar sempre o mesmo lote.
                if ($maxNsuInBatch <= $lastNsu) {
                    $moreDocsAvailable = false;
                } else {
                    $lastNsu = $maxNsuInBatch; // Próxima iteração começa do maior NSU encontrado
                }
                
                // Se retornou menos que 50 docs, provavelmente é a última página
                if (count($docs) < 50) {
                    $moreDocsAvailable = false;
                }
                
                // Pequeno delay para não floodar a API
                usleep(200000); // 0.2s
            }

            $this->json([
                'success' => true,
                'message' => "Sincronização concluída. $totalImported documentos processados em $currentLoop iterações.",
                'count' => $totalImported,
                'iterations' => $currentLoop
            ]);
        } catch (Exception $e) {
            if (ob_get_length()) ob_clean();
            $this->json(['success' => false, 'message' => 'Erro: ' . mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8')]);
        }
    }

    public function testar_sefin()
    {
        // Limpa qualquer output anterior para garantir JSON limpo
        if (ob_get_length()) ob_clean();

        $certArquivo = $_POST['cert_arquivo'] ?? '';
        $certSenha = $_POST['cert_senha'] ?? '';

        if (empty($certArquivo) || empty($certSenha)) {
            $this->json(['success' => false, 'message' => 'Parâmetros inválidos']);
        }

        // Tentar encontrar o arquivo em vários locais
        $possiblePaths = [
            $certArquivo,
            __DIR__ . '/../../' . $certArquivo,
            $_SERVER['DOCUMENT_ROOT'] . '/' . $certArquivo,
            $_SERVER['DOCUMENT_ROOT'] . '/bmlab/' . $certArquivo
        ];

        $certPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $certPath = $path;
                break;
            }
        }

        if (!$certPath) {
            $this->json(['success' => false, 'message' => 'Arquivo de certificado não encontrado: ' . $certArquivo]);
        }

        try {
            // Inicializa cliente apenas para teste
            // Se houver URLs personalizadas no POST, poderíamos usar aqui, mas por enquanto usa o padrão do ambiente
            // O teste de conexão geralmente testa o ambiente de homologação ou o configurado
            // Vamos assumir produção para teste
            $ambiente = 'producao';

            // Verificar se temos URLs customizadas no config (não vieram no POST do teste, mas poderiam vir)
            // Para teste rápido, vamos instanciar normal.

            $client = new SefinNacionalClient($certPath, $certSenha, $ambiente);

            // Se tivermos URLs customizadas salvas, deveríamos usar.
            // Como este é um teste ANTES de salvar (possivelmente), o ideal seria receber as URLs via POST também.
            // Mas o usuário clica em "Testar" na tela de config.
            // Vamos checar se o usuário enviou h_url_service ou p_url_service
            
            if (isset($_POST['p_url_service'])) {
                $client->setCustomUrl($_POST['p_url_service']);
            }

            $resp = $client->checkConnection();

            if ($resp['success']) {
                $this->json([
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso! ' . ($resp['message'] ?? '')
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'message' => 'Erro ao conectar: ' . ($resp['message'] ?? 'Erro desconhecido')
                ]);
            }
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ]);
        }
    }

    private function parseMoney($val)
    {
        if (empty($val)) return 0.00;
        return (float)str_replace(['.', ','], ['', '.'], $val);
    }

    public function emitir()
    {
        $pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$pedido_id) {
            $pedido_id = filter_input(INPUT_GET, 'pedido_id', FILTER_VALIDATE_INT);
        }

        if (!$pedido_id) {
            die("Pedido inválido.");
        }

        $nfseConfig = $this->getNfseConfig();
        $msg = '';
        $tipo_msg = '';

        // Buscar Pedido
        $sql = "SELECT p.*, pac.nome as paciente_nome, pac.cpf as paciente_cpf, 
               pac.endereco, pac.numero, pac.bairro, pac.cep, 
               pac.nf_responsavel_nome, pac.nf_responsavel_cpf, pac.nf_responsavel_nascimento,
               mun.nome as nome_municipio, mun.uf as uf_municipio, mun.codigo_municipio as codigo_ibge
        FROM pedidos p
        LEFT JOIN pacientes pac ON p.paciente_id = pac.id
        LEFT JOIN municipios mun ON pac.municipio_id = mun.id
        WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $pedido_id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();

        if (!$pedido) {
            die("Pedido não encontrado.");
        }

        // Lógica para usar dados do Responsável (se houver) para emissão da nota
        if (!empty($pedido['nf_responsavel_nome']) && !empty($pedido['nf_responsavel_cpf'])) {
            $pedido['paciente_nome'] = $pedido['nf_responsavel_nome'];
            $pedido['paciente_cpf'] = $pedido['nf_responsavel_cpf'];
            // Opcional: Ajustar nascimento se for relevante para alguma validação futura
            // $pedido['paciente_nascimento'] = $pedido['nf_responsavel_nascimento'];
        }

        // Processamento POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
            try {
                $sefin = $this->_getSefinClient();

                if ($_POST['acao'] === 'emitir') {
                    // Lógica de Emissão SEFIN
                    if (empty($nfseConfig['cnpj_prestador']) || empty($nfseConfig['codigo_municipio_prestador'])) {
                        throw new Exception("Configure o CNPJ e Município do Prestador no Painel NFSe.");
                    }
                    if (empty($pedido['paciente_cpf'])) {
                        throw new Exception("Paciente precisa de CPF.");
                    }

                    // Helpers de formatação
                    $cleanDoc = function($val) { return preg_replace('/\D/', '', $val); };
                    $fmtDate = function($date) { 
                        if (!$date) return date('Y-m-d');
                        $d = \DateTime::createFromFormat('Y-m-d', $date); // Form envia Y-m-d input date
                        if (!$d) $d = \DateTime::createFromFormat('d/m/Y', $date);
                        return $d ? $d->format('Y-m-d') : date('Y-m-d');
                    };
                    $parseMoney = function($val) {
                        if (!$val) return 0.00;
                        $val = str_replace(['R$', ' ', '.'], '', $val);
                        return (float)str_replace(',', '.', $val);
                    };

                    // Montagem do array DPS com dados do POST
                    $dadosDPS = [
                        'nSerie' => $nfseConfig['serie_rps'] ?: '1',
                        'nDPS' => (int)$nfseConfig['ultimo_rps'] + 1,
                        'dEmissao' => date('Y-m-d\TH:i:sP'),
                        'dCompetencia' => $fmtDate($_POST['data_competencia']),
                        'prestador' => [
                            'cnpj' => $cleanDoc($nfseConfig['cnpj_prestador']),
                            'cMun' => $nfseConfig['codigo_municipio_prestador'],
                            'opSimpNac' => $_POST['emitente_opcao_simples'] ?? '1', // 1=Optante, 2=Não
                            // regApTribSN será inferido na lib ou passado explicitamente?
                            // A lib atual usa lógica default. Vamos passar se necessário ou ajustar lib.
                            // Mas a lib SefinNacionalClient já tem lógica para regApTribSN baseada em opSimpNac != 1.
                            // Se o usuário selecionou uma opção específica (1, 2 ou 3) no form para Regime Apuração:
                            'cRegTrib' => $_POST['emitente_regime_apuracao'] ?? '1' // Usado na lib para lógica customizada
                        ],
                        'tomador' => [
                            'cpf' => (strlen($cleanDoc($_POST['tomador_cpf_cnpj'])) == 11) ? $cleanDoc($_POST['tomador_cpf_cnpj']) : null,
                            'cnpj' => (strlen($cleanDoc($_POST['tomador_cpf_cnpj'])) == 14) ? $cleanDoc($_POST['tomador_cpf_cnpj']) : null,
                            'nome' => mb_substr($_POST['tomador_nome'], 0, 60, 'UTF-8'),
                        ],
                        'servico' => [
                            'vServ' => $parseMoney($_POST['valor_servico'] ?? '0'),
                            'cServ' => '04.03', // Fixo Laboratório ou vindo de config? Manter fixo por enquanto.
                            'cTribNac' => $cleanDoc($_POST['servico_c_trib_nac'] ?? '040302'),
                            'cNBS' => $cleanDoc($_POST['servico_nbs'] ?? ''),
                            'xDescServ' => $_POST['servico_descricao'] ?? 'Serviços Laboratoriais',
                            'cLocPrestacao' => $cleanDoc($_POST['local_municipio'] ?? $nfseConfig['codigo_municipio_prestador'])
                        ],
                        'valores' => [
                            'vDescIncond' => $parseMoney($_POST['valor_desconto_incondicionado'] ?? '0'),
                            'vDescCond' => $parseMoney($_POST['valor_desconto_condicionado'] ?? '0'),
                            'issRetido' => ($_POST['tributacao_iss_retido'] ?? 'nao') === 'sim',
                            'aliquota' => $parseMoney($_POST['tributacao_aliquota_simples'] ?? '0'), // 15,50 -> 15.50
                            'vPis' => $parseMoney($_POST['valor_retido_pis'] ?? '0'),
                            'vCofins' => $parseMoney($_POST['valor_retido_cofins'] ?? '0'),
                            'vInss' => $parseMoney($_POST['valor_retido_inss'] ?? '0'),
                            'vIr' => $parseMoney($_POST['valor_retido_irrf'] ?? '0'),
                            'vCsll' => $parseMoney($_POST['valor_retido_csll'] ?? '0'),
                        ]
                    ];

                    // Endereço Tomador (Opcional)
                    if (isset($_POST['tomador_informar_endereco'])) {
                        $dadosDPS['tomador']['endereco'] = [
                            'xLgr' => mb_substr($_POST['tomador_logradouro'], 0, 60, 'UTF-8'),
                            'nro' => mb_substr($_POST['tomador_numero'], 0, 10, 'UTF-8'),
                            'xCpl' => mb_substr($_POST['tomador_complemento'], 0, 60, 'UTF-8'),
                            'xBairro' => mb_substr($_POST['tomador_bairro'], 0, 60, 'UTF-8'),
                            'cMun' => $cleanDoc($_POST['tomador_municipio_ibge']), // Campo hidden ou select
                            'uf' => $_POST['tomador_uf'] ?? 'GO', // Idealmente viria do select municipio
                            'cep' => $cleanDoc($_POST['tomador_cep'])
                        ];
                        // Fallback UF se não vier
                         if (empty($dadosDPS['tomador']['endereco']['uf'])) $dadosDPS['tomador']['endereco']['uf'] = 'GO'; 
                    }

                    // Intermediário (Opcional)
                    if (($_POST['intermediario_localizacao'] ?? '') !== 'nao_informado') {
                        $dadosDPS['intermediario'] = [
                            'cpf' => (strlen($cleanDoc($_POST['intermediario_cpf_cnpj'])) == 11) ? $cleanDoc($_POST['intermediario_cpf_cnpj']) : null,
                            'cnpj' => (strlen($cleanDoc($_POST['intermediario_cpf_cnpj'])) == 14) ? $cleanDoc($_POST['intermediario_cpf_cnpj']) : null,
                            'nome' => mb_substr($_POST['intermediario_nome'], 0, 60, 'UTF-8')
                        ];
                    }

                    $xml = $sefin->gerarDpsXml($dadosDPS);

                    // Assinar e Enviar
                    $retorno = $sefin->emitirDps($xml);

                    if ($retorno['success']) {
                        // Sucesso na transmissão
                        // Atualizar pedido com status provisório ou final dependendo da resposta
                        // SEFIN Nacional pode ser assíncrono? Geralmente retorna o NSU ou a nota.
                        // Assumindo síncrono para simplicidade inicial ou "Enviado"

                        $novoRps = $dadosDPS['nDPS'];
                        
                        // Tentar extrair chave e XML oficial da NFSe para salvar "os mesmos arquivos"
                        $xmlSalvar = $xml; // Default: DPS enviado
                        $chaveAcesso = null;
                        
                        $respJson = json_decode($retorno['response_body'] ?? '{}', true);
                        if (isset($respJson['chaveAcesso'])) {
                            $chaveAcesso = $respJson['chaveAcesso'];
                            
                            // Consultar NFSe Completa Imediatamente para ter o XML Oficial
                            $cons = $sefin->consultarNotaPorChave($chaveAcesso);
                            
                            // Se tiver XML da NFSe no retorno (xmlNfseGZipB64)
                            if ($cons['success'] && !empty($cons['data']['xmlNfseGZipB64'])) {
                                $decoded = base64_decode($cons['data']['xmlNfseGZipB64']);
                                // Tentar gunzip se necessário (GZIP pode estar raw ou com headers)
                                // Geralmente GZipB64 é: Base64 -> Gzip -> XML
                                $xmlDecoded = @gzdecode($decoded);
                                if ($xmlDecoded) {
                                    $xmlSalvar = $xmlDecoded;
                                } else {
                                    // Se gzdecode falhar, tenta salvar o decoded (pode ser raw)
                                    $xmlSalvar = $decoded; 
                                }
                            }
                        }

                        // Atualizar Pedido
                        $sqlUpdate = "UPDATE pedidos SET 
                                      nfse_numero = ?, 
                                      nfse_status = 'ENVIADO',
                                      nfse_data_emissao = NOW(),
                                      nfse_xml_retorno = ?
                                      WHERE id = ?";
                        $stmtUp = $this->db->prepare($sqlUpdate);
                        // Salvar o XML Oficial (ou DPS se falhar consulta)
                        $stmtUp->bind_param('isi', $novoRps, $xmlSalvar, $pedido_id);
                        $stmtUp->execute();

                        // Atualizar Config (incrementar RPS)
                        $this->db->query("UPDATE nfse_config SET ultimo_rps = $novoRps WHERE id = 1");

                        $msg = "NFS-e Transmitida com Sucesso! DPS: $novoRps";
                        $tipo_msg = "success";
                    } else {
                        // Erro
                        throw new Exception("Erro no envio SEFIN: " . $retorno['message']);
                    }
                } elseif ($_POST['acao'] === 'consultar') {
                    // Consulta Status / Atualizar XML
                    $chave = null;
                    if (!empty($pedido['nfse_xml_retorno'])) {
                         if (preg_match('/<chaveAcesso>([0-9]{50})<\/chaveAcesso>/', $pedido['nfse_xml_retorno'], $m)) {
                             $chave = $m[1];
                         }
                    }
                    
                    $resultado = null;
                    if ($chave) {
                        $resultado = $sefin->consultarNotaPorChave($chave);
                    } elseif (!empty($pedido['nfse_numero'])) {
                        // Reconstruir ID do DPS para consulta (DPS + cMun + tpInsc + CNPJ + Serie + nDPS)
                        $cMunPrest = $nfseConfig['codigo_municipio_prestador'] ?? '';
                        $cnpjPrestador = preg_replace('/[^0-9]/', '', $nfseConfig['cnpj_prestador'] ?? '');
                        $tpInsc = (strlen($cnpjPrestador) == 14) ? '2' : '1';
                        $nSerie = str_pad($nfseConfig['serie_rps'] ?? '1', 5, '0', STR_PAD_LEFT);
                        $nDpsVal = $pedido['nfse_numero'];
                        $nDpsPad = str_pad($nDpsVal, 15, '0', STR_PAD_LEFT);
                        
                        $idDps = "DPS" . $cMunPrest . $tpInsc . $cnpjPrestador . $nSerie . $nDpsPad;
                        
                        $resultado = $sefin->consultarDps($idDps);
                    } else {
                        throw new Exception("Não há dados (Chave ou DPS) para consultar.");
                    }
                    
                    // === FALLBACK: BUSCA NO FLUXO DFe (Sincronização) ===
                    // Se a consulta direta falhar (404), pode ser que o DPS já tenha sido processado e convertido em NFSe,
                    // mas o endpoint de DPS não o encontra (ou requer ID diferente).
                    // A NFSe emitida deve aparecer no fluxo de distribuição (DFe).
                    
                    if ((!$resultado || !$resultado['success']) && !empty($pedido['nfse_numero'])) {
                        // Tentar sincronizar notas recentes
            $resMax = $this->db->query("SELECT MAX(nsu) as max_nsu FROM nfse_nacional_docs");
            $rowMax = $resMax->fetch_assoc();
            // Recuar 100 NSUs para garantir (antes era 50)
            $dbMaxNsu = $rowMax['max_nsu'] ?? 0;
            $lastNsu = ($dbMaxNsu > 100) ? $dbMaxNsu - 100 : 0;
                        
                        // Busca novos documentos
                        $resDfe = $sefin->distribuirDfe($lastNsu);
                        
                        // LOG DEBUG
                        file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync.txt', "Sync iniciada. LastNSU: $lastNsu. Pedido DPS: " . $pedido['nfse_numero'] . "\n", FILE_APPEND);

                        if ($resDfe['success'] && !empty($resDfe['data']['LoteDFe'])) {
                            $docs = $resDfe['data']['LoteDFe'];
                            file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync.txt', "Docs encontrados: " . count($docs) . "\n", FILE_APPEND);
                            
                            foreach ($docs as $doc) {
                                $nsu = $doc['NSU'];
                                $chaveDfe = $doc['ChaveAcesso'] ?? '';
                                $xmlEnc = $doc['ArquivoXml'] ?? '';
                                
                                if (!$xmlEnc) continue;
                                
                                // Decodificar
                                $zipContent = base64_decode($xmlEnc, true);
                                $xmlDfe = @gzdecode($zipContent) ?: @gzuncompress($zipContent);
                                
                                if ($xmlDfe) {
                                    $numNfse = null;
                                    $numDps = null;

                                    // Processar e Salvar (usando método unificado)
                                    $dadosDfe = $this->_processarXmlDfe($xmlDfe, $nsu, $chaveDfe);
                                    
                                    if ($dadosDfe) {
                                        $numNfse = $dadosDfe['numero_nfse'];
                                        $numDps = $dadosDfe['numero_dps'];
                                        
                                        file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync.txt', "NSU $nsu: NF $numNfse / DPS $numDps (Processado)\n", FILE_APPEND);
                                    }
                                    
                                    // Verificar se é o nosso pedido
                                    if ($numDps && (int)$numDps == (int)$pedido['nfse_numero']) {
                                        // BINGO!
                                        $xmlSalvar = $xmlDfe;
                                        $chave = $chaveDfe;
                                        $resultado = ['success' => true, 'data' => ['status' => 'ENCONTRADO_VIA_DFE']];
                                        $msg = "Nota localizada via Sincronização DFe!";
                                    }
                                }
                            }
                        }
                    }
                    // === FIM FALLBACK ===
                    
                    if ($resultado && $resultado['success']) {
                         // Tentar extrair XML da resposta (se não veio do DFe)
                         if (!isset($xmlSalvar)) {
                             $xmlSalvar = null;
                         
                             // Se resposta direta da nota
                             if (!empty($resultado['data']['xmlNfseGZipB64'])) {
                                 $decoded = base64_decode($resultado['data']['xmlNfseGZipB64']);
                                 $xmlSalvar = @gzdecode($decoded) ?: $decoded;
                             } 
                             // Se resposta do DPS que contém a nota
                             elseif (!empty($resultado['data']['nfs-e']['xmlNfseGZipB64'])) {
                                 $decoded = base64_decode($resultado['data']['nfs-e']['xmlNfseGZipB64']);
                                 $xmlSalvar = @gzdecode($decoded) ?: $decoded;
                             }
                         }

                         if ($xmlSalvar) {
                            $stmtUp = $this->db->prepare("UPDATE pedidos SET nfse_xml_retorno = ? WHERE id = ?");
                            $stmtUp->bind_param('si', $xmlSalvar, $pedido_id);
                            $stmtUp->execute();
                            $msg = "Consulta realizada! XML Oficial atualizado.";
                            
                            // Tentar Sincronizar PDF (Se tiver Chave de Acesso)
                            $chaveSync = null;
                            
                            // Debug XML para Regex
                            file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_sync.txt', "XML para Regex (inicio): " . substr($xmlSalvar, 0, 500) . "\n", FILE_APPEND);
                            
                            if (preg_match('/<chaveAcesso>(.*?)<\/chaveAcesso>/', $xmlSalvar, $m)) {
                                $chaveSync = $m[1];
                            } elseif (preg_match('/<ns.:chaveAcesso>(.*?)<\/ns.:chaveAcesso>/', $xmlSalvar, $m)) {
                                $chaveSync = $m[1];
                            } elseif (preg_match('/:chaveAcesso>(.*?)<\/:chaveAcesso>/', $xmlSalvar, $m)) { // Tentativa genérica de namespace
                                $chaveSync = $m[1];
                            } elseif (preg_match('/infNFSe Id="NFS([0-9]+)"/', $xmlSalvar, $m)) {
                                // Fallback: Extrair do atributo ID da tag infNFSe
                                $chaveSync = $m[1];
                            }
                            
                            file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_sync.txt', "Tentando Sync PDF. Chave: " . ($chaveSync ?? 'Nao encontrada') . "\n", FILE_APPEND);

                            if ($chaveSync) {
                                // Garantir que a nota esteja no repositório oficial (nfse_nacional_docs)
                                // Isso permite que o download/visualização em 'Pedidos' use o arquivo oficial
                                $this->_processarXmlDfe($xmlSalvar, 0, $chaveSync);

                                try {
                                    $pdfResp = $sefin->baixarDanfse($chaveSync);
                                    file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_sync.txt', "Resp Baixar PDF: " . json_encode($pdfResp) . "\n", FILE_APPEND);
                                    
                                    if ($pdfResp['success'] && !empty($pdfResp['pdf_content'])) {
                                        $pdfContent = $pdfResp['pdf_content'];
                                        
                                        $stmtPdf = $this->db->prepare("UPDATE pedidos SET nfse_pdf_retorno = ? WHERE id = ?");
                                        $stmtPdf->bind_param('si', $pdfContent, $pedido_id);
                                        $stmtPdf->execute();
                                        
                                        $stmtDocPdf = $this->db->prepare("UPDATE nfse_nacional_docs SET pdf_conteudo = ? WHERE chave_acesso = ?");
                                        $stmtDocPdf->bind_param('ss', $pdfContent, $chaveSync);
                                        $stmtDocPdf->execute();
                                        
                                        $msg .= " PDF Oficial atualizado.";
                                    } else {
                                        $httpCode = $pdfResp['http_code'] ?? 'N/A';
                                        // Mensagem técnica direta conforme solicitado
                                        $msg .= " (Aviso: PDF Oficial não baixado. Erro HTTP {$httpCode})";
                                    }
                                } catch (Exception $e) {
                                    file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_sync.txt', "Erro Baixar PDF: " . $e->getMessage() . "\n", FILE_APPEND);
                                    $msg .= " (Aviso: Erro ao tentar baixar PDF Oficial)";
                                }
                            }
                            
                            $tipo_msg = "success";
                            // Atualizar objeto pedido para refletir na view
                            $pedido['nfse_xml_retorno'] = $xmlSalvar;
                         } else {
                             $msg = "Consulta realizada (Status: " . ($resultado['data']['status'] ?? 'OK') . "), mas XML não retornado.";
                             $tipo_msg = "warning";
                         }
                    } else {
                        // Verificação especial para 404 (Processamento Pendente)
                        $httpCode = $resultado['http_code'] ?? 0;
                        
                        // Se for erro 404 e tivermos um número de DPS, assumimos que é delay de processamento
                        if ($httpCode == 404 && !empty($pedido['nfse_numero'])) {
                            $msg = "Nota emitida com sucesso! Aguardando processamento da Receita Federal para disponibilizar os arquivos oficiais. Tente novamente em alguns minutos.";
                            $tipo_msg = "warning";
                        } else {
                            throw new Exception("Falha na consulta: " . ($resultado['message'] ?? 'Erro desconhecido'));
                        }
                    }
                }
            } catch (Exception $e) {
                $msg = "Erro: " . $e->getMessage();
                $tipo_msg = "error";
            }
        }

        // View de Emissão (Reutilizando a estrutura antiga ou criando nova)
        // Por enquanto, mostraremos os dados para conferência
        $this->view('nfse/emitir', [
            'pedido' => $pedido,
            'pedido_id' => $pedido['id'],
            'pedido_codigo_visual' => $pedido['codigo_pedido'],
            'valorServico' => $pedido['total_liquido'],
            'config' => $nfseConfig,
            'msg' => $msg,
            'tipo_msg' => $tipo_msg,
            'titulo_pagina' => 'Emitir NFS-e Nacional',
            'route' => 'nfse/emitir'
        ]);
    }

    public function downloadXml()
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $source = $_GET['source'] ?? 'local';

        if (!$id) {
            die("ID inválido.");
        }

        $xmlContent = null;
        $filename = "nfse_{$id}.xml";

        if ($source === 'nacional') {
            $stmt = $this->db->prepare("SELECT xml_conteudo, numero_nfse, chave_acesso FROM nfse_nacional_docs WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $xmlContent = $row['xml_conteudo'];
                if ($row['chave_acesso']) $filename = $row['chave_acesso'] . ".xml";
            }
        } else {
            // Local (Pedidos)
            $stmt = $this->db->prepare("SELECT nfse_xml_retorno, nfse_numero FROM pedidos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $xmlContent = $row['nfse_xml_retorno'];
                $dpsNumero = $row['nfse_numero'];
                
                // Tentar encontrar o XML Oficial (NFSe) na tabela sincronizada
                if ($dpsNumero) {
                    $stmtDoc = $this->db->prepare("SELECT xml_conteudo, chave_acesso FROM nfse_nacional_docs WHERE numero_dps = ? ORDER BY id DESC LIMIT 1");
                    $stmtDoc->bind_param('s', $dpsNumero);
                    $stmtDoc->execute();
                    $resDoc = $stmtDoc->get_result();
                    if ($rowDoc = $resDoc->fetch_assoc()) {
                        // Encontrou a nota oficial! Substituir.
                        $xmlContent = $rowDoc['xml_conteudo'];
                        if ($rowDoc['chave_acesso']) $filename = $rowDoc['chave_acesso'] . ".xml";
                    } else {
                        // Fallback: Nome do arquivo DPS
                        $filename = "dps_{$dpsNumero}.xml";
                    }
                }
            }
        }

        if (!$xmlContent) {
            die("Conteúdo XML não encontrado para este registro.");
        }

        // Limpar output buffer para evitar corromper o arquivo
        if (ob_get_level()) ob_end_clean();

        header('Content-Description: File Transfer');
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($xmlContent));
        echo $xmlContent;
        exit;
    }

    public function baixarPdfOficial() {
        $chave = $_GET['chave'] ?? null;
        $id = $_GET['id'] ?? null;
        
        if (!$chave) die("Chave não informada.");

        require_once __DIR__ . '/../Libraries/Nfse/SefinNacionalClient.php';
        try {
            $sefinPdf = $this->_getSefinClient();
            $pdfResp = $sefinPdf->baixarDanfse($chave);

            if ($pdfResp['success']) {
                 // Salvar e exibir
                 if ($id) {
                    $stmtPdf = $this->db->prepare("UPDATE nfse_nacional_docs SET pdf_conteudo = ? WHERE id = ?");
                    $stmtPdf->bind_param("si", $pdfResp['pdf_content'], $id);
                    $stmtPdf->execute();
                 }
                 
                 header('Content-Type: application/pdf');
                 header('Content-Disposition: inline; filename="nfse_' . $chave . '.pdf"');
                 echo $pdfResp['pdf_content'];
                 exit;
            } else {
                echo "<script>alert('Falha ao baixar PDF do Portal Nacional. O serviço pode estar indisponível (Erro 503/502). Tente novamente mais tarde.'); window.history.back();</script>";
            }
        } catch (Exception $e) {
            echo "<script>alert('Erro interno: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        }
    }

    public function visualizarDanfse()
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $source = $_GET['source'] ?? 'local';

        if (!$id) die("ID inválido.");

        $xmlContent = null;
        $pdfContent = null;
        $numero_nfse = 'PENDENTE';
        $chave_acesso = 'PENDENTE';
        $data_emissao = date('d/m/Y H:i:s');
        $id_nacional_doc = null;

        // 1. Obter XML (e PDF) do Banco
        if ($source === 'nacional') {
            $stmt = $this->db->prepare("SELECT id, xml_conteudo, numero_nfse, chave_acesso, data_emissao, pdf_conteudo FROM nfse_nacional_docs WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $id_nacional_doc = $row['id'];
                $xmlContent = $row['xml_conteudo'];
                $numero_nfse = $row['numero_nfse'];
                $chave_acesso = $row['chave_acesso'];
                $data_emissao = date('d/m/Y H:i:s', strtotime($row['data_emissao']));
                $pdfContent = $row['pdf_conteudo'];
            }
        } else {
            // Local (Pedidos)
            $stmt = $this->db->prepare("SELECT nfse_xml_retorno, nfse_numero, nfse_data_emissao, nfse_pdf_retorno FROM pedidos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $xmlContent = $row['nfse_xml_retorno'];
                $numero_nfse = $row['nfse_numero'] ?: 'PENDENTE';
                $pdfContent = $row['nfse_pdf_retorno'];

                if ($row['nfse_data_emissao']) {
                    $data_emissao = date('d/m/Y H:i:s', strtotime($row['nfse_data_emissao']));
                }

                // Tentar encontrar a nota oficial para obter Chave e Tentar Download PDF
                if ($row['nfse_numero']) {
                    $stmtDoc = $this->db->prepare("SELECT id, xml_conteudo, chave_acesso, numero_nfse, data_emissao, pdf_conteudo FROM nfse_nacional_docs WHERE numero_dps = ? ORDER BY id DESC LIMIT 1");
                    $stmtDoc->bind_param('s', $row['nfse_numero']);
                    $stmtDoc->execute();
                    $resDoc = $stmtDoc->get_result();
                    if ($rowDoc = $resDoc->fetch_assoc()) {
                         // Achou a nota oficial!
                         $id_nacional_doc = $rowDoc['id'];
                         $xmlContent = $rowDoc['xml_conteudo']; // Prefira XML oficial
                         $numero_nfse = $rowDoc['numero_nfse'];
                         $chave_acesso = $rowDoc['chave_acesso'];
                         $data_emissao = date('d/m/Y H:i:s', strtotime($rowDoc['data_emissao']));
                         
                         // Se tiver PDF na tabela sync, priorizar
                         if (!empty($rowDoc['pdf_conteudo'])) {
                             $pdfContent = $rowDoc['pdf_conteudo'];
                         }
                    }
                }
            }
        }

        // SE TIVER PDF ARMAZENADO, SERVIR
        if (!empty($pdfContent)) {
             if (ob_get_level()) ob_end_clean();
             header('Content-Type: application/pdf');
             header('Content-Disposition: inline; filename="' . ($chave_acesso ?: 'documento') . '.pdf"');
             echo $pdfContent;
             exit;
        }

        if (!$xmlContent) die("XML da Nota não encontrado para gerar o DANFS-e.");

        // Se a chave não veio do banco, tentar extrair do XML (Robusto)
        if (empty($chave_acesso) || $chave_acesso === 'PENDENTE' || strlen($chave_acesso) != 50) {
            // Regex melhorada que ignora namespaces
            if (preg_match('/<([a-zA-Z0-9]+:)?chaveAcesso>([0-9]{50})<\/([a-zA-Z0-9]+:)?chaveAcesso>/', $xmlContent, $m)) {
                $chave_acesso = $m[2];
            } elseif (preg_match('/infNFSe Id="NFS([0-9]{50})"/', $xmlContent, $m)) {
                $chave_acesso = $m[1];
            }
        }

        // === TENTATIVA DE DOWNLOAD DO PDF OFICIAL (ADN) ===
        // Se tivermos a Chave de Acesso, tentamos baixar o PDF direto da API/Portal Nacional.
        
        $api_unavailable = false;

        if ($chave_acesso && strlen($chave_acesso) == 50) {
            try {
                if (!class_exists('App\Libraries\Nfse\SefinNacionalClient')) {
                    require_once __DIR__ . '/../Libraries/Nfse/SefinNacionalClient.php';
                }
                
                $sefinPdf = $this->_getSefinClient();
                
                // Log Debug
                file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_recebidas.txt', "--- Nova Tentativa (Visualizar) ---\nID: $id, Source: $source, Chave: $chave_acesso\n", FILE_APPEND);
                
                $pdfResp = $sefinPdf->baixarDanfse($chave_acesso);
        
                file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_recebidas.txt', "Resp Baixar PDF: " . ($pdfResp['success'] ? 'OK' : 'Erro') . "\n", FILE_APPEND);

                if ($pdfResp['success'] && !empty($pdfResp['pdf_content'])) {
                    $pdfBaixado = $pdfResp['pdf_content'];

                    // Salvar Cache (Nacional Docs)
                    // Se tiver ID nacional identificado, usa ele. Se não, tenta pela chave.
                    if ($id_nacional_doc) {
                        $stmtPdf = $this->db->prepare("UPDATE nfse_nacional_docs SET pdf_conteudo = ? WHERE id = ?");
                        $stmtPdf->bind_param("si", $pdfBaixado, $id_nacional_doc);
                        $stmtPdf->execute();
                    } else {
                        $stmtPdf = $this->db->prepare("UPDATE nfse_nacional_docs SET pdf_conteudo = ? WHERE chave_acesso = ?");
                        $stmtPdf->bind_param("ss", $pdfBaixado, $chave_acesso);
                        $stmtPdf->execute();
                    }

                    // Se for local (pedido), atualizar pedidos também
                    if ($source !== 'nacional' && $id) {
                        $stmtSavePed = $this->db->prepare("UPDATE pedidos SET nfse_pdf_retorno = ? WHERE id = ?");
                        $stmtSavePed->bind_param('si', $pdfBaixado, $id);
                        $stmtSavePed->execute();
                    }

                    if (ob_get_level()) ob_end_clean();
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="nfse_' . $chave_acesso . '.pdf"');
                    echo $pdfBaixado;
                    exit;
                } else {
                    $api_unavailable = true;
                }
            } catch (Exception $e) {
                 // Falha silenciosa no download oficial, segue para fallback local
                 file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_recebidas.txt', "Exception Download: " . $e->getMessage() . "\n", FILE_APPEND);
                 $api_unavailable = true;
            }
        } else {
             file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_recebidas.txt', "Chave nao encontrada ou invalida: $chave_acesso\n", FILE_APPEND);
        }
        // === FIM TENTATIVA PDF OFICIAL ===

        // 2. Parsear XML (Igual ao Importar)

        // Limpeza básica
        $xmlContentClean = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
        $xmlContentClean = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $xmlContentClean);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContentClean);
        libxml_clear_errors();

        if (!$xml) die("Erro ao ler XML da Nota.");

        // Localizar infNFSe ou DPS
        $inf = null;
        if (isset($xml->infNFSe)) {
            $inf = $xml->infNFSe;
        } elseif (isset($xml->infDPS)) {
            $inf = $xml->infDPS;
        } elseif (isset($xml->DPS->infDPS)) {
            $inf = $xml->DPS->infDPS;
        } else {
            $inf = $xml; // Tenta usar a raiz se não achar filhos conhecidos
        }

        // Helpers de Extração
        $getStr = function ($node) {
            return (string)$node;
        };
        $getVal = function ($node) {
            return number_format((float)$node, 2, ',', '.');
        };
        $formatDoc = function ($doc) {
            $doc = preg_replace('/\D/', '', $doc);
            if (strlen($doc) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
            if (strlen($doc) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
            return $doc;
        };

        // Extração de Dados

        // Chave e Número (Prioridade para o extraído do banco se não tiver no XML)
        if (empty($chave_acesso) || $chave_acesso === 'PENDENTE') {
            // Tenta extrair ID do DPS se não tiver chave
            $chave_acesso = $getStr($inf['Id'] ?? '');
        }

        // Prestador
        $prestador = [
            'nome' => $getStr($inf->emit->xNome ?? $inf->prest->xNome ?? 'BRAGA MENDES LABORATORIO LTDA'),
            'doc' => $formatDoc($getStr($inf->emit->CNPJ ?? $inf->prest->CNPJ ?? '')),
            'im' => $getStr($inf->emit->IM ?? $inf->prest->IM ?? ''),
            'fone' => $getStr($inf->emit->fone ?? $inf->prest->fone ?? ''),
            'email' => $getStr($inf->emit->email ?? $inf->prest->email ?? ''),
            'endereco' => $getStr($inf->emit->end->xLgr ?? $inf->prest->end->xLgr ?? '') . ', ' . $getStr($inf->emit->end->nro ?? $inf->prest->end->nro ?? ''),
            'municipio' => $getStr($inf->emit->end->xMun ?? $inf->emit->end->endNac->cMun ?? ''), // cMun precisa de de-para se for codigo
            'uf' => $getStr($inf->emit->end->UF ?? '')
        ];

        // Se município for código, tentar resolver (simplificado) ou deixar código
        // Melhoria: Consultar tabela municipios se possível, mas para evitar overhead agora, deixaremos o dado do XML.

        // Tomador
        $tomadorNode = $inf->toma ?? null;
        $tomador = [
            'nome' => $getStr($tomadorNode->xNome ?? 'Consumidor Final'),
            'doc' => $formatDoc($getStr($tomadorNode->CNPJ ?? $tomadorNode->CPF ?? '')),
            'im' => $getStr($tomadorNode->IM ?? ''),
            'fone' => $getStr($tomadorNode->fone ?? ''),
            'email' => $getStr($tomadorNode->email ?? ''),
            'endereco' => $getStr($tomadorNode->end->xLgr ?? '') . ', ' . $getStr($tomadorNode->end->nro ?? ''),
            'municipio' => $getStr($tomadorNode->end->xMun ?? $tomadorNode->end->endNac->cMun ?? ''),
            'uf' => $getStr($tomadorNode->end->UF ?? ''),
            'cep' => $getStr($tomadorNode->end->CEP ?? $tomadorNode->end->endNac->CEP ?? '')
        ];

        // Serviço
        $servicoNode = $inf->serv ?? null;
        $servico = [
            'codigo' => $getStr($servicoNode->cServ->cTribNac ?? ''),
            'nbs' => $getStr($servicoNode->cServ->cNBS ?? ''),
            'discriminacao' => $getStr($servicoNode->cServ->xDescServ ?? ''),
            'local_prestacao' => $getStr($servicoNode->locPrest->cLocPrestacao ?? '')
        ];

        // Valores
        $valNode = $inf->valores ?? null;
        $valores = [
            'servico' => $getVal($valNode->vServPrest->vServ ?? 0),
            'pis' => $getVal($valNode->trib->vPis ?? 0),
            'cofins' => $getVal($valNode->trib->vCofins ?? 0),
            'inss' => $getVal($valNode->trib->vInss ?? 0),
            'ir' => $getVal($valNode->trib->vIr ?? 0),
            'csll' => $getVal($valNode->trib->vCsll ?? 0),
            'aliquota' => $getVal($valNode->trib->pAliq ?? 0),
            'outras_retencoes' => '0,00', // Campo genérico
            'iss' => '0,00', // Depende do regime
            'desconto' => $getVal($valNode->vDescCond ?? $valNode->vDescIncond ?? 0),
            'deducoes' => '0,00',
            'liquido' => $getVal($valNode->vServPrest->vServ ?? 0) // Calcular se houver retenções
        ];

        // Ajuste ISS se disponível
        // O padrão nacional pode ter vISSQN dentro de tribMun
        if (isset($valNode->trib->tribMun->vISSQN)) {
            $valores['iss'] = $getVal($valNode->trib->tribMun->vISSQN);
        }

        // Calcular líquido real (Serviço - Retenções)
        $vServ = (float)($valNode->vServPrest->vServ ?? 0);
        $vRet = (float)($valNode->trib->vPis ?? 0) +
            (float)($valNode->trib->vCofins ?? 0) +
            (float)($valNode->trib->vInss ?? 0) +
            (float)($valNode->trib->vIr ?? 0) +
            (float)($valNode->trib->vCsll ?? 0);
        $valores['liquido'] = number_format($vServ - $vRet, 2, ',', '.');


        // Dados View
        $qrUrl = "https://www.nfse.gov.br/consultapublica?chave={$chave_acesso}";
        $qrCodeBase64 = $this->_getQrCodeBase64($qrUrl);

        $dadosView = [
            'numero_nfse' => $numero_nfse,
            'serie_dps' => $getStr($inf->serie ?? '1'),
            'data_emissao' => $data_emissao,
            'competencia' => isset($inf->dCompet) ? date('m/Y', strtotime((string)$inf->dCompet)) : '-',
            'chave_acesso' => $chave_acesso,
            'qr_code_url' => $qrUrl,
            'qr_code_base64' => $qrCodeBase64,
            'prestador' => $prestador,
            'tomador' => $tomador,
            'servico' => $servico,
            'valores' => $valores,
            'outras_informacoes' => 'Trib aprox: ' . $getVal($valNode->trib->vTotTrib->vTotTribFed ?? 0) . ' (Fed), ' . $getVal($valNode->trib->vTotTrib->vTotTribMun ?? 0) . ' (Mun). Fonte: IBPT.',
            'is_preview' => ($numero_nfse === 'PENDENTE'),
            'api_unavailable' => $api_unavailable ?? false
        ];

        // Se API indisponível, usar Gerador PDF Local (Fiel ao Nacional)
        if (isset($dadosView['api_unavailable']) && $dadosView['api_unavailable']) {
            require_once __DIR__ . '/../Libraries/Nfse/DanfseGenerator.php';
            $pdf = new \App\Libraries\Nfse\DanfseGenerator($dadosView);
            $pdf->generate();
            exit;
        }

        $this->view('nfse/danfse_print', $dadosView);
    }

    public function cancelar()
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $source = filter_input(INPUT_GET, 'source') ?? 'local';
        $confirmar = filter_input(INPUT_POST, 'confirmar');

        if (!$id) die("ID inválido.");

        $nfseConfig = $this->getNfseConfig();
        
        // Buscar Nota e Chave de Acesso
        $nota = null;
        $chaveAcesso = '';
        
        if ($source === 'local') {
            // Busca em pedidos
            // Tenta pegar chave da tabela sincronizada OU do XML de retorno
            $sql = "SELECT p.*, pac.nome as paciente_nome, pac.cpf as paciente_cpf, 
                           (SELECT chave_acesso FROM nfse_nacional_docs WHERE numero_dps = p.nfse_numero AND tipo_documento = 'NFSE' AND status != 'CANCELADA' ORDER BY id DESC LIMIT 1) as chave_nacional
                    FROM pedidos p 
                    LEFT JOIN pacientes pac ON p.paciente_id = pac.id
                    WHERE p.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $nota = $stmt->get_result()->fetch_assoc();
            
            if ($nota) {
                $chaveAcesso = $nota['chave_nacional'] ?? ''; 
                
                // Se não tiver chave na subquery, tenta buscar no XML de retorno se existir
                if (empty($chaveAcesso) && !empty($nota['nfse_xml_retorno'])) {
                    // Tenta extrair <chNFSe>
                    if (preg_match('/<chNFSe>(.*?)<\/chNFSe>/', $nota['nfse_xml_retorno'], $matches)) {
                        $chaveAcesso = $matches[1];
                    } 
                    // Tenta extrair Id="NFS..." (Atributo)
                    elseif (preg_match('/Id="NFS([0-9]+)"/', $nota['nfse_xml_retorno'], $matches)) {
                        $chaveAcesso = $matches[1];
                    }
                }
            }
        } 
        
        if (!$nota || empty($chaveAcesso)) {
             die("<div style='font-family:sans-serif;padding:20px;color:red;'><h3>Erro</h3><p>Nota não encontrada ou Chave de Acesso não disponível. <br>Certifique-se que a nota foi sincronizada e possui status AUTORIZADA.</p><a href='javascript:history.back()'>Voltar</a></div>");
        }

        // Se GET, mostra view
        if (!$confirmar) {
            $this->view('nfse/cancelar', [
                'nota' => $nota,
                'chave_acesso' => $chaveAcesso,
                'source' => $source,
                'titulo_pagina' => 'Cancelar NFS-e'
            ]);
            return;
        }

        // Se POST, processa
        $codigo = $_POST['codigo_cancelamento'];
        $motivo = $_POST['motivo'];
        
        if (empty($codigo) || empty($motivo)) {
            die("Código e Motivo são obrigatórios.");
        }

        try {
            // Instancia Client
            require_once __DIR__ . '/../Libraries/Nfse/SefinNacionalClient.php';
            $certPath = $nfseConfig['certificado_caminho']; 
            $certPass = $nfseConfig['certificado_senha'];
            
            $client = new \App\Libraries\Nfse\SefinNacionalClient($certPath, $certPass, 'producao');
            // Auth opcional dependendo do endpoint, mas bom setar
            if (!empty($nfseConfig['usuario_cpf'])) {
                $client->setAuth($nfseConfig['usuario_cpf'], $nfseConfig['usuario_senha']);
            }
            
            // Dados Prestador (Config)
            $prestadorCnpj = $nfseConfig['prestador_cnpj'];
            $prestadorMun = $nfseConfig['prestador_ibge']; // Código IBGE Município

            // Gerar XML Evento
            $xmlEvento = $client->gerarEventoCancelamentoXml($chaveAcesso, $codigo, $motivo, $prestadorCnpj, $prestadorMun);
            
            // Enviar
            $res = $client->registrarEvento($xmlEvento);
            
            if ($res['success']) {
                // Atualizar Banco Local
                // Status CANCELADA (Feminino para alinhar com Nacional Docs, Pedidos usa CANCELADO/CANCELADA? O Enum tem CANCELADO? Vou checar depois, mas string aceita)
                // O código legado usa 'CANCELADO' ou 'CANCELADA'? 
                // Na listagem vi: `elseif (strpos($statusUpper, 'CANCEL') !== false)` então aceita ambos.
                // Vou usar 'CANCELADA' para padronizar.
                $this->db->query("UPDATE pedidos SET nfse_status = 'CANCELADA' WHERE id = $id");
                
                // Atualizar Banco Nacional (Cache)
                $this->db->query("UPDATE nfse_nacional_docs SET status = 'CANCELADA' WHERE chave_acesso = '$chaveAcesso'");

                // Redirecionar
                header("Location: index.php?r=nfse/notas_expedidas&msg=Cancelamento realizado com sucesso!&tipo_msg=success");
                exit;
            } else {
                // Erro
                $msgErro = $res['message'] ?? 'Erro desconhecido';
                // Decodificar JSON de erro se possível
                if (!empty($res['response_body'])) {
                    $jsonErr = json_decode($res['response_body'], true);
                    if ($jsonErr && isset($jsonErr['erros'])) {
                        $msgErro = "";
                        foreach($jsonErr['erros'] as $e) {
                            $msgErro .= $e['Descricao'] . " ";
                        }
                    }
                }
                
                die("<div style='font-family:sans-serif;padding:20px;'><h3>Falha no Cancelamento</h3><p>$msgErro</p><p><small>Detalhes técnicos salvos no log.</small></p><a href='javascript:history.back()'>Voltar</a></div>");
            }

        } catch (Exception $e) {
            die("Erro crítico: " . $e->getMessage());
        }
    }

    public function substituir()
    {
        // TODO: Implementar lógica de substituição
        die("<div style='font-family:sans-serif;padding:20px;'><h3>Funcionalidade em Desenvolvimento</h3><p>A substituição de NFS-e via sistema ainda não está disponível. Por favor, realize o procedimento no Portal Nacional.</p></div>");
    }

    public function reprocessar_tudo()
    {
        // Verifica permissão (Admin)
        if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
            die("Acesso negado.");
        }

        set_time_limit(0);
        
        $sql = "SELECT nsu, chave_acesso, xml_conteudo FROM nfse_nacional_docs WHERE xml_conteudo IS NOT NULL AND xml_conteudo != ''";
        $result = $this->db->query($sql);
        
        $count = 0;
        $total = $result->num_rows;
        
        echo "Iniciando reprocessamento de $total registros...<br>";
        
        while ($row = $result->fetch_assoc()) {
            $this->_processarXmlDfe($row['xml_conteudo'], $row['nsu'], $row['chave_acesso']);
            $count++;
            if ($count % 10 == 0) echo ".";
            if ($count % 1000 == 0) echo " $count<br>";
        }
        
        echo "<br>Concluído! $count registros reprocessados.";
    }

    /**
     * Processa e salva XML DFe na tabela nfse_nacional_docs
     * @param string $xmlContent
     * @param int $nsu
     * @param string $chave
     * @return array|bool Retorna dados extraídos ou false em falha
     */
    public function _processarXmlDfe($xmlContent, $nsu, $chave)
    {
        $numero = null;
        $numeroDps = null;
        $dataEmissao = null;
        $valor = null;
        $valorServico = null; // Novo campo
        $status = 'AUTORIZADA'; // Default
        $tipoDoc = 'DESCONHECIDO';
        $tipoFluxo = 'TOMADO'; // Default safe
        
        $nfseConfig = $this->getNfseConfig();
        $cnpjSistema = preg_replace('/[^0-9]/', '', $nfseConfig['cnpj_prestador'] ?? '');

        $tomador_nome = null;
        $tomador_doc = null;
        $prestador_nome = null;
        $prestador_doc = null;
        $municipio_emissao_descricao = null;
        $competencia = null;

        // Limpeza de caracteres inválidos para XML (UTF-8)
        $xmlContent = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $xmlContent);

        // Parse XML
        $xmlContentClean = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
        $xmlContentClean = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $xmlContentClean);

        libxml_use_internal_errors(true);
        $xmlObj = simplexml_load_string($xmlContentClean);
        libxml_clear_errors();

        if ($xmlObj) {
            // === CASO 1: NOTA FISCAL (NFSe ou DPS convertida) ===
            if ($xmlObj->getName() == 'NFSe' || isset($xmlObj->infNFSe) || isset($xmlObj->DPS)) {
                $tipoDoc = 'NFSE';

                // Tenta localizar infNFSe ou DPS
                $inf = isset($xmlObj->infNFSe) ? $xmlObj->infNFSe : $xmlObj;

                $numero = (string)($inf->numero ?? $inf->nNFSe ?? '');
                $dataEmissao = (string)($inf->dhEmi ?? $inf->dhProc ?? '');
                $valor = (float)($inf->valores->vLiq ?? 0);
                $valorServico = (float)($inf->valores->vServPrest->vServ ?? 0);

                // Extração de Dados Adicionais
                $municipio_emissao_descricao = (string)($inf->xLocEmi ?? '');

                // Prestador
                $prestador_nome = (string)($inf->emit->xNome ?? '');
                $prestador_doc = (string)($inf->emit->CNPJ ?? ($inf->emit->CPF ?? ''));

                // Busca dados do DPS (Tomador, Competência, e Fallbacks)
                if (isset($inf->DPS->infDPS)) {
                    $infDps = $inf->DPS->infDPS;

                    if (isset($infDps->nDPS)) $numeroDps = (string)$infDps->nDPS;

                    // Se não pegou dados principais do cabeçalho NFSe, pega do DPS
                    if (!$numero && isset($infDps->nDPS)) {
                        // Data emissao do DPS
                        if (!$dataEmissao) $dataEmissao = (string)$infDps->dhEmi;
                        if (!$valor) $valor = (float)$infDps->valores->vServPrest->vServ;
                    }
                    
                    // Se valorServico ainda for 0, tenta pegar do DPS
                    if (!$valorServico) $valorServico = (float)($infDps->valores->vServPrest->vServ ?? 0);

                    $competencia = (string)($infDps->dCompet ?? '');

                    // Tomador
                    $tomador_nome = (string)($infDps->toma->xNome ?? '');
                    $tomador_doc = (string)($infDps->toma->CNPJ ?? ($infDps->toma->CPF ?? ''));

                    // Fallback Prestador
                    if (!$prestador_doc && isset($infDps->prest->CNPJ)) {
                        $prestador_doc = (string)$infDps->prest->CNPJ;
                    }
                }

                // Identificar Fluxo (Emitida vs Recebida)
                $cnpjEmit = $prestador_doc;

                if ($cnpjEmit && $cnpjEmit == $cnpjSistema) {
                    $tipoFluxo = 'PRESTADO';

                    // === SYNC PEDIDOS (LOCAL) ===
                    // Se for nota emitida por nós e tivermos o número do DPS, atualizamos o pedido local
                    if (!empty($numeroDps)) {
                        $statusLocal = 'AUTORIZADA'; // Status oficial da NFSe
                        
                        // Atualiza status e garante que o XML esteja salvo (caso tenha falhado na emissão)
                        // Usamos COALESCE para não sobrescrever se já existir algo (mas se for NULL, preenche)
                        // Na verdade, o XML do Portal é "melhor" que o de envio, então vamos forçar update se estiver vazio ou diferente.
                        // Simplificando: UPDATE direto.
                        
                        $stmtSync = $this->db->prepare("UPDATE pedidos SET nfse_status = ?, nfse_xml_retorno = ? WHERE nfse_numero = ?");
                        // numeroDps vem como string do XML, mas no banco é int/string.
                        $stmtSync->bind_param('sss', $statusLocal, $xmlContent, $numeroDps);
                        $stmtSync->execute();
                    }
                } else {
                    $tipoFluxo = 'TOMADO';
                }
            }
            // === CASO 2: EVENTO (Cancelamento, etc) ===
            elseif ($xmlObj->getName() == 'evento' || isset($xmlObj->infEvento)) {
                $tipoDoc = 'EVENTO';
                $tipoFluxo = 'EVENTO';

                // Tenta extrair ID único do evento para usar como chave e evitar colisão
                $eventoId = (string)($xmlObj->infEvento['Id'] ?? '');
                if ($eventoId) {
                    $chave = $eventoId; // Substitui chave da nota pela chave do evento
                } else {
                    // Fallback se não tiver ID: criar chave composta
                    $chave = $chave . '_EVT_' . $nsu;
                }

                // Checar Cancelamento
                $isCancelamento = false;
                if (isset($xmlObj->infEvento->pedRegEvento->e101101)) {
                    $isCancelamento = true;
                    $status = 'CANCELADA';
                } elseif (stripos($xmlContent, 'Cancelamento') !== false) {
                    $isCancelamento = true;
                    $status = 'CANCELADA';
                } else {
                    // Outros eventos (ex: Análise Fiscal, Carta de Correção)
                    $status = 'EVENTO';
                }

                if ($isCancelamento) {
                    $chaveRef = (string)($xmlObj->infEvento->pedRegEvento->infPedReg->chNFSe ?? '');
                    
                    // Fallback para encontrar chave referenciada
                    if (!$chaveRef && isset($xmlObj->infEvento->chNFSe)) {
                         $chaveRef = (string)$xmlObj->infEvento->chNFSe;
                    }

                    if ($chaveRef) {
                        // Atualiza nota original para CANCELADA (nfse_nacional_docs usa feminino)
                        $this->db->query("UPDATE nfse_nacional_docs SET status = 'CANCELADA' WHERE chave_acesso = '$chaveRef' AND tipo_documento = 'NFSE'");

                        // SYNC PEDIDOS (LOCAL)
                        // Tenta encontrar o DPS associado a esta chave para atualizar o pedido local
                        $resDps = $this->db->query("SELECT numero_dps FROM nfse_nacional_docs WHERE chave_acesso = '$chaveRef' LIMIT 1");
                        if ($resDps && $rowDps = $resDps->fetch_assoc()) {
                            $dpsNum = $rowDps['numero_dps'];
                            if ($dpsNum) {
                                // Pedidos usa ENUM masculino (CANCELADO)
                                $this->db->query("UPDATE pedidos SET nfse_status = 'CANCELADO' WHERE nfse_numero = '$dpsNum'");
                            }
                        }
                    }
                }
            }
        }

        // Insere ou Atualiza no Banco
        $stmt = $this->db->prepare("INSERT INTO nfse_nacional_docs 
            (nsu, chave_acesso, numero_nfse, numero_dps, data_emissao, valor_liquido, valor_servico, xml_conteudo, status, tipo_documento, tipo_fluxo,
             tomador_nome, tomador_doc, prestador_nome, prestador_doc, municipio_emissao_descricao, competencia) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            xml_conteudo = VALUES(xml_conteudo), 
            status = IF(status = 'CANCELADA', 'CANCELADA', VALUES(status)), 
            tipo_documento = VALUES(tipo_documento), 
            tipo_fluxo = VALUES(tipo_fluxo), 
            numero_dps = VALUES(numero_dps),
            numero_nfse = VALUES(numero_nfse),
            data_emissao = VALUES(data_emissao),
            valor_liquido = VALUES(valor_liquido),
            valor_servico = VALUES(valor_servico),
            tomador_nome = VALUES(tomador_nome),
            tomador_doc = VALUES(tomador_doc),
            prestador_nome = VALUES(prestador_nome),
            prestador_doc = VALUES(prestador_doc),
            municipio_emissao_descricao = VALUES(municipio_emissao_descricao),
            competencia = VALUES(competencia)");

        $stmt->bind_param(
            'issssddssssssssss',
            $nsu,
            $chave,
            $numero,
            $numeroDps,
            $dataEmissao,
            $valor,
            $valorServico,
            $xmlContent,
            $status,
            $tipoDoc,
            $tipoFluxo,
            $tomador_nome,
            $tomador_doc,
            $prestador_nome,
            $prestador_doc,
            $municipio_emissao_descricao,
            $competencia
        );

        $success = $stmt->execute();
        
        if ($success) {
            // Tentar baixar PDF Oficial (apenas se for AUTORIZADA e tiver chave)
            // REMOVIDO TEMPORARIAMENTE: O download em massa causa timeout/travamento (Erros 503 na API).
            // O download sera feito sob demanda no clique "Visualizar PDF".
            /*
            if (!empty($chave) && $status == 'AUTORIZADA') {
                try {
                    // Verificar se já existe PDF para evitar re-download desnecessário em sync repetido
                    // Porém, se o usuário está rodando sync, pode estar querendo atualizar.
                    // Vamos verificar se o campo pdf_conteudo está vazio.
                    $checkPdf = $this->db->query("SELECT id, pdf_conteudo FROM nfse_nacional_docs WHERE chave_acesso = '$chave'");
                    $hasPdf = false;
                    if ($rowPdf = $checkPdf->fetch_assoc()) {
                        if (!empty($rowPdf['pdf_conteudo'])) {
                            $hasPdf = true;
                        }
                    }

                    if (!$hasPdf) {
                        require_once __DIR__ . '/../Libraries/Nfse/SefinNacionalClient.php';
                        
                        file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync_flow.txt', "Auto-PDF Tentativa: $chave\n", FILE_APPEND);
                        
                        $sefinPdf = $this->_getSefinClient();
                        
                        // Pequeno delay para evitar rate limit agressivo se for loop rápido
                        usleep(200000); // 0.2s

                        $pdfResp = $sefinPdf->baixarDanfse($chave);
                        
                        if ($pdfResp['success'] && !empty($pdfResp['pdf_content'])) {
                             $stmtPdf = $this->db->prepare("UPDATE nfse_nacional_docs SET pdf_conteudo = ? WHERE chave_acesso = ?");
                             $stmtPdf->bind_param("ss", $pdfResp['pdf_content'], $chave);
                             $stmtPdf->execute();
                             file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync_flow.txt', "Auto-PDF OK: $chave\n", FILE_APPEND);
                        } else {
                             file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync_flow.txt', "Auto-PDF Falha: $chave | " . json_encode($pdfResp) . "\n", FILE_APPEND);
                        }
                    } else {
                        file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_sync_flow.txt', "Auto-PDF Skip (Ja existe): $chave\n", FILE_APPEND);
                    }
                } catch (Exception $e) {
                    file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_pdf_sync_auto.txt', "Auto-PDF Erro: $chave | " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
            */

            return [
                'nsu' => $nsu,
                'chave' => $chave,
                'numero_nfse' => $numero,
                'numero_dps' => $numeroDps,
                'data_emissao' => $dataEmissao
            ];
        }
        return false;
    }

    /**
     * Gera QR Code em Base64 usando API externa
     * Evita erro de 'Image could not be read' no DomPDF
     */
    private function _getQrCodeBase64($content) {
        // Tenta usar api.qrserver.com (Estável e simples)
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($content);
        
        $arrContextOptions = [
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
            "http" => [
                "timeout" => 5
            ]
        ];  
        
        try {
            $img = @file_get_contents($url, false, stream_context_create($arrContextOptions));
            if ($img) {
                return 'data:image/png;base64,' . base64_encode($img);
            }
        } catch (Exception $e) {
            // Silencioso
        }
        
        return null;
    }
}
