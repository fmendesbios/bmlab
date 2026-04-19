<?php
// app/Views/financeiro/contas_pagar/index.php
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold">Contas a Pagar</h1>
        <div class="text-sm breadcrumbs">
            <ul>
                <li><a href="index.php?r=home">Home</a></li>
                <li>Financeiro</li>
                <li>Contas a Pagar</li>
            </ul>
        </div>
    </div>
    <a href="index.php?r=contas_pagar/create" class="btn btn-error text-white">
        <i class="bi bi-plus-lg"></i> Novo Pagamento
    </a>
</div>

<div class="card bg-base-100 shadow-xl mb-6">
    <div class="card-body p-4">
        <form method="GET" action="index.php">
            <input type="hidden" name="r" value="contas_pagar">
            <div class="flex flex-wrap items-end gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Vencimento</span></label>
                    <div class="input-group">
                        <input type="date" name="ini" value="<?= $filters['ini'] ?>" class="input input-bordered input-sm">
                        <span class="bg-base-200 px-2 flex items-center">-</span>
                        <input type="date" name="fim" value="<?= $filters['fim'] ?>" class="input input-bordered input-sm">
                        <button type="button" class="btn btn-sm btn-square btn-ghost text-error" onclick="limparDatas()" title="Limpar Datas">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-control w-32">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select name="status" class="select select-bordered select-sm">
                        <option value="">Todos</option>
                        <option value="A" <?= $filters['status'] === 'A' ? 'selected' : '' ?>>Aberto</option>
                        <option value="P" <?= $filters['status'] === 'P' ? 'selected' : '' ?>>Pago</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-sm btn-ghost border-base-300">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-0">
        <!-- Pagination Controls -->
        <?php
        $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
        $end = min($page * $limit, $totalRecords);
        $prev = max(1, $page - 1);
        $next = min($totalPages, $page + 1);
        // Build query string for filters
        $q = '';
        if (!empty($filters['ini'])) $q .= '&ini=' . urlencode($filters['ini']);
        if (!empty($filters['fim'])) $q .= '&fim=' . urlencode($filters['fim']);
        if (!empty($filters['status'])) $q .= '&status=' . urlencode($filters['status']);
        ?>
        <div class="flex justify-end p-4 border-b border-base-200">
            <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                <div class="join">
                    <!-- First -->
                    <a href="index.php?r=contas_pagar&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=contas_pagar&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=contas_pagar&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=contas_pagar&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=contas_pagar&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-forward-fill"></i>
                    </a>
                </div>
                <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                    <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vencimento</th>
                        <th>Descrição / Credor</th>
                        <th>Competência</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="text-center text-gray-500 py-8">Nenhum registro encontrado.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($items as $item): ?>
                        <tr class="hover">
                            <td class="font-mono text-xs">#<?= $item['id'] ?></td>
                            <td>
                                <div class="font-bold"><?= date('d/m/Y', strtotime($item['vencimento'])) ?></div>
                                <?php if ($item['status'] === 'A' && $item['vencimento'] < date('Y-m-d')): ?>
                                    <span class="text-xs text-error font-bold">Vencido</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-bold text-sm"><?= htmlspecialchars($item['descricao']) ?></div>
                                <div class="text-xs opacity-50">
                                    <?= htmlspecialchars($item['paciente_nome'] ?: ($item['fornecedor_nome'] ?: '-')) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['competencia']) ?></td>
                            <td class="text-right font-mono font-bold">
                                R$ <?= number_format($item['valor_parcela'], 2, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['status'] === 'A'): ?>
                                    <span class="badge badge-warning badge-sm">Aberto</span>
                                <?php elseif ($item['status'] === 'P'): ?>
                                    <span class="badge badge-success badge-sm">Pago</span>
                                    <div class="text-[10px] text-gray-500 mt-1">
                                        <?= date('d/m/y', strtotime($item['data_pagamento'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($item['status'] === 'A'): ?>
                                    <button onclick="pagar(<?= $item['id'] ?>, <?= $item['valor_parcela'] ?>)" class="btn btn-xs btn-success btn-outline" title="Pagar">
                                        <i class="bi bi-cash"></i> Pagar
                                    </button>
                                <?php else: ?>
                                    <button onclick="estornar(<?= $item['id'] ?>)" class="btn btn-xs btn-warning btn-outline" title="Estornar Pagamento">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t bg-base-100 rounded-b-box text-sm opacity-50">
            Exibindo <?= count($items) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>

<!-- Modal Pagar -->
<dialog id="modal_pagar" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Realizar Pagamento</h3>
        <p class="py-2">Selecione a conta de origem para o pagamento.</p>
        <div class="alert alert-info py-2 mb-4 text-xs">
            Valor a Pagar: R$ <span id="pagar_valor_display" class="font-bold">0,00</span>
        </div>
        
        <input type="hidden" id="pagar_id">
        
        <div class="form-control w-full">
            <label class="label"><span class="label-text">Conta Bancária</span></label>
            <select id="pagar_conta_id" class="select select-bordered w-full">
                <option value="">Selecione...</option>
                <?php foreach ($bancos as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= $b['banco'] ?> - <?= $b['agencia'] ?> / <?= $b['conta'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="modal-action">
            <form method="dialog">
                <button class="btn">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarPagamento()">Confirmar Pagamento</button>
            </form>
        </div>
    </div>
</dialog>

<script>
    function pagar(id, valor) {
        document.getElementById('pagar_id').value = id;
        document.getElementById('pagar_valor_display').innerText = valor.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('modal_pagar').showModal();
    }

    function confirmarPagamento() {
        const id = document.getElementById('pagar_id').value;
        const contaId = document.getElementById('pagar_conta_id').value;
        
        if (!contaId) {
            alert('Selecione uma conta bancária.');
            return;
        }

        fetch('index.php?r=contas_pagar/pay', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&conta_id=${contaId}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) location.reload();
            else alert('Erro: ' + data.message);
        });
    }

    function estornar(id) {
        if (!confirm('Deseja realmente estornar este pagamento? O status voltará para Aberto.')) return;
        
        fetch('index.php?r=contas_pagar/reverse', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) location.reload();
            else alert('Erro: ' + data.message);
        });
    }

    function limparDatas() {
        document.querySelector('input[name="ini"]').value = '';
        document.querySelector('input[name="fim"]').value = '';
        document.forms[0].submit();
    }
</script>
