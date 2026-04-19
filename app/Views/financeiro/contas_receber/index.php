<?php
// app/Views/financeiro/contas_receber/index.php
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold">Contas a Receber</h1>
        <div class="text-sm breadcrumbs">
            <ul>
                <li><a href="index.php?r=home">Home</a></li>
                <li>Financeiro</li>
                <li>Contas a Receber</li>
            </ul>
        </div>
    </div>
    <a href="index.php?r=contas_receber/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Novo Lançamento
    </a>
</div>

<div class="card bg-base-100 shadow-xl mb-6">
    <div class="card-body p-4">
        <form method="GET" action="index.php">
            <input type="hidden" name="r" value="contas_receber">
            <div class="flex flex-wrap items-end gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Período</span></label>
                    <div class="input-group">
                        <input type="date" name="ini" value="<?= $filters['ini'] ?>" class="input input-bordered input-sm">
                        <span class="bg-base-200 px-2 flex items-center">-</span>
                        <input type="date" name="fim" value="<?= $filters['fim'] ?>" class="input input-bordered input-sm">
                        <button type="button" onclick="limparDatas()" class="btn btn-square btn-sm btn-ghost text-error" title="Limpar Datas">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-control w-32">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select name="status" class="select select-bordered select-sm">
                        <option value="">Todos</option>
                        <option value="A" <?= $filters['status'] === 'A' ? 'selected' : '' ?>>Aberto</option>
                        <option value="R" <?= $filters['status'] === 'R' ? 'selected' : '' ?>>Recebido</option>
                        <option value="T" <?= $filters['status'] === 'T' ? 'selected' : '' ?>>Transferido</option>
                    </select>
                </div>

                <div class="form-control w-32">
                    <label class="label"><span class="label-text">Origem</span></label>
                    <select name="origem" class="select select-bordered select-sm">
                        <option value="">Todas</option>
                        <option value="P" <?= $filters['origem'] === 'P' ? 'selected' : '' ?>>Pedido</option>
                        <option value="A" <?= $filters['origem'] === 'A' ? 'selected' : '' ?>>Avulso</option>
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
        if (!empty($filters['origem'])) $q .= '&origem=' . urlencode($filters['origem']);
        ?>
        <div class="flex justify-end p-4 border-b border-base-200">
            <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                <div class="join">
                    <!-- First -->
                    <a href="index.php?r=contas_receber&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=contas_receber&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=contas_receber&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=contas_receber&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=contas_receber&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
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
                        <th class="w-10">
                            <label><input type="checkbox" class="checkbox checkbox-xs" id="selectAll"></label>
                        </th>
                        <th>ID</th>
                        <th>Origem</th>
                        <th>Descrição / Sacado</th>
                        <th>Vencimento</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 py-8">Nenhum registro encontrado.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($items as $item): ?>
                        <tr class="hover">
                            <th>
                                <label><input type="checkbox" class="checkbox checkbox-xs item-check" value="<?= $item['id'] ?>"></label>
                            </th>
                            <td class="font-mono text-xs">#<?= $item['id'] ?></td>
                            <td>
                                <?php if ($item['origem'] === 'P'): ?>
                                    <span class="badge badge-sm badge-ghost">Pedido</span>
                                <?php else: ?>
                                    <span class="badge badge-sm badge-outline">Avulso</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-bold text-sm"><?= htmlspecialchars($item['descricao']) ?></div>
                                <div class="text-xs opacity-50">
                                    <?= htmlspecialchars($item['paciente_nome'] ?: ($item['fornecedor_nome'] ?: '-')) ?>
                                </div>
                            </td>
                            <td><?= date('d/m/Y', strtotime($item['vencimento'])) ?></td>
                            <td class="text-right font-mono font-bold">
                                R$ <?= number_format($item['valor'], 2, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['status'] === 'A'): ?>
                                    <span class="badge badge-warning badge-sm">Aberto</span>
                                <?php elseif ($item['status'] === 'R'): ?>
                                    <span class="badge badge-success badge-sm">Recebido</span>
                                <?php elseif ($item['status'] === 'T'): ?>
                                    <span class="badge badge-info badge-sm">Transferido</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($item['status'] === 'A'): ?>
                                    <button onclick="abrirModalReceber(<?= $item['id'] ?>)" class="btn btn-xs btn-success btn-outline" title="Receber">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                <?php endif; ?>
                                <button onclick="deletar(<?= $item['id'] ?>)" class="btn btn-xs btn-error btn-outline" title="Excluir/Estornar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="p-4 border-t bg-base-100 rounded-b-box flex justify-between items-center">
            <div class="text-sm opacity-50">
                Exibindo <?= count($items) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
            </div>
            <div>
                <button onclick="receberEmLote()" class="btn btn-sm btn-success text-white">
                    <i class="bi bi-wallet2 me-2"></i> Receber Selecionados
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Receber -->
<dialog id="modal_receber" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Receber Títulos</h3>
        <p class="py-4">Selecione a conta bancária onde o valor será creditado.</p>
        
        <input type="hidden" id="receber_single_id" value="">

        <div class="form-control w-full">
            <label class="label"><span class="label-text">Conta Bancária</span></label>
            <select id="receber_conta_id" class="select select-bordered w-full">
                <option value="">Selecione...</option>
                <?php foreach ($bancos as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= $b['banco'] ?> - <?= $b['agencia'] ?> / <?= $b['conta'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="modal-action">
            <form method="dialog">
                <button class="btn">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarRecebimento()">Confirmar Recebimento</button>
            </form>
        </div>
    </div>
</dialog>

<script>
    function limparDatas() {
        document.querySelector('input[name="ini"]').value = '';
        document.querySelector('input[name="fim"]').value = '';
        document.forms[0].submit();
    }

    // Select All
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.item-check').forEach(c => c.checked = this.checked);
    });

    function abrirModalReceber(id = null) {
        document.getElementById('receber_single_id').value = id || '';
        document.getElementById('modal_receber').showModal();
    }

    function receberEmLote() {
        const checked = document.querySelectorAll('.item-check:checked');
        if (checked.length === 0) {
            alert('Selecione pelo menos um item.');
            return;
        }
        abrirModalReceber(null);
    }

    function deletar(id) {
        if (!confirm('Deseja realmente excluir/estornar este lançamento?')) return;
        
        fetch('index.php?r=contas_receber/delete', {
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

    function confirmarRecebimento() {
        let ids = [];
        const singleId = document.getElementById('receber_single_id').value;
        
        if (singleId) {
            ids.push(singleId);
        } else {
            const checked = document.querySelectorAll('.item-check:checked');
            ids = Array.from(checked).map(c => c.value);
        }

        const contaId = document.getElementById('receber_conta_id').value;
        
        if (ids.length === 0) {
            alert('Nenhum item selecionado.');
            return;
        }
        if (!contaId) {
            alert('Selecione uma conta bancária.');
            return;
        }

        fetch('index.php?r=contas_receber/receive', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ids[]=${ids.join('&ids[]=')}&conta_id=${contaId}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) location.reload();
            else alert('Erro: ' + data.message);
        });
    }
</script>
