<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Logs de Envio - ChatMix</h1>
        <a href="index.php?r=agendamento" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr class="bg-base-200">
                            <th>Data/Hora</th>
                            <th>Tipo</th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Retorno API</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">Nenhum log encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover">
                                    <td class="whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></td>
                                    <td><?= htmlspecialchars($log['tipo'] ?? '–') ?></td>
                                    <td><?= htmlspecialchars($log['nome'] ?? '–') ?></td>
                                    <td><?= htmlspecialchars($log['telefone'] ?? '–') ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = match($log['status'] ?? '') {
                                            'sucesso' => 'badge badge-success',
                                            'erro' => 'badge badge-error',
                                            default => 'badge badge-ghost'
                                        };
                                        ?>
                                        <span class="<?= $statusClass ?> text-white text-xs">
                                            <?= ucfirst($log['status'] ?? '–') ?>
                                        </span>
                                    </td>
                                    <td class="text-xs font-mono max-w-xs truncate" title="<?= htmlspecialchars($log['retorno'] ?? '') ?>">
                                        <?= htmlspecialchars($log['retorno'] ?? '–') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
        <div class="flex justify-center mt-6">
            <div class="join">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="index.php?r=agendamento/logs&pagina=<?= $i ?>" class="join-item btn btn-sm <?= $i === $pagina_atual ? 'btn-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
