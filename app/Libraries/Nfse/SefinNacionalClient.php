<?php
namespace App\Libraries\Nfse;

use DOMDocument;
use Exception;
use SimpleXMLElement;

class SefinNacionalClient {
    const VERSION = 'DEBUG_2026_01_06_V18_FIX_XML_GZIP';

    private $certFile;
    private $certPassword;
    private $environment; // 'producao' or 'homologacao'
    private $customUrl = null;
    
    private $usuario;
    private $senha;

    private $privateKey;
    private $certContent;
    
    // Endpoints baseados na documentação
    const URL_PRODUCAO = 'https://sefin.nfse.gov.br'; // Descoberto via Probe: sefin.nfse.gov.br
    // const URL_HOMOLOGACAO = 'https://adn.producaorestrita.nfse.gov.br'; // REMOVIDO: Apenas Produção

    public function __construct($certFile, $certPassword, $environment = 'producao') {
        if (!file_exists($certFile)) {
            throw new Exception("Certificado não encontrado: $certFile");
        }
        $this->certFile = $certFile;
        $this->certPassword = $certPassword;
        $this->environment = 'producao'; // Forçado para Produção
        
        $this->loadCertificado();
    }

    private $extracerts = [];

    private function loadCertificado() {
        if (!file_exists($this->certFile)) return false;
        
        $pfxContent = file_get_contents($this->certFile);
        $certs = [];
        if (openssl_pkcs12_read($pfxContent, $certs, $this->certPassword)) {
            $this->privateKey = $certs['pkey'];
            $this->certContent = $certs['cert']; // X509 Certificate
            $this->extracerts = $certs['extracerts'] ?? [];
            return true;
        }
        return false;
    }

    public function setAuth($usuario, $senha) {
        $this->usuario = $usuario;
        $this->senha = $senha;
    }

    public function setCustomUrl($url) {
        $this->customUrl = $url;
    }

    private function getBaseUrl() {
        if ($this->customUrl) return $this->customUrl;
        return self::URL_PRODUCAO;
    }

    public function checkConnection() {
        // Tenta consultar DFe (NSU 0) para validar credenciais e conexão
        $res = $this->distribuirDfe(0);
        
        if ($res['success']) {
            return [
                'success' => true, 
                'message' => 'Conexão estabelecida com sucesso.',
                'http_code' => 200
            ];
        }
        
        return [
            'success' => false, 
            'message' => 'Falha na conexão: ' . ($res['message'] ?? 'Erro desconhecido'),
            'http_code' => 0
        ];
    }

    /**
     * Gera o XML da DPS usando SimpleXMLElement para garantir estrutura limpa
     */
    public function gerarDpsXml($dados) {
        // Log para debug
        file_put_contents('d:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt', "=== GERANDO XML (SimpleXML) ===\n", FILE_APPEND);

        $ns = 'http://www.sped.fazenda.gov.br/nfse';
        
        // Versão 1.01 (Alinhado com NacionalClient)
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><DPS xmlns=\"$ns\" versao=\"1.01\"></DPS>");
        
        // infDPS
        $infDPS = $xml->addChild('infDPS');
        
        // ID
        $cnpjPrestador = $dados['prestador']['cnpj'] ?? '';
        $nSerie = str_pad($dados['nSerie'] ?? '1', 5, '0', STR_PAD_LEFT);
        
        $nDpsInput = $dados['nDPS'] ?? time();
        
        // CORREÇÃO DEFINITIVA (Esperada):
        // 1. Tag nDPS deve ser sem zeros (Schema Integer).
        // 2. ID deve ter nDPS com zeros (Schema Pattern).
        // 3. Validação E0004 falhava porque o Tipo de Inscrição (3º campo do ID) estava errado (1 em vez de 2 para CNPJ).
        
        $nDpsPad = str_pad($nDpsInput, 15, '0', STR_PAD_LEFT); 
        $nDpsVal = (string)(int)$nDpsInput; // Tag SEM zeros
        $idDpsComponent = $nDpsPad; // ID COM zeros
        
        $cMunPrest = $dados['prestador']['cMun'] ?? '';
        
        // Tipo de Inscrição no ID: 1=CPF, 2=CNPJ
        // Tipo de Emitente no XML: 1=Prestador, 2=Tomador...
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpjPrestador);
        $tpInsc = (strlen($cnpjLimpo) == 14) ? '2' : '1'; 
        
        // ID: DPS + cMun(7) + tpInsc(1) + CNPJ(14) + serie(5) + nDPS(15)
        $idDps = "DPS" . $cMunPrest . $tpInsc . $cnpjLimpo . $nSerie . $idDpsComponent;
        
        $infDPS->addAttribute('Id', $idDps);
        
        // Tags Iniciais
        $tpAmb = '1'; // Produção
        $infDPS->addChild('tpAmb', $tpAmb);
        
        $dhEmi = $dados['dEmissao'] ?? date('Y-m-d\TH:i:s');
        // Fix: Check for timezone offset properly (looking for + or - after the time part)
        // If length is 19 (Y-m-dTH:i:s), it has no timezone.
        if (strlen($dhEmi) <= 19) {
             $dhEmi .= '-03:00';
        }
        $infDPS->addChild('dhEmi', $dhEmi);
        $infDPS->addChild('verAplic', 'BMLab_1.0');
        $infDPS->addChild('serie', $nSerie);
        $infDPS->addChild('nDPS', $nDpsVal); // XML Sem Zeros
        
        $dCompet = $dados['dCompetencia'] ?? date('Y-m-d');
        $infDPS->addChild('dCompet', $dCompet);
        // tpEmit sempre 1 (Prestador) para BMLab emissor
        $infDPS->addChild('tpEmit', '1');
        
        $cLocEmi = $dados['prestador']['cMun'] ?? ($dados['cLocEmi'] ?? ''); 
        if (!empty($cLocEmi)) {
            $infDPS->addChild('cLocEmi', $cLocEmi);
        }
        
        // Prestador
        $prest = $infDPS->addChild('prest');
        $prest->addChild('CNPJ', $cnpjLimpo);
        $im = $dados['prestador']['im'] ?? '3003410'; 
        $prest->addChild('IM', $im);
        
        if (!empty($dados['prestador']['fone'])) $prest->addChild('fone', $dados['prestador']['fone']);
        if (!empty($dados['prestador']['email'])) $prest->addChild('email', $dados['prestador']['email']);
        
        // regTrib
        $regTrib = $prest->addChild('regTrib');
        $opSimpNac = '3'; // Default para ME/EPP
        if (!empty($dados['prestador']['opSimpNac'])) {
            $opSimpNac = (string)$dados['prestador']['opSimpNac'];
        } elseif (isset($dados['prestador']['cRegTrib']) && (string)$dados['prestador']['cRegTrib'] === '1') {
            $opSimpNac = '1';
        }
        $regTrib->addChild('opSimpNac', $opSimpNac);
        
        // regApTribSN só deve existir se opSimpNac != 1 (Não Optante)
        if ($opSimpNac !== '1') {
            $regTrib->addChild('regApTribSN', '1'); // Default 1 (Regime de apuração dos tributos federais e municipal pelo SN)
        }

        $regTrib->addChild('regEspTrib', '0');
        
        // Tomador
        $toma = $infDPS->addChild('toma');
        if (!empty($dados['tomador']['cpf'])) {
            $toma->addChild('CPF', preg_replace('/[^0-9]/', '', $dados['tomador']['cpf']));
        } elseif (!empty($dados['tomador']['cnpj'])) {
            $toma->addChild('CNPJ', preg_replace('/[^0-9]/', '', $dados['tomador']['cnpj']));
        }
        $toma->addChild('xNome', substr($dados['tomador']['nome'], 0, 60));
        
        if (!empty($dados['tomador']['endereco'])) {
            $end = $toma->addChild('end');
            $endNac = $end->addChild('endNac');
            $endNac->addChild('cMun', $dados['tomador']['endereco']['cMun']);
            $endNac->addChild('CEP', preg_replace('/[^0-9]/', '', $dados['tomador']['endereco']['cep']));
            
            $end->addChild('xLgr', substr($dados['tomador']['endereco']['xLgr'], 0, 60));
            $end->addChild('nro', substr($dados['tomador']['endereco']['nro'], 0, 10));
            if (!empty($dados['tomador']['endereco']['xCpl'])) {
                $end->addChild('xCpl', substr($dados['tomador']['endereco']['xCpl'], 0, 60));
            }
            $end->addChild('xBairro', substr($dados['tomador']['endereco']['xBairro'], 0, 60));
        }
        
        // Serviço
        $serv = $infDPS->addChild('serv');
        if (!empty($dados['servico']['cLocPrestacao'])) {
            $locPrest = $serv->addChild('locPrest');
            $locPrest->addChild('cLocPrestacao', $dados['servico']['cLocPrestacao']);
        }
        
        $cServ = $serv->addChild('cServ');
        if (!empty($dados['servico']['cTribNac'])) {
            $cServ->addChild('cTribNac', $dados['servico']['cTribNac']);
        }
        $xDescServ = $dados['servico']['xDescServ'] ?? 'Serviços Prestados';
        $cServ->addChild('xDescServ', mb_substr($xDescServ, 0, 2000, 'UTF-8'));
        if (!empty($dados['servico']['cNBS'])) {
            $cServ->addChild('cNBS', $dados['servico']['cNBS']);
        }
        
        // Valores
        $valores = $infDPS->addChild('valores');
        $vServPrest = $valores->addChild('vServPrest');
        $vServValor = $dados['servico']['vServ'] ?? 0.00;
        $vServPrest->addChild('vServ', number_format($vServValor, 2, '.', ''));
        
        if (!empty($dados['valores']['vDescCond'])) {
            $vServPrest->addChild('vDescCond', number_format($dados['valores']['vDescCond'], 2, '.', ''));
        }
        if (!empty($dados['valores']['vDescIncond'])) {
            $vServPrest->addChild('vDescIncond', number_format($dados['valores']['vDescIncond'], 2, '.', ''));
        }
        
        $trib = $valores->addChild('trib');
        $tribMun = $trib->addChild('tribMun');
        $tribMun->addChild('tribISSQN', '1'); // 1=Normal, 2=Isenção, 3=Exportação, 4=Imunidade... (Simplificado para 1 por enquanto, ou mapear do form)
        
        $tpRetISSQN = (isset($dados['valores']['issRetido']) && $dados['valores']['issRetido']) ? '1' : '2'; // 1=Retido, 2=Não Retido
        $tribMun->addChild('tpRetISSQN', $tpRetISSQN);
        
        // pAliq (Alíquota)
        $pAliq = $dados['valores']['aliquota'] ?? 0.00;
        $tribMun->addChild('pAliq', number_format($pAliq, 2, '.', ''));
        
        // Tributos Federais
        $camposFed = ['vPis' => 'vPIS', 'vCofins' => 'vCOFINS', 'vInss' => 'vINSS', 'vIr' => 'vIRRF', 'vCsll' => 'vCSLL'];
        $temValorFed = false;
        foreach ($camposFed as $key => $tag) {
            if (!empty($dados['valores'][$key]) && $dados['valores'][$key] > 0) {
                $temValorFed = true;
                break;
            }
        }
        
        if ($temValorFed) {
            $tribFed = $trib->addChild('tribFed');
            foreach ($camposFed as $key => $tag) {
                if (!empty($dados['valores'][$key]) && $dados['valores'][$key] > 0) {
                    $tribFed->addChild($tag, number_format($dados['valores'][$key], 2, '.', ''));
                }
            }
        }

        // totTrib (Obrigatório)
        $totTrib = $trib->addChild('totTrib');
        
        // vTotTrib agora é um container, não um valor direto
        $vTotTribNode = $totTrib->addChild('vTotTrib');
        $vTotTribNode->addChild('vTotTribFed', '0.00');
        $vTotTribNode->addChild('vTotTribEst', '0.00');
        $vTotTribNode->addChild('vTotTribMun', '0.00');
        
        // Retorna XML String
        return $xml->asXML();
    }

    public function signXml($xml) {
        if (!$this->privateKey) {
            throw new Exception("Chave privada não carregada para assinatura.");
        }

        // Carregar XML
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        // Canonicalizar para hash (C14N)
        $rootTag = 'infDPS';
        $infNode = $dom->getElementsByTagName('infDPS')->item(0);
        
        if (!$infNode) {
            $infNode = $dom->getElementsByTagName('infEvento')->item(0);
            $rootTag = 'infEvento';
        }
        
        if (!$infNode) throw new Exception("Tag infDPS ou infEvento não encontrada para assinatura.");
        
        $id = $infNode->getAttribute('Id');
        $canonical = $infNode->C14N(true, false, null, null);

        // Digest (SHA-256) - Correção para E0714
        $digest = base64_encode(hash('sha256', $canonical, true));

        // Montar Bloco Signature
        $sigNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        $dom->documentElement->appendChild($sigNode);

        $signedInfo = $dom->createElement('SignedInfo');
        $sigNode->appendChild($signedInfo);

        $canonMethod = $dom->createElement('CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);

        $sigMethod = $dom->createElement('SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sigMethod);

        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', "#$id");
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $trans1 = $dom->createElement('Transform');
        $trans1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($trans1);

        $trans2 = $dom->createElement('Transform');
        $trans2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($trans2);

        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);

        $digestVal = $dom->createElement('DigestValue', $digest);
        $reference->appendChild($digestVal);

        // Assinar SignedInfo (SHA-256)
        $c14nSignedInfo = $signedInfo->C14N(true, false, null, null);
        
        $signature = '';
        if (!openssl_sign($c14nSignedInfo, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Erro ao gerar assinatura OpenSSL (SHA-256).");
        }
        $signatureValue = base64_encode($signature);

        $sigValNode = $dom->createElement('SignatureValue', $signatureValue);
        $sigNode->appendChild($sigValNode);

        $keyInfo = $dom->createElement('KeyInfo');
        $sigNode->appendChild($keyInfo);
        
        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);
        
        // Limpar headers do certificado para X509Certificate
        $certClean = $this->certContent;
        $certClean = str_replace("-----BEGIN CERTIFICATE-----", "", $certClean);
        $certClean = str_replace("-----END CERTIFICATE-----", "", $certClean);
        $certClean = str_replace("\n", "", $certClean);
        $certClean = str_replace("\r", "", $certClean);
        
        $x509Cert = $dom->createElement('X509Certificate', $certClean);
        $x509Data->appendChild($x509Cert);

        return $dom->saveXML();
    }

    public function emitirDps($xmlDps) {
        $logFile = 'd:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt';
        
        // Log de Versão e Início
        file_put_contents($logFile, "=== NOVA EXECUÇÃO: " . date('Y-m-d H:i:s') . " | VERSION: " . self::VERSION . " ===\n", FILE_APPEND);

        // 0. Assinar XML se necessário
        if (strpos($xmlDps, '<Signature') === false && strpos($xmlDps, ':Signature') === false) {
            $xmlDps = $this->signXml($xmlDps);
        }
        
        // Limpeza final XML
        $xmlDps = trim($xmlDps);
        $xmlDps = preg_replace('/^\xEF\xBB\xBF/', '', $xmlDps);

        // Extrair ID do DPS para recuperação automática de erro E0014
        $idDpsRecovery = '';
        if (preg_match('/Id="([^"]+)"/', $xmlDps, $matches)) {
            $idDpsRecovery = $matches[1];
        }

        // Extrair dados para validação de conflito (Evitar recuperar DPS de outro tomador)
        $inputCpfCnpj = '';
        $inputValor = 0.0;
        
        if (preg_match('/<CNPJ>([0-9]+)<\/CNPJ>/', $xmlDps, $mCnpj)) {
            $inputCpfCnpj = $mCnpj[1];
        } elseif (preg_match('/<CPF>([0-9]+)<\/CPF>/', $xmlDps, $mCpf)) {
            $inputCpfCnpj = $mCpf[1];
        }
        
        if (preg_match('/<vServ>([0-9\.]+)<\/vServ>/', $xmlDps, $mVal)) {
            $inputValor = (float)$mVal[1];
        }

        // 1. Configurações de Timeout e Memória para evitar interrupções
        set_time_limit(300); // 5 minutos
        ini_set('memory_limit', '512M');

        // 2. Lista de Hosts (Prioridade para o Oficial ADN - Produção)
        $hosts = [];
        // Oficial Nacional (Prioritário)
        $hosts[] = 'https://sefin.nfse.gov.br'; // Validado em testes
        // Fallbacks
        $hosts[] = 'https://adn.nfse.gov.br';

        // 3. Caminhos possíveis
        $paths = [
            '/sefinnacional/nfse',   // Validado em testes (JSON_LIB_KEY -> 400 E6154)
            '/contribuintes/v1/dps', 
            '/api/contribuintes/v1/dps', 
            '/nfse',                 
            '/nfse/v1/dps',          
            '/api/nfse',             
        ];

        // 4. Modos de Envio "Dual Mode"
        // O XML deve ser compactado (GZIP) antes de qualquer coisa
        $gzXml = gzencode($xmlDps, 9, FORCE_GZIP); // Force GZIP Header and Max Compression
        $base64GzXml = base64_encode($gzXml);

        $modes = [
            // Modo 1: JSON com Chave da Biblioteca (Prioritário - Validado: Retorna E6154/Sucesso)
            'JSON_LIB_KEY' => [
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'payload' => json_encode(['dpsXmlGZipB64' => $base64GzXml]),
                'no_auth' => true 
            ],
            // Modo 3: GZIP Binário Direto
            'GZIP_BIN' => [
                'headers' => ['Content-Type: application/xml', 'Content-Encoding: gzip'],
                'payload' => $gzXml
            ],
            // Modo 2: JSON Encapsulado
            'JSON_ENC' => [
                'headers' => ['Content-Type: application/json'], 
                'payload' => json_encode(['dps' => $base64GzXml]) 
            ]
        ];

        $lastError = "";
        $lastResponse = "";
        $lastHttpCode = 0;

        foreach ($hosts as $host) {
            foreach ($paths as $path) {
                $url = $host . $path;
                
                foreach ($modes as $modeName => $modeConfig) {
                    // Prepara Payload
                    $payload = $modeConfig['payload'];
                    
                    // Log
                    $logMsg = "Tentando URL ($modeName): " . $url . " | Size: " . strlen($payload);
                    file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);

                    // cURL
                    $ch = curl_init($url);
                    
                    // SSL e Certificado
                    if ($this->certContent && $this->privateKey) {
                        // Salva PEM temporário contendo CHAVE PRIVADA + CERTIFICADO + CADEIA
                        // Usar diretório específico do projeto para evitar erros de permissão no Windows/Apache
                        $tempDir = 'd:/xampp/htdocs/bmlab/temp_certs';
                        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
                        
                        $pemFile = tempnam($tempDir, 'nfse_cert_');
                        
                        $fullPem = $this->privateKey . "\n" . $this->certContent;
                        
                        // Adicionar cadeia de certificados (extracerts)
                        if (!empty($this->extracerts)) {
                            foreach ($this->extracerts as $ec) {
                                $fullPem .= "\n" . $ec;
                            }
                        }
                        
                        file_put_contents($pemFile, $fullPem);
                        
                        curl_setopt($ch, CURLOPT_SSLCERT, $pemFile);
                        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLKEY, $pemFile); // Explicitly set Key
                        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                    }

                    // Headers e Opções
                    $authHeader = [];
                    if (!isset($modeConfig['no_auth']) || !$modeConfig['no_auth']) {
                        $authHeader = ["Authorization: Basic " . base64_encode($this->usuario . ":" . $this->senha)];
                    }
                    
                    $headers = array_merge($modeConfig['headers'], $authHeader);

                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Debug
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Debug
                    curl_setopt($ch, CURLOPT_VERBOSE, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout aumentado para 120s
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Timeout de conexão
                    
                    // Debug cURL
                    $fp = fopen('d:/xampp/htdocs/bmlab/nfse_curl_debug.log', 'w+');
                    curl_setopt($ch, CURLOPT_STDERR, $fp);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    
                    curl_close($ch);
                    if (isset($pemFile) && file_exists($pemFile)) unlink($pemFile);
                    fclose($fp);

                    // Log Resultado
                    $logResponseSnippet = substr(str_replace(["\r", "\n"], " ", $response), 0, 500);
                    file_put_contents($logFile, "Result ($modeName): HTTP $httpCode | Error: $curlError | Body: $logResponseSnippet\n", FILE_APPEND);

                    $lastResponse = $response;
                    
                    // Analisa Sucesso (200, 201 ou 202)
                    if (($httpCode >= 200 && $httpCode <= 202) && !empty($response)) {
                        return [
                            'success' => true,
                            'message' => 'Enviado com sucesso',
                            'response_body' => $response,
                            'http_code' => $httpCode
                        ];
                    }
                    
                    // Analisa Erro JSON
                    if (!empty($response)) {
                        $jsonResp = json_decode($response, true);
                        if ($jsonResp && isset($jsonResp['erros'])) {
                            $errMsgs = [];
                            foreach ($jsonResp['erros'] as $err) {
                                $errMsgs[] = $err['Codigo'] . " - " . $err['Descricao'];
                            }
                            $lastError = implode(" | ", $errMsgs);
                        } else {
                            $lastError = "HTTP $httpCode: " . substr($response, 0, 200);
                        }
                    } else {
                        $lastError = "HTTP $httpCode (Sem resposta) - Curl: $curlError";
                    }

                    // Se não for 404 (endpoint não encontrado) e não for erro de conexão (0),
                    // assume que achou o endpoint certo e para de tentar outros hosts/paths
                    if ($httpCode != 404 && $httpCode != 0) {
                        break 3;
                    }
                }
            }
        }

        // Se chegou aqui, falhou tudo

        // RECUPERAÇÃO AUTOMÁTICA DE ERRO E0014 (DPS JÁ ENVIADA)
        if (strpos($lastError, 'E0014') !== false && !empty($idDpsRecovery)) {
            $logMsg = "Erro E0014 detectado. Tentando recuperar DPS: $idDpsRecovery";
            file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
            
            $recup = $this->consultarDps($idDpsRecovery);
             
             if ($recup['success'] && isset($recup['data']['chaveAcesso'])) {
                 // VALIDAÇÃO DE SEGURANÇA: Verificar se o DPS recuperado pertence ao mesmo tomador
                 $rawResp = $recup['raw'] ?? '';
                 // Busca simples pelo CPF/CNPJ no JSON de resposta para evitar conflitos de numeração
                 if (!empty($inputCpfCnpj) && strpos($rawResp, $inputCpfCnpj) === false) {
                     $logMsg = "CRÍTICO: DPS $idDpsRecovery recuperada na API pertence a OUTRO tomador! (Input: $inputCpfCnpj não encontrado na resposta)";
                     file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
                     
                     return [
                        'success' => false,
                        'message' => "CONFLITO DE SEQUÊNCIA: O número de DPS $idDpsRecovery já foi utilizado por outro cliente. Por favor, contate o suporte para ajuste do sequencial.",
                        'response_body' => $lastResponse
                     ];
                 }

                 $logMsg = "Recuperação bem-sucedida! Chave: " . $recup['data']['chaveAcesso'];
                 file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
                 
                 return [
                     'success' => true,
                     'message' => 'DPS já existia. Recuperada com sucesso.',
                     'response_body' => $recup['raw'],
                     'http_code' => 200
                 ];
             } else {
                 // Fallback: Busca Local de XML (Caso API retorne 404 por delay)
                 $logMsg = "Recuperação API falhou (HTTP 404?). Tentando busca local de XML...";
                 file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
                 
                 $files = glob('d:/xampp/htdocs/bmlab/*.xml');
                 if ($files) {
                     usort($files, function($a, $b) {
                         return filemtime($b) - filemtime($a);
                     });
                     $files = array_slice($files, 0, 50); // Últimos 50 arquivos
                     
                     foreach ($files as $file) {
                         $content = file_get_contents($file);
                         if (strpos($content, $idDpsRecovery) !== false) {
                             if (preg_match('/infNFSe Id="NFS([0-9]+)"/', $content, $matchesKey)) {
                                 // VALIDAÇÃO DE SEGURANÇA LOCAL
                                 // Verifica se CPF/CNPJ do arquivo bate com o input
                                 if (!empty($inputCpfCnpj) && strpos($content, $inputCpfCnpj) === false) {
                                     $logMsg = "CRÍTICO: XML Local encontrado ($file) mas pertence a OUTRO tomador! Ignorando.";
                                     file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
                                     continue; // Pula este arquivo, não é o nosso
                                 }
                                 
                                 // Verifica Valor (Opcional, mas recomendado)
                                 // Formata valor input para buscar (ex: 25.00 -> 25.00) - XML geralmente usa ponto
                                 // Mas pode ser complexo validar string exata. CPF já é um bom filtro.

                                 $chaveEncontrada = $matchesKey[1];
                                 
                                 $logMsg = "Recuperação Local bem-sucedida! Arquivo: " . basename($file);
                                 file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
                                 
                                 return [
                                    'success' => true,
                                    'message' => 'DPS já existia. Recuperada via XML Local.',
                                    // Simula estrutura de resposta que o Controller espera
                                    'response_body' => json_encode([
                                        'chaveAcesso' => $chaveEncontrada,
                                        'idDps' => $idDpsRecovery // Opcional
                                    ]),
                                    'http_code' => 200
                                 ];
                             }
                         }
                     }
                 }
             }
        }

        return [
            'success' => false,
            'message' => "Falha em todas as tentativas. Último erro: " . $lastError,
            'response_body' => $lastResponse
        ];
    }

    /**
     * Consulta NFSe pela Chave de Acesso
     * Endpoints preferenciais:
     *  - GET /contribuintes/nfse/{chaveAcesso}
     *  - Fallback: /contribuintes/v1/nfse/{chaveAcesso}
     */
    public function consultarNfse($chaveAcesso) {
        // Novo padrão oficial (sem /v1)
        $res = $this->executeGet("/contribuintes/nfse/{$chaveAcesso}");
        if (!$res['success']) {
            // Compatibilidade com versões antigas da API
            $res = $this->executeGet("/contribuintes/v1/nfse/{$chaveAcesso}");
        }
        return $res;
    }

    /**
     * Consulta DPS pelo ID
     * Endpoints preferenciais:
     *  - GET /contribuintes/dps/{idDps}
     *  - Fallback: /contribuintes/v1/dps/{idDps}
     */
    public function consultarDps($idDps) {
        $res = $this->executeGet("/contribuintes/dps/{$idDps}");
        if (!$res['success']) {
            $res = $this->executeGet("/contribuintes/v1/dps/{$idDps}");
        }
        return $res;
    }

    /**
     * Distribuição de DFe (Documentos Fiscais Eletrônicos)
     * Endpoint: GET /contribuintes/DFe/{nsu}
     * Retorna lista de documentos a partir do NSU informado
     */
    public function distribuirDfe($nsu = 0) {
        // Tenta endpoint conforme SDK nfse-php
        return $this->executeGet("/contribuintes/DFe/{$nsu}");
    }

    /**
     * Consulta Nota Fiscal por Chave de Acesso
     * Endpoints:
     *  - Preferencial: GET /contribuintes/nfse/{chave}
     *  - Alternativos: /nfse/{chave}, /contribuintes/v1/nfse/{chave}
     */
    public function consultarNotaPorChave($chave) {
        // 1) Novo padrão sob /contribuintes
        $res = $this->executeGet("/contribuintes/nfse/{$chave}");
        if ($res['success']) {
            return $res;
        }

        // 2) Endpoint legado /nfse/{chave}
        $res = $this->executeGet("/nfse/{$chave}");
        if ($res['success']) {
            return $res;
        }

        // 3) Versão antiga com /v1
        return $this->executeGet("/contribuintes/v1/nfse/{$chave}");
    }

    /**
     * Helper para detectar ambiente pela chave de acesso
     * 8º dígito: 1=Produção, 2=Homologação
     * AGORA FORÇADO PARA PRODUÇÃO (Ambiente Único)
     */
    private function getEnvironmentFromKey($chave) {
        return 'producao';
    }

    /**
     * Baixa o PDF do DANFSe (Layout Oficial Nacional)
     * Endpoints preferenciais (sob /contribuintes):
     *  - GET /contribuintes/nfse/{chaveAcesso}/danfse
     *  - Alternativos: /nfse/{chaveAcesso}/danfse, /danfse/{chaveAcesso}
     */
    public function baixarDanfse($chaveAcesso) {
        set_time_limit(180);
        $logFile = 'd:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt';
        
        $env = $this->getEnvironmentFromKey($chaveAcesso);
        
        $hosts = [];
        // Apenas Produção (Prioridade ADN para PDF, conforme documentação nova e fóruns)
        // O host 'sefin' tem retornado 501 (Not Implemented) para endpoints de PDF recentemente.
        $hosts[] = 'https://adn.nfse.gov.br';
        $hosts[] = 'https://sefin.nfse.gov.br';
            
        $lastResult = [
            'success' => false,
            'message' => 'Nenhum host disponível.'
        ];

        // Caminhos possíveis dentro de cada host, na ordem de preferência
        // Atualizado: Prioridade para o endpoint curto /danfse/{chave} (Novo Padrão)
        $paths = [
            "/danfse/{$chaveAcesso}",
            "/contribuintes/nfse/{$chaveAcesso}/danfse",
            "/contribuintes/v1/nfse/{$chaveAcesso}/danfse",
            "/nfse/{$chaveAcesso}/danfse",
        ];

        foreach ($hosts as $host) {
            foreach ($paths as $path) {
                $url = $host . $path;
                
                file_put_contents($logFile, "GET PDF DANFSe (Host: $host Path: $path): $url\n", FILE_APPEND);
        
                $maxRetries = 3; // Reduzido para evitar Timeout de 120s (Fatal Error)
                $attempt = 0;
                $response = false;
                $httpCode = 0;
                $sleepTime = 1; // Começa mais rápido
        
                do {
                    $attempt++;
                    
                    $ch = curl_init($url);
            
                    // SSL e Certificado (Reuso da Lógica Split PEM)
                    if ($this->certContent && $this->privateKey) {
                        $tempDir = 'd:/xampp/htdocs/bmlab/temp_certs';
                        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
                        
                        $pemFile = tempnam($tempDir, 'nfse_cert_pdf_');
                        
                        $fullPem = $this->privateKey . "\n" . $this->certContent;
                        if (!empty($this->extracerts)) {
                            foreach ($this->extracerts as $ec) {
                                $fullPem .= "\n" . $ec;
                            }
                        }
                        file_put_contents($pemFile, $fullPem);
                        
                        curl_setopt($ch, CURLOPT_SSLCERT, $pemFile);
                        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLKEY, $pemFile);
                        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                    }
            
                    // Headers (Aceitar PDF)
                    $headers = [
                        'Accept: application/pdf',
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Cache-Control: no-cache',
                        'Connection: close' // keep-alive pode dar timeout em WAF
                    ];
            
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_VERBOSE, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
                    curl_setopt($ch, CURLOPT_ENCODING, ""); // Aceitar gzip/deflate
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    $curlError = curl_error($ch);

                    curl_close($ch);
                    if (isset($pemFile) && file_exists($pemFile)) unlink($pemFile);
        
                    // Sucesso se 200 e é PDF
                    if ($httpCode == 200 && strpos($response, '%PDF') === 0) {
                        return [
                            'success' => true,
                            'pdf_content' => $response,
                            'http_code' => $httpCode
                        ];
                    }
        
                    // Se falhou, espera e tenta de novo (Backoff)
                    if ($attempt < $maxRetries) {
                        // Backoff mais agressivo para instabilidade (502, 503, 429)
                        if ($httpCode == 503 || $httpCode == 429 || $httpCode == 502) {
                             $sleepTime = max($sleepTime, 3); // Força espera mínima de 3s
                        }

                        file_put_contents($logFile, "GET PDF Retry $attempt/$maxRetries ($host $path): HTTP $httpCode | Type: $contentType | Error: $curlError | Wait {$sleepTime}s\n", FILE_APPEND);
                        
                        // Log body se for erro API (ajuda a diagnosticar 404/500)
                        if ($httpCode >= 400 && $httpCode < 600 && $response) {
                             file_put_contents($logFile, "GET PDF Error Body: " . substr($response, 0, 1000) . "\n", FILE_APPEND);
                        }
                        
                        sleep($sleepTime);
                        $sleepTime *= 2; // Backoff exponencial: 2, 4, 8...
                    }
                } while ($attempt < $maxRetries);

        
                file_put_contents($logFile, "GET PDF Result ($host $path): HTTP $httpCode | Type: $contentType | Size: " . strlen($response) . " bytes\n", FILE_APPEND);
        
                // Guarda último resultado para mensagem de erro
                $lastResult = [
                    'success' => false,
                    'message' => "Falha no host $host, path $path. HTTP $httpCode. Type: $contentType",
                    'http_code' => $httpCode
                ];
            }
        }
        
        return $lastResult;
    }

    /**
     * Executa requisição GET com Autenticação SSL (MTLS) e Fallback/Retry
     */
    private function executeGet($path) {
        $logFile = 'd:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt';
        
        // Hosts para tentativa (Principal e Fallback)
        $hosts = [];
        if ($this->environment === 'producao') {
            $hosts[] = 'https://adn.nfse.gov.br';
            $hosts[] = 'https://sefin.nfse.gov.br'; // Fallback
        } else {
            $hosts[] = 'https://adn.producaorestrita.nfse.gov.br';
        }

        $lastResult = [
            'success' => false,
            'message' => 'Nenhum host disponível.'
        ];

        foreach ($hosts as $host) {
            $url = $host . $path;
            
            file_put_contents($logFile, "GET Request ($host): $url\n", FILE_APPEND);

            // Retry Logic
            $maxRetries = 3;
            $attempt = 0;
            $httpCode = 0;
            $response = false;
            $curlError = '';
            $sleepTime = 2;

            do {
                $attempt++;
                
                $ch = curl_init($url);

                // SSL e Certificado
                $pemFile = null;
                if ($this->certContent && $this->privateKey) {
                    $tempDir = 'd:/xampp/htdocs/bmlab/temp_certs';
                    if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
                    
                    $pemFile = tempnam($tempDir, 'nfse_cert_get_');
                    
                    $fullPem = $this->privateKey . "\n" . $this->certContent;
                    if (!empty($this->extracerts)) {
                        foreach ($this->extracerts as $ec) {
                            $fullPem .= "\n" . $ec;
                        }
                    }
                    file_put_contents($pemFile, $fullPem);
                    
                    curl_setopt($ch, CURLOPT_SSLCERT, $pemFile);
                    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                    curl_setopt($ch, CURLOPT_SSLKEY, $pemFile);
                    curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                }

                // Headers
                $headers = [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: BMLab/1.0'
                ];

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);

                curl_close($ch);
                if (isset($pemFile) && file_exists($pemFile)) unlink($pemFile);

                // Se sucesso (2xx) ou 404 (Endpoint válido mas sem dados), sai do loop de retry
                if (($httpCode >= 200 && $httpCode <= 299) || $httpCode == 404) {
                    break;
                }

                // Se falhou (500, 502, 503, Timeout), espera e tenta de novo
                if ($attempt < $maxRetries) {
                    file_put_contents($logFile, "GET Retry $attempt/$maxRetries ($host): HTTP $httpCode | Error: $curlError | Wait {$sleepTime}s\n", FILE_APPEND);
                    sleep($sleepTime);
                    $sleepTime *= 2;
                }

            } while ($attempt < $maxRetries);

            file_put_contents($logFile, "GET Result ($host): HTTP $httpCode | Error: $curlError | Body: " . substr($response, 0, 200) . "\n", FILE_APPEND);

            // Analisa Resultado
            if (($httpCode >= 200 && $httpCode <= 202) && !empty($response)) {
                return [
                    'success' => true,
                    'data' => json_decode($response, true),
                    'raw' => $response
                ];
            }

            // Tratamento especial para 404 (Nenhum documento localizado)
            if ($httpCode == 404 && !empty($response)) {
                $json = json_decode($response, true);
                if (isset($json['StatusProcessamento']) && $json['StatusProcessamento'] === 'NENHUM_DOCUMENTO_LOCALIZADO') {
                    return [
                        'success' => true,
                        'data' => $json, 
                        'raw' => $response
                    ];
                }
            }

            // Se chegou aqui, falhou neste host. Salva erro e tenta próximo host.
            $lastResult = [
                'success' => false,
                'message' => "Erro HTTP $httpCode no host $host: $curlError",
                'raw' => $response,
                'http_code' => $httpCode
            ];
        }

        return $lastResult;
    }

    /**
     * Método auxiliar para testar endpoints arbitrários (DEBUG)
     */
    /**
     * Gera o XML do Evento de Cancelamento (e101101)
     */
    public function gerarEventoCancelamentoXml($chaveAcesso, $codigoCancelamento, $motivo, $prestadorCnpj, $prestadorMun) {
        $logFile = 'd:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt';
        file_put_contents($logFile, "=== GERANDO XML EVENTO CANCELAMENTO ===\n", FILE_APPEND);

        $ns = 'http://www.sped.fazenda.gov.br/nfse';
        
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><evento xmlns=\"$ns\" versao=\"1.00\"></evento>");
        
        $infEvento = $xml->addChild('infEvento');
        
        // ID do Evento: "ID" + "e" + tpEvento(6) + chNFSe(50) + nSeqEvento(2)
        $tpEvento = '101101'; // Cancelamento
        $nSeqEvento = '01'; // Cancelamento é sempre 01
        
        // Garantir Chave com 50 dígitos
        $chaveAcesso = preg_replace('/[^0-9]/', '', $chaveAcesso);
        
        $idEvento = "IDe{$tpEvento}{$chaveAcesso}{$nSeqEvento}";
        $infEvento->addAttribute('Id', $idEvento);
        
        $infEvento->addChild('cOrgao', $prestadorMun); 
        $infEvento->addChild('tpAmb', '1'); // Produção
        
        $infEvento->addChild('CNPJ', preg_replace('/[^0-9]/', '', $prestadorCnpj));
        
        $infEvento->addChild('chNFSe', $chaveAcesso);
        
        $dhEvento = date('Y-m-d\TH:i:s');
        if (strlen($dhEvento) <= 19) $dhEvento .= '-03:00';
        $infEvento->addChild('dhEvento', $dhEvento);
        
        $infEvento->addChild('tpEvento', $tpEvento);
        $infEvento->addChild('nSeqEvento', $nSeqEvento);
        $infEvento->addChild('verEvento', '1.00');
        
        $detEvento = $infEvento->addChild('detEvento');
        $detEvento->addChild('descEvento', 'Cancelamento');
        $detEvento->addChild('cMotivo', $codigoCancelamento); 
        $detEvento->addChild('xJust', mb_substr($motivo, 0, 255, 'UTF-8'));
        
        return $xml->asXML();
    }

    /**
     * Envia o Evento para a API Nacional
     */
    public function registrarEvento($xmlEvento) {
        $logFile = 'd:/xampp/htdocs/bmlab/nfse_debug_FORCE.txt';
        file_put_contents($logFile, "=== REGISTRANDO EVENTO ===\n", FILE_APPEND);

        // 0. Assinar XML se necessário (infEvento)
        if (strpos($xmlEvento, '<Signature') === false) {
            $xmlEvento = $this->signXml($xmlEvento);
        }
        
        // Limpeza
        $xmlEvento = trim($xmlEvento);
        $xmlEvento = preg_replace('/^\xEF\xBB\xBF/', '', $xmlEvento);

        // Configs
        set_time_limit(300);
        
        // Hosts (Mesma lógica do DPS)
        $hosts = [];
        $hosts[] = 'https://sefin.nfse.gov.br'; 
        $hosts[] = 'https://adn.nfse.gov.br';

        // Paths para Eventos
        $paths = [
            '/contribuintes/v1/eventos',
            '/nfse/v1/eventos',
            '/api/contribuintes/v1/eventos'
        ];

        // Payload (GZIP)
        $gzXml = gzencode($xmlEvento, 9, FORCE_GZIP);
        $base64GzXml = base64_encode($gzXml);

        // Modes (Dual Mode)
        $modes = [
            'JSON_LIB_KEY' => [
                'headers' => ['Content-Type: application/json', 'Accept: application/json'],
                'payload' => json_encode(['eventoXmlGZipB64' => $base64GzXml]), 
                'no_auth' => true 
            ],
             'GZIP_BIN' => [
                'headers' => ['Content-Type: application/xml', 'Content-Encoding: gzip'],
                'payload' => $gzXml
            ]
        ];

        $lastError = "";
        $lastResponse = "";
        $lastHttpCode = 0;

        foreach ($hosts as $host) {
            foreach ($paths as $path) {
                $url = $host . $path;
                
                foreach ($modes as $modeName => $modeConfig) {
                    // Prepara Payload
                    $payload = $modeConfig['payload'];
                    
                    file_put_contents($logFile, "Tentando Evento URL ($modeName): " . $url . "\n", FILE_APPEND);

                    // cURL
                    $ch = curl_init($url);
                    
                    // SSL e Certificado
                    if ($this->certContent && $this->privateKey) {
                        $tempDir = 'd:/xampp/htdocs/bmlab/temp_certs';
                        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
                        
                        $pemFile = tempnam($tempDir, 'nfse_cert_evt_');
                        
                        $fullPem = $this->privateKey . "\n" . $this->certContent;
                        if (!empty($this->extracerts)) {
                            foreach ($this->extracerts as $ec) {
                                $fullPem .= "\n" . $ec;
                            }
                        }
                        file_put_contents($pemFile, $fullPem);
                        
                        curl_setopt($ch, CURLOPT_SSLCERT, $pemFile);
                        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLKEY, $pemFile);
                        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                    }

                    // Headers e Opções
                    $authHeader = [];
                    if (!isset($modeConfig['no_auth']) || !$modeConfig['no_auth']) {
                        $authHeader = ["Authorization: Basic " . base64_encode($this->usuario . ":" . $this->senha)];
                    }
                    
                    $headers = array_merge($modeConfig['headers'], $authHeader);

                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_VERBOSE, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    
                    curl_close($ch);
                    if (isset($pemFile) && file_exists($pemFile)) unlink($pemFile);

                    file_put_contents($logFile, "Result Evento ($modeName): HTTP $httpCode | Error: $curlError\n", FILE_APPEND);
                    $lastResponse = $response;

                    // Analisa Sucesso
                    if (($httpCode >= 200 && $httpCode <= 202) && !empty($response)) {
                        return [
                            'success' => true,
                            'message' => 'Evento enviado com sucesso',
                            'response_body' => $response,
                            'http_code' => $httpCode
                        ];
                    }
                    
                    // Se erro fatal de conexão, continua tentando
                    if (!empty($response)) {
                        $lastError = "HTTP $httpCode: " . substr($response, 0, 200);
                    } else {
                        $lastError = "HTTP $httpCode (Sem resposta) - Curl: $curlError";
                    }

                    if ($httpCode != 404 && $httpCode != 0) {
                        break 3;
                    }
                }
            }
        }

        return [
            'success' => false,
            'message' => "Falha no envio do evento: " . $lastError,
            'response_body' => $lastResponse
        ];
    }
}
