<div class="flex flex-col md:flex-row gap-6 h-full">
    <!-- Sidebar: Groups List -->
    <div class="w-full md:w-1/4 flex flex-col gap-4">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold"><i class="bi bi-people-fill"></i> Grupos</h2>
            <button class="btn btn-sm btn-primary" onclick="openNewGroupModal()">
                <i class="bi bi-plus-lg"></i> Novo
            </button>
        </div>

        <div class="card bg-base-100 shadow-xl overflow-hidden">
            <ul class="menu bg-base-100 w-full p-0 [&_li>*]:rounded-none">
                <?php foreach ($grupos as $g): ?>
                    <li>
                        <a href="index.php?r=grupos&id_grupo=<?= $g['id'] ?>" class="<?= ($id_grupo_selecionado == $g['id']) ? 'active' : '' ?>">
                            <?= htmlspecialchars($g['nome']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Main Content: Edit & Matrix -->
    <div class="w-full md:w-3/4">
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

        <?php if ($id_grupo_selecionado > 0 && $grupo_atual): ?>
            <!-- Group Edit Form -->
            <div class="card bg-base-100 shadow-xl mb-6">
                <div class="card-body p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="card-title text-base">Editar Grupo</h3>
                        <a href="index.php?r=grupos/delete&id=<?= $grupo_atual['id'] ?>" class="btn btn-sm btn-ghost text-error" onclick="return confirm('Tem certeza? Isso removerá o grupo e desvinculará usuários.')">
                            <i class="bi bi-trash"></i> Excluir
                        </a>
                    </div>
                    <form method="post" action="index.php?r=grupos/store" class="flex flex-col md:flex-row gap-4 items-end">
                        <input type="hidden" name="id_grupo" value="<?= $grupo_atual['id'] ?>">
                        <div class="form-control w-full md:w-1/3">
                            <label class="label"><span class="label-text">Nome</span></label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($grupo_atual['nome']) ?>" class="input input-bordered w-full" required>
                        </div>
                        <div class="form-control w-full md:w-1/2">
                            <label class="label"><span class="label-text">Descrição</span></label>
                            <input type="text" name="descricao" value="<?= htmlspecialchars($grupo_atual['descricao']) ?>" class="input input-bordered w-full">
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>

            <!-- Permissions Matrix -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h3 class="card-title text-base mb-4">Permissões de Acesso</h3>
                    
                    <form method="post" action="index.php?r=grupos/save_permissions">
                        <input type="hidden" name="id_grupo" value="<?= $id_grupo_selecionado ?>">
                        <input type="hidden" name="page" value="<?= $page ?>">

                        <div class="overflow-x-auto">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr>
                                        <th class="w-1/3 bg-base-200">Módulo / Categoria</th>
                                        <?php 
                                        $cols = ['menu', 'acesso', 'novo', 'alterar', 'salvar', 'cancelar', 'deletar', 'listar'];
                                        foreach($cols as $c): ?>
                                            <th class="text-center capitalize bg-base-200"><?= $c ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matriz_paginada as $categoria => $acoes): ?>
                                        <tr class="hover">
                                            <td class="font-semibold bg-base-100"><?= htmlspecialchars($categoria) ?></td>
                                            <?php foreach($cols as $c): ?>
                                                <td class="text-center">
                                                    <?php if (isset($acoes[$c])): 
                                                        $perm = $acoes[$c];
                                                        $checked = in_array($perm['id'], $permissoes_grupo) ? 'checked' : '';
                                                    ?>
                                                        <input type="checkbox" name="perms[]" value="<?= $perm['id'] ?>" class="toggle toggle-xs toggle-success" <?= $checked ?>>
                                                    <?php else: ?>
                                                        <span class="opacity-10">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination & Actions -->
                        <div class="flex justify-between items-center mt-6">
                            <div class="join">
                                <?php for($i=1; $i<=$total_pages; $i++): ?>
                                    <a href="index.php?r=grupos&id_grupo=<?= $id_grupo_selecionado ?>&page=<?= $i ?>" class="join-item btn btn-sm <?= ($page == $i) ? 'btn-active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salvar Permissões (Pág. <?= $page ?>)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="hero bg-base-100 rounded-box shadow-xl h-full">
                <div class="hero-content text-center">
                    <div class="max-w-md">
                        <h1 class="text-2xl font-bold opacity-50">Selecione um grupo ao lado</h1>
                        <p class="py-6 opacity-50">Ou crie um novo grupo para começar a gerenciar permissões.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Group Modal -->
<dialog id="modal_novo_grupo" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Novo Grupo</h3>
        <form method="post" action="index.php?r=grupos/store">
            <input type="hidden" name="id_grupo" value="0">
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Nome do Grupo *</span></label>
                <input type="text" name="nome" class="input input-bordered w-full" required>
            </div>
            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Descrição</span></label>
                <input type="text" name="descricao" class="input input-bordered w-full">
            </div>
            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_novo_grupo').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function openNewGroupModal() {
    document.getElementById('modal_novo_grupo').showModal();
}
</script>
