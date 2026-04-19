<div class="p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-base-content">Fechamento de Caixa</h1>
            <p class="text-sm text-base-content/70">Gerencie os lotes de fechamento financeiro</p>
        </div>
        <button onclick="document.getElementById('modal_novo_fechamento').showModal()" class="btn btn-primary gap-2">
            <i class="bi bi-plus-lg"></i> Novo Fechamento
        </button>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="r" value="fechamento_caixa">

                <div class="form-control">
                    <label class="label"><span class="label-text">Período (Início)</span></label>
                    <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" class="input input-bordered w-full input-sm">
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text">Período (Fim)</span></label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="input input-bordered w-full input-sm">
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text">Posto</span></label>
                    <select name="posto_id" class="select select-bordered w-full select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($postos as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $posto_id == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome_posto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text">Usuário</span></label>
                    <select name="usuario_id" class="select select-bordered w-full select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $usuario_id == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-control">
                    <button type="submit" class="btn btn-ghost btn-sm bg-base-200">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card bg-base-100 shadow-xl">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Posto</th>
                        <th>Caixa</th>
                        <th>Período</th>
                        <th>Fechado em</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Status</th>
                        <th>Validado em</th>
                        <th>Validado por</th>
                        <th>Remetido em</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lotes)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-8 text-base-content/50">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="bi bi-inbox text-4xl"></i>
                                    <span>Nenhum fechamento encontrado no período.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lotes as $lote): ?>
                            <?php
                            $statusClass = 'badge-ghost';
                            $statusLabel = 'Novo';
                            if ($lote['status'] === 'V') {
                                $statusClass = 'badge-info';
                                $statusLabel = 'Validado';
                            } elseif ($lote['status'] === 'E') {
                                $statusClass = 'badge-success';
                                $statusLabel = 'Enviado';
                            }

                            $ref = $lote['posto_nome'] ? 'Posto: ' . $lote['posto_nome'] : ($lote['usuario_nome'] ? 'Usuário: ' . $lote['usuario_nome'] : '-');
                            ?>
                            <tr class="hover">
                                <td class="font-mono text-xs">#<?= $lote['id'] ?></td>
                                <td><?= htmlspecialchars($lote['posto_nome'] ?? '-') ?></td>
                                <td class="text-xs">
                                    <?= htmlspecialchars($lote['usuario_nome'] ?? '-') ?>
                                </td>
                                <td class="text-sm">
                                    <div class="flex flex-col">
                                        <span class="text-xs opacity-70">De: <?= date('d/m/Y H:i', strtotime($lote['inicio'])) ?></span>
                                        <span class="text-xs opacity-70">Até: <?= date('d/m/Y H:i', strtotime($lote['fim'])) ?></span>
                                    </div>
                                </td>
                                <td class="text-xs"><?= $lote['fechado_em'] ? date('d/m/Y H:i', strtotime($lote['fechado_em'])) : '-' ?></td>
                                <td class="text-right font-bold font-mono">
                                    R$ <?= number_format($lote['total_conferido'], 2, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $statusClass ?> gap-1">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= $lote['validado_em'] ? date('d/m/Y H:i', strtotime($lote['validado_em'])) : '-' ?></td>
                                <td class="text-xs"><?= htmlspecialchars($lote['validado_por_nome'] ?? '-') ?></td>
                                <td class="text-xs"><?= $lote['remetido_em'] ? date('d/m/Y H:i', strtotime($lote['remetido_em'])) : '-' ?></td>
                                <td class="text-center">
                                    <div class="join">
                                        <button onclick='abrirDetalhes(<?= json_encode($lote) ?>)' class="btn btn-xs btn-ghost join-item" title="Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($lote['status'] !== 'E'): ?>
                                            <button onclick="excluirLote(<?= $lote['id'] ?>)" class="btn btn-xs btn-ghost text-error join-item" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Novo Fechamento -->
<dialog id="modal_novo_fechamento" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Novo Fechamento de Caixa</h3>
        <form id="form_novo_fechamento" onsubmit="criarLote(event)">
            <input type="hidden" name="acao" value="criar_lote_simples">

            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Posto (Opcional)</span></label>
                <select name="posto_id" class="select select-bordered w-full">
                    <option value="0">Selecione...</option>
                    <?php foreach ($postos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome_posto']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="label"><span class="label-text-alt">Se não selecionar Posto, o fechamento será vinculado ao seu Usuário atual.</span></label>
            </div>

            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Data/Hora Final do Fechamento</span></label>
                <input type="datetime-local" name="fim" required class="input input-bordered w-full" value="<?= date('Y-m-d\TH:i') ?>">
                <label class="label"><span class="label-text-alt">O sistema calculará o caixa do início do dia até este horário.</span></label>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_novo_fechamento').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-calculator"></i> Calcular e Criar
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Modal Detalhes -->
<dialog id="modal_detalhes" class="modal">
    <div class="modal-box w-11/12 max-w-3xl">
        <h3 class="font-bold text-lg mb-4">Detalhes do Fechamento #<span id="detalhe_id"></span></h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="stats shadow stats-vertical lg:stats-horizontal bg-base-200">
                <div class="stat">
                    <div class="stat-title">Total Calculado</div>
                    <div class="stat-value text-primary text-2xl" id="detalhe_total">R$ 0,00</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Status</div>
                    <div class="stat-value text-lg" id="detalhe_status">-</div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-box border border-base-300">
                <h4 class="font-semibold mb-2 text-sm uppercase opacity-70">Informações</h4>
                <p class="text-sm"><strong>Início:</strong> <span id="detalhe_inicio"></span></p>
                <p class="text-sm"><strong>Fim:</strong> <span id="detalhe_fim"></span></p>
                <p class="text-sm"><strong>Referência:</strong> <span id="detalhe_ref"></span></p>
            </div>
        </div>

        <h4 class="font-bold mb-2">Composição do Caixa</h4>
        <div class="overflow-x-auto mb-6">
            <table class="table table-sm w-full bg-base-100 border border-base-300">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody id="detalhe_metodos_body">
                    <!-- JS fills this -->
                </tbody>
            </table>
        </div>

        <div class="modal-action justify-between">
            <button type="button" class="btn" onclick="document.getElementById('modal_detalhes').close()">Fechar</button>
            <div class="join" id="detalhe_actions">
                <!-- JS fills this -->
            </div>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
    function getSwalTarget() {
        const m1 = document.getElementById('modal_detalhes');
        const m2 = document.getElementById('modal_novo_fechamento');
        if (m1 && m1.open) return m1;
        if (m2 && m2.open) return m2;
        return 'body';
    }

    async function criarLote(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const btn = form.querySelector('button[type="submit"]');

        // Basic validation
        // if (formData.get('posto_id') == '0' && formData.get('usuario_id') == '0') {
        //    Swal.fire({ ... });
        //    return;
        // }

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="loading loading-spinner"></span> Processando...';

        try {
            const resp = await fetch('index.php?r=fechamento_caixa/store', {
                method: 'POST',
                body: formData
            });

            let data;
            const text = await resp.text();
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Non-JSON response:', text);
                throw new Error('Resposta inválida do servidor');
            }

            if (data.ok) {
                Swal.fire({
                    title: 'Sucesso',
                    text: 'Fechamento criado com sucesso!',
                    icon: 'success',
                    target: getSwalTarget()
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erro',
                    text: data.erro || 'Erro ao criar fechamento',
                    icon: 'error',
                    target: getSwalTarget()
                });
            }
        } catch (err) {
            console.error(err);
            Swal.fire({
                title: 'Erro',
                text: 'Erro de conexão: ' + err.message,
                icon: 'error',
                target: getSwalTarget()
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function abrirDetalhes(lote) {
        document.getElementById('detalhe_id').innerText = lote.id;
        document.getElementById('detalhe_total').innerText = parseFloat(lote.total_conferido).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });

        const statusMap = {
            'N': 'Novo',
            'V': 'Validado',
            'E': 'Enviado'
        };
        document.getElementById('detalhe_status').innerText = statusMap[lote.status] || lote.status;

        document.getElementById('detalhe_inicio').innerText = new Date(lote.inicio).toLocaleString('pt-BR');
        document.getElementById('detalhe_fim').innerText = new Date(lote.fim).toLocaleString('pt-BR');

        let ref = '';
        if (lote.posto_nome) ref += 'Posto: ' + lote.posto_nome + ' ';
        if (lote.usuario_nome) ref += 'Usuário: ' + lote.usuario_nome;
        document.getElementById('detalhe_ref').innerText = ref;

        // Methods
        const tbody = document.getElementById('detalhe_metodos_body');
        tbody.innerHTML = '';
        let metodos = {};
        try {
            metodos = JSON.parse(lote.totais_por_metodo || '{}');
        } catch (e) {}

        if (Object.keys(metodos).length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center opacity-50">Nenhum valor registrado</td></tr>';
        } else {
            for (const [k, v] of Object.entries(metodos)) {
                const val = parseFloat(v);
                tbody.innerHTML += `
                <tr>
                    <td>${k}</td>
                    <td class="text-right font-mono">${val.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'})}</td>
                </tr>
            `;
            }
        }

        // Actions
        const actions = document.getElementById('detalhe_actions');
        actions.innerHTML = '';

        if (lote.status === 'N') {
            actions.innerHTML += `
            <button onclick="validarLote(${lote.id})" class="btn btn-info join-item text-white">
                <i class="fas fa-check"></i> Validar
            </button>
        `;
        }

        if (lote.status === 'V' || lote.status === 'N') {
            actions.innerHTML += `
            <button onclick="enviarLote(${lote.id})" class="btn btn-success join-item text-white">
                <i class="fas fa-paper-plane"></i> Enviar p/ Financeiro
            </button>
        `;
        }

        if (lote.status === 'E') {
            actions.innerHTML += `<span class="btn btn-disabled join-item">Já Enviado (Código: ${lote.remessa_codigo || '-'})</span>`;
        }

        document.getElementById('modal_detalhes').showModal();
    }

    async function validarLote(id) {
        if (!await confirmAction('Confirmar validação deste lote?')) return;

        const formData = new FormData();
        formData.append('acao', 'validar_lote');
        formData.append('lote_id', id);

        executeAction(formData);
    }

    async function enviarLote(id) {
        if (!await confirmAction('Confirmar envio deste lote para o Contas a Receber? Esta ação não pode ser desfeita.')) return;

        const formData = new FormData();
        formData.append('acao', 'enviar_lote');
        formData.append('lote_id', id);

        executeAction(formData);
    }

    async function excluirLote(id) {
        if (!await confirmAction('Tem certeza que deseja excluir este fechamento?')) return;

        const formData = new FormData();
        formData.append('acao', 'excluir_lote');
        formData.append('lote_id', id);

        executeAction(formData);
    }

    async function confirmAction(msg) {
        const res = await Swal.fire({
            title: 'Confirmação',
            text: msg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim',
            cancelButtonText: 'Não',
            target: getSwalTarget()
        });
        return res.isConfirmed;
    }

    async function executeAction(formData) {
        try {
            const resp = await fetch('index.php?r=fechamento_caixa/store', {
                method: 'POST',
                body: formData
            });

            let data;
            const text = await resp.text();
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Non-JSON response:', text);
                throw new Error('Resposta inválida do servidor');
            }

            if (data.ok) {
                Swal.fire({
                    title: 'Sucesso',
                    text: 'Operação realizada!',
                    icon: 'success',
                    target: getSwalTarget()
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erro',
                    text: data.erro || 'Erro na operação',
                    icon: 'error',
                    target: getSwalTarget()
                });
            }
        } catch (err) {
            console.error(err);
            Swal.fire({
                title: 'Erro',
                text: 'Erro de conexão: ' + err.message,
                icon: 'error',
                target: getSwalTarget()
            });
        }
    }
</script>