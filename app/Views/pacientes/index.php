<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <style>
        /* Fix calendar icon visibility using color-scheme */
        input[type="date"] {
            color-scheme: light; /* Forces dark icon for light backgrounds */
        }
        
        /* Dark Themes - Forces light icon for dark backgrounds */
        [data-theme="dark"] input[type="date"],
        [data-theme="business"] input[type="date"],
        [data-theme="halloween"] input[type="date"],
        [data-theme="forest"] input[type="date"],
        [data-theme="black"] input[type="date"],
        [data-theme="luxury"] input[type="date"],
        [data-theme="dracula"] input[type="date"],
        [data-theme="night"] input[type="date"],
        [data-theme="coffee"] input[type="date"],
        [data-theme="dim"] input[type="date"],
        [data-theme="sunset"] input[type="date"],
        .dark input[type="date"] {
            color-scheme: dark;
        }

        /* Badge Circle for Icons */
        .badge-circle { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            width: 24px; 
            height: 24px; 
            border-radius: 50%; 
            font-size: .75rem; 
            vertical-align: middle;
        }
    </style>
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-people"></i> Pacientes</h1>
    <?php if (temPermissao('pacientes.acesso')): ?>
        <a href="index.php?r=pacientes/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo Paciente
        </a>
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
        <!-- Search & Pagination -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <!-- Search Form -->
            <form method="GET" action="index.php" class="flex gap-2 w-full md:w-auto">
                <input type="hidden" name="r" value="pacientes">
                <div class="join w-full max-w-sm">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Nome, CPF ou Código...">
                    <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
                </div>
                <?php if (!empty($busca)): ?>
                    <a href="index.php?r=pacientes" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
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
                    <a href="index.php?r=pacientes&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=pacientes&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=pacientes&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=pacientes&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=pacientes&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-forward-fill"></i>
                    </a>
                </div>
                <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                    <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-20">Cód</th>
                        <th>Nome / Nascimento</th>
                        <th>Documentos</th>
                        <th>Contato / Localização</th>
                        <th class="text-center">Status</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pacientes) > 0): ?>
                        <?php foreach ($pacientes as $p): ?>
                            <tr class="hover">
                                <td class="font-mono"><?= htmlspecialchars($p['codigo_paciente'] ?? str_pad($p['id'], 7, '0', STR_PAD_LEFT)) ?></td>
                                <td>
                                    <div class="font-bold">
                                        <?= htmlspecialchars($p['nome']) ?>
                                        <?php
                                        // Logic for Underage
                                        $menor = false;
                                        if (!empty($p['nascimento'])) {
                                            try {
                                                $dtN = new DateTime($p['nascimento']);
                                                $menor = (new DateTime())->diff($dtN)->y < 18;
                                            } catch (Exception $e) { $menor = false; }
                                        }
                                        if ($menor) {
                                            echo '<span class="badge bg-error text-white badge-circle ml-2" title="Menor de 18 anos">-18</span>';
                                        }

                                        // Logic for Pregnant
                                        $obsP = (string)($p['observacoes'] ?? '');
                                        $gestante = (bool)preg_match('/\[GESTANTE/i', $obsP);
                                        $gestDpp = '';
                                        if (preg_match('/\[GESTANTE\s+DPP:(\d{4}-\d{2}-\d{2})\]/i', $obsP, $mm)) { $gestDpp = $mm[1]; }
                                        
                                        if ($gestante) {
                                            $tt = 'Gestante' . ($gestDpp ? ' • DPP ' . date('d/m/Y', strtotime($gestDpp)) : '');
                                            echo '<span class="badge badge-circle ml-2 text-white" style="background-color: #d63384;" title="' . htmlspecialchars($tt) . '"><i class="bi bi-heart-pulse"></i></span>';
                                        }
                                        ?>
                                    </div>
                                    <div class="text-xs opacity-70">
                                        <i class="bi bi-cake2"></i> <?= $p['nascimento'] ? date('d/m/Y', strtotime($p['nascimento'])) : '-' ?>
                                        <span class="ml-2 badge badge-ghost badge-xs"><?= htmlspecialchars($p['sexo']) ?></span>
                                    </div>
                                    <?php if ($menor && !empty($p['nf_responsavel_nome'])): ?>
                                        <div class="text-xs text-base-content/60 mt-1">
                                            <i class="bi bi-person-badge"></i> Resp: <?= htmlspecialchars($p['nf_responsavel_nome']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm">
                                    <?php if ($p['cpf']): ?>
                                        <div class="font-mono text-xs" title="CPF">CPF: <?= htmlspecialchars($p['cpf']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($p['rg']): ?>
                                        <div class="text-xs opacity-70" title="RG">RG: <?= htmlspecialchars($p['rg']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm">
                                    <?php if ($p['celular']): ?>
                                        <div class="text-xs"><i class="bi bi-whatsapp"></i> <?= htmlspecialchars($p['celular']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs opacity-70"><?= htmlspecialchars($p['municipio_nome'] ?? '-') ?>/<?= htmlspecialchars($p['municipio_uf'] ?? '') ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['ativo']): ?>
                                        <div class="badge badge-success badge-sm">Ativo</div>
                                    <?php else: ?>
                                        <div class="badge badge-ghost badge-sm">Inativo</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="join">
                                        <?php if (temPermissao('pacientes.acesso')): ?>
                                            <a href="index.php?r=pacientes/edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-ghost join-item" title="Editar">
                                                <i class="bi bi-pencil text-warning"></i>
                                            </a>

                                            <a href="index.php?r=pacientes/excluir&id=<?= $p['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este paciente?')" title="Excluir">
                                                <i class="bi bi-trash text-error"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-base-content/50">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                Nenhum paciente encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="text-xs text-center mt-4 text-base-content/50">
            Exibindo <?= count($pacientes) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>
