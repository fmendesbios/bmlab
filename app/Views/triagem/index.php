<?php
// app/Views/triagem/index.php
?>
<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold">Triagem de Amostras</h1>
    <div class="text-sm breadcrumbs">
        <ul>
            <li><a href="index.php?r=home">Home</a></li>
            <li>Triagem</li>
        </ul>
    </div>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title text-sm uppercase text-gray-500 mb-4">Pedidos com Pendências</h2>
        
        <?php if (empty($pedidos)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>Nenhuma pendência de triagem encontrada. Todos os exames estão em dia!</span>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Data Entrada</th>
                            <th>Paciente</th>
                            <th>Pendências</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr class="hover">
                                <td class="font-mono font-bold"><?= $p['codigo_pedido'] ?: $p['id'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($p['entrada'])) ?></td>
                                <td>
                                    <div class="font-bold"><?= htmlspecialchars($p['paciente_nome']) ?></div>
                                    <div class="text-xs opacity-50">
                                        <?= $p['paciente_sexo'] ?> • 
                                        <?= date('d/m/Y', strtotime($p['paciente_nascimento'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-warning gap-2">
                                        <?= $p['pending_count'] ?> exames
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="index.php?r=triagem/show&id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> Analisar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
