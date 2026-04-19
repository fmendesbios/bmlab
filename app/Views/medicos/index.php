<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-person-video2"></i> Médicos</h1>
    <?php if (temPermissao('medicos.acesso')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Médico
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
        <!-- Search & Pagination -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <!-- Search Form -->
            <form method="GET" action="index.php" class="flex gap-2 w-full md:w-auto">
                <input type="hidden" name="r" value="medicos">
                <div class="join w-full max-w-sm">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome, CRM ou código...">
                    <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
                </div>
                <?php if (!empty($busca)): ?>
                    <a href="index.php?r=medicos" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>

            <!-- Pagination Controls -->
            <?php 
                $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
                $end = min($page * $limit, $totalRecords);
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                $q = !empty($busca) ? '&busca='.urlencode($busca) : '';
            ?>
            <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                <div class="join">
                    <!-- First -->
                    <a href="index.php?r=medicos&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=medicos&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=medicos&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=medicos&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=medicos&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
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
                        <th class="w-24">Cód</th>
                        <th>Nome do Médico</th>
                        <th>Conselho</th>
                        <th class="text-center">Status</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($medicos) > 0): ?>
                        <?php foreach($medicos as $m): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($m['codigo_medico'] ?? '01'.str_pad($m['id'], 5, '0', STR_PAD_LEFT)) ?></td>
                            <td class="font-bold"><?= htmlspecialchars($m['nome']) ?></td>
                            <td>
                                <span class="badge badge-ghost badge-sm">
                                    <?= htmlspecialchars($m['conselho']) ?> <?= htmlspecialchars($m['conselho_codigo']) ?>/<?= htmlspecialchars($m['conselho_uf']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if($m['ativo']): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('medicos.acesso')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($m)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    
                                    <a href="index.php?r=medicos/excluir&id=<?= $m['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este médico?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-base-content/50">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                Nenhum médico encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Exibindo <?= count($medicos) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_medico" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-3xl">
        <h3 class="font-bold text-lg" id="modal_title">Novo Médico</h3>
        <form method="POST" action="index.php?r=medicos/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Código (Auto) -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_medico" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Auto" />
                </div>

                <!-- Nome -->
                <div class="form-control md:col-span-9">
                    <label class="label"><span class="label-text font-bold">Nome do Médico *</span></label>
                    <input type="text" name="nome" id="input_nome" class="input input-bordered w-full" required />
                </div>

                <!-- Conselho Tipo -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Conselho *</span></label>
                    <select name="conselho" id="input_conselho" class="select select-bordered w-full">
                        <?php foreach($opcoesConselho as $opc): ?>
                            <option value="<?= $opc ?>"><?= $opc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Conselho Número -->
                <div class="form-control md:col-span-6">
                    <label class="label"><span class="label-text">Número Conselho *</span></label>
                    <input type="text" name="conselho_codigo" id="input_conselho_codigo" class="input input-bordered w-full" required />
                </div>

                <!-- Conselho UF -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">UF Conselho *</span></label>
                    <select name="conselho_uf" id="input_conselho_uf" class="select select-bordered w-full">
                        <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                        <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                        <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                        <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                        <option value="MG">MG</option><option value="PA" selected>PA</option><option value="PB">PB</option>
                        <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                        <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                        <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                        <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                    </select>
                </div>

                <!-- Ativo -->
                <div class="form-control md:col-span-12">
                    <label class="cursor-pointer label justify-start gap-4 mt-4">
                        <span class="label-text">Ativo</span>
                        <input type="checkbox" name="ativo" id="input_ativo" class="toggle toggle-primary" checked />
                    </label>
                </div>

            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_medico').close()">Cancelar</button>
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
    document.getElementById('input_conselho').value = 'CRM';
    document.getElementById('input_conselho_codigo').value = '';
    document.getElementById('input_conselho_uf').value = 'PA';
    document.getElementById('input_ativo').checked = true;
    
    // Fetch next code
    fetch('index.php?r=medicos/next_code')
        .then(response => response.json())
        .then(data => {
            if (data.code) {
                document.getElementById('input_codigo').value = data.code;
            }
        })
        .catch(err => console.error('Erro ao buscar código:', err));

    document.getElementById('modal_title').innerText = 'Novo Médico';
    document.getElementById('modal_medico').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_codigo').value = data.codigo_medico || '';
    document.getElementById('input_nome').value = data.nome;
    document.getElementById('input_conselho').value = data.conselho || 'CRM';
    document.getElementById('input_conselho_codigo').value = data.conselho_codigo || '';
    document.getElementById('input_conselho_uf').value = data.conselho_uf || 'PA';
    document.getElementById('input_ativo').checked = (data.ativo == 1);
    
    document.getElementById('modal_title').innerText = 'Editar Médico';
    document.getElementById('modal_medico').showModal();
}
</script>
