<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-geo-alt"></i> Municípios</h1>
    <?php if (temPermissao('municipios.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Município
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
                <input type="hidden" name="r" value="municipios">
                <div class="join w-full max-w-sm">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca ?? '') ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome, UF ou CEP...">
                    <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
                </div>
                <?php if (!empty($busca)): ?>
                    <a href="index.php?r=municipios" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
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
                    <a href="index.php?r=municipios&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=municipios&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=municipios&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=municipios&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=municipios&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-forward-fill"></i>
                    </a>
                </div>
                <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                    <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <?php if (temPermissao('municipios.menu')): ?>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-24">Código</th>
                        <th class="w-24">Situação</th>
                        <th>Município</th>
                        <th class="w-16">UF</th>
                        <th class="w-32">CEP</th>
                        <th class="w-40">Criado em</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($municipios)): ?>
                        <?php foreach($municipios as $m): ?>
                        <tr class="hover">
                            <th><?= htmlspecialchars($m['codigo_municipio'] ?? str_pad($m['id'], 6, '0', STR_PAD_LEFT)) ?></th>
                            <td>
                                <?php if ((int)$m['status'] === 1): ?>
                                    <div class="badge badge-success gap-2 text-white">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-error gap-2 text-white">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="font-medium"><?= htmlspecialchars($m['nome']) ?></td>
                            <td><?= htmlspecialchars($m['uf']) ?></td>
                            <td><?= htmlspecialchars($m['cep']) ?></td>
                            <td class="text-sm opacity-70"><?= isset($m['criado_em']) ? date('d/m/Y H:i', strtotime($m['criado_em'])) : '-' ?></td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('municipios.alterar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick='edit(<?= json_encode($m) ?>)' title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('municipios.deletar')): ?>
                                    <a href="index.php?r=municipios/excluir&id=<?= $m['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este município?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-base-content/50">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                Nenhum registro encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Exibindo <?= count($municipios) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>

<!-- Modal DaisyUI -->
<dialog id="modal_municipio" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box">
        <h3 class="font-bold text-lg" id="modal_title">Novo Município</h3>
        <form method="POST" action="index.php?r=municipios/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Código -->
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_municipio" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly />
                    <label class="label"><span class="label-text-alt">Gerado automaticamente</span></label>
                </div>

                <!-- Situação -->
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Situação</span></label>
                    <label class="cursor-pointer label justify-start gap-4 border rounded-btn p-3">
                        <input type="checkbox" name="status" id="input_status" value="1" class="toggle toggle-success" checked />
                        <span class="label-text" id="label_status">Ativo</span>
                    </label>
                </div>

                <!-- Município -->
                <div class="form-control w-full md:col-span-2">
                    <label class="label"><span class="label-text font-medium">Município *</span></label>
                    <input type="text" name="nome" id="input_nome" class="input input-bordered w-full" required placeholder="Ex: São Paulo" />
                </div>
                
                <!-- UF -->
                <div class="form-control w-full">
                    <label class="label"><span class="label-text font-medium">UF *</span></label>
                    <select name="uf" id="input_uf" class="select select-bordered w-full" required>
                        <option value="">Selecione...</option>
                        <?php if (isset($ufs)): ?>
                            <?php foreach ($ufs as $u): ?>
                                <option value="<?= $u ?>"><?= $u ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- CEP -->
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">CEP</span></label>
                    <input type="text" name="cep" id="input_cep" class="input input-bordered w-full" maxlength="9" placeholder="00000-000" />
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_municipio').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // CEP Mask
    const cepInput = document.getElementById('input_cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 8) v = v.substring(0, 8);
            if (v.length > 5) v = v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
            e.target.value = v;
        });
    }

    // Toggle label update
    const statusInput = document.getElementById('input_status');
    const statusLabel = document.getElementById('label_status');
    if (statusInput && statusLabel) {
        statusInput.addEventListener('change', function() {
            statusLabel.innerText = this.checked ? 'Ativo' : 'Inativo';
        });
    }
});

function openModal() {
    document.getElementById('input_id').value = '0';
    document.getElementById('input_nome').value = '';
    document.getElementById('input_uf').value = '';
    document.getElementById('input_cep').value = '';
    // Use PHP value for next ID if available
    document.getElementById('input_codigo').value = '<?= str_pad((string)($proximoId ?? ''), 6, "0", STR_PAD_LEFT) ?>';
    
    document.getElementById('input_status').checked = true;
    document.getElementById('label_status').innerText = 'Ativo';
    
    document.getElementById('modal_title').innerText = 'Novo Município';
    document.getElementById('modal_municipio').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_nome').value = data.nome;
    document.getElementById('input_uf').value = data.uf;
    document.getElementById('input_cep').value = data.cep || '';
    
    let cod = data.codigo_municipio;
    if (!cod) {
        cod = String(data.id).padStart(6, '0');
    }
    document.getElementById('input_codigo').value = cod;
    
    const isActive = parseInt(data.status) === 1;
    document.getElementById('input_status').checked = isActive;
    document.getElementById('label_status').innerText = isActive ? 'Ativo' : 'Inativo';
    
    document.getElementById('modal_title').innerText = 'Editar Município';
    document.getElementById('modal_municipio').showModal();
}
</script>
