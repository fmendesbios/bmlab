<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-receipt-cutoff"></i> Orçamentos</h1>
    <?php if (temPermissao('orcamentos.novo')): ?>
        <button type="button" class="btn btn-primary gap-2" onclick="abrirModalNovo()">
            <i class="bi bi-plus-lg"></i> Novo Orçamento
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($mensagem)): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($mensagem) ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <div class="alert alert-error mb-4">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($erro) ?></span>
    </div>
<?php endif; ?>

<div class="card bg-base-100 shadow-xl mb-6">
    <div class="card-body p-4 md:p-6">
        <!-- Filters and Pagination -->
        <form method="get" action="index.php" class="mb-6 bg-base-200 p-4 rounded-lg">
            <input type="hidden" name="r" value="orcamentos">
            <div class="flex flex-col md:flex-row justify-between items-end gap-4">
                <div class="flex flex-wrap gap-4 items-end w-full md:w-auto">
                    <div class="form-control w-full max-w-xs">
                        <label class="label"><span class="label-text">Buscar</span></label>
                        <input type="text" name="busca" class="input input-bordered w-full" placeholder="Nome ou Código" value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Data Inicial</span></label>
                        <input type="date" name="data_ini" class="input input-bordered" value="<?= htmlspecialchars($data_ini) ?>">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Data Final</span></label>
                        <input type="date" name="data_fim" class="input input-bordered" value="<?= htmlspecialchars($data_fim) ?>">
                    </div>
                    <div class="form-control w-full max-w-xs">
                        <label class="label"><span class="label-text">Posto</span></label>
                        <select name="posto_filtro" class="select select-bordered w-full">
                            <option value="0">Todos</option>
                            <?php foreach ($postos as $po): ?>
                                <option value="<?= (int)$po['id'] ?>" <?= ($posto_filtro === (int)$po['id'] ? 'selected' : '') ?>><?= htmlspecialchars($po['nome_posto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>

                <!-- Pagination Controls -->
                <?php
                $limit = $limit ?? 10;
                $totalRecords = $totalRecords ?? 0;
                $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
                $end = min($page * $limit, $totalRecords);
                $prev = max(1, $page - 1);
                $next = min($total_paginas, $page + 1);

                $qs = $_GET;
                unset($qs['page']);
                if (!isset($qs['r'])) $qs['r'] = 'orcamentos';

                function _orc_page_link($p, $qs)
                {
                    $qs['page'] = $p;
                    return 'index.php?' . http_build_query($qs);
                }
                ?>

                <?php if ($totalRecords > 0): ?>
                    <div class="flex items-center gap-2 bg-base-100 p-2 rounded-lg shadow-sm">
                        <div class="join">
                            <!-- First -->
                            <a href="<?= _orc_page_link(1, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-skip-backward-fill"></i>
                            </a>
                            <!-- Prev -->
                            <a href="<?= _orc_page_link($prev, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-caret-left-fill"></i>
                            </a>
                            <!-- Refresh/Current -->
                            <a href="<?= _orc_page_link($page, $qs) ?>" class="join-item btn btn-sm btn-ghost">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                            <!-- Next -->
                            <a href="<?= _orc_page_link($next, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $total_paginas) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-caret-right-fill"></i>
                            </a>
                            <!-- Last -->
                            <a href="<?= _orc_page_link($total_paginas, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $total_paginas) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-skip-forward-fill"></i>
                            </a>
                        </div>
                        <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                            <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th class="text-center">Data</th>
                        <th>Paciente</th>
                        <th class="text-center">Pedido</th>
                        <th class="text-center">Convênio</th>
                        <th>Usuário</th>
                        <th class="text-center">Validade</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusMap = [
                        'N' => ['label' => 'Novo', 'class' => 'badge-info'],
                        'NE' => ['label' => 'Negociação', 'class' => 'badge-warning'],
                        'P' => ['label' => 'Perdemos', 'class' => 'badge-error'],
                        'G' => ['label' => 'Ganhamos', 'class' => 'badge-success'],
                    ];
                    ?>
                    <?php if (!empty($orcamentos)): ?>
                        <?php foreach ($orcamentos as $o): ?>
                            <?php
                            $st = $o['status'] ?? 'N';
                            $statusBadge = $statusMap[$st] ?? ['label' => $st, 'class' => 'badge-ghost'];
                            ?>
                            <tr>
                                <td class="font-mono text-sm"><?= htmlspecialchars(($o['codigo_orcamento'] ?? '') !== '' ? (string)$o['codigo_orcamento'] : ('01' . str_pad((string)$o['id'], 9, '0', STR_PAD_LEFT))) ?></td>
                                <td class="text-center"><?= (!empty($o['criado_em']) ? date('d/m/Y H:i', strtotime($o['criado_em'])) : '') ?></td>
                                <td><?= htmlspecialchars($o['paciente_nome'] ?: ($o['paciente_avulso'] ?: '')) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($o['pedido_id'])): ?>
                                        <span class="badge badge-outline"><?= htmlspecialchars(($o['codigo_pedido'] ?? '') !== '' ? (string)$o['codigo_pedido'] : ('01' . str_pad((string)$o['pedido_id'], 7, '0', STR_PAD_LEFT))) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= htmlspecialchars($o['convenio_nome'] ?? '') ?></td>
                                <td class="text-sm"><?= htmlspecialchars($o['usuario_nome'] ?? '') ?></td>
                                <td class="text-center"><?= (!empty($o['validade']) ? date('d/m/Y', strtotime($o['validade'])) : '') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $statusBadge['class'] ?>"><?= $statusBadge['label'] ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="join">
                                        <?php if (($o['bloqueio_desconto'] ?? 'N') === 'S'): ?>
                                            <button type="button" class="btn btn-warning btn-sm join-item" onclick="abrirModalDesbloqueio(<?= (int)$o['id'] ?>)">Bloqueado</button>
                                        <?php else: ?>
                                            <?php if (($o['status'] ?? 'N') === 'N'): ?>
                                                <button type="button" class="btn btn-primary btn-sm join-item" title="Iniciar Negociação" onclick="abrirCRM(<?= (int)$o['id'] ?>)">
                                                    <i class="bi bi-chat-dots"></i> Iniciar Negociação
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-ghost btn-sm join-item text-primary" title="CRM / Notas" onclick="abrirCRM(<?= (int)$o['id'] ?>)">
                                                    <i class="bi bi-chat-left-text"></i>
                                                </button>
                                                <a href="index.php?r=orcamentos&acao=ver&id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm join-item" title="Visualizar"><i class="bi bi-eye"></i></a>
                                                <?php if (temPermissao('orcamentos.alterar')): ?>
                                                    <a href="index.php?r=orcamentos&acao=editar&id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm join-item text-info" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                                <?php endif; ?>
                                                <a href="relatorios/orcamento_pdf.php?id=<?= (int)$o['id'] ?>" target="_blank" class="btn btn-ghost btn-sm join-item" title="Imprimir"><i class="bi bi-printer"></i></a>
                                                <?php if (empty($o['pedido_id'])): ?>
                                                    <?php if (temPermissao('pedidos.novo')): ?>
                                                        <a href="index.php?r=pedidos/create&prefill_orcamento_id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm join-item text-success" title="Exportar para Pedido"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="index.php?r=pedidos/edit&id=<?= (int)$o['pedido_id'] ?>" class="btn btn-ghost btn-sm join-item text-success" title="Abrir Pedido"><i class="bi bi-box-arrow-up-right"></i></a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('orcamentos.deletar')): ?>
                                                    <a href="index.php?r=orcamentos/delete&id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm join-item text-error" title="Excluir" onclick="return confirm('Excluir este orçamento?')"><i class="bi bi-trash"></i></a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center p-4">Nenhum orçamento encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Modal Orcamento -->
<dialog id="modal_orcamento" class="modal">
    <div class="modal-box w-11/12 max-w-7xl">
        <h3 class="font-bold text-lg mb-4" id="modal_orcamento_titulo">Novo Orçamento</h3>
        <form method="post" action="index.php?r=orcamentos/store" id="form_orcamento">
            <input type="hidden" name="id" id="orc_id" value="">

            <fieldset id="fieldsetOrc">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Código</span></label>
                        <input type="text" class="input input-bordered w-full bg-base-200" id="orc_codigo" readonly value="Auto">
                    </div>
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Validade</span></label>
                        <input type="date" name="validade" id="orc_validade" class="input input-bordered w-full" required>
                    </div>
                    <div class="md:col-span-8">
                        <label class="label"><span class="label-text">Paciente</span></label>
                        <div class="flex gap-4 items-center">
                            <label class="cursor-pointer label gap-2">
                                <input type="radio" name="modo_paciente" value="cadastrado" class="radio radio-primary" id="pac_modo_cad" checked>
                                <span class="label-text">Cadastrado</span>
                            </label>
                            <label class="cursor-pointer label gap-2">
                                <input type="radio" name="modo_paciente" value="avulso" class="radio radio-primary" id="pac_modo_avulso">
                                <span class="label-text">Avulso</span>
                            </label>

                            <div id="paciente_cadastrado_wrap" class="flex-grow relative">
                                <input type="hidden" name="paciente_id" id="orc_paciente_id">
                                <input type="text" id="busca_paciente" class="input input-bordered w-full" placeholder="Digite para buscar..." autocomplete="off">
                                <ul id="lista_paciente" class="dropdown-content absolute z-[10] menu p-2 shadow bg-base-100 rounded-box w-full max-h-60 overflow-y-auto hidden"></ul>
                            </div>
                            <div id="paciente_avulso_wrap" class="flex-grow hidden">
                                <input type="text" name="paciente_avulso" id="orc_paciente_avulso" class="input input-bordered w-full" placeholder="Nome do paciente">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">País</span></label>
                        <select class="select select-bordered w-full" name="contato_celular_pais" id="orc_celular_pais">
                            <option value="+55">Brasil (+55)</option>
                            <option value="+1">EUA (+1)</option>
                            <option value="+351">Portugal (+351)</option>
                            <option value="+34">Espanha (+34)</option>
                            <option value="+33">França (+33)</option>
                            <option value="+49">Alemanha (+49)</option>
                            <option value="+39">Itália (+39)</option>
                            <option value="+44">Reino Unido (+44)</option>
                            <option value="+52">México (+52)</option>
                            <option value="+54">Argentina (+54)</option>
                            <option value="+56">Chile (+56)</option>
                            <option value="+57">Colômbia (+57)</option>
                            <option value="+51">Peru (+51)</option>
                            <option value="+595">Paraguai (+595)</option>
                            <option value="+598">Uruguai (+598)</option>
                            <option value="+61">Austrália (+61)</option>
                            <option value="+81">Japão (+81)</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="label"><span class="label-text">Celular</span></label>
                        <input type="text" name="contato_celular" id="orc_celular" class="input input-bordered w-full" maxlength="20" required>
                    </div>
                    <div class="md:col-span-3">
                        <label class="label"><span class="label-text">Posto</span></label>
                        <select name="posto_id" id="orc_posto_id" class="select select-bordered w-full" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($postos as $po): ?>
                                <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['nome_posto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Convênio</span></label>
                        <select name="convenio_id" id="orc_convenio_id" class="select select-bordered w-full" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($convenios as $conv): ?>
                                <option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Médico</span></label>
                        <div class="relative">
                            <input type="hidden" name="medico_id" id="orc_medico_id">
                            <input type="text" id="busca_medico" class="input input-bordered w-full" placeholder="Digite para buscar..." autocomplete="off" required>
                            <ul id="lista_medico" class="dropdown-content absolute z-[10] menu p-2 shadow bg-base-100 rounded-box w-full max-h-60 overflow-y-auto hidden"></ul>
                        </div>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Parceiro</span></label>
                        <select name="parceiro_id" id="orc_parceiro_id" class="select select-bordered w-full" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($parceiros as $parc): ?>
                                <option value="<?= $parc['id'] ?>" data-desconto="<?= $parc['desconto_permitido'] ?>"><?= htmlspecialchars($parc['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Campanha/Programa</span></label>
                        <select name="campanha_programa_id" id="orc_campanha_programa_id" class="select select-bordered w-full">
                            <option value="">Selecione...</option>
                            <?php foreach ($campanhas as $camp): ?>
                                <option value="<?= $camp['id'] ?>" data-desconto="<?= $camp['desconto_permitido'] ?>"><?= htmlspecialchars($camp['nome_campanha_programa']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="divider">Exames</div>

                <div id="area_busca_exame" class="mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Buscar exame (mnemônico ou nome)</span></label>
                        <input type="text" id="busca_exame" class="input input-bordered w-full" placeholder="Ex.: HMG, GLICOSE...">
                    </div>
                    <div id="resultado_busca_exame" class="mt-2 bg-base-200 rounded-box p-2 max-h-48 overflow-y-auto hidden"></div>
                </div>

                <div class="overflow-x-auto mb-4 border rounded-lg max-h-96">
                    <table class="table table-xs table-pin-rows">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Ação</th>
                                <th style="width: 100px;">Mnemônico</th>
                                <th>Exame</th>
                                <th>Material</th>
                                <th>Laboratório</th>
                                <th style="width: 120px;">Valor (R$)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_itens_orcamento">
                            <!-- Items inserted via JS -->
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Total Bruto</span></label>
                        <input type="text" id="total_bruto" name="total_bruto" class="input input-bordered text-right font-mono bg-base-200" readonly value="0,00">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Desconto (%)</span></label>
                        <input type="text" id="desconto_percentual" name="desconto_percentual" class="input input-bordered text-right font-mono" value="0,00">
                        <label class="label hidden" id="desconto_permitido_alert"><span class="label-text-alt text-error font-bold">Limite excedido!</span></label>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Desconto (R$)</span></label>
                        <input type="text" id="desconto_valor" name="desconto_valor" class="input input-bordered text-right font-mono" value="0,00">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Total Líquido</span></label>
                        <input type="text" id="total_liquido" name="total_liquido" class="input input-bordered text-right font-mono font-bold bg-base-200" readonly value="0,00">
                    </div>
                </div>

            </fieldset>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_orcamento').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarOrcamento">Salvar</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Desbloqueio -->
<dialog id="modal_desbloqueio" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Desbloquear Orçamento</h3>
        <form method="post" action="index.php?r=orcamentos/desbloquear">
            <input type="hidden" name="orcamento_id" id="desbloqueio_orc_id">
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Usuário (com permissão)</span></label>
                <input type="text" name="usuario_login" class="input input-bordered" required>
            </div>
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Senha</span></label>
                <input type="password" name="senha_login" class="input input-bordered" required>
            </div>
            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_desbloqueio').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Desbloquear</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Selecao Lab Apoio -->
<dialog id="modal_selecao_lab" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Selecione o Laboratório de Apoio</h3>
        <p class="mb-4">Este exame possui opções de laboratórios de apoio. Escolha um:</p>
        <div id="lista_labs_apoio" class="flex flex-col gap-2">
            <!-- Buttons injected by JS -->
        </div>
        <div class="modal-action">
            <button class="btn" onclick="document.getElementById('modal_selecao_lab').close()">Cancelar</button>
        </div>
    </div>
</dialog>

<!-- Modal CRM -->
<dialog id="modal_crm" class="modal">
    <div class="modal-box w-11/12 max-w-2xl">
        <h3 class="font-bold text-lg mb-4">Histórico</h3>
        <div id="crm_content_area">
            <!-- Loaded via AJAX -->
        </div>
    </div>
</dialog>

<script>
    const listaExames = <?= json_encode($exames) ?>;
    const listaApoios = <?= json_encode($apoios) ?>;
    const listaPacientes = <?= json_encode(array_map(function ($p) {
                                return [
                                    'id' => $p['id'],
                                    'nome' => $p['nome'],
                                    'celular' => $p['celular'] ?? '',
                                    'campanha_padrao_id' => $p['campanha_padrao_id'] ?? ''
                                ];
                            }, $pacientes)) ?>;
    const listaMedicos = <?= json_encode(array_map(function ($m) {
                                $label = $m['nome'];
                                $cons = trim(($m['conselho'] ?? '') . ' ' . ($m['conselho_codigo'] ?? '') . ' ' . ($m['conselho_uf'] ?? ''));
                                if ($cons) $label .= " — $cons";
                                return ['id' => $m['id'], 'label' => $label];
                            }, $medicos)) ?>;
    const mapaPrecos = <?= json_encode($mapa_precos) ?>;
    const maxDescontoPermitido = <?= (int)$desconto_maximo_usuario ?>;
    const proximoId = <?= (int)$proximoId ?>;

    // Globals for editing
    let isVisualizando = false;
    let isEditando = false;

    // Elements
    const tbodyItens = document.getElementById('tbody_itens_orcamento');
    const buscaExameInput = document.getElementById('busca_exame');
    const resultadoBuscaEl = document.getElementById('resultado_busca_exame');
    const selectConvenio = document.getElementById('orc_convenio_id');
    const campoTotalBruto = document.getElementById('total_bruto');
    const campoTotalLiquido = document.getElementById('total_liquido');
    const campoDescPerc = document.getElementById('desconto_percentual');
    const campoDescVal = document.getElementById('desconto_valor');
    const descontoAlert = document.getElementById('desconto_permitido_alert');

    // Helper functions
    function parseMoedaBR(v) {
        v = String(v || '').trim();
        if (v === '') return 0;
        v = v.replace(/\./g, '');
        v = v.replace(',', '.');
        return parseFloat(v) || 0;
    }

    function formatarMoedaBR(n) {
        return (n || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Dropdown Search Helper
    function setupDropdownSearch(inputId, hiddenId, listId, dataSource, onSelect = null) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const list = document.getElementById(listId);
        let activeIndex = -1;

        if (!input || !hidden || !list) return;

        function filter(showAll = false) {
            const val = input.value.toLowerCase();
            list.innerHTML = '';
            const filtered = showAll ? dataSource : dataSource.filter(item => (item.label || item.nome).toLowerCase().includes(val));

            // Limit results for performance
            const limit = 50;
            const displayList = filtered.slice(0, limit);

            if (displayList.length === 0) {
                list.classList.add('hidden');
                return;
            }

            displayList.forEach((item, index) => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.textContent = item.label || item.nome;
                // Highlight match
                if (!showAll && val.length > 0) {
                    // simple highlight logic if needed, skipping for now
                }

                a.onclick = () => {
                    selectItem(item);
                };
                li.appendChild(a);
                list.appendChild(li);
            });

            list.classList.remove('hidden');
            activeIndex = -1;
        }

        function selectItem(item) {
            input.value = item.label || item.nome;
            hidden.value = item.id;
            list.classList.add('hidden');
            if (onSelect) onSelect(item);
        }

        input.addEventListener('input', () => {
            hidden.value = ''; // Clear selection on type
            filter();
        });

        input.addEventListener('focus', () => {
            filter(true); // Show all (or filter if text exists)
        });

        // Hide on click outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !list.contains(e.target)) {
                list.classList.add('hidden');
            }
        });

        // Return a method to manually set value
        return {
            setValue: (id, triggerCallback = true) => {
                const item = dataSource.find(i => String(i.id) === String(id));
                if (item) {
                    input.value = item.label || item.nome;
                    hidden.value = item.id;
                    if (triggerCallback && onSelect) onSelect(item);
                } else {
                    input.value = '';
                    hidden.value = '';
                }
            },
            clear: () => {
                input.value = '';
                hidden.value = '';
            }
        };
    }

    // Init Dropdowns
    let pacienteDropdown, medicoDropdown;

    document.addEventListener('DOMContentLoaded', () => {
        pacienteDropdown = setupDropdownSearch('busca_paciente', 'orc_paciente_id', 'lista_paciente', listaPacientes, (item) => {
            const campanhaPadrao = item.campanha_padrao_id;
            const campanhaSel = document.getElementById('orc_campanha_programa_id');
            if (campanhaSel) {
                campanhaSel.value = campanhaPadrao || '';
                campanhaSel.dispatchEvent(new Event('change'));
            }
            
            const celularInput = document.getElementById('orc_celular');
            if (celularInput) {
                celularInput.value = item.celular || '';
            }
        });

        medicoDropdown = setupDropdownSearch('busca_medico', 'orc_medico_id', 'lista_medico', listaMedicos);
    });

    function abrirModalNovo() {
        document.getElementById('form_orcamento').reset();
        document.getElementById('orc_id').value = '';
        document.getElementById('orc_codigo').value = '01' + String(proximoId).padStart(9, '0');
        document.getElementById('modal_orcamento_titulo').innerText = 'Novo Orçamento';

        if (pacienteDropdown) pacienteDropdown.clear();
        if (medicoDropdown) medicoDropdown.clear();

        tbodyItens.innerHTML = '';
        document.getElementById('fieldsetOrc').disabled = false;
        document.getElementById('btnSalvarOrcamento').classList.remove('hidden');
        document.getElementById('area_busca_exame').classList.remove('hidden');

        isVisualizando = false;
        isEditando = false;

        // Default date
        document.getElementById('orc_validade').valueAsDate = new Date();

        document.getElementById('modal_orcamento').showModal();
        atualizarModoPaciente();
        recalcularTotais();
    }

    function abrirModalDesbloqueio(id) {
        document.getElementById('desbloqueio_orc_id').value = id;
        document.getElementById('modal_desbloqueio').showModal();
    }

    // Calculation Logic
    function recalcularTotais() {
        if (isVisualizando) return;
        let total = 0;
        document.querySelectorAll('#tbody_itens_orcamento .valor-exame').forEach(function(inp) {
            total += parseMoedaBR(inp.value);
        });
        if (campoTotalBruto) campoTotalBruto.value = formatarMoedaBR(total);

        let dp = parseFloat(String(campoDescPerc && campoDescPerc.value ? campoDescPerc.value : '0').replace('.', '').replace(',', '.')) || 0;
        let dv = parseMoedaBR(campoDescVal && campoDescVal.value ? campoDescVal.value : '0');

        if (dp > 0) {
            dv = Math.min(total, Math.round(total * dp / 100 * 100) / 100);
            if (campoDescVal) campoDescVal.value = formatarMoedaBR(dv);
        } else if (dv > 0) {
            dp = total > 0 ? Math.round(dv * 100 / total * 100) / 100 : 0;
            if (campoDescPerc) campoDescPerc.value = dp.toString().replace('.', ',');
        }

        const tl = Math.max(0, total - dv);
        if (campoTotalLiquido) campoTotalLiquido.value = formatarMoedaBR(tl);
        validarDescontoPermitido(total);
    }

    function validarDescontoPermitido(totalAtual) {
        const total = (typeof totalAtual === 'number') ? totalAtual : parseMoedaBR(campoTotalBruto ? campoTotalBruto.value : 0);
        let dp = parseFloat(String(campoDescPerc && campoDescPerc.value ? campoDescPerc.value : '0').replace(',', '.')) || 0;
        let dv = parseMoedaBR(campoDescVal && campoDescVal.value ? campoDescVal.value : '0');

        if (!(dp > 0)) {
            dp = total > 0 ? Math.round(dv * 100 / total * 100) / 100 : 0;
        }
        const excedeu = dp > maxDescontoPermitido;
        if (descontoAlert) descontoAlert.classList.toggle('hidden', !excedeu);
    }

    // Events
    if (campoDescPerc) {
        campoDescPerc.addEventListener('blur', function() {
            recalcularTotais();
        });
        campoDescPerc.addEventListener('change', function() {
            let total = parseMoedaBR(campoTotalBruto.value);
            let dp = parseFloat(this.value.replace(',', '.')) || 0;
            let dv = Math.round(total * dp / 100 * 100) / 100;
            campoDescVal.value = formatarMoedaBR(dv);
            recalcularTotais();
        });
    }
    if (campoDescVal) {
        campoDescVal.addEventListener('blur', function() {
            this.value = formatarMoedaBR(parseMoedaBR(this.value));
            recalcularTotais();
        });
        campoDescVal.addEventListener('change', function() {
            let total = parseMoedaBR(campoTotalBruto.value);
            let dv = parseMoedaBR(this.value);
            let dp = total > 0 ? Math.round(dv * 100 / total * 100) / 100 : 0;
            campoDescPerc.value = dp.toString().replace('.', ',');
            recalcularTotais();
        });
    }

    // Item management
    function bindEventosLinha(row) {
        const valorInput = row.querySelector('.valor-exame');
        if (valorInput) {
            valorInput.addEventListener('blur', function() {
                this.value = formatarMoedaBR(parseMoedaBR(this.value));
                recalcularTotais();
            });
        }
        const btnRem = row.querySelector('.btn-remover-exame');
        if (btnRem) {
            btnRem.addEventListener('click', function() {
                row.remove();
                recalcularTotais();
            });
        }
    }

    let pendingExameId = null;

    function mostrarSelecaoLab(apoios) {
        const div = document.getElementById('lista_labs_apoio');
        div.innerHTML = '';
        apoios.forEach(lab => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline justify-between normal-case h-auto py-2';
            btn.innerHTML = `
                <div class="flex flex-col items-start">
                    <span class="font-bold">${lab.laboratorio_nome}</span>
                    <span class="text-xs">Cód: ${lab.codigo_no_laboratorio || '-'}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="badge badge-sm badge-ghost">${lab.prazo} dias</span>
                </div>
            `;
            btn.onclick = () => {
                document.getElementById('modal_selecao_lab').close();
                adicionarExameNaGrid(pendingExameId, null, lab.laboratorio_id, lab.laboratorio_nome);
            };
            div.appendChild(btn);
        });
        document.getElementById('modal_selecao_lab').showModal();
    }

    function adicionarExameNaGrid(exameId, valorOverride = null, labId = null, labNome = null) {
        exameId = parseInt(exameId);
        if (document.querySelector('#tbody_itens_orcamento tr[data-exame-id="' + exameId + '"]')) {
            alert('Este exame já foi adicionado.');
            return;
        }
        const exame = listaExames.find(e => parseInt(e.id) === exameId);
        if (!exame) return;

        // Check support labs
        const apoios = listaApoios[exameId];
        if (!labId && apoios && apoios.length > 1) {
            pendingExameId = exameId;
            mostrarSelecaoLab(apoios);
            return;
        }

        const convenioId = parseInt(selectConvenio ? (selectConvenio.value || '0') : '0');
        let valor = 0;
        if (valorOverride !== null) {
            valor = valorOverride;
        } else if (mapaPrecos[exameId] && mapaPrecos[exameId][convenioId]) {
            valor = mapaPrecos[exameId][convenioId];
        }

        const tr = document.createElement('tr');
        tr.className = 'hover';
        tr.setAttribute('data-exame-id', exameId);

        let hiddenLab = '';
        if (labId) {
            hiddenLab = `<input type="hidden" name="itens[${exameId}][laboratorio_id]" value="${labId}">`;
        }

        let acaoHtml = `<button type="button" class="btn btn-ghost btn-xs text-error btn-remover-exame"><i class="bi bi-x"></i></button>
                        <input type="hidden" name="itens[${exameId}][exame_id]" value="${exameId}">
                        ${hiddenLab}`;

        let valorHtml = `<input type="text" name="itens[${exameId}][valor]" class="input input-xs input-bordered w-full text-right valor-exame" value="${formatarMoedaBR(valor)}">`;

        if (isVisualizando) {
            acaoHtml = '-';
            valorHtml = formatarMoedaBR(valor);
        }

        let nomeLabExibicao = labNome || exame.laboratorio_nome || '';

        tr.innerHTML = `
            <td class="text-center">${acaoHtml}</td>
            <td>${exame.mnemonico || ''}</td>
            <td>${exame.nome || ''}</td>
            <td>${exame.material_nome || ''}</td>
            <td>${nomeLabExibicao}</td>
            <td>${valorHtml}</td>
        `;
        tbodyItens.appendChild(tr);
        if (!isVisualizando) {
            bindEventosLinha(tr);
            recalcularTotais();
        }
    }

    // Search
    function atualizarListaResultados() {
        const termo = buscaExameInput.value.trim().toLowerCase();
        if (!termo || termo.length < 2) {
            resultadoBuscaEl.classList.add('hidden');
            return;
        }
        let html = '<ul class="menu bg-base-100 w-full p-2 rounded-box">';
        let count = 0;
        for (let ex of listaExames) {
            if ((ex.nome || '').toLowerCase().includes(termo) || (ex.mnemonico || '').toLowerCase().includes(termo)) {
                html += `<li><a href="javascript:void(0)" onclick="adicionarExameNaGrid(${ex.id}); document.getElementById('busca_exame').value=''; document.getElementById('resultado_busca_exame').classList.add('hidden');">
                    <span class="font-bold">${ex.mnemonico || ''}</span> ${ex.nome}
                    <span class="badge badge-sm">${ex.material_nome || ''}</span>
                </a></li>`;
                count++;
                if (count > 20) break;
            }
        }
        html += '</ul>';
        if (count === 0) html = '<div class="p-2 text-sm text-gray-500">Nenhum exame encontrado.</div>';

        resultadoBuscaEl.innerHTML = html;
        resultadoBuscaEl.classList.remove('hidden');
    }

    buscaExameInput.addEventListener('input', atualizarListaResultados);
    buscaExameInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const termo = this.value.trim().toLowerCase();
            const ex = listaExames.find(e => (e.mnemonico || '').toLowerCase() === termo);
            if (ex) {
                adicionarExameNaGrid(ex.id);
                this.value = '';
                resultadoBuscaEl.classList.add('hidden');
            }
        }
    });

    // Convenio Change
    if (selectConvenio) {
        selectConvenio.addEventListener('change', function() {
            if (isVisualizando) return;
            const cid = parseInt(this.value || '0');
            document.querySelectorAll('#tbody_itens_orcamento tr').forEach(row => {
                const eid = parseInt(row.getAttribute('data-exame-id'));
                const inp = row.querySelector('.valor-exame');
                if (eid && inp) {
                    let valor = 0;
                    if (mapaPrecos[eid] && mapaPrecos[eid][cid]) valor = mapaPrecos[eid][cid];
                    inp.value = formatarMoedaBR(valor);
                }
            });
            recalcularTotais();
        });
    }

    // Patient Mode
    const radioCad = document.getElementById('pac_modo_cad');
    const radioAv = document.getElementById('pac_modo_avulso');
    const wrapCad = document.getElementById('paciente_cadastrado_wrap');
    const wrapAv = document.getElementById('paciente_avulso_wrap');
    const selPac = document.getElementById('orc_paciente_id'); // Hidden
    const inpBuscaPac = document.getElementById('busca_paciente');
    const inpAv = document.getElementById('orc_paciente_avulso');

    function atualizarModoPaciente() {
        const av = radioAv.checked;
        wrapCad.classList.toggle('hidden', av);
        wrapAv.classList.toggle('hidden', !av);
        if (inpBuscaPac) {
            inpBuscaPac.disabled = av;
            inpBuscaPac.required = !av;
            if (av) {
                inpBuscaPac.value = '';
                if (selPac) selPac.value = '';
            }
        }
        if (inpAv) {
            inpAv.disabled = !av;
            inpAv.required = av;
            if (!av) inpAv.value = '';
        }
    }
    radioCad.addEventListener('change', atualizarModoPaciente);
    radioAv.addEventListener('change', atualizarModoPaciente);

    // Masks
    const mascaras = {
        '+55': '(##) #####-####',
        '+1': '(###) ###-####',
        '+351': '### ### ###',
        '+34': '### ### ###',
        '+33': '## ## ## ## ##',
        '+49': '#### ######',
        '+39': '### ### ####',
        '+44': '#### ######',
        '+52': '## #### ####',
        '+54': '### #### ####',
        '+56': '#### ### ###',
        '+57': '### ### ####',
        '+51': '### ### ###',
        '+595': '#### ######',
        '+598': '### ### ###',
        '+61': '#### ### ###',
        '+81': '###-####-####'
    };

    function aplicarMascara(input, mask) {
        let v = input.value.replace(/\D/g, '');
        let r = '';
        let idx = 0;
        for (let i = 0; i < mask.length && idx < v.length; i++) {
            if (mask[i] === '#') r += v[idx++];
            else r += mask[i];
        }
        input.value = r;
    }
    const celPais = document.getElementById('orc_celular_pais');
    const celInput = document.getElementById('orc_celular');
    if (celInput && celPais) {
        function onCelInput() {
            aplicarMascara(celInput, mascaras[celPais.value] || '###############');
        }
        celInput.addEventListener('input', onCelInput);
        celPais.addEventListener('change', function() {
            celInput.value = '';
            onCelInput();
        });
    }

    // Parceiro Discount
    const selParceiro = document.getElementById('orc_parceiro_id');
    const selCampanha = document.getElementById('orc_campanha_programa_id');

    function aplicarDescontoExterno(selectEl) {
        if (isVisualizando) return;
        const opt = selectEl.options[selectEl.selectedIndex];
        const desc = parseFloat(opt.getAttribute('data-desconto')) || 0;
        if (campoDescPerc) {
            campoDescPerc.value = desc.toString().replace('.', ',');
            campoDescVal.value = '0,00';
            recalcularTotais();
        }
    }

    if (selParceiro) {
        selParceiro.addEventListener('change', function() {
            aplicarDescontoExterno(this);
        });
    }
    if (selCampanha) {
        selCampanha.addEventListener('change', function() {
            aplicarDescontoExterno(this);
        });
    }

    // Auto Open Edit/View
    <?php if ($editando || $visualizando): ?>
        document.addEventListener('DOMContentLoaded', () => {
            isEditando = <?= $editando ? 'true' : 'false' ?>;
            isVisualizando = <?= $visualizando ? 'true' : 'false' ?>;

            // Fill Data
            const orcData = <?= json_encode($orcamento) ?>;
            const itensData = <?= json_encode($itens_existentes) ?>;

            document.getElementById('orc_id').value = orcData.id;
            document.getElementById('orc_codigo').value = orcData.codigo_orcamento || ('01' + String(orcData.id).padStart(9, '0'));
            document.getElementById('orc_validade').value = orcData.validade;

            if (orcData.paciente_avulso) {
                radioAv.checked = true;
                inpAv.value = orcData.paciente_avulso;
            } else {
                radioCad.checked = true;
                if (pacienteDropdown) pacienteDropdown.setValue(orcData.paciente_id, false);
            }
            atualizarModoPaciente();

            document.getElementById('orc_posto_id').value = orcData.posto_id;
            document.getElementById('orc_convenio_id').value = orcData.convenio_id;
            if (medicoDropdown) medicoDropdown.setValue(orcData.medico_id, false);
            document.getElementById('orc_parceiro_id').value = orcData.parceiro_id;
            document.getElementById('orc_campanha_programa_id').value = orcData.campanha_programa_id;

            // Populate Cell Phone
            const fullCell = orcData.contato_celular || '';
            let foundCountry = '+55';
            let cleanPhone = fullCell;
            const countries = ['+55', '+1', '+351', '+34', '+33', '+49', '+39', '+44', '+52', '+54', '+56', '+57', '+51', '+595', '+598', '+61', '+81'];

            // Try to match country code
            for (let c of countries) {
                // Remove any potential spaces from fullCell for check or just check raw
                // Assuming fullCell starts with +CC
                if (fullCell.trim().startsWith(c)) {
                    foundCountry = c;
                    cleanPhone = fullCell.trim().substring(c.length).trim();
                    break;
                }
            }

            const celPaisEl = document.getElementById('orc_celular_pais');
            const celInpEl = document.getElementById('orc_celular');
            if (celPaisEl) celPaisEl.value = foundCountry;
            if (celInpEl) {
                celInpEl.value = cleanPhone;
                // Apply mask if function exists
                if (typeof aplicarMascara === 'function' && mascaras[foundCountry]) {
                    aplicarMascara(celInpEl, mascaras[foundCountry]);
                }
            }

            document.getElementById('total_bruto').value = formatarMoedaBR(orcData.total_bruto);
            document.getElementById('desconto_percentual').value = String(orcData.desconto_percentual).replace('.', ',');
            document.getElementById('desconto_valor').value = formatarMoedaBR(orcData.desconto_valor);
            document.getElementById('total_liquido').value = formatarMoedaBR(orcData.total_liquido);

            // Load Items
            tbodyItens.innerHTML = '';
            Object.values(itensData).forEach(item => {
                adicionarExameNaGrid(item.exame_id, parseFloat(item.valor));
            });

            // Set Title
            document.getElementById('modal_orcamento_titulo').innerText = isVisualizando ? 'Visualizar Orçamento' : 'Editar Orçamento';

            if (isVisualizando) {
                document.getElementById('fieldsetOrc').disabled = true;
                document.getElementById('btnSalvarOrcamento').classList.add('hidden');
                document.getElementById('area_busca_exame').classList.add('hidden');
            } else {
                document.getElementById('fieldsetOrc').disabled = false;
                document.getElementById('btnSalvarOrcamento').classList.remove('hidden');
                document.getElementById('area_busca_exame').classList.remove('hidden');
            }

            document.getElementById('modal_orcamento').showModal();
            recalcularTotais();
        });
    <?php endif; ?>

    // CRM Functions
    function clickContinuar() {
        if (confirm('Deseja continuar a negociação deste orçamento? O status voltará para Negociação.')) {
            submitCRM('continuar');
        }
    }

    function clickPerdemos() {
        const div = document.getElementById('divMotivo');
        const sel = document.getElementById('motivoCRM');
        const msg = document.getElementById('mensagemCRM');

        // Ensure message is not required for loss, but reason is
        if (msg) msg.required = false;

        if (div.classList.contains('hidden')) {
            div.classList.remove('hidden');
            if (sel) sel.focus();
            // Optional: Change button text? Not easy since we don't have a reference to "this".
            // But the user will see the dropdown appear.
            return;
        }

        if (sel && sel.value === '') {
            alert('Por favor, selecione um motivo.');
            sel.focus();
            return;
        }

        if (confirm('Confirmar que PERDEMOS este orçamento?')) {
            submitCRM('perdemos');
        }
    }

    function abrirCRM(id) {
        const contentDiv = document.getElementById('crm_content_area');
        contentDiv.innerHTML = '<div class="flex justify-center p-4"><span class="loading loading-spinner loading-lg"></span></div>';
        document.getElementById('modal_crm').showModal();

        fetch('index.php?r=orcamentos/crm_history&id=' + id)
            .then(r => r.text())
            .then(html => {
                contentDiv.innerHTML = html;
            })
            .catch(err => {
                contentDiv.innerHTML = '<div class="alert alert-error">Erro ao carregar CRM.</div>';
            });
    }

    function submitCRM(acao) {
        const msg = document.getElementById('mensagemCRM');
        if (acao === 'comentario') {
            if (msg && msg.value.trim() === '') {
                msg.required = true; // Ensure it's required
                alert('Digite uma anotação.');
                msg.focus();
                return;
            }
        }

        const form = document.getElementById('formCRM');
        if (!form.reportValidity()) return;

        document.getElementById('acaoCRM').value = acao;
        const formData = new FormData(form);

        fetch('index.php?r=orcamentos/crm_save', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'ok') {
                    document.getElementById('modal_crm').close();
                    // Refresh page to update status if needed
                    window.location.reload();
                } else {
                    alert('Erro ao salvar.');
                }
            })
            .catch(err => alert('Erro de comunicação.'));
    }
</script>