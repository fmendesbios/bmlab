<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-building"></i> Laboratórios</h1>
    <?php if (temPermissao('laboratorios.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Laboratório
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
        <form method="GET" action="index.php" class="flex gap-2 mb-6">
            <input type="hidden" name="r" value="laboratorios">
            <div class="join w-full max-w-sm">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome ou código...">
                <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
            </div>
            <?php if (!empty($busca)): ?>
                <a href="index.php?r=laboratorios" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-20">Cód</th>
                        <th>Nome do Laboratório</th>
                        <th class="text-center">Tipo</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($laboratorios) > 0): ?>
                        <?php foreach($laboratorios as $l): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($l['codigo_laboratorio'] ?? str_pad($l['id'], 5, '0', STR_PAD_LEFT)) ?></td>
                            <td class="font-bold"><?= htmlspecialchars($l['laboratorio_nome']) ?></td>
                            <td class="text-center">
                                <?php if($l['tipo'] === 'local'): ?>
                                    <span class="badge badge-primary badge-sm">Local</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral badge-sm">Apoio</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('laboratorios.alterar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($l)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('laboratorios.deletar')): ?>
                                    <a href="index.php?r=laboratorios/excluir&id=<?= $l['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este laboratório?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-8 text-base-content/50">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                Nenhum laboratório encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Total de registros: <?= count($laboratorios) ?>
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_laboratorio" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-2xl">
        <h3 class="font-bold text-lg" id="modal_title">Novo Laboratório</h3>
        <form method="POST" action="index.php?r=laboratorios/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Código (Auto) -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_laboratorio" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Auto" />
                </div>

                <!-- Nome -->
                <div class="form-control md:col-span-9">
                    <label class="label"><span class="label-text font-bold">Nome do Laboratório *</span></label>
                    <input type="text" name="laboratorio_nome" id="input_nome" class="input input-bordered w-full" required />
                </div>

                <!-- Tipo -->
                <div class="form-control md:col-span-12">
                    <label class="label"><span class="label-text">Tipo de Laboratório</span></label>
                    <select name="tipo" id="input_tipo" class="select select-bordered w-full">
                        <option value="apoio">Apoio</option>
                        <option value="local">Local</option>
                    </select>
                </div>

            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_laboratorio').close()">Cancelar</button>
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
    document.getElementById('input_tipo').value = 'apoio';
    
    // Fetch next code
    fetch('index.php?r=laboratorios/next_code')
        .then(response => response.json())
        .then(data => {
            if (data.code) {
                document.getElementById('input_codigo').value = data.code;
            }
        })
        .catch(err => console.error('Erro ao buscar código:', err));

    document.getElementById('modal_title').innerText = 'Novo Laboratório';
    document.getElementById('modal_laboratorio').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_codigo').value = data.codigo_laboratorio || '';
    document.getElementById('input_nome').value = data.laboratorio_nome;
    document.getElementById('input_tipo').value = data.tipo || 'apoio';
    
    document.getElementById('modal_title').innerText = 'Editar Laboratório';
    document.getElementById('modal_laboratorio').showModal();
}
</script>
