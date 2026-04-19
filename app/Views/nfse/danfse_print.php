<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFS-E - Documento Auxiliar da NFS-e</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            border: 1px solid #000;
        }
        .row {
            display: flex;
            width: 100%;
            border-bottom: 1px solid #000;
        }
        .row:last-child {
            border-bottom: none;
        }
        .col {
            padding: 4px;
            border-right: 1px solid #000;
            flex-grow: 1;
        }
        .col:last-child {
            border-right: none;
        }
        .col-1 { width: 8.33%; }
        .col-2 { width: 16.66%; }
        .col-3 { width: 25%; }
        .col-4 { width: 33.33%; }
        .col-6 { width: 50%; }
        .col-8 { width: 66.66%; }
        .col-12 { width: 100%; }

        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 10px 0;
        }
        .header-subtitle {
            text-align: center;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .section-title {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 11px;
            padding: 3px;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }
        .field-label {
            font-size: 9px;
            font-weight: bold;
            color: #444;
            display: block;
            margin-bottom: 2px;
        }
        .field-value {
            font-size: 11px;
            font-weight: normal;
        }
        .qr-code-placeholder {
            width: 100px;
            height: 100px;
            background-color: #eee;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #888;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        table.impostos {
            width: 100%;
            border-collapse: collapse;
        }
        table.impostos th, table.impostos td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            font-size: 10px;
        }
        table.impostos th {
            background-color: #f0f0f0;
        }
        .no-border-bottom { border-bottom: none !important; }
        .no-border-top { border-top: none !important; }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(200, 200, 200, 0.3);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body onload="window.print()">

    <?php if (!empty($api_unavailable) && $api_unavailable): ?>
        <div class="api-warning">
            PDF OFICIAL INDISPONÍVEL NO MOMENTO (ERRO NO EMISSOR NACIONAL). ESTA É UMA REPRESENTAÇÃO GERADA PELO SISTEMA.
        </div>
        <div class="watermark">VISUALIZAÇÃO PROVISÓRIA</div>
    <?php endif; ?>
    <?php if (isset($is_preview) && $is_preview): ?>
        <div class="watermark">PRÉ-VISUALIZAÇÃO</div>
    <?php endif; ?>

    <div class="container">
        <!-- Cabeçalho -->
        <div class="row">
            <div class="col col-2 text-center" style="display:flex; align-items:center; justify-content:center;">
                 <!-- Brasão Nacional SVG Inline -->
                 <svg width="60" height="60" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#ccc" stroke-width="2"/>
                    <text x="50" y="55" font-family="Arial" font-size="12" text-anchor="middle" fill="#ccc">BRASÃO</text>
                 </svg>
            </div>
            <div class="col col-8">
                <div class="header-title">DANFS-E - Documento Auxiliar da Nota Fiscal de Serviço Eletrônica</div>
                <div class="header-subtitle">Emitido pelo Sistema Nacional de NFS-e</div>
                <div class="text-center" style="font-size: 10px;">Comprovante de Entrega de NFS-e</div>
            </div>
            <div class="col col-2 text-center">
                <!-- QR Code (Prioridade Base64 para evitar erro do DomPDF) -->
                <?php if (!empty($qr_code_base64)): ?>
                    <img src="<?= $qr_code_base64 ?>" style="width:90px; height:90px;">
                <?php elseif (!empty($qr_code_url)): ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($qr_code_url) ?>" style="width:90px; height:90px;">
                <?php else: ?>
                    <div class="qr-code-placeholder">QR Code</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dados da Nota -->
        <div class="row">
            <div class="col col-3">
                <span class="field-label">Número da NFS-e</span>
                <span class="field-value font-bold"><?= $numero_nfse ?></span>
            </div>
            <div class="col col-3">
                <span class="field-label">Série do DPS</span>
                <span class="field-value"><?= $serie_dps ?? '1' ?></span>
            </div>
            <div class="col col-3">
                <span class="field-label">Data e Hora de Emissão</span>
                <span class="field-value"><?= $data_emissao ?></span>
            </div>
            <div class="col col-3">
                <span class="field-label">Competência</span>
                <span class="field-value"><?= $competencia ?></span>
            </div>
        </div>
        <div class="row">
            <div class="col col-12">
                <span class="field-label">Chave de Acesso</span>
                <span class="field-value font-bold"><?= $chave_acesso ?></span>
            </div>
        </div>

        <!-- Prestador -->
        <div class="section-title">Prestador de Serviços</div>
        <div class="row">
            <div class="col col-2 text-center" style="display:flex; align-items:center; justify-content:center;">
                <!-- Logo Prestador -->
                <div style="font-size:9px; color:#ccc;">LOGO</div>
            </div>
            <div class="col col-10">
                <div class="row no-border-bottom">
                    <div class="col col-8 no-border-top" style="border-right:none;">
                        <span class="field-label">Razão Social</span>
                        <span class="field-value font-bold"><?= $prestador['nome'] ?></span>
                    </div>
                    <div class="col col-4 no-border-top">
                        <span class="field-label">CNPJ / CPF</span>
                        <span class="field-value"><?= $prestador['doc'] ?></span>
                    </div>
                </div>
                <div class="row no-border-bottom">
                     <div class="col col-12 no-border-top">
                        <span class="field-label">Endereço</span>
                        <span class="field-value">
                            <?= $prestador['endereco'] ?? '' ?>, <?= $prestador['municipio'] ?? '' ?> - <?= $prestador['uf'] ?? '' ?>
                        </span>
                     </div>
                </div>
                <div class="row no-border-bottom">
                    <div class="col col-4 no-border-top" style="border-right:none; border-bottom:none;">
                         <span class="field-label">Inscrição Municipal</span>
                         <span class="field-value"><?= $prestador['im'] ?? '-' ?></span>
                    </div>
                    <div class="col col-4 no-border-top" style="border-right:none; border-bottom:none;">
                         <span class="field-label">Telefone</span>
                         <span class="field-value"><?= $prestador['fone'] ?? '-' ?></span>
                    </div>
                    <div class="col col-4 no-border-top" style="border-bottom:none;">
                         <span class="field-label">E-mail</span>
                         <span class="field-value"><?= $prestador['email'] ?? '-' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tomador -->
        <div class="section-title">Tomador de Serviços</div>
        <div class="row">
            <div class="col col-8">
                <span class="field-label">Nome / Razão Social</span>
                <span class="field-value font-bold"><?= $tomador['nome'] ?></span>
            </div>
            <div class="col col-4">
                <span class="field-label">CNPJ / CPF</span>
                <span class="field-value"><?= $tomador['doc'] ?></span>
            </div>
        </div>
        <div class="row">
            <div class="col col-6">
                <span class="field-label">Endereço</span>
                <span class="field-value"><?= $tomador['endereco'] ?? '-' ?></span>
            </div>
            <div class="col col-3">
                <span class="field-label">Município</span>
                <span class="field-value"><?= $tomador['municipio'] ?? '-' ?> - <?= $tomador['uf'] ?? '' ?></span>
            </div>
             <div class="col col-3">
                <span class="field-label">CEP</span>
                <span class="field-value"><?= $tomador['cep'] ?? '-' ?></span>
            </div>
        </div>
        <div class="row">
             <div class="col col-4">
                <span class="field-label">Inscrição Municipal</span>
                <span class="field-value"><?= $tomador['im'] ?? '-' ?></span>
            </div>
            <div class="col col-4">
                <span class="field-label">Telefone</span>
                <span class="field-value"><?= $tomador['fone'] ?? '-' ?></span>
            </div>
            <div class="col col-4">
                <span class="field-label">E-mail</span>
                <span class="field-value"><?= $tomador['email'] ?? '-' ?></span>
            </div>
        </div>

        <!-- Serviço -->
        <div class="section-title">Serviço Prestado</div>
        <div class="row">
            <div class="col col-3">
                <span class="field-label">Código do Serviço (Nacional)</span>
                <span class="field-value"><?= $servico['codigo'] ?? '-' ?></span>
            </div>
             <div class="col col-3">
                <span class="field-label">Código da NBS</span>
                <span class="field-value"><?= $servico['nbs'] ?? '-' ?></span>
            </div>
            <div class="col col-6">
                <span class="field-label">Local da Prestação</span>
                <span class="field-value"><?= $servico['local_prestacao'] ?? '' ?></span>
            </div>
        </div>
        <div class="row" style="min-height: 100px;">
            <div class="col col-12">
                <span class="field-label">Discriminação dos Serviços</span>
                <span class="field-value" style="white-space: pre-wrap;"><?= $servico['discriminacao'] ?></span>
            </div>
        </div>

        <!-- Tributos -->
        <div class="section-title">Detalhamento dos Tributos (Valores em R$)</div>
        <div style="border-bottom: 1px solid #000;">
            <table class="impostos">
                <thead>
                    <tr>
                        <th style="border-left:none;">PIS/PASEP</th>
                        <th>COFINS</th>
                        <th>INSS</th>
                        <th>IRRF</th>
                        <th>CSLL</th>
                        <th>Outras Retenções</th>
                        <th>Valor ISS</th>
                        <th style="border-right:none;">Desconto Incond.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border-left:none; border-bottom:none;"><?= $valores['pis'] ?></td>
                        <td style="border-bottom:none;"><?= $valores['cofins'] ?></td>
                        <td style="border-bottom:none;"><?= $valores['inss'] ?></td>
                        <td style="border-bottom:none;"><?= $valores['ir'] ?></td>
                        <td style="border-bottom:none;"><?= $valores['csll'] ?></td>
                        <td style="border-bottom:none;"><?= $valores['outras_retencoes'] ?></td>
                        <td style="border-bottom:none;"><?= $valores['iss'] ?></td>
                        <td style="border-right:none; border-bottom:none;"><?= $valores['desconto'] ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totais -->
        <div class="row">
             <div class="col col-12">
                 <table style="width:100%; border-collapse:collapse;">
                     <tr>
                         <td style="border:none; text-align:right; font-weight:bold; padding:5px;">Valor Total do Serviço:</td>
                         <td style="border:none; width:120px; text-align:right; padding:5px;"><?= $valores['servico'] ?></td>
                     </tr>
                     <tr>
                         <td style="border:none; text-align:right; font-weight:bold; padding:5px;">(-) Deduções:</td>
                         <td style="border:none; width:120px; text-align:right; padding:5px;"><?= $valores['deducoes'] ?></td>
                     </tr>
                     <tr>
                         <td style="border:none; text-align:right; font-weight:bold; padding:5px; font-size:12px;">(=) Valor Líquido:</td>
                         <td style="border:none; width:120px; text-align:right; padding:5px; font-weight:bold; font-size:12px;"><?= $valores['liquido'] ?></td>
                     </tr>
                 </table>
             </div>
        </div>

        <!-- Rodapé -->
        <div class="section-title">Informações Adicionais</div>
        <div class="row" style="min-height: 50px;">
            <div class="col col-12">
                <span class="field-value"><?= $outras_informacoes ?? '' ?></span>
            </div>
        </div>
        
        <div class="row" style="border-bottom:none; background-color:#eee; font-size:9px; padding:5px; text-align:center;">
             Este documento é um auxiliar de nota fiscal e foi emitido pelo sistema BMLAB. A validade jurídica desta NFS-e deve ser confirmada no Portal Nacional de NFS-e (www.nfse.gov.br).
        </div>

    </div>
</body>
</html>
