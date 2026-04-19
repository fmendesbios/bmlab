<div class="flex flex-col gap-4">
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-primary"><i class="bi bi-wallet2"></i> Tipos de Recebimentos</h2>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div role="alert" class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <span><?= $_SESSION['flash_success'] ?></span>
            <?php unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div role="alert" class="alert alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <span><?= $_SESSION['flash_error'] ?></span>
            <?php unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div role="tablist" class="tabs tabs-boxed">
        <a role="tab" class="tab <?= $tab === 'tipos' ? 'tab-active' : '' ?>" href="index.php?r=tipos_recebimentos&tab=tipos">Tipos</a>
        <a role="tab" class="tab <?= $tab === 'classificacoes' ? 'tab-active' : '' ?>" href="index.php?r=tipos_recebimentos&tab=classificacoes">Classificações</a>
    </div>

    <!-- Tab Content: Tipos -->
    <?php if ($tab === 'tipos'): ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex flex-col md:flex-row justify-between gap-4 mb-4">
                    <!-- Search -->
                    <form action="index.php" method="get" class="join">
                        <input type="hidden" name="r" value="tipos_recebimentos">
                        <input type="hidden" name="tab" value="tipos">
                        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full md:w-64" placeholder="Buscar...">
                        <button class="btn btn-square join-item"><i class="bi bi-search"></i></button>
                    </form>

                    <button class="btn btn-primary" onclick="openTipoModal()">
                        <i class="bi bi-plus-lg"></i> Novo Tipo
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Classificação</th>
                                <th>Status</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos as $t): ?>
                                <tr>
                                    <td><?= $t['id'] ?></td>
                                    <td class="font-bold"><?= htmlspecialchars($t['nome']) ?></td>
                                    <td>
                                        <?php if ($t['classificacao_nome']): ?>
                                            <span class="badge badge-outline"><?= htmlspecialchars($t['classificacao_nome']) ?></span>
                                        <?php else: ?>
                                            <span class="opacity-50">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['ativo']): ?>
                                            <span class="badge badge-success badge-sm">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge badge-ghost badge-sm">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-sm btn-ghost text-primary" onclick='openTipoModal(<?= json_encode($t) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="index.php?r=tipos_recebimentos/delete&id=<?= $t['id'] ?>" class="btn btn-sm btn-ghost text-error" onclick="return confirm('Excluir este tipo?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tipos)): ?>
                                <tr>
                                    <td colspan="5" class="text-center opacity-50 py-8">Nenhum registro encontrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab Content: Classificações -->
    <?php if ($tab === 'classificacoes'): ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-end mb-4">
                    <button class="btn btn-primary" onclick="openClassModal()">
                        <i class="bi bi-plus-lg"></i> Nova Classificação
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classificacoes as $c): ?>
                                <tr>
                                    <td><?= $c['id'] ?></td>
                                    <td class="font-bold"><?= htmlspecialchars($c['nome']) ?></td>
                                    <td class="text-right">
                                        <button class="btn btn-sm btn-ghost text-primary" onclick='openClassModal(<?= json_encode($c) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="index.php?r=tipos_recebimentos/delete_classificacao&id=<?= $c['id'] ?>" class="btn btn-sm btn-ghost text-error" onclick="return confirm('Excluir esta classificação?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($classificacoes)): ?>
                                <tr>
                                    <td colspan="3" class="text-center opacity-50 py-8">Nenhuma classificação encontrada.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Tipo -->
<dialog id="modal_tipo" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4" id="modal_tipo_title">Novo Tipo</h3>
        <form method="post" action="index.php?r=tipos_recebimentos/store">
            <input type="hidden" name="id" id="tipo_id" value="0">
            
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Nome *</span></label>
                <input type="text" name="nome" id="tipo_nome" class="input input-bordered w-full" required>
            </div>

            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Classificação</span></label>
                <select name="classificacao_id" id="tipo_classificacao" class="select select-bordered w-full">
                    <option value="">Selecione...</option>
                    <?php foreach ($classificacoes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-control mb-6">
                <label class="label cursor-pointer justify-start gap-4">
                    <span class="label-text">Ativo</span> 
                    <input type="checkbox" name="ativo" id="tipo_ativo" class="checkbox checkbox-primary" checked />
                </label>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_tipo').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Modal: Classificação -->
<dialog id="modal_classificacao" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4" id="modal_class_title">Nova Classificação</h3>
        <form method="post" action="index.php?r=tipos_recebimentos/store_classificacao">
            <input type="hidden" name="id" id="class_id" value="0">
            
            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Nome *</span></label>
                <input type="text" name="nome" id="class_nome" class="input input-bordered w-full" required>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_classificacao').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function openTipoModal(data = null) {
    const modal = document.getElementById('modal_tipo');
    if (data) {
        document.getElementById('modal_tipo_title').innerText = 'Editar Tipo';
        document.getElementById('tipo_id').value = data.id;
        document.getElementById('tipo_nome').value = data.nome;
        document.getElementById('tipo_classificacao').value = data.classificacao_id || '';
        document.getElementById('tipo_ativo').checked = (data.ativo == 1);
    } else {
        document.getElementById('modal_tipo_title').innerText = 'Novo Tipo';
        document.getElementById('tipo_id').value = 0;
        document.getElementById('tipo_nome').value = '';
        document.getElementById('tipo_classificacao').value = '';
        document.getElementById('tipo_ativo').checked = true;
    }
    modal.showModal();
}

function openClassModal(data = null) {
    const modal = document.getElementById('modal_classificacao');
    if (data) {
        document.getElementById('modal_class_title').innerText = 'Editar Classificação';
        document.getElementById('class_id').value = data.id;
        document.getElementById('class_nome').value = data.nome;
    } else {
        document.getElementById('modal_class_title').innerText = 'Nova Classificação';
        document.getElementById('class_id').value = 0;
        document.getElementById('class_nome').value = '';
    }
    modal.showModal();
}
</script>
