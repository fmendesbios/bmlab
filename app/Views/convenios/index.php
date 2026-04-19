<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-journal-medical"></i> Convênios</h1>
    <?php if (temPermissao('convenios.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Convênio
    </button>
    <?php endif; ?>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_success'])): ?>
    <div role="alert" class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
        <?php unset($_SESSION['flash_success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div role="alert" class="alert alert-error mb-4">
        <i class="bi bi-exclamation-circle"></i>
        <span><?= $_SESSION['flash_error'] ?></span>
        <?php unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>

<!-- Search & Table -->
<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-4 md:p-6">
        <!-- Search Form -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <form method="GET" action="index.php" class="flex gap-2 w-full md:w-auto">
                <input type="hidden" name="r" value="convenios">
                <div class="join w-full max-w-sm">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome, tipo ou código...">
                    <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
                </div>
                <?php if (!empty($busca)): ?>
                    <a href="index.php?r=convenios" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>

            <!-- Pagination Controls -->
            <?php
            $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
            $end = min($page * $limit, $totalRecords);
            $prev = max(1, $page - 1);
            $next = min($totalPages, $page + 1);
            $q = !empty($busca) ? '&busca=' . urlencode($busca) : '';
            ?>
            <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                <div class="join">
                    <!-- First -->
                    <a href="index.php?r=convenios&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=convenios&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=convenios&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=convenios&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=convenios&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-forward-fill"></i>
                    </a>
                </div>
                <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                    <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-20">Cód</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th class="text-center">Faturável</th>
                        <th class="text-center">Status</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($convenios) > 0): ?>
                        <?php foreach($convenios as $c): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($c['codigo_convenio'] ?? str_pad($c['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                            <td class="font-bold"><?= htmlspecialchars($c['nome']) ?></td>
                            <td>
                                <?php 
                                    $tipos = [
                                        'particular' => 'Particular',
                                        'cortesia' => 'Cortesia',
                                        'plano' => 'Plano de Saúde',
                                        'governo' => 'Governo / SUS',
                                        'outros' => 'Outros'
                                    ];
                                    echo $tipos[$c['tipo']] ?? $c['tipo'];
                                ?>
                            </td>
                            <td class="text-center">
                                <?php if($c['faturavel']): ?>
                                    <i class="bi bi-check-circle-fill text-success" title="Sim"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-base-content/30" title="Não"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($c['ativo']): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('convenios.salvar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($c)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('convenios.novo')): // Using 'novo' as proxy for delete rights as per Controller ?>
                                    <a href="index.php?r=convenios/excluir&id=<?= $c['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este convênio?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-base-content/50">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                Nenhum convênio encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Exibindo <?= count($convenios) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_convenio" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-2xl">
        <h3 class="font-bold text-lg" id="modal_title">Novo Convênio</h3>
        <form method="POST" action="index.php?r=convenios/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Código (Auto) -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_convenio" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Auto" />
                </div>

                <!-- Nome -->
                <div class="form-control md:col-span-9">
                    <label class="label"><span class="label-text font-bold">Nome do Convênio *</span></label>
                    <input type="text" name="nome" id="input_nome" class="input input-bordered w-full" required />
                </div>

                <!-- Tipo -->
                <div class="form-control md:col-span-6">
                    <label class="label"><span class="label-text">Tipo</span></label>
                    <select name="tipo" id="input_tipo" class="select select-bordered w-full">
                        <option value="plano">Plano de Saúde</option>
                        <option value="particular">Particular</option>
                        <option value="cortesia">Cortesia</option>
                        <option value="governo">Governo / SUS</option>
                        <option value="outros">Outros</option>
                    </select>
                </div>

                <!-- Faturável -->
                <div class="form-control md:col-span-3">
                    <label class="cursor-pointer label justify-start gap-4 mt-8">
                        <span class="label-text">Faturável</span>
                        <input type="checkbox" name="faturavel" id="input_faturavel" class="checkbox checkbox-primary" checked />
                    </label>
                </div>

                <!-- Ativo -->
                <div class="form-control md:col-span-3">
                    <label class="cursor-pointer label justify-start gap-4 mt-8">
                        <span class="label-text">Ativo</span>
                        <input type="checkbox" name="ativo" id="input_ativo" class="toggle toggle-primary" checked />
                    </label>
                </div>

            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_convenio').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function openModal() {
    document.getElementById('input_id').value = '0';
    document.getElementById('input_codigo').value = '';
    document.getElementById('input_nome').value = '';
    document.getElementById('input_tipo').value = 'plano';
    document.getElementById('input_faturavel').checked = true;
    document.getElementById('input_ativo').checked = true;
    
    // Fetch Next Code
    fetch('index.php?r=convenios/next_code')
        .then(res => res.json())
        .then(data => {
            if(data.code) {
                document.getElementById('input_codigo').value = data.code;
            }
        });

    document.getElementById('modal_title').innerText = 'Novo Convênio';
    document.getElementById('modal_convenio').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_codigo').value = data.codigo_convenio || '';
    document.getElementById('input_nome').value = data.nome;
    document.getElementById('input_tipo').value = data.tipo || 'plano';
    document.getElementById('input_faturavel').checked = (data.faturavel == 1);
    document.getElementById('input_ativo').checked = (data.ativo == 1);
    
    document.getElementById('modal_title').innerText = 'Editar Convênio';
    document.getElementById('modal_convenio').showModal();
}
</script>
