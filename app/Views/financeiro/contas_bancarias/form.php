<div class="max-w-3xl mx-auto p-6 bg-base-100 rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-base-content"><?= $isEdit ? 'Editar Conta Bancária' : 'Nova Conta Bancária' ?></h1>
        <a href="index.php?r=contas_bancarias" class="btn btn-ghost gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            Voltar
        </a>
    </div>

    <form method="POST" action="index.php?r=contas_bancarias/store" class="space-y-6">
        <input type="hidden" name="id" value="<?= $conta['id'] ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- ID (Read-only) -->
            <div class="form-control">
                <label class="label">
                    <span class="label-text">ID</span>
                </label>
                <input type="text" value="<?= $nextId ?>" class="input input-bordered w-full bg-base-200" readonly>
            </div>

            <!-- Ativo Switch -->
            <div class="form-control">
                <label class="label cursor-pointer justify-start gap-4">
                    <span class="label-text">Status</span>
                    <input type="checkbox" name="ativa" class="toggle toggle-primary" <?= $conta['ativa'] ? 'checked' : '' ?>>
                    <span class="label-text"><?= $conta['ativa'] ? 'Ativo' : 'Inativo' ?></span>
                </label>
            </div>

            <!-- Banco -->
            <div class="form-control md:col-span-2">
                <label class="label">
                    <span class="label-text">Banco <span class="text-error">*</span></span>
                </label>
                <input type="text" name="banco" value="<?= htmlspecialchars($conta['banco']) ?>" class="input input-bordered w-full" required placeholder="Nome do Banco">
            </div>

            <!-- Agência -->
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Agência</span>
                </label>
                <input type="text" name="agencia" value="<?= htmlspecialchars($conta['agencia']) ?>" class="input input-bordered w-full" placeholder="0000-0">
            </div>

            <!-- Conta -->
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Conta</span>
                </label>
                <input type="text" name="conta" value="<?= htmlspecialchars($conta['conta']) ?>" class="input input-bordered w-full" placeholder="00000-0">
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-8">
            <a href="index.php?r=contas_bancarias" class="btn btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                    <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
                </svg>
                Salvar
            </button>
        </div>
    </form>
</div>
