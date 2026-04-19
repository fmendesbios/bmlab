<div class="p-6 bg-base-100 rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-base-content">Contas Bancárias</h1>
        <a href="index.php?r=contas_bancarias/create" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2z"/>
            </svg>
            Nova Conta
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <form method="GET" action="index.php" class="flex gap-4 items-end">
            <input type="hidden" name="r" value="contas_bancarias">
            <div class="form-control flex-1">
                <label class="label">
                    <span class="label-text">Buscar</span>
                </label>
                <div class="join w-full">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca ?? '') ?>" class="input input-bordered w-full join-item" placeholder="Banco, agência ou conta...">
                    <button type="submit" class="btn btn-primary join-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success shadow-lg mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>
                <?php 
                    if ($_GET['success'] == '1') echo 'Conta cadastrada com sucesso.';
                    elseif ($_GET['success'] == '2') echo 'Conta atualizada com sucesso.';
                    elseif ($_GET['success'] == '3') echo 'Conta excluída com sucesso.';
                ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <th class="w-20">ID</th>
                    <th>Banco</th>
                    <th>Agência</th>
                    <th>Conta</th>
                    <th class="text-center w-24">Status</th>
                    <th class="text-center w-32">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contas)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-8 text-base-content/70">
                            Nenhuma conta bancária encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contas as $conta): ?>
                        <tr>
                            <td><?= $conta['id'] ?></td>
                            <td class="font-medium"><?= htmlspecialchars($conta['banco']) ?></td>
                            <td><?= htmlspecialchars($conta['agencia']) ?></td>
                            <td><?= htmlspecialchars($conta['conta']) ?></td>
                            <td class="text-center">
                                <?php if ($conta['ativa']): ?>
                                    <span class="badge badge-success badge-sm">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-error badge-sm">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="flex justify-center gap-2">
                                    <a href="index.php?r=contas_bancarias/edit&id=<?= $conta['id'] ?>" class="btn btn-sm btn-ghost text-primary tooltip" data-tip="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                                            <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                            <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                                        </svg>
                                    </a>
                                    <a href="index.php?r=contas_bancarias/delete&id=<?= $conta['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir esta conta?')" class="btn btn-sm btn-ghost text-error tooltip" data-tip="Excluir">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/>
                                            <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-6">
            <div class="join">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="index.php?r=contas_bancarias&page=<?= $i ?>&busca=<?= urlencode($busca) ?>" class="join-item btn <?= $i === $page ? 'btn-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
