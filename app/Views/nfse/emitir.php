<!DOCTYPE html>
<html lang="pt-br" data-theme="bmlab">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emitir NFSe - Pedido #<?= $pedido_codigo_visual ?></title>
    
    <link href="public/css/app.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'bmlab';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        .section-title {
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--fallback-bc,oklch(var(--bc)/0.6));
            border-bottom: 1px solid var(--fallback-bc,oklch(var(--bc)/0.1));
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
        }
        .form-control-sm {
            min-height: 2rem;
            height: 2rem;
            font-size: 0.875rem;
        }
        .label-text {
            font-size: 0.875rem;
            font-weight: 600;
        }
        .bg-readonly {
            background-color: var(--fallback-b2,oklch(var(--b2)/0.5));
        }
    </style>
</head>
<body class="bg-base-200 min-h-screen p-4">
    <div class="container mx-auto max-w-5xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold flex items-center gap-2">
                <i class="bi bi-receipt text-primary"></i> 
                Emissão de NFSe Nacional
                <span class="badge badge-neutral">Pedido #<?= $pedido_codigo_visual ?></span>
            </h3>
            <button onclick="window.close()" class="btn btn-ghost">Fechar</button>
        </div>

        <?php if ($msg): ?>
            <div role="alert" class="alert alert-<?= $tipo_msg ?> mb-4 shadow-lg">
                <i class="bi bi-info-circle"></i>
                <div><?= $msg ?></div>
            </div>
        <?php endif; ?>

        <?php if ($pedido['nfse_status'] == 'ENVIADO'): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <i class="bi bi-check-circle-fill text-2xl"></i>
                <div class="flex-1">
                    <h3 class="font-bold">Nota Fiscal já emitida!</h3>
                    <div class="text-xs">Protocolo/Número: <?= $pedido['nfse_numero'] ?>. Você pode emitir novamente para fins de teste em homologação.</div>
                    <?php 
                    $xmlRetorno = $pedido['nfse_xml_retorno'] ?? '';
                    $isOficial = stripos($xmlRetorno, '<infNFSe') !== false || stripos($xmlRetorno, '<NFSe') !== false;
                    ?>
                    <?php if (!$isOficial): ?>
                        <div class="text-warning text-xs mt-2 font-semibold">
                            <i class="bi bi-clock-history"></i> 
                            Aguardando distribuição oficial da Receita Federal. Os arquivos abaixo são provisórios (DPS).
                            Clique em "Consultar Status" para atualizar.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2">
                    <a href="index.php?r=nfse/downloadXml&id=<?= $pedido['id'] ?>&source=local" class="btn btn-sm btn-outline btn-success bg-white" target="_blank">
                        <i class="bi bi-file-earmark-code"></i> Baixar XML
                    </a>
                    <a href="index.php?r=nfse/visualizarDanfse&id=<?= $pedido['id'] ?>&source=local" class="btn btn-sm btn-outline btn-success bg-white" target="_blank">
                        <i class="bi bi-file-pdf"></i> Visualizar PDF
                    </a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="acao" value="consultar">
                        <button type="submit" class="btn btn-sm btn-outline btn-success bg-white"><i class="bi bi-search"></i> Consultar Status</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$msg || $tipo_msg === 'error'): ?>
        <form method="POST" id="formEmitir">
            <input type="hidden" name="acao" value="emitir">
            
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body p-6">
                    
                    <!-- Data de Competência -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                        <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Data de Competência *</span></label>
                            <input type="date" name="data_competencia" class="input input-bordered w-full" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Emitente -->
                    <h4 class="section-title">Emitente da NFS-e</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Você irá emitir esta NFS-e como? *</span></label>
                            <select name="emitente_tipo" class="select select-bordered w-full">
                                <option value="1" selected>Prestador</option>
                                <option value="2">Tomador</option>
                                <option value="3">Intermediário</option>
                            </select>
                        </div>
                        
                        <!-- Dados Fixos -->
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Município *</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="Rondon do Pará/PA" readonly>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Indicador Municipal *</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="3003410" readonly>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">CNPJ</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="59.664.321/0001-33" readonly>
                        </div>
                        <div class="col-span-12 md:col-span-8">
                            <label class="label"><span class="label-text">Razão Social</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="BRAGA MENDES LABORATORIO LTDA" readonly>
                        </div>

                        <!-- Simples Nacional -->
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">Opção no Simples Nacional *</span></label>
                            <select name="emitente_opcao_simples" class="select select-bordered w-full" id="emitente_simples_opcao">
                                <option value="1">Não Optante</option>
                                <option value="2">Optante - Microempreendedor Individual (MEI)</option>
                                <option value="3" selected>Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)</option>
                            </select>
                        </div>
                        <div class="col-span-12" id="div_regime_apuracao">
                            <label class="label"><span class="label-text">Regime de Apuração dos Tributos no Simples Nacional *</span></label>
                            <select name="emitente_regime_apuracao" class="select select-bordered w-full">
                                <option value="1" selected>Regime de apuração dos tributos federais e municipal pelo Simples Nacional</option>
                                <option value="2">Regime de apuração dos tributos federais pelo Simples Nacional e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo</option>
                                <option value="3">Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tomador -->
                    <h4 class="section-title">Tomador do Serviço</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Onde está localizado o estabelecimento/domicílio? *</span></label>
                            <select name="tomador_localizacao" class="select select-bordered w-full" id="tomador_localizacao">
                                <option value="nao_informado">Tomador não informado</option>
                                <option value="brasil" selected>Brasil</option>
                                <option value="exterior">Exterior</option>
                            </select>
                        </div>
                        
                        <div class="col-span-12 md:col-span-4 tomador-brasil">
                            <label class="label"><span class="label-text">CPF/CNPJ *</span></label>
                            <input type="text" name="tomador_cpf_cnpj" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['paciente_cpf']) ?>">
                        </div>
                        <div class="col-span-12 md:col-span-4 tomador-brasil">
                            <label class="label"><span class="label-text">Indicador Municipal</span></label>
                            <input type="text" name="tomador_im" class="input input-bordered w-full" value="">
                        </div>
                        
                        <div class="col-span-12 md:col-span-6">
                            <label class="label"><span class="label-text">Nome/Razão Social *</span></label>
                            <input type="text" name="tomador_nome" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['paciente_nome']) ?>" required>
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Telefone</span></label>
                            <input type="text" name="tomador_telefone" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['paciente_telefone'] ?? '') ?>">
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">E-mail</span></label>
                            <input type="email" name="tomador_email" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['paciente_email'] ?? '') ?>">
                        </div>

                        <!-- Endereço Checkbox -->
                        <div class="col-span-12">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" class="checkbox" name="tomador_informar_endereco" id="tomador_informar_endereco" checked onchange="toggleEndereco()">
                                <span class="label-text font-bold">Informar endereço</span>
                            </label>
                        </div>
                        
                        <!-- Campos Endereço -->
                        <div class="col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4" id="tomador_endereco_div">
                            <div class="col-span-12 md:col-span-2">
                                <label class="label"><span class="label-text">CEP *</span></label>
                                <input type="text" name="tomador_cep" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['cep']) ?>">
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="label"><span class="label-text">Município</span></label>
                                <input type="text" name="tomador_municipio_desc" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['nome_municipio'] . '/' . $pedido['uf_municipio']) ?>" readonly>
                                <input type="hidden" name="tomador_municipio_ibge" value="<?= htmlspecialchars($pedido['codigo_ibge']) ?>">
                                <input type="hidden" name="tomador_uf" value="<?= htmlspecialchars($pedido['uf_municipio']) ?>">
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <label class="label"><span class="label-text">Bairro *</span></label>
                                <input type="text" name="tomador_bairro" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['bairro']) ?>">
                            </div>
                            <div class="col-span-12 md:col-span-5">
                                <label class="label"><span class="label-text">Logradouro *</span></label>
                                <input type="text" name="tomador_logradouro" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['endereco']) ?>">
                            </div>
                            <div class="col-span-12 md:col-span-2">
                                <label class="label"><span class="label-text">Número *</span></label>
                                <input type="text" name="tomador_numero" class="input input-bordered w-full" value="<?= htmlspecialchars($pedido['numero']) ?>">
                            </div>
                            <div class="col-span-12 md:col-span-5">
                                <label class="label"><span class="label-text">Complemento</span></label>
                                <input type="text" name="tomador_complemento" class="input input-bordered w-full" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Intermediário -->
                    <h4 class="section-title">Intermediário do Serviço</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-6">
                            <label class="label"><span class="label-text">Onde está localizado o estabelecimento/domicílio? *</span></label>
                            <select name="intermediario_localizacao" id="intermediario_localizacao" class="select select-bordered w-full" onchange="toggleIntermediario()">
                                <option value="nao_informado" selected>Intermediário não informado</option>
                                <option value="brasil">Brasil</option>
                                <option value="exterior">Exterior</option>
                            </select>
                        </div>
                    </div>

                    <!-- Local Prestação -->
                    <h4 class="section-title">Local da prestação do serviço</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">País *</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="Brasil" readonly>
                        </div>
                        <div class="col-span-12 md:col-span-8">
                            <label class="label"><span class="label-text">Município *</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="Rondon do Pará/PA" readonly>
                        </div>
                    </div>

                    <!-- Serviço Prestado -->
                    <h4 class="section-title">Serviço prestado</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">Código de Tributação Nacional *</span></label>
                            <select name="servico_c_trib_nac" class="select select-bordered w-full">
                                <option value="040302" selected>04.03.02 - Laboratórios e congêneres</option>
                            </select>
                        </div>
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">O serviço prestado é um caso de: imunidade, exportação de serviço ou não incidência do ISSQN? *</span></label>
                            <select name="servico_imunidade" class="select select-bordered w-full">
                                <option value="0" selected>Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="label"><span class="label-text">Município de incidência do ISSQN</span></label>
                            <input type="text" class="input input-bordered w-full bg-readonly" value="Rondon do Pará/PA" readonly>
                        </div>
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">Descrição do Serviço *</span></label>
                            <textarea name="servico_descricao" class="textarea textarea-bordered w-full" rows="3" required>Serviço de Análise Laboratorial</textarea>
                        </div>
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">Item da NBS correspondente ao serviço prestado</span></label>
                            <select name="servico_nbs" class="select select-bordered w-full">
                                <option value="">Selecione...</option>
                                <option value="127010100">1.2701.01.00 - Serviços de saúde humana</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Informações Complementares -->
                    <h4 class="section-title">Informações Complementares</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-6">
                            <label class="label"><span class="label-text">Número do documento de responsabilidade técnica</span></label>
                            <input type="text" name="info_resp_tecnica" class="input input-bordered w-full">
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="label"><span class="label-text">Documento de referência</span></label>
                            <input type="text" name="info_doc_referencia" class="input input-bordered w-full">
                        </div>
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">Informações complementares</span></label>
                            <textarea name="info_complementar" class="textarea textarea-bordered w-full" rows="2"></textarea>
                        </div>
                        <div class="col-span-12">
                            <label class="label"><span class="label-text">Número do Pedido, Ordem de Compra, OS ou Projeto (B2B)</span></label>
                            <input type="text" name="info_num_pedido" class="input input-bordered w-full" value="<?= $pedido_codigo_visual ?>">
                        </div>
                    </div>

                    <!-- Valores -->
                    <h4 class="section-title">Valores do serviço prestado</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Valor do serviço prestado *</span></label>
                            <div class="join w-full">
                                <span class="join-item btn btn-static">R$</span>
                                <input type="text" name="valor_servico" class="input input-bordered join-item w-full" value="<?= number_format($valorServico, 2, ',', '.') ?>" required>
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Valor recebido pelo intermediário</span></label>
                            <div class="join w-full">
                                <span class="join-item btn btn-static">R$</span>
                                <input type="text" name="valor_intermediario" class="input input-bordered join-item w-full" value="0,00">
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Desconto incondicionado</span></label>
                            <div class="join w-full">
                                <span class="join-item btn btn-static">R$</span>
                                <input type="text" name="valor_desconto_incondicionado" class="input input-bordered join-item w-full" value="0,00">
                            </div>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Desconto condicionado</span></label>
                            <div class="join w-full">
                                <span class="join-item btn btn-static">R$</span>
                                <input type="text" name="valor_desconto_condicionado" class="input input-bordered join-item w-full" value="0,00">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info text-xs mt-4">
                        <i class="bi bi-info-circle"></i> As informações de Tributação Municipal abaixo, exceto retenção, não podem ser alteradas pois o tributo (ISSQN) será apurado pelo Simples Nacional.
                    </div>

                    <!-- Tributação Municipal -->
                    <h4 class="section-title">Tributação Municipal</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Regime Especial de Tributação *</span></label>
                            <select name="tributacao_regime_especial" class="select select-bordered w-full">
                                <option value="0" selected>Nenhum</option>
                            </select>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Exigibilidade Suspensa? *</span></label>
                            <select name="tributacao_suspensa" class="select select-bordered w-full">
                                <option value="0" selected>Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Há retenção do ISSQN? *</span></label>
                            <select name="tributacao_iss_retido" class="select select-bordered w-full">
                                <option value="0" selected>Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Benefício municipal? *</span></label>
                            <select name="tributacao_beneficio" class="select select-bordered w-full">
                                <option value="0" selected>Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>
                        <div class="col-span-12">
                            <div class="text-xs text-base-content/70">Na data de competência informada, o município de incidência definido para esta NFS-e não permite nenhum tipo de redução da base de cálculo do ISSQN.</div>
                        </div>
                        
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Alíquota (%)</span></label>
                            <input type="text" name="tributacao_aliquota" class="input input-bordered w-full bg-readonly" value="-" readonly>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">BC ISSQN (R$)</span></label>
                            <input type="text" name="tributacao_bc_issqn" class="input input-bordered w-full bg-readonly" value="-" readonly>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Valor ISSQN (R$)</span></label>
                            <input type="text" name="tributacao_valor_issqn" class="input input-bordered w-full bg-readonly" value="-" readonly>
                        </div>
                    </div>

                    <!-- Tributação Federal -->
                    <h4 class="section-title">Tributação Federal</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Valor Retido IRRF (R$)</span></label>
                            <input type="text" name="valor_ret_irrf" class="input input-bordered w-full" value="0,00">
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Valor Retido CSLL (R$)</span></label>
                            <input type="text" name="valor_ret_csll" class="input input-bordered w-full" value="0,00">
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Valor Retido CP (R$)</span></label>
                            <input type="text" name="valor_ret_cp" class="input input-bordered w-full" value="0,00">
                        </div>
                         <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Valor Retido PIS/PASEP (R$)</span></label>
                            <input type="text" name="valor_ret_pis" class="input input-bordered w-full" value="0,00">
                        </div>
                         <div class="col-span-12 md:col-span-3">
                            <label class="label"><span class="label-text">Valor Retido COFINS (R$)</span></label>
                            <input type="text" name="valor_ret_cofins" class="input input-bordered w-full" value="0,00">
                        </div>
                    </div>

                    <!-- Valor Aproximado -->
                    <h4 class="section-title">Valor aproximado dos tributos</h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="col-span-12">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="radio" name="config_valores_aprox" class="radio" checked>
                                <span class="label-text">Informar alíquota do Simples Nacional</span>
                            </label>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="label"><span class="label-text">Alíquota no Simples Nacional *</span></label>
                            <div class="join w-full">
                                <input type="text" name="aliquota_simples" class="input input-bordered join-item w-full" value="15,50">
                                <span class="join-item btn btn-static">%</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="mt-8 flex justify-end gap-4 pb-8">
                <button type="button" onclick="window.close()" class="btn btn-ghost">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-wide">
                    <i class="bi bi-send"></i> Emitir NFSe
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        function toggleEndereco() {
            const chk = document.getElementById('tomador_informar_endereco');
            const div = document.getElementById('tomador_endereco_div');
            if (chk.checked) {
                div.style.display = 'grid';
                div.querySelectorAll('input').forEach(el => el.disabled = false);
            } else {
                div.style.display = 'none';
            }
        }

        function toggleIntermediario() {
            const sel = document.getElementById('intermediario_localizacao');
            const div = document.getElementById('div_intermediario_dados');
            if (sel.value !== 'nao_informado') {
                div.style.display = 'grid';
                div.querySelectorAll('input').forEach(el => el.disabled = false);
            } else {
                div.style.display = 'none';
            }
        }
        
        // Initial state
        toggleEndereco();
        toggleIntermediario();

        // Masks could be added here
    </script>
</body>
</html>
