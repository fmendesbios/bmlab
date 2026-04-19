<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Pendências de Material -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-error"><i class="bi bi-exclamation-triangle-fill"></i> Pendências de Material</h2>
            
            <div class="overflow-y-auto max-h-[600px]">
                <?php if (empty($pendMateriais)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Nenhuma pendência encontrada.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach ($pendMateriais as $p): ?>
                            <div class="card bg-base-200 border-l-4 border-error p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-bold text-lg"><?= htmlspecialchars($p['paciente_nome']) ?></h3>
                                        <p class="text-sm opacity-70">Pedido: <?= $p['pedido_id'] ?> | Amostra: <?= $p['id_amostra'] ?></p>
                                        <p class="text-sm font-semibold mt-1"><?= htmlspecialchars($p['exame_nome']) ?> (<?= htmlspecialchars($p['mnemonico']) ?>)</p>
                                        <p class="text-xs italic"><?= htmlspecialchars($p['material_biologico']) ?></p>
                                    </div>
                                    <button class="btn btn-sm btn-ghost" onclick='openPendenciaModal(<?= json_encode($p) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                                <?php if ($p['data_entrega_material_triagem']): ?>
                                    <div class="badge badge-warning gap-2 mt-2">
                                        <i class="bi bi-clock"></i> Prev: <?= date('d/m/Y', strtotime($p['data_entrega_material_triagem'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($p['obs_pendencia_triagem']): ?>
                                    <p class="text-xs mt-2 bg-base-100 p-2 rounded">
                                        <strong>Obs:</strong> <?= htmlspecialchars($p['obs_pendencia_triagem']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bloqueios de Desconto -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-warning"><i class="bi bi-lock-fill"></i> Bloqueios de Desconto</h2>
            
            <div class="overflow-y-auto max-h-[600px]">
                <?php if (empty($bloqPedidos) && empty($bloqOrcamentos)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Nenhum bloqueio pendente.
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-4">
                        <!-- Pedidos -->
                        <?php foreach ($bloqPedidos as $bp): ?>
                            <div class="card bg-base-200 border-l-4 border-warning p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="badge badge-sm badge-outline mb-1">Pedido #<?= $bp['id'] ?></div>
                                        <h3 class="font-bold"><?= htmlspecialchars($bp['paciente_nome']) ?></h3>
                                        <p class="text-sm">Total: R$ <?= number_format($bp['total_bruto'], 2, ',', '.') ?></p>
                                        <p class="text-sm text-error font-bold">
                                            Desc: <?= number_format($bp['desconto_percentual'], 2, ',', '.') ?>% 
                                            (R$ <?= number_format($bp['desconto_valor'], 2, ',', '.') ?>)
                                        </p>
                                    </div>
                                    <button class="btn btn-sm btn-warning" onclick="openDesbloqueioModal('P', <?= $bp['id'] ?>)">
                                        <i class="bi bi-unlock"></i> Liberar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Orçamentos -->
                        <?php foreach ($bloqOrcamentos as $bo): ?>
                            <div class="card bg-base-200 border-l-4 border-info p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="badge badge-sm badge-outline badge-info mb-1">Orçamento #<?= $bo['id'] ?></div>
                                        <h3 class="font-bold"><?= htmlspecialchars($bo['paciente_nome'] ?: $bo['paciente_avulso']) ?></h3>
                                        <p class="text-sm">Total: R$ <?= number_format($bo['total_bruto'], 2, ',', '.') ?></p>
                                        <p class="text-sm text-error font-bold">
                                            Desc: <?= number_format($bo['desconto_percentual'], 2, ',', '.') ?>% 
                                            (R$ <?= number_format($bo['desconto_valor'], 2, ',', '.') ?>)
                                        </p>
                                    </div>
                                    <button class="btn btn-sm btn-info" onclick="openDesbloqueioModal('O', <?= $bo['id'] ?>)">
                                        <i class="bi bi-unlock"></i> Liberar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Novos Cards (Top 10, Aniversariantes, Check-up) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <!-- Orçamentos (Novo/Negociação) -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-info mb-2">
                <i class="bi bi-file-text-fill"></i> Orçamentos
            </h2>

            <div class="flex gap-2 mb-2 w-full">
                <div class="flex-1 bg-base-200 rounded p-2 flex items-center justify-between">
                    <span class="text-xs font-bold text-info uppercase">Novos</span>
                    <span class="text-xl font-black"><?= $total_novo ?? 0 ?></span>
                </div>
                <div class="flex-1 bg-base-200 rounded p-2 flex items-center justify-between">
                    <span class="text-xs font-bold text-warning uppercase">Negociação</span>
                    <span class="text-xl font-black"><?= $total_negociacao ?? 0 ?></span>
                </div>
            </div>
            
            <div class="overflow-y-auto max-h-[400px]">
                <?php if (empty($orcamentos)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Nenhum orçamento encontrado.
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-2">
                        <?php foreach ($orcamentos as $o): ?>
                            <div class="flex justify-between items-center p-2 hover:bg-base-200 rounded cursor-pointer border border-base-200" onclick="window.location.href='index.php?r=orcamentos&acao=ver&id=<?= $o['id'] ?>'">
                                <div class="flex items-center gap-2">
                                    <div class="badge badge-sm badge-info">
                                        <?= htmlspecialchars(($o['codigo_orcamento'] ?? '') !== '' ? (string)$o['codigo_orcamento'] : ('01' . str_pad((string)$o['id'], 9, '0', STR_PAD_LEFT))) ?>
                                    </div>
                                    <div class="text-sm">
                                        <div class="font-semibold"><?= htmlspecialchars($o['paciente']) ?></div>
                                        <div class="text-xs opacity-70">Usuário: <?= htmlspecialchars($o['usuario']) ?></div>
                                    </div>
                                </div>
                                <div class="badge badge-sm <?= $o['status'] === 'N' ? 'badge-info' : 'badge-warning' ?>">
                                    <?= $o['status'] === 'N' ? 'Novo' : 'Negociação' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_paginas_orc > 1): ?>
                <div class="join mt-4 justify-center">
                    <?php for ($i = 1; $i <= $total_paginas_orc; $i++): ?>
                        <a href="index.php?r=home&pag_aniv=<?= $pag_aniv ?>&pag_check=<?= $pag_check ?>&pag_orc=<?= $i ?>" class="join-item btn btn-sm <?= $i == $pag_orc ? 'btn-active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Coluna Central: Aniversariantes e Check-up -->
    <div class="flex flex-col gap-6">
        <!-- Aniversariantes -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="card-title text-secondary"><i class="bi bi-gift"></i> Aniversariantes</h2>
                    <button class="btn btn-sm btn-ghost btn-circle" onclick="abrirModulo('disparos_whatsapp/logs_envio.php','Logs de Mensagens','bi-chat-dots')" title="Logs">
                        <i class="bi bi-chat-dots"></i>
                    </button>
                </div>

                <form id="form-aniversariantes">
                    <div class="flex gap-2 mb-4 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline" onclick="selecionarTodos(true)">Todos</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="selecionarTodos(false)">Limpar</button>
                        <button type="submit" class="btn btn-xs btn-success text-white">Enviar Mensagem</button>
                    </div>

                    <div class="flex flex-col gap-2 max-h-[400px] overflow-y-auto">
                        <?php if (!empty($aniversariantes)): ?>
                            <?php foreach ($aniversariantes as $p): ?>
                                <label class="flex justify-between items-center p-2 hover:bg-base-200 rounded cursor-pointer border border-base-200">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="selecionados[]" value="<?= htmlspecialchars($p['whatsapp']) ?>" data-nome="<?= htmlspecialchars($p['nome']) ?>" class="checkbox checkbox-sm checkbox-primary">
                                        <div class="text-sm">
                                            <div class="font-semibold"><?= htmlspecialchars($p['nome']) ?></div>
                                            <div class="text-xs opacity-70"><?= $p['nascimento_formatado'] ?></div>
                                        </div>
                                    </div>
                                    <div class="badge badge-sm badge-ghost"><?= htmlspecialchars($p['whatsapp']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-sm opacity-70">Nenhum aniversariante.</div>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="join mt-4 justify-center">
                    <?php for ($i = 1; $i <= $total_paginas_aniv; $i++): ?>
                        <a href="index.php?r=home&pag_aniv=<?= $i ?>&pag_check=<?= $pag_check ?>&pag_orc=<?= $pag_orc ?>" class="join-item btn btn-sm <?= $i == $pag_aniv ? 'btn-active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Check-up -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="card-title text-error"><i class="bi bi-heart-pulse"></i> Check-up (90 dias)</h2>
                    <button class="btn btn-sm btn-ghost btn-circle" onclick="abrirModulo('disparos_whatsapp/logs_envio.php','Logs de Mensagens','bi-chat-dots')" title="Logs">
                        <i class="bi bi-chat-dots"></i>
                    </button>
                </div>

                <form id="form-checkup">
                    <div class="flex gap-2 mb-4 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline" onclick="selecionarTodosCheckup(true)">Todos</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="selecionarTodosCheckup(false)">Limpar</button>
                        <button type="submit" class="btn btn-xs btn-success text-white">Enviar Mensagem</button>
                    </div>

                    <div class="flex flex-col gap-2 max-h-[400px] overflow-y-auto">
                        <?php if (!empty($checkups)): ?>
                            <?php foreach ($checkups as $c): ?>
                                <label class="flex justify-between items-center p-2 hover:bg-base-200 rounded cursor-pointer border border-base-200">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="checkup[]"
                                            value="<?= htmlspecialchars($c['whatsapp']) ?>"
                                            data-nome="<?= htmlspecialchars($c['nome']) ?>"
                                            data-pedido-id="<?= htmlspecialchars($c['pedido_id']) ?>"
                                            class="checkbox checkbox-sm checkbox-error">
                                        <div class="text-sm">
                                            <div class="font-semibold"><?= htmlspecialchars($c['nome']) ?></div>
                                            <div class="text-xs opacity-70"><?= $c['data_formatada'] ?></div>
                                        </div>
                                    </div>
                                    <div class="badge badge-sm badge-ghost"><?= htmlspecialchars($c['whatsapp']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-sm opacity-70">Nenhum check-up pendente.</div>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="join mt-4 justify-center">
                    <?php for ($i = 1; $i <= $total_paginas_check; $i++): ?>
                        <a href="index.php?r=home&pag_aniv=<?= $pag_aniv ?>&pag_check=<?= $i ?>&pag_orc=<?= $pag_orc ?>" class="join-item btn btn-sm <?= $i == $pag_check ? 'btn-active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 Pacientes -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-primary"><i class="bi bi-trophy"></i> Top 10 pacientes</h2>
            
            <form method="get" action="index.php" class="flex gap-2 mb-2">
                <input type="hidden" name="r" value="home">
                <input type="hidden" name="pag_aniv" value="<?= $pag_aniv ?>">
                <input type="hidden" name="pag_check" value="<?= $pag_check ?>">
                <input type="hidden" name="pag_orc" value="<?= $pag_orc ?>">
                
                <input type="text" name="busca_nome" class="input input-bordered input-sm w-full"
                    placeholder="Pesquisar nome"
                    value="<?= htmlspecialchars($nome_busca ?? '') ?>">
                
                <button class="btn btn-sm btn-primary" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <?php if (!empty($nome_busca)): ?>
                <div class="bg-base-200 p-2 rounded mb-2">
                    <?php if (empty($resultados_busca)): ?>
                        <span class="text-sm opacity-70">Nenhum paciente encontrado.</span>
                    <?php else: ?>
                        <div class="flex flex-col gap-2">
                            <?php foreach ($resultados_busca as $rb): ?>
                                <?php
                                $total = (int)$rb['total_pedidos'];
                                $mod = $total % 5;
                                $faltam = ($total === 0) ? 5 : (($mod === 0) ? 0 : (5 - $mod));
                                if ($total > 0 && $mod === 0) {
                                    $beneficio_rb = 'Próximo pedido: 20% OFF';
                                    $badgeClass_rb = 'badge-success';
                                } else {
                                    $beneficio_rb = "Faltam {$faltam} p/ 20% OFF";
                                    $badgeClass_rb = 'badge-warning';
                                }
                                ?>
                                <div class="flex justify-between items-center border-b border-base-300 pb-1 last:border-0">
                                    <div>
                                        <div class="font-semibold text-sm"><?= htmlspecialchars($rb['nome']) ?></div>
                                        <?php if (!empty($rb['ultimo_pedido'])): ?>
                                            <div class="text-xs opacity-70">Último: <?= htmlspecialchars($rb['ultimo_pedido']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="badge badge-sm badge-neutral">Pedidos: <?= $total ?></span>
                                        <span class="badge badge-sm <?= $badgeClass_rb ?> text-xs text-center h-auto py-1"><?= $beneficio_rb ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col gap-2">
                <?php if (!empty($top_clientes)): ?>
                    <?php foreach ($top_clientes as $tc): ?>
                        <?php
                        $total  = (int)$tc['total_pedidos'];
                        $mod    = $total % 5;
                        $faltam = ($total === 0) ? 5 : (($mod === 0) ? 0 : (5 - $mod));
                        if ($total > 0 && $mod === 0) {
                            $beneficio = "Próximo pedido: 20% OFF";
                            $badgeClass = 'badge-success';
                        } else {
                            $beneficio = "Faltam {$faltam} p/ 20% OFF";
                            $badgeClass = 'badge-warning';
                        }
                        ?>
                        <div class="flex justify-between items-center border-b border-base-200 pb-2 last:border-0">
                            <div>
                                <div class="font-semibold text-sm"><?= htmlspecialchars($tc['nome']) ?></div>
                                <?php if (!empty($tc['ultimo_pedido'])): ?>
                                    <div class="text-xs opacity-70">Último: <?= htmlspecialchars($tc['ultimo_pedido']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="badge badge-sm badge-neutral">Pedidos: <?= $total ?></span>
                                <span class="badge badge-sm <?= $badgeClass ?> text-xs text-center h-auto py-1"><?= $beneficio ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-sm opacity-70">Sem dados para o ranking.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modulo (Iframe) -->
<dialog id="modal_modulo" class="modal">
    <div class="modal-box w-11/12 max-w-5xl h-[80vh]">
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold text-lg" id="modal_modulo_title">Módulo</h3>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost">✕</button>
            </form>
        </div>
        <iframe id="modal_modulo_iframe" src="" class="w-full h-full border-0"></iframe>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>


<!-- Modal Pendência -->
<dialog id="modal_pendencia" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Atualizar Pendência</h3>
        <form method="post" action="index.php?r=home/update_pendencia">
            <input type="hidden" name="pedido_id" id="pend_pedido_id">
            <input type="hidden" name="id_amostra" id="pend_amostra_id">
            
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Data Prevista Entrega</span></label>
                <input type="date" name="data_entrega_material_triagem" id="pend_data" class="input input-bordered w-full">
            </div>

            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Observação</span></label>
                <textarea name="obs_pendencia_triagem" id="pend_obs" class="textarea textarea-bordered w-full" rows="3"></textarea>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_pendencia').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Modal Desbloqueio -->
<dialog id="modal_desbloqueio" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Autorizar Desbloqueio</h3>
        <p class="mb-4 text-sm opacity-70">Insira as credenciais de um usuário com permissão de desconto suficiente.</p>
        
        <form id="form_desbloqueio">
            <input type="hidden" name="tipo" id="desb_tipo">
            <input type="hidden" name="id" id="desb_id">
            
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Usuário</span></label>
                <input type="text" name="usuario_login" class="input input-bordered w-full" required>
            </div>

            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Senha</span></label>
                <input type="password" name="senha_login" class="input input-bordered w-full" required>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_desbloqueio').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Autorizar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function openPendenciaModal(data) {
    document.getElementById('pend_pedido_id').value = data.pedido_id;
    document.getElementById('pend_amostra_id').value = data.id_amostra;
    document.getElementById('pend_data').value = data.data_entrega_material_triagem || '';
    document.getElementById('pend_obs').value = data.obs_pendencia_triagem || '';
    document.getElementById('modal_pendencia').showModal();
}

function openDesbloqueioModal(tipo, id) {
    document.getElementById('desb_tipo').value = tipo;
    document.getElementById('desb_id').value = id;
    document.getElementById('form_desbloqueio').reset();
    document.getElementById('modal_desbloqueio').showModal();
}

document.getElementById('form_desbloqueio').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('index.php?r=home/desbloquear', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            alert(data.msg);
            location.reload();
        } else {
            alert('Erro: ' + data.erro);
        }
    })
    .catch(err => alert('Erro de conexão.'));
});

// --- Funções para os Cards (Top 10, Aniversariantes, Check-up) ---

function selecionarTodos(checked) {
    document.querySelectorAll('#form-aniversariantes input[name="selecionados[]"]').forEach(el => el.checked = checked);
}

function selecionarTodosCheckup(checked) {
    document.querySelectorAll('#form-checkup input[name="checkup[]"]').forEach(el => el.checked = checked);
}

function abrirModulo(url, title, icon) {
    document.getElementById('modal_modulo_title').innerText = title;
    document.getElementById('modal_modulo_iframe').src = url;
    document.getElementById('modal_modulo').showModal();
}

document.getElementById('form-aniversariantes').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const checkboxes = this.querySelectorAll('input[name="selecionados[]"]:checked');
    if (checkboxes.length === 0) {
        Swal.fire('Atenção', 'Selecione pelo menos um aniversariante.', 'warning');
        return;
    }

    const lista = [];
    checkboxes.forEach(cb => {
        lista.push({
            nome: cb.getAttribute('data-nome'),
            telefone: cb.value
        });
    });

    Swal.fire({
        title: 'Enviar Mensagens?',
        text: `Deseja enviar mensagem para ${lista.length} aniversariante(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch('disparos_whatsapp/enviar_aniversario.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aniversariantes: lista })
            })
            .then(res => res.json())
            .then(data => {
                if (Array.isArray(data)) {
                     const erros = data.filter(d => d.status === 'falha');
                     if (erros.length > 0) {
                         Swal.fire('Atenção', `${data.length - erros.length} enviados com sucesso. ${erros.length} falhas.`, 'warning');
                     } else {
                         Swal.fire('Sucesso', 'Todas as mensagens foram enviadas!', 'success');
                     }
                } else if (data.sucesso === false) {
                    Swal.fire('Erro', data.erro || 'Erro desconhecido', 'error');
                } else {
                     Swal.fire('Sucesso', 'Processo concluído.', 'success');
                }
            })
            .catch(err => Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error'));
        }
    });
});

document.getElementById('form-checkup').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const checkboxes = this.querySelectorAll('input[name="checkup[]"]:checked');
    if (checkboxes.length === 0) {
        Swal.fire('Atenção', 'Selecione pelo menos um paciente.', 'warning');
        return;
    }

    const lista = [];
    checkboxes.forEach(cb => {
        lista.push({
            nome: cb.getAttribute('data-nome'),
            telefone: cb.value,
            pedido_id: cb.getAttribute('data-pedido-id')
        });
    });

    Swal.fire({
        title: 'Enviar Lembretes?',
        text: `Deseja enviar lembrete para ${lista.length} paciente(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch('disparos_whatsapp/enviar_checkup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pacientes: lista })
            })
            .then(res => res.json())
            .then(data => {
                 if (Array.isArray(data)) {
                     const erros = data.filter(d => d.status === 'falha');
                     if (erros.length > 0) {
                         Swal.fire('Atenção', `${data.length - erros.length} enviados com sucesso. ${erros.length} falhas.`, 'warning');
                     } else {
                         Swal.fire('Sucesso', 'Todas as mensagens foram enviadas!', 'success');
                     }
                } else if (data.sucesso === false) {
                    Swal.fire('Erro', data.erro || 'Erro desconhecido', 'error');
                } else {
                     Swal.fire('Sucesso', 'Processo concluído.', 'success');
                }
            })
            .catch(err => Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error'));
        }
    });
});

</script>
