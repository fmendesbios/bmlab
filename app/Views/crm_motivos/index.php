<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-list-check"></i> Motivos de Perda (CRM)</h1>
    <button type="button" class="btn btn-primary gap-2" onclick="abrirModalMotivo()">
        <i class="bi bi-plus-lg"></i> Novo Motivo
    </button>
</div>

<?php if (!empty($mensagem)): ?>
    <div class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i> <span><?= htmlspecialchars($mensagem) ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($erro)): ?>
    <div class="alert alert-error mb-4">
        <i class="bi bi-exclamation-circle"></i> <span><?= htmlspecialchars($erro) ?></span>
    </div>
<?php endif; ?>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="w-16">ID</th>
                        <th>Motivo</th>
                        <th class="text-center w-24">Ativo</th>
                        <th class="text-center w-32">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($motivos)): ?>
                        <tr>
                            <td colspan="4" class="text-center p-4">Nenhum motivo cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($motivos as $m): ?>
                            <tr class="hover">
                                <td><?= $m['id'] ?></td>
                                <td class="font-bold"><?= htmlspecialchars($m['motivo']) ?></td>
                                <td class="text-center">
                                    <?php if ($m['ativo']): ?>
                                        <span class="badge badge-success badge-sm">Sim</span>
                                    <?php else: ?>
                                        <span class="badge badge-error badge-sm">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="join">
                                        <button class="btn btn-sm btn-ghost join-item" onclick='editarMotivo(<?= json_encode($m) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="index.php?r=crm_motivos/delete&id=<?= $m['id'] ?>" class="btn btn-sm btn-ghost text-error join-item" onclick="return confirm('Excluir este motivo?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<!-- Modal Motivo -->
<dialog id="modal_motivo" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4" id="modal_titulo">Novo Motivo</h3>
        <form method="post" action="index.php?r=crm_motivos/store">
            <input type="hidden" name="id" id="motivo_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Descrição do Motivo</span></label>
                <input type="text" name="motivo" id="motivo_texto" class="input input-bordered w-full" required>
            </div>
            
            <div class="form-control mb-4">
                <label class="cursor-pointer label justify-start gap-4">
                    <span class="label-text">Ativo</span>
                    <input type="checkbox" name="ativo" id="motivo_ativo" class="toggle toggle-primary" checked>
                </label>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_motivo').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    function abrirModalMotivo() {
        document.getElementById('motivo_id').value = '';
        document.getElementById('motivo_texto').value = '';
        document.getElementById('motivo_ativo').checked = true;
        document.getElementById('modal_titulo').innerText = 'Novo Motivo';
        document.getElementById('modal_motivo').showModal();
    }

    function editarMotivo(data) {
        document.getElementById('motivo_id').value = data.id;
        document.getElementById('motivo_texto').value = data.motivo;
        document.getElementById('motivo_ativo').checked = (data.ativo == 1);
        document.getElementById('modal_titulo').innerText = 'Editar Motivo';
        document.getElementById('modal_motivo').showModal();
    }
</script>
