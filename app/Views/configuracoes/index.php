<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-gear-fill"></i> Configurações do Sistema</h1>
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

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Papel Timbrado -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="card-title">Papel Timbrado</h2>
                    <p class="text-sm opacity-70">Arquivo PDF usado como fundo dos relatórios</p>
                </div>
                <?php if ($configAtual && !empty($configAtual['arquivo_path'])): ?>
                    <a class="btn btn-sm btn-outline" href="<?= htmlspecialchars($configAtual['arquivo_path']) ?>" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Visualizar Atual
                    </a>
                <?php endif; ?>
            </div>

            <form method="post" action="index.php?r=configuracoes/store" enctype="multipart/form-data" class="flex flex-col gap-4">
                <input type="hidden" name="acao" value="upload_papel_timbrado">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Selecionar PDF</span></label>
                    <input type="file" name="papel_timbrado" accept="application/pdf" required class="file-input file-input-bordered w-full">
                </div>
                
                <?php if ($configAtual && !empty($configAtual['arquivo_nome'])): ?>
                    <div class="text-xs opacity-70">
                        Atual: <?= htmlspecialchars($configAtual['arquivo_nome']) ?> (<?= number_format(($configAtual['tamanho_bytes'] ?? 0) / 1024, 2) ?> KB)
                    </div>
                <?php endif; ?>

                <div class="card-actions justify-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assinatura/Carimbo -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="card-title">Assinatura / Carimbo</h2>
                    <p class="text-sm opacity-70">PNG, JPG ou PDF para carimbo nos documentos</p>
                </div>
                <?php if ($assinAtual && !empty($assinAtual['arquivo_path'])): ?>
                    <a class="btn btn-sm btn-outline" href="<?= htmlspecialchars($assinAtual['arquivo_path']) ?>" target="_blank">
                        <i class="bi bi-paperclip"></i> Visualizar Atual
                    </a>
                <?php endif; ?>
            </div>

            <form method="post" action="index.php?r=configuracoes/store" enctype="multipart/form-data" class="flex flex-col gap-4">
                <input type="hidden" name="acao" value="upload_assinatura">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Selecionar Arquivo</span></label>
                    <input type="file" name="assinatura_arquivo" accept="image/png,image/jpeg,application/pdf" required class="file-input file-input-bordered w-full">
                </div>
                
                <?php if ($assinAtual && !empty($assinAtual['arquivo_nome'])): ?>
                    <div class="text-xs opacity-70">
                        Atual: <?= htmlspecialchars($assinAtual['arquivo_nome']) ?> (<?= number_format(($assinAtual['tamanho_bytes'] ?? 0) / 1024, 2) ?> KB)
                    </div>
                <?php endif; ?>

                <div class="card-actions justify-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>
