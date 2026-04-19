<?php
// app/Views/resultados/index.php
?>
<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title">Lançamento de Resultados</h2>
        
        <form method="GET" action="index.php" class="flex flex-wrap gap-4 items-end mb-6">
            <input type="hidden" name="r" value="resultados">
            
            <div class="form-control w-full max-w-xs">
                <label class="label"><span class="label-text">Paciente</span></label>
                <input type="text" name="paciente" value="<?= htmlspecialchars($filtros['paciente']) ?>" class="input input-bordered" placeholder="Nome do paciente...">
            </div>
            
            <div class="form-control">
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" name="incluir_liberados" value="1" class="checkbox" <?= $filtros['incluir_liberados'] ? 'checked' : '' ?>>
                    <span class="label-text">Incluir Liberados</span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
        </form>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Data</th>
                        <th>Paciente</th>
                        <th>Qtd. Exames</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-gray-500">Nenhum pedido pendente encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td><?= $p['codigo_pedido'] ?: $p['id'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($p['entrada'])) ?></td>
                                <td>
                                    <div class="font-bold"><?= htmlspecialchars($p['paciente_nome']) ?></div>
                                    <div class="text-xs opacity-50"><?= date('d/m/Y', strtotime($p['paciente_nascimento'])) ?></div>
                                </td>
                                <td><?= $p['qtd_exames'] ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="index.php?r=resultados/entry&pedido_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary" title="Lançar Resultados">
                                            <i class="bi bi-pencil-square"></i> Lançar
                                        </a>
                                        <a href="index.php?r=resultados/print_laudo&pedido_id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-info text-white" title="Imprimir Laudo">
                                            <i class="bi bi-printer"></i> Imprimir
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
