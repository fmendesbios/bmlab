<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-people-fill"></i> Usuários</h1>
    <?php if (temPermissao('usuarios.acesso')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Usuário
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
            <input type="hidden" name="r" value="usuarios">
            <div class="join w-full max-w-sm">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Nome, Email ou Login...">
                <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
            </div>
            <?php if (!empty($busca)): ?>
                <a href="index.php?r=usuarios" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Login / Email</th>
                        <th>Grupo</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Criado em</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios) > 0): ?>
                        <?php foreach($usuarios as $u): ?>
                        <tr class="hover">
                            <td>
                                <div class="font-bold"><?= htmlspecialchars($u['nome']) ?></div>
                            </td>
                            <td>
                                <div class="font-mono text-sm"><?= htmlspecialchars($u['usuario']) ?></div>
                                <div class="text-xs opacity-70"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td>
                                <div class="badge badge-outline"><?= htmlspecialchars($u['grupo_nome'] ?? 'Sem Grupo') ?></div>
                            </td>
                            <td class="text-center">
                                <?php if($u['status']): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-sm">
                                <?= date('d/m/Y', strtotime($u['criado_em'])) ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('usuarios.acesso')): // Assuming edit access is covered by general access or checking specific inside controller ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('usuarios.deletar')): ?>
                                    <a href="index.php?r=usuarios/excluir&id=<?= $u['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este usuário?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 opacity-50">Nenhum usuário encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<dialog id="modal_usuario" class="modal">
    <div class="modal-box w-11/12 max-w-3xl">
        <h3 class="font-bold text-lg mb-4" id="modal_title">Novo Usuário</h3>
        <form method="POST" action="index.php?r=usuarios/store">
            <input type="hidden" name="id" id="form_id" value="0">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Nome -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Nome Completo *</span></label>
                    <input type="text" name="nome" id="form_nome" class="input input-bordered w-full" required>
                </div>

                <!-- Email -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Email *</span></label>
                    <input type="email" name="email" id="form_email" class="input input-bordered w-full" required>
                </div>

                <!-- Usuário -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Login (Usuário) *</span></label>
                    <input type="text" name="usuario" id="form_usuario" class="input input-bordered w-full" required>
                </div>

                <!-- Grupo -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Grupo de Permissões</span></label>
                    <select name="id_grupo" id="form_grupo" class="select select-bordered w-full">
                        <option value="0">Selecione...</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Senha -->
                <div class="form-control">
                    <label class="label"><span class="label-text" id="label_senha">Senha *</span></label>
                    <input type="password" name="senha" id="form_senha" class="input input-bordered w-full">
                    <label class="label"><span class="label-text-alt text-warning" id="help_senha" style="display:none;">Deixe em branco para manter a atual</span></label>
                </div>

                <!-- Confirmar Senha -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Confirmar Senha *</span></label>
                    <input type="password" name="senha2" id="form_senha2" class="input input-bordered w-full">
                </div>

                <!-- Desconto -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Desconto Máximo (%)</span></label>
                    <input type="number" name="desconto_maximo_permitido" id="form_desconto" class="input input-bordered w-full" min="0" max="100" value="0">
                </div>

                <!-- Status -->
                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-4 mt-8">
                        <span class="label-text">Ativo</span> 
                        <input type="checkbox" name="status" id="form_status" class="toggle toggle-success" checked>
                    </label>
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_usuario').close()">Cancelar</button>
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
    document.getElementById('form_id').value = '0';
    document.getElementById('form_nome').value = '';
    document.getElementById('form_email').value = '';
    document.getElementById('form_usuario').value = '';
    document.getElementById('form_grupo').value = '0';
    document.getElementById('form_desconto').value = '0';
    document.getElementById('form_status').checked = true;
    
    // Senha required for new
    document.getElementById('form_senha').required = true;
    document.getElementById('form_senha2').required = true;
    document.getElementById('help_senha').style.display = 'none';
    document.getElementById('label_senha').innerText = 'Senha *';
    
    document.getElementById('modal_title').innerText = 'Novo Usuário';
    document.getElementById('modal_usuario').showModal();
}

function edit(user) {
    document.getElementById('form_id').value = user.id;
    document.getElementById('form_nome').value = user.nome;
    document.getElementById('form_email').value = user.email;
    document.getElementById('form_usuario').value = user.usuario;
    document.getElementById('form_grupo').value = user.id_grupo || 0;
    document.getElementById('form_desconto').value = user.desconto_maximo_permitido || 0; // Assuming this field might be in user object if selected in query
    document.getElementById('form_status').checked = (user.status == 1);
    
    // Senha optional for edit
    document.getElementById('form_senha').required = false;
    document.getElementById('form_senha2').required = false;
    document.getElementById('form_senha').value = '';
    document.getElementById('form_senha2').value = '';
    document.getElementById('help_senha').style.display = 'block';
    document.getElementById('label_senha').innerText = 'Nova Senha (Opcional)';
    
    document.getElementById('modal_title').innerText = 'Editar Usuário';
    document.getElementById('modal_usuario').showModal();
}

// Ensure discount field is in the query or handle it?
// The controller select query didn't include 'desconto_maximo_permitido'. 
// I should update the controller to fetch it.
</script>
