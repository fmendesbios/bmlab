<?php
// app/Views/whatsapp/logs.php
?>
<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title text-2xl mb-4">Logs de Envio - WhatsApp</h2>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th>Retorno API</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover">
                            <td><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></td>
                            <td><?= htmlspecialchars($log['tipo'] ?? '–') ?></td>
                            <td><?= htmlspecialchars($log['nome'] ?? '–') ?></td>
                            <td><?= htmlspecialchars($log['telefone'] ?? '–') ?></td>
                            <td>
                                <?php if (($log['status'] ?? '') === 'sucesso'): ?>
                                    <span class="badge badge-success">Sucesso</span>
                                <?php else: ?>
                                    <span class="badge badge-error"><?= ucfirst($log['status'] ?? 'Erro') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs font-mono max-w-xs truncate" title="<?= htmlspecialchars($log['retorno'] ?? '') ?>">
                                <?= htmlspecialchars($log['retorno'] ?? '–') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <div class="flex justify-center mt-6">
                <div class="join">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="index.php?r=disparos_whatsapp_logs&pagina=<?= $i ?>" class="join-item btn btn-sm <?= $i === $pagina_atual ? 'btn-active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
