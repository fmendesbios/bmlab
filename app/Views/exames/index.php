<?php
// app/Views/exames/index.php
?>
<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-file-medical"></i> Exames</h1>
    <a href="index.php?r=exames/edit" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Novo Exame
    </a>
</div>

        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success shadow-lg mb-4">
                <div>
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= $_SESSION['flash_success'] ?></span>
                </div>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-error shadow-lg mb-4">
                <div>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $_SESSION['flash_error'] ?></span>
                </div>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-4 md:p-6">
                <form method="GET" action="index.php" class="mb-6 bg-base-200 p-4 rounded-lg">
            <input type="hidden" name="r" value="exames">
            <div class="flex flex-col md:flex-row justify-between items-end gap-4">
                <div class="flex flex-wrap gap-4 items-end w-full md:w-auto">
                    <div class="form-control w-full max-w-xs">
                        <label class="label">
                            <span class="label-text">Buscar</span>
                        </label>
                        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered w-full" placeholder="Nome ou Mnemônico...">
                    </div>

                    <div class="form-control w-full max-w-xs">
                        <label class="label">
                            <span class="label-text">Setor</span>
                        </label>
                        <select name="filtro_setor" class="select select-bordered w-full">
                            <option value="0">Todos</option>
                            <?php foreach ($setores as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $filtro_setor == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['setor_nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <?php if (!empty($busca) || $filtro_setor > 0): ?>
                        <a href="index.php?r=exames" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>

                <!-- Pagination Controls -->
                <?php
                $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
                $end = min($page * $limit, $totalRecords);
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                $q = !empty($busca) ? '&busca=' . urlencode($busca) : '';
                $q .= $filtro_setor > 0 ? '&filtro_setor=' . $filtro_setor : '';
                ?>
                <div class="flex items-center gap-2 bg-base-100 p-2 rounded-lg shadow-sm">
                    <div class="join">
                        <!-- First -->
                        <a href="index.php?r=exames&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                            <i class="bi bi-skip-backward-fill"></i>
                        </a>
                        <!-- Prev -->
                        <a href="index.php?r=exames&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                            <i class="bi bi-caret-left-fill"></i>
                        </a>
                        <!-- Refresh -->
                        <a href="index.php?r=exames&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                        <!-- Next -->
                        <a href="index.php?r=exames&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                            <i class="bi bi-caret-right-fill"></i>
                        </a>
                        <!-- Last -->
                        <a href="index.php?r=exames&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                            <i class="bi bi-skip-forward-fill"></i>
                        </a>
                    </div>
                    <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                        <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                    </div>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th class="w-20">ID</th>
                        <th class="w-32">Mnemônico</th>
                        <th>Nome</th>
                        <th>Setor</th>
                        <th class="w-24 text-center">Ativo</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exames)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">Nenhum exame encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exames as $ex): ?>
                            <tr class="hover">
                                <td><?= $ex['id'] ?></td>
                                <td class="font-mono font-bold text-primary"><?= htmlspecialchars($ex['mnemonico_local']) ?></td>
                                <td class="font-bold"><?= htmlspecialchars($ex['nome']) ?></td>
                                <td><?= htmlspecialchars($ex['setor_nome'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($ex['ativo']): ?>
                                        <span class="badge badge-success badge-sm">Sim</span>
                                    <?php else: ?>
                                        <span class="badge badge-error badge-sm">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if (temPermissao('exames.acesso')): ?>
                                            <a href="index.php?r=exames/edit&id=<?= $ex['id'] ?>" class="btn btn-sm btn-ghost text-info" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (temPermissao('exames.excluir')): ?>
                                            <a href="index.php?r=exames/delete&id=<?= $ex['id'] ?>" class="btn btn-sm btn-ghost text-error" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este exame?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
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
