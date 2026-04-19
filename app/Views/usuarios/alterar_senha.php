<?php
// app/Views/usuarios/alterar_senha.php
?>
<div class="card bg-base-100 shadow-xl max-w-lg mx-auto mt-10">
    <div class="card-body">
        <h2 class="card-title text-2xl mb-6 flex items-center gap-2">
            <i class="bi bi-key text-primary"></i> Alterar Minha Senha
        </h2>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error mb-4">
                <i class="bi bi-exclamation-triangle"></i>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success mb-4">
                <i class="bi bi-check-circle"></i>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <form action="index.php?r=alterar_senha" method="POST">
            
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Senha Atual</span></label>
                <div class="join">
                    <span class="join-item btn btn-square btn-ghost no-animation bg-base-200"><i class="bi bi-lock"></i></span>
                    <input type="password" name="senha_atual" class="input input-bordered w-full join-item" required autofocus>
                </div>
            </div>

            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Nova Senha</span></label>
                <div class="join">
                    <span class="join-item btn btn-square btn-ghost no-animation bg-base-200"><i class="bi bi-key"></i></span>
                    <input type="password" name="nova_senha" class="input input-bordered w-full join-item" required minlength="4">
                </div>
            </div>

            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Confirmar Nova Senha</span></label>
                <div class="join">
                    <span class="join-item btn btn-square btn-ghost no-animation bg-base-200"><i class="bi bi-check-lg"></i></span>
                    <input type="password" name="confirmar_senha" class="input input-bordered w-full join-item" required minlength="4">
                </div>
            </div>

            <div class="card-actions justify-end">
                <a href="index.php?r=home" class="btn btn-ghost">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Salvar Nova Senha
                </button>
            </div>

        </form>
    </div>
</div>
