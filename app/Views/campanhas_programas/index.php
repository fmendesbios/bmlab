<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-megaphone"></i> Campanhas/Programas</h1>
    <?php if (temPermissao('campanhas_programas.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Nova Campanha/Programa
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
            <input type="hidden" name="r" value="campanhas_programas">
            <div class="join w-full max-w-sm">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome ou código...">
                <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
            </div>
            <?php if (!empty($busca)): ?>
                <a href="index.php?r=campanhas_programas" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-32">Código</th>
                        <th>Nome</th>
                        <th>Desconto (%)</th>
                        <th class="w-32 text-center">Status</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($campanhas) > 0): ?>
                        <?php foreach($campanhas as $c): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($c['codigo_campanha_programa']) ?></td>
                            <td class="font-bold"><?= htmlspecialchars($c['nome_campanha_programa']) ?></td>
                            <td><?= number_format($c['desconto_permitido'] ?? 0, 2, ',', '.') ?>%</td>
                            <td class="text-center">
                                <?php if($c['status'] == 'A'): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('campanhas_programas.alterar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($c)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('campanhas_programas.deletar')): ?>
                                    <a href="index.php?r=campanhas_programas/delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir esta campanha/programa?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-8 opacity-50">Nenhum registro encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-6">
            <div class="join">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="index.php?r=campanhas_programas&page=<?= $i ?>&busca=<?= urlencode($busca) ?>" class="join-item btn btn-sm <?= $i == $page ? 'btn-active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Form -->
<dialog id="modal_form" class="modal">
    <div class="modal-box w-11/12 max-w-2xl">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        
        <h3 class="font-bold text-lg mb-4" id="modal_title">Nova Campanha/Programa</h3>
        
        <form method="POST" action="index.php?r=campanhas_programas/store" id="form_campanha">
            <input type="hidden" name="id" id="form_id" value="0">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Código -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_campanha_programa" id="form_codigo" class="input input-bordered" readonly placeholder="Automático">
                </div>
                
                <!-- Status -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select name="status" id="form_status" class="select select-bordered">
                        <option value="A">Ativo</option>
                        <option value="I">Inativo</option>
                    </select>
                </div>
                
                <!-- Nome -->
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Nome da Campanha/Programa <span class="text-error">*</span></span></label>
                    <input type="text" name="nome_campanha_programa" id="form_nome" class="input input-bordered" required>
                </div>

                <!-- Desconto Permitido -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Desconto Permitido (%)</span></label>
                    <input type="number" step="0.01" name="desconto_permitido" id="form_desconto" class="input input-bordered" placeholder="0.00">
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_form').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    const modal = document.getElementById('modal_form');
    const form = document.getElementById('form_campanha');
    const title = document.getElementById('modal_title');

    function openModal() {
        form.reset();
        document.getElementById('form_id').value = '0';
        document.getElementById('form_codigo').value = '';
        title.innerText = 'Nova Campanha/Programa';
        
        // Fetch next code
        fetch('index.php?r=campanhas_programas/next_code')
            .then(res => res.json())
            .then(data => {
                if(data.code) {
                    document.getElementById('form_codigo').value = data.code;
                }
            });
            
        modal.showModal();
    }

    function edit(data) {
        form.reset();
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_codigo').value = data.codigo_campanha_programa;
        document.getElementById('form_nome').value = data.nome_campanha_programa;
        document.getElementById('form_status').value = data.status;
        document.getElementById('form_desconto').value = data.desconto_permitido;
        
        title.innerText = 'Editar Campanha/Programa';
        modal.showModal();
    }
</script>
