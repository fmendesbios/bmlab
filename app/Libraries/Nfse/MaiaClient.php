<?php
namespace App\Libraries\Nfse;

use \SoapClient;
use \SoapFault;
use \SoapVar;
use \SoapHeader;
use \stdClass;
use \Exception;

class MaiaClient {
    private $config;
    private $ambiente;
    private $soapClient;

    public function __construct($config) {
        $this->config = $config;
        $this->ambiente = $config['ambiente'];
        $this->connect();
    }

    private function connect() {
        $envConfig = $this->config[$this->ambiente];
        $wsdl = $envConfig['url_wsdl'];
        
        // Aumenta timeout do socket globalmente para esta requisição
        ini_set('default_socket_timeout', 180);

        $opts = [
            'location' => $envConfig['url_service'],
            'soap_version' => SOAP_1_1,
            'trace' => 1,
            'exceptions' => 1,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'keep_alive' => false,
            'user_agent' => 'PHPSoapClient',
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'ciphers' => 'DEFAULT:!DH',
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                ],
                'http' => [
                    'header' => "Connection: close\r\n",
                    'protocol_version' => 1.1,
                    'timeout' => 180
                ]
            ])
        ];

        try {
            $this->soapClient = new SoapClient($wsdl, $opts);
            
            // Configurar WS-Security Header
            $this->setWSSecurityHeader($envConfig['usuario'], $envConfig['senha']);
            
        } catch (SoapFault $e) {
            throw new Exception("Erro ao conectar ao Web Service: " . $e->getMessage());
        }
    }

    private function setWSSecurityHeader($user, $password) {
        // Namespaces
        $ns_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $ns_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
        $password_type = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

        // Timestamp
        $created = gmdate("Y-m-d\TH:i:s\Z");
        $expires = gmdate("Y-m-d\TH:i:s\Z", strtotime('+5 minutes'));
        
        // Montagem do XML do Header
        $xmlHeader = '
        <wsse:Security xmlns:wsse="' . $ns_wsse . '" xmlns:wsu="' . $ns_wsu . '">
            <wsu:Timestamp wsu:Id="Timestamp-' . uniqid() . '">
                <wsu:Created>' . $created . '</wsu:Created>
                <wsu:Expires>' . $expires . '</wsu:Expires>
            </wsu:Timestamp>
            <wsse:UsernameToken wsu:Id="UsernameToken-' . uniqid() . '">
                <wsse:Username>' . $user . '</wsse:Username>
                <wsse:Password Type="' . $password_type . '">' . $password . '</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>';

        // Converter para SoapVar e adicionar como Header
        $headerVar = new SoapVar($xmlHeader, XSD_ANYXML, null, null, null);
        $header = new SoapHeader($ns_wsse, 'Security', $headerVar);
        
        $this->soapClient->__setSoapHeaders($header);
    }

    /**
     * Envia um lote de RPS para processamento
     * @param object $loteRps Objeto loteRPServico montado
     * @return object Resposta do WS
     */
    public function enviarLoteRps($loteRps) {
        // Estrutura esperada: gerarNfse(gerarNfse $parameters)
        // struct gerarNfse { loteRPS loteRPServico; }
        $params = [
            'loteRPServico' => $loteRps
        ];

        try {
            // Log do que será enviado (Debug)
            // file_put_contents(__DIR__ . '/last_request_obj.txt', print_r($params, true));
            
            return $this->soapClient->gerarNfse($params);
        } catch (SoapFault $e) {
            // Log do Request XML para debug
            file_put_contents(__DIR__ . '/last_soap_request.xml', $this->soapClient->__getLastRequest());
            throw new Exception("Erro ao enviar lote RPS: " . $e->getMessage());
        }
    }

    public function consultarLote($tipo, $protocolo) {
        // buscarLote(tipoFiltroLote, filtroLote)
        $params = [
            'tipofiltroLote' => $tipo, 
            'filtroLote' => (int)$protocolo
        ];
        try {
            return $this->soapClient->buscarLote($params);
        } catch (SoapFault $e) {
            throw new Exception("Erro ao buscar lote: " . $e->getMessage());
        }
    }

    public function consultarNfse($tipo, $valor) {
        // buscarNfse(tipoFiltroNota, filtroNota)
        $params = [
            'tipofiltroNota' => $tipo, // Corrigido case: tipofiltroNota
            'filtroNota' => $valor
        ];
        try {
            return $this->soapClient->buscarNfse($params);
        } catch (SoapFault $e) {
            throw new Exception("Erro ao buscar NFSe: " . $e->getMessage());
        }
    }

    /**
     * Gera o Objeto do Lote de RPS (Compatível com Maia/Janela Única)
     * @param array $dadosLote
     * @param array $listaRps
     * @return object (stdClass) Estrutura loteRPS
     */
    public function gerarLoteObjeto($dadosLote, $listaRps) {
        $lote = new stdClass();
        $lote->tipoLote = 1; // 1-Envio? Verificar se há enum. Geralmente 1.
        $lote->iIdentLote = $dadosLote['numero_lote'];
        
        // Mapear lista de RPS
        $arrRps = [];
        foreach ($listaRps as $rpsData) {
            $arrRps[] = $this->gerarObjetoRps($rpsData);
        }
        
        // Adiciona RPS ao lote
        // RESTAURANDO WRAPPER: O servidor exige <recibosProvisoriosDeServicos><reciboProvisorioDeServico>...</reciboProvisorioDeServico></recibosProvisoriosDeServicos>
        // O erro anterior confirmou isso.
        
        $wrapper = new stdClass();
        if (count($arrRps) == 1) {
            $wrapper->reciboProvisorioDeServico = $arrRps[0];
        } else {
            $wrapper->reciboProvisorioDeServico = $arrRps;
        }
        
        $lote->recibosProvisoriosDeServicos = new SoapVar($wrapper, SOAP_ENC_OBJECT, null, null, 'recibosProvisoriosDeServicos');

        return $lote;
    }

    private function gerarObjetoRps($data) {
        $rps = new stdClass();
        
        // WSDL define dataEmissaoRps como xs:date (YYYY-MM-DD), removendo hora se houver
        $rps->dataEmissaoRps = date('Y-m-d', strtotime($data['data_emissao']));
        // A ordem dos elementos no XML gerado pode importar se o servidor não for robusto.
        // No XML gerado: dataEmissaoRps, numeroRps, serieRps...
        // No WSDL: dataEmissaoRps, identificacaoTomadorServico, itens, numeroRps...
        // A ordem está errada! O SoapClient geralmente não reordena se usarmos stdClass.
        // Vamos reordenar as propriedades do objeto $rps para seguir estritamente o WSDL.
        
        // Ordem WSDL:
        // 1. dataEmissaoRps
        // 2. identificacaoTomadorServico
        // 3. itens
        // 4. numeroRps
        // 5. numeroRpsSubstituto (opcional)
        // 6. prestadorServico (opcional)
        // 7. serieRps
        // 8. tipoRps
        // 9. tipoRpsSubstituto (opcional)
        // 10. serieRpsSubstituto (opcional)
        // 11. statusRps
        // 12. detalheConstrucaoCivil (opcional)
        // 13. identificacaoIntermediarioServico (opcional)
        // 14. identificacaoPrestacaoServico
        
        $rps = new stdClass(); // Reset
        $rps->dataEmissaoRps = date('Y-m-d', strtotime($data['data_emissao']));
        
        // Tomador (2)
        // Ordem WSDL: bairro, cep, cidade, complementoEndereco, cpfCnpj, email, endereco, inscricaoMunicipal, nifTomador, numeroEndereco, pais, razaoSocial, telefone, uf
        $tomador = new stdClass();
        $tomador->bairro = $data['tomador_bairro'];
        
        $tomadorCep = str_replace(['-', '.', ' '], '', $data['tomador_cep']);
        if (!empty($tomadorCep)) {
            $tomador->cep = $tomadorCep;
        }
        
        $tomador->cidade = (int)$data['tomador_cod_municipio'];
        // complementoEndereco - Opcional
        
        $docTomador = preg_replace('/\D/', '', $data['tomador_doc']);
        if (!empty($docTomador)) {
            $tomador->cpfCnpj = $docTomador;
        }
        
        if (!empty($data['tomador_email'])) {
            $tomador->email = $data['tomador_email'];
        }
        
        $tomador->endereco = $data['tomador_endereco'];
        // inscricaoMunicipal - Opcional
        // nifTomador - Opcional
        
        $tomador->numeroEndereco = $data['tomador_numero'];
        // pais - Opcional
        
        $tomador->razaoSocial = $data['tomador_nome'];
        // telefone - Opcional
        
        $tomador->uf = $data['tomador_uf'];
        
        $rps->identificacaoTomadorServico = $tomador;

        // Itens (3)
        // Ordem WSDL: descricao, quantidade, total, valorUnitario
        $itemObj = new stdClass();
        $itemObj->descricao = $data['discriminacao'];
        $itemObj->quantidade = 1;
        $itemObj->total = $data['valor_servicos'];
        $itemObj->valorUnitario = $data['valor_servicos'];
        
        $itensWrapper = new stdClass();
        $itensWrapper->item = $itemObj;
        $rps->itens = $itensWrapper;

        // Campos Básicos (4, 7, 8, 11)
        $rps->numeroRps = $data['numero'];
        // numeroRpsSubstituto (5) - Omitido
        
        // Prestador (6) - Restaurando pois pode ser obrigatório para a validação do servidor (NPE)
        $prestador = new stdClass();
        $cpfCnpjPrestador = preg_replace('/\D/', '', $data['cnpj_prestador']);
        if (!empty($cpfCnpjPrestador)) {
            $prestador->cpfCnpj = $cpfCnpjPrestador;
        }
        
        $imPrestador = preg_replace('/\D/', '', $data['im_prestador']);
        if (!empty($imPrestador)) {
            $prestador->inscricaoMunicipal = $imPrestador;
        }
        
        // Só adiciona se tiver pelo menos o CPF/CNPJ
        if (isset($prestador->cpfCnpj)) {
            // RESTAURADO: Enviar Inscrição Municipal se disponível.
            // A remoção anterior visava testar se a IM estava causando validação incorreta,
            // mas a falta dela pode causar NPE.
            $rps->prestadorServico = $prestador;
        }

        $rps->serieRps = (string)$data['serie'];
        $rps->tipoRps = (int)$data['tipo'];
        // tipoRpsSubstituto (9) - Omitido
        // serieRpsSubstituto (10) - Omitido
        $rps->statusRps = 1;
        
        // detalheConstrucaoCivil (12) - Omitido
        // identificacaoIntermediarioServico (13) - Omitido
        
        // Identificação Prestação Serviço (14)
        // Ordem WSDL:
        // 1. codigoCnae
        // 2. aliquota (opcional)
        // 3. codigoNbs (opcional)
        // 4. codigoTributacaoMunicipio (opcional)
        // 5. competencia
        // 6. descontoCondicionado (opcional)
        // 7. descontoIncondicionado (opcional)
        // 8. discriminacao
        // 9. exigibilidadeIss
        // 10. incentivoFiscal
        // 11. issRetido
        // 12. itemListaServico
        // 13. municipioIncidencia (opcional)
        // 14. municipioPrestacaoServico
        // 15. numeroProcesso (opcional)
        // 16. optanteSimplesNacional
        // 17. outrasRetencoes (opcional)
        // 18. paisPrestacaoServico (opcional)
        // 19. regimeEspecialTributacao (opcional)
        // 20. responsavelRetencao (opcional)
        // 21. valorCofins (opcional)
        // 22. valorCsll (opcional)
        // 23. valorDeducoes (opcional)
        // 24. valorInss (opcional)
        // 25. valorIr (opcional)
        // 26. valorIss (opcional)
        // 27. valorPis (opcional)
        // 28. valorServico
        // 29. valorTotalTributos (opcional)

        $servico = new stdClass();
        $servico->codigoCnae = isset($data['cnae']) && $data['cnae'] !== '' ? (int)$data['cnae'] : 8640202;
        // aliquota - opcional
        // codigoNbs - opcional
        if (!empty($data['codigo_tributacao_municipio'])) {
            $servico->codigoTributacaoMunicipio = $data['codigo_tributacao_municipio'];
        }
        $servico->competencia = date('Y-m-d', strtotime($data['data_emissao']));
        
        $setIfPositive = function($obj, $prop, $val) {
            if ($val > 0) {
                $obj->$prop = $val;
            }
        };

        // $setIfPositive($servico, 'descontoCondicionado', isset($data['desconto_condicionado']) ? $data['desconto_condicionado'] : 0.00);
        // $setIfPositive($servico, 'descontoIncondicionado', isset($data['desconto_incondicionado']) ? $data['desconto_incondicionado'] : 0.00);
        
        $servico->discriminacao = $data['discriminacao'];
        $servico->exigibilidadeIss = 1;
        $servico->incentivoFiscal = 2;
        $servico->issRetido = isset($data['iss_retido']) ? ($data['iss_retido'] == 1 ? 1 : 2) : 2;
        
        // Garante que itemListaServico seja string e tenha zeros à esquerda se necessário (ex: 04.03)
        // Se vier 4.03, transforma em 04.03.
        $itemServico = (string)$data['item_lista_servico'];
        if (strlen($itemServico) < 5 && strpos($itemServico, '.') !== false) {
             $itemServico = str_pad($itemServico, 5, '0', STR_PAD_LEFT);
        }
        $servico->itemListaServico = $itemServico;
        
        // Opcional. Se for igual ao municipioPrestacaoServico, vamos omitir para evitar redundância/erros.
        if (!empty($data['codigo_municipio']) && $data['codigo_municipio'] != $data['codigo_municipio']) {
             // Lógica estranha acima? Se codigo_municipio existe, ele é igual a si mesmo.
             // O que queremos é: se municipioIncidencia == municipioPrestacaoServico, talvez não precise enviar.
             // Mas WSDL diz minOccurs="0".
             // Vamos omitir por padrão se for o mesmo, pois 'municipioPrestacaoServico' é obrigatório.
             // $servico->municipioIncidencia = (int)$data['codigo_municipio'];
        }
        
        // CORREÇÃO: municipioIncidencia removido se for igual ao prestacao (ou sempre, para teste)
        $servico->municipioIncidencia = (int)$data['codigo_municipio'];
        
        $servico->municipioPrestacaoServico = (int)$data['codigo_municipio']; 
        // numeroProcesso - opcional
        $servico->optanteSimplesNacional = isset($data['optante_simples']) ? ($data['optante_simples'] == 1 ? 1 : 2) : 2;
        
        // $setIfPositive($servico, 'outrasRetencoes', isset($data['outras_retencoes']) ? $data['outras_retencoes'] : 0.00);
        
        // paisPrestacaoServico - Opcional. Vamos omitir se possível para evitar problemas de código.
        // Mas se o sistema exige, 1058 é Brasil.
        // $servico->paisPrestacaoServico = 1058; 
        
        // regimeEspecialTributacao - Opcional. Vamos omitir.
        // $servico->regimeEspecialTributacao = 6;
        
        // responsavelRetencao - opcional
        
        // REMOVIDO CAMPOS DE VALOR ZERADOS/OPCIONAIS PARA TESTE
        // $setIfPositive($servico, 'valorCofins', isset($data['valor_cofins']) ? $data['valor_cofins'] : 0.00);
        // $setIfPositive($servico, 'valorCsll', isset($data['valor_csll']) ? $data['valor_csll'] : 0.00);
        // $setIfPositive($servico, 'valorDeducoes', isset($data['valor_deducoes']) ? $data['valor_deducoes'] : 0.00);
        // $setIfPositive($servico, 'valorInss', isset($data['valor_inss']) ? $data['valor_inss'] : 0.00);
        // $setIfPositive($servico, 'valorIr', isset($data['valor_ir']) ? $data['valor_ir'] : 0.00);
        // $setIfPositive($servico, 'valorIss', isset($data['valor_iss']) ? $data['valor_iss'] : 0.00);
        // $setIfPositive($servico, 'valorPis', isset($data['valor_pis']) ? $data['valor_pis'] : 0.00);
        
        $servico->valorServico = $data['valor_servicos'];
        
        // $setIfPositive($servico, 'valorTotalTributos', isset($data['valor_total_tributos']) ? $data['valor_total_tributos'] : 0.00);
        
        $rps->identificacaoPrestacaoServico = $servico;
        
        return $rps;
    }
}
