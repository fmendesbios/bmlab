<?php

namespace App\Libraries\Nfse;

require_once __DIR__ . '/../../../fpdf/fpdf.php';

class DanfseGenerator extends \FPDF
{
    protected $data;
    protected $tempFiles = [];

    public function __construct($data)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->data = $data;
        $this->SetAutoPageBreak(true, 10);
        $this->SetTitle('DANFS-e - ' . $data['numero_nfse']);
    }

    public function generate($dest = 'I', $name = null)
    {
        if ($name === null) $name = 'DANFSE_' . $this->data['numero_nfse'] . '.pdf';

        $this->AddPage();
        $this->drawHeader();
        $this->drawAccessKey();
        $this->drawPrestador();
        $this->drawTomador();
        $this->drawIntermediario();
        $this->drawServico();
        $this->drawTributacaoMunicipal();
        $this->drawTributacaoFederal();
        $this->drawTotais();
        $this->drawInformacoes();
        
        // Limpar arquivos temporários
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) unlink($file);
        }

        $this->Output($dest, $name);
    }

    protected function drawHeader()
    {
        // Logo NFSe (Esquerda)
        // Placeholder ou texto se não tiver imagem
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(10, 10);
        $this->Cell(40, 15, 'NFS-e', 1, 0, 'C');
        
        // Título Central
        $this->SetXY(50, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(110, 8, 'DANFSe v1.0', 'T', 0, 'C');
        $this->SetXY(50, 18);
        $this->SetFont('Arial', '', 10);
        $this->Cell(110, 7, 'Documento Auxiliar da NFS-e', 'B', 0, 'C');

        // Dados Município (Direita)
        $this->SetXY(160, 10);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(40, 15, '', 1, 0); // Box
        
        $this->SetXY(162, 11);
        $this->MultiCell(38, 3, mb_convert_encoding("MUNICÍPIO DE " . strtoupper($this->data['prestador']['municipio']), 'ISO-8859-1'), 0, 'L');
    }

    protected function drawAccessKey()
    {
        $y = 26;
        $this->SetXY(10, $y);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(190, 4, 'Chave de Acesso da NFS-e', 'LTR', 1);
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(190, 5, $this->data['chave_acesso'], 'LbR', 1);

        // QR Code
        $yQr = $y;
        if (!empty($this->data['qr_code_base64'])) {
            $qrFile = $this->saveBase64Image($this->data['qr_code_base64']);
            if ($qrFile) {
                $this->Image($qrFile, 170, $yQr + 2, 25, 25);
            }
        }

        // Linha de Informações da Nota
        $y = $this->GetY() + 2;
        $this->SetXY(10, $y);
        
        // Colunas
        $colW = 160 / 2; // Espaço restante sem o QR Code
        
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(30, 4, mb_convert_encoding('Número da NFS-e', 'ISO-8859-1'), 'L', 0);
        $this->Cell(50, 4, mb_convert_encoding('Competência da NFS-e', 'ISO-8859-1'), 0, 0);
        $this->Cell(50, 4, mb_convert_encoding('Data e Hora da emissão da NFS-e', 'ISO-8859-1'), 0, 1);
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, $this->data['numero_nfse'], 'L', 0);
        $this->Cell(50, 4, $this->data['competencia'], 0, 0);
        $this->Cell(50, 4, $this->data['data_emissao'], 0, 1);

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(30, 4, mb_convert_encoding('Número da DPS', 'ISO-8859-1'), 'L', 0);
        $this->Cell(50, 4, mb_convert_encoding('Série da DPS', 'ISO-8859-1'), 0, 0);
        $this->Cell(50, 4, mb_convert_encoding('Data e Hora da emissão da DPS', 'ISO-8859-1'), 0, 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, isset($this->data['numero_dps']) ? $this->data['numero_dps'] : '-', 'L', 0); // Ajustar se tiver numero DPS
        $this->Cell(50, 4, $this->data['serie_dps'], 0, 0);
        $this->Cell(50, 4, $this->data['data_emissao'], 0, 1); // Usando mesma data por enquanto
        
        // Fechar caixa do QR code area
        $this->Rect(10, $yQr, 190, 25);
        $this->SetY($yQr + 26);
    }

    protected function drawPrestador()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('EMITENTE DA NFS-e', 'ISO-8859-1'), 1, 1, 'L', true);
        
        $y = $this->GetY();
        $p = $this->data['prestador'];

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(95, 4, mb_convert_encoding('Prestador do Serviço', 'ISO-8859-1'), 'L', 0);
        $this->Cell(45, 4, mb_convert_encoding('CNPJ / CPF / NIF', 'ISO-8859-1'), 0, 0);
        $this->Cell(50, 4, mb_convert_encoding('Inscrição Municipal', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 4, mb_convert_encoding($p['nome'], 'ISO-8859-1'), 'L', 0);
        $this->Cell(45, 4, $p['doc'], 0, 0);
        $this->Cell(50, 4, $p['im'], 'R', 1);

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(140, 4, mb_convert_encoding('Endereço', 'ISO-8859-1'), 'L', 0);
        $this->Cell(50, 4, mb_convert_encoding('Município', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(140, 4, mb_convert_encoding($p['endereco'], 'ISO-8859-1'), 'L', 0);
        $this->Cell(50, 4, mb_convert_encoding($p['municipio'] . ' - ' . $p['uf'], 'ISO-8859-1'), 'R', 1);

        // Linha inferior
        $this->Cell(190, 0, '', 'T', 1);
    }

    protected function drawTomador()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('TOMADOR DO SERVIÇO', 'ISO-8859-1'), 1, 1, 'L', true);
        
        $t = $this->data['tomador'];

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(95, 4, mb_convert_encoding('Nome / Nome Empresarial', 'ISO-8859-1'), 'L', 0);
        $this->Cell(45, 4, mb_convert_encoding('CNPJ / CPF / NIF', 'ISO-8859-1'), 0, 0);
        $this->Cell(50, 4, mb_convert_encoding('Inscrição Municipal', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 4, mb_convert_encoding($t['nome'], 'ISO-8859-1'), 'L', 0);
        $this->Cell(45, 4, $t['doc'], 0, 0);
        $this->Cell(50, 4, $t['im'], 'R', 1);

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(140, 4, mb_convert_encoding('Endereço', 'ISO-8859-1'), 'L', 0);
        $this->Cell(50, 4, mb_convert_encoding('Município', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(140, 4, mb_convert_encoding($t['endereco'], 'ISO-8859-1'), 'L', 0);
        $this->Cell(50, 4, mb_convert_encoding($t['municipio'] . ' - ' . $t['uf'], 'ISO-8859-1'), 'R', 1);
        
        $this->Cell(190, 0, '', 'T', 1);
    }

    protected function drawIntermediario()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e', 'ISO-8859-1'), 1, 1, 'C', true);
    }

    protected function drawServico()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('SERVIÇO PRESTADO', 'ISO-8859-1'), 1, 1, 'L', true);

        $s = $this->data['servico'];

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(60, 4, mb_convert_encoding('Código de Tributação Nacional', 'ISO-8859-1'), 'L', 0);
        $this->Cell(60, 4, mb_convert_encoding('Código de Tributação Municipal', 'ISO-8859-1'), 0, 0);
        $this->Cell(70, 4, mb_convert_encoding('Descrição do Serviço', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(60, 4, $s['codigo'], 'L', 0);
        $this->Cell(60, 4, '-', 0, 0); // Código municipal geralmente não usado no nacional
        $this->MultiCell(70, 4, mb_convert_encoding($s['discriminacao'], 'ISO-8859-1'), 0, 'R');
        
        $this->Cell(190, 0, '', 'T', 1);
    }

    protected function drawTributacaoMunicipal()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('TRIBUTAÇÃO MUNICIPAL', 'ISO-8859-1'), 1, 1, 'L', true);
        
        $v = $this->data['valores'];

        // Linha 1
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(63, 4, mb_convert_encoding('Tributação do ISSQN', 'ISO-8859-1'), 'L', 0);
        $this->Cell(63, 4, mb_convert_encoding('Município de Incidência do ISSQN', 'ISO-8859-1'), 0, 0);
        $this->Cell(64, 4, mb_convert_encoding('Regime Especial de Tributação', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(63, 4, 'Operacao Tributavel', 'L', 0); // Mock
        $this->Cell(63, 4, mb_convert_encoding($this->data['prestador']['municipio'], 'ISO-8859-1'), 0, 0);
        $this->Cell(64, 4, 'Nenhum', 'R', 1);

        // Linha 2 (Valores)
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(47, 4, mb_convert_encoding('Valor do Serviço', 'ISO-8859-1'), 'L', 0);
        $this->Cell(47, 4, mb_convert_encoding('Desconto Incondicionado', 'ISO-8859-1'), 0, 0);
        $this->Cell(47, 4, mb_convert_encoding('Base de Cálculo', 'ISO-8859-1'), 0, 0);
        $this->Cell(49, 4, mb_convert_encoding('Alíquota Aplicada', 'ISO-8859-1'), 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(47, 4, 'R$ ' . $v['servico'], 'L', 0);
        $this->Cell(47, 4, 'R$ ' . $v['desconto'], 0, 0);
        $this->Cell(47, 4, 'R$ ' . $v['servico'], 0, 0); // BC geralmente = Serviço
        $this->Cell(49, 4, $v['aliquota'] . '%', 'R', 1);

        $this->Cell(190, 0, '', 'T', 1);
    }

    protected function drawTributacaoFederal()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('TRIBUTAÇÃO FEDERAL', 'ISO-8859-1'), 1, 1, 'L', true);

        $v = $this->data['valores'];

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(38, 4, 'PIS', 'L', 0);
        $this->Cell(38, 4, 'COFINS', 0, 0);
        $this->Cell(38, 4, 'INSS', 0, 0);
        $this->Cell(38, 4, 'IR', 0, 0);
        $this->Cell(38, 4, 'CSLL', 'R', 1);

        $this->SetFont('Arial', '', 8);
        $this->Cell(38, 4, 'R$ ' . $v['pis'], 'L', 0);
        $this->Cell(38, 4, 'R$ ' . $v['cofins'], 0, 0);
        $this->Cell(38, 4, 'R$ ' . $v['inss'], 0, 0);
        $this->Cell(38, 4, 'R$ ' . $v['ir'], 0, 0);
        $this->Cell(38, 4, 'R$ ' . $v['csll'], 'R', 1);
        
        $this->Cell(190, 0, '', 'T', 1);
    }

    protected function drawTotais()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('VALOR TOTAL DA NFS-E', 'ISO-8859-1'), 1, 1, 'L', true);

        $v = $this->data['valores'];
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(95, 8, mb_convert_encoding('Valor Líquido da NFS-e', 'ISO-8859-1'), 'L', 0);
        $this->Cell(95, 8, 'R$ ' . $v['liquido'], 'R', 1);
        
        $this->Cell(190, 0, '', 'T', 1);
    }

    protected function drawInformacoes()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, mb_convert_encoding('INFORMAÇÕES COMPLEMENTARES', 'ISO-8859-1'), 1, 1, 'L', true);
        
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(190, 4, mb_convert_encoding($this->data['outras_informacoes'], 'ISO-8859-1'), 0, 'L');
    }

    private function saveBase64Image($base64)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            $data = substr($base64, strpos($base64, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
                return false;
            }

            $data = base64_decode($data);
            if ($data === false) {
                return false;
            }

            $file = sys_get_temp_dir() . '/qr_' . uniqid() . '.' . $type;
            file_put_contents($file, $data);
            $this->tempFiles[] = $file;
            return $file;
        }
        return false;
    }
}
