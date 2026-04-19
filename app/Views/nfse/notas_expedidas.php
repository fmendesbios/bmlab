<div class="container mx-auto max-w-6xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-primary flex items-center gap-2">
            <i class="bi bi-receipt"></i> <?= $titulo_pagina ?>
        </h1>
        <div class="flex gap-2">
            <button id="btnSyncNacional" class="btn btn-sm btn-secondary gap-2">
                <i class="bi bi-cloud-download"></i> Sincronizar Notas
            </button>
            <!--
            <button id="btnSyncReset" class="btn btn-sm btn-warning gap-2" title="Baixar todas as notas novamente (Reset)">
                <i class="bi bi-arrow-repeat"></i> Sincronizar Tudo
            </button>
            -->
        </div>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-0">
            <!-- Tabs -->
            <div role="tablist" class="tabs tabs-lifted">
                <input type="radio" name="my_tabs_1" role="tab" class="tab" aria-label="Pedidos do Sistema" <?= ($active_tab ?? 'pedidos') == 'pedidos' ? 'checked' : '' ?> />
                <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                    
                    <!-- Filtro Local -->
                    <div class="card bg-base-100 shadow-sm mb-4 border border-base-200">
                        <div class="card-body p-4">
                            <form method="get" class="flex flex-col md:flex-row gap-4 items-end">
                                <input type="hidden" name="r" value="nfse/notas_expedidas">
                                <input type="hidden" name="active_tab" value="pedidos">
                                <div class="form-control w-full md:w-1/4">
                                    <label class="label"><span class="label-text font-bold">Data Início</span></label>
                                    <input type="date" name="data_inicio" class="input input-bordered w-full input-sm" value="<?= $data_inicio ?>">
                                </div>
                                <div class="form-control w-full md:w-1/4">
                                    <label class="label"><span class="label-text font-bold">Data Fim</span></label>
                                    <input type="date" name="data_fim" class="input input-bordered w-full input-sm" value="<?= $data_fim ?>">
                                </div>
                                <div class="form-control w-full md:w-auto flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter"></i> Filtrar</button>
                                    <a href="index.php?r=nfse/notas_expedidas&active_tab=pedidos" class="btn btn-outline btn-secondary btn-sm" title="Limpar Filtros"><i class="bi bi-x-lg"></i> Limpar</a>
                                </div>

                                <div class="ml-auto">
                                    <?php
                                    $limit = 10;
                                    $page = $pagePedidos ?? 1;
                                    $totalRecords = $totalPedidos ?? 0;
                                    $totalPages = $totalPagesPedidos ?? 1;

                                    $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
                                    $end = min($page * $limit, $totalRecords);
                                    $prev = max(1, $page - 1);
                                    $next = min($totalPages, $page + 1);
                                    
                                    $params = [
                                        'r' => 'nfse/notas_expedidas',
                                        'active_tab' => 'pedidos',
                                        'data_inicio' => $data_inicio,
                                        'data_fim' => $data_fim
                                    ];
                                    $baseLink = 'index.php?' . http_build_query($params);
                                    ?>
                                    <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                                        <div class="join">
                                            <a href="<?= $baseLink ?>&page=1" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>"><i class="bi bi-skip-backward-fill"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $prev ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>"><i class="bi bi-caret-left-fill"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $page ?>" class="join-item btn btn-sm btn-ghost"><i class="bi bi-arrow-clockwise"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $next ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>"><i class="bi bi-caret-right-fill"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $totalPages ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>"><i class="bi bi-skip-forward-fill"></i></a>
                                        </div>
                                        <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                                            <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="alert alert-info shadow-sm mb-4 bg-opacity-10 text-info-content border-info">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-receipt"></i>
                                <span class="font-bold">Pedidos com status de NFS-e (Local).</span>
                            </div>
                            <div class="text-sm opacity-75 mt-1">
                                <i class="bi bi-calendar-range"></i> Exibindo período: <?= date('d/m/Y', strtotime($data_inicio)) ?> até <?= date('d/m/Y', strtotime($data_fim)) ?>
                            </div>
                            <div class="text-sm opacity-75 mt-1">
                                <i class="bi bi-list-ol"></i> Total de notas encontradas: <strong><?= number_format($totalPedidos ?? 0, 0, ',', '.') ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Controls (Pedidos) - MOVED TO FILTER BAR -->

                    <div class="overflow-x-auto">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>Chave de Acesso</th>
                                    <th>Emissão</th>
                                    <th>Pedido</th>
                                    <th>Tomador</th>
                                    <th>Valor</th>
                                    <th>Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($notas) > 0): ?>
                                    <?php foreach ($notas as $nota): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($nota['nfse_chave_acesso'])): ?>
                                                    <span class="font-mono text-[11px] select-all block">
                                                        <?= $nota['nfse_chave_acesso'] ?>
                                                    </span>
                                                <?php elseif ($nota['nfse_numero']): ?>
                                                    <span class="text-xs text-base-content/50" title="RPS/DPS">DPS: <?= $nota['nfse_numero'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-base-content/50">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $nota['nfse_data_emissao'] ? date('d/m/Y H:i', strtotime($nota['nfse_data_emissao'])) : '-' ?></td>
                                            <td>
                                                <div class="badge badge-neutral"><?= $nota['codigo_pedido'] ?: $nota['id'] ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($nota['paciente_nome'] ?? 'Consumidor Final') ?></td>
                                            <td class="font-mono">R$ <?= number_format($nota['total_liquido'], 2, ',', '.') ?></td>
                                            <!-- Situação -->
                                            <td>
                                                <?php
                                                $statusUpper = strtoupper($nota['nfse_status'] ?: 'PENDENTE');
                                                $sit = $statusUpper;
                                                $clsSit = 'badge-info'; // Default

                                                if ($statusUpper === 'AUTORIZADA') {
                                                    $clsSit = 'badge-success text-white';
                                                } elseif (strpos($statusUpper, 'CANCEL') !== false) {
                                                    $sit = 'CANCELADA';
                                                    $clsSit = 'badge-error text-white';
                                                } elseif (strpos($statusUpper, 'ERRO') !== false) {
                                                    $clsSit = 'badge-ghost';
                                                } elseif (strpos($statusUpper, 'PROCESS') !== false) {
                                                    $sit = 'PROCESSAMENTO';
                                                    $clsSit = 'badge-warning';
                                                } elseif ($statusUpper === 'EVENTO') {
                                                    $sit = 'EVENTO';
                                                    $clsSit = 'badge-warning';
                                                }
                                                ?>
                                                <span class="badge <?= $clsSit ?>"><?= $sit ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-10 text-base-content/50">
                                            <i class="bi bi-inbox text-4xl block mb-2"></i>
                                            Nenhuma nota fiscal encontrada no período selecionado (Pedidos Locais).
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <input type="radio" name="my_tabs_1" role="tab" class="tab" aria-label="NFS-e Emitidas" <?= ($active_tab ?? '') == 'emitidas' ? 'checked' : '' ?> />
                <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
                    
                    <!-- Filtro Emitidas -->
                    <div class="card bg-base-100 shadow-sm mb-4 border border-base-200">
                        <div class="card-body p-4">
                            <form method="get" class="flex flex-col md:flex-row gap-4 items-end">
                                <input type="hidden" name="r" value="nfse/notas_expedidas">
                                <input type="hidden" name="active_tab" value="emitidas">
                                <div class="form-control w-full md:w-1/4">
                                    <label class="label"><span class="label-text font-bold">Data Início</span></label>
                                    <input type="date" name="data_inicio_emitidas" class="input input-bordered w-full input-sm" value="<?= $data_inicio_emitidas ?>">
                                </div>
                                <div class="form-control w-full md:w-1/4">
                                    <label class="label"><span class="label-text font-bold">Data Fim</span></label>
                                    <input type="date" name="data_fim_emitidas" class="input input-bordered w-full input-sm" value="<?= $data_fim_emitidas ?>">
                                </div>
                                <div class="form-control w-full md:w-auto flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter"></i> Filtrar</button>
                                    <a href="index.php?r=nfse/notas_expedidas&active_tab=emitidas" class="btn btn-outline btn-secondary btn-sm" title="Limpar Filtros"><i class="bi bi-x-lg"></i> Limpar</a>
                                </div>

                                <div class="ml-auto">
                                    <?php
                                    $limit = 10;
                                    $page = $pageEmitidas ?? 1;
                                    $totalRecords = $totalEmitidas ?? 0;
                                    $totalPages = $totalPagesEmitidas ?? 1;

                                    $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
                                    $end = min($page * $limit, $totalRecords);
                                    $prev = max(1, $page - 1);
                                    $next = min($totalPages, $page + 1);
                                    
                                    $params = [
                                        'r' => 'nfse/notas_expedidas',
                                        'active_tab' => 'emitidas',
                                        'data_inicio_emitidas' => $data_inicio_emitidas,
                                        'data_fim_emitidas' => $data_fim_emitidas
                                    ];
                                    $baseLink = 'index.php?' . http_build_query($params);
                                    ?>
                                    <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                                        <div class="join">
                                            <a href="<?= $baseLink ?>&page=1" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>"><i class="bi bi-skip-backward-fill"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $prev ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>"><i class="bi bi-caret-left-fill"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $page ?>" class="join-item btn btn-sm btn-ghost"><i class="bi bi-arrow-clockwise"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $next ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>"><i class="bi bi-caret-right-fill"></i></a>
                                            <a href="<?= $baseLink ?>&page=<?= $totalPages ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>"><i class="bi bi-skip-forward-fill"></i></a>
                                        </div>
                                        <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                                            <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="alert alert-success shadow-sm mb-4 bg-opacity-10 text-success-content border-success">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-arrow-up-right-circle-fill"></i>
                                <span class="font-bold">Notas Fiscais emitidas pela sua empresa no Ambiente Nacional.</span>
                            </div>
                            <div class="text-sm opacity-75 mt-1">
                                <i class="bi bi-calendar-range"></i> Exibindo período: <?= date('d/m/Y', strtotime($data_inicio_emitidas)) ?> até <?= date('d/m/Y', strtotime($data_fim_emitidas)) ?>
                            </div>
                            <div class="text-sm opacity-75 mt-1">
                                <i class="bi bi-list-ol"></i> Total de notas encontradas: <strong><?= number_format($totalEmitidas ?? 0, 0, ',', '.') ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Controls (Emitidas) - MOVED TO FILTER BAR -->

                    <?php
                    $formatDoc = function ($doc) {
                        $doc = preg_replace('/\D/', '', $doc);
                        if (strlen($doc) === 11) {
                            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
                        } elseif (strlen($doc) === 14) {
                            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
                        }
                        return $doc;
                    };
                    ?>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>Geração</th>
                                    <th>Emitida para</th>
                                    <th>Competência</th>
                                    <th>Município Emissor</th>
                                    <th>Preço Serviço (R$)</th>
                                    <th>Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($notasNacionalEmitidas) && count($notasNacionalEmitidas) > 0): ?>
                                    <?php foreach ($notasNacionalEmitidas as $nota): ?>
                                        <tr>
                                            <!-- Geração -->
                                            <td><?= $nota['data_emissao'] ? date('d/m/Y', strtotime($nota['data_emissao'])) : '-' ?></td>

                                            <!-- Emitida para -->
                                            <td>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-xs">
                                                        <?= $formatDoc($nota['tomador_doc'] ?? '') ?> - <?= strtoupper($nota['tomador_nome'] ?? 'CONSUMIDOR FINAL') ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Competência -->
                                            <td><?= $nota['competencia'] ? date('m/Y', strtotime($nota['competencia'])) : '-' ?></td>

                                            <!-- Município Emissor -->
                                            <td><?= $nota['municipio_emissao_descricao'] ?? '-' ?></td>

                                            <!-- Preço Serviço -->
                                            <td class="font-mono">
                                                <?= number_format($nota['valor_liquido'], 2, ',', '.') ?>
                                            </td>

                                            <!-- Situação -->
                                            <td>
                                                <?php
                                                $st = strtoupper($nota['status'] ?? 'AUTORIZADA');
                                                $cls = 'badge-info';
                                                if ($st == 'AUTORIZADA') $cls = 'badge-success text-white';
                                                elseif (strpos($st, 'CANCEL') !== false) $cls = 'badge-error text-white';
                                                ?>
                                                <span class="badge <?= $cls ?>"><?= $st ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-10 text-base-content/50">
                                            <i class="bi bi-send-slash text-4xl block mb-2"></i>
                                            Nenhuma nota emitida encontrada no Portal Nacional.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function filterTable(type, btn) {
        const rows = document.querySelectorAll('#tableNacional tbody tr');
        rows.forEach(row => {
            if (type === 'all' || row.getAttribute('data-fluxo') === type) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Update buttons
        const buttons = btn.parentElement.querySelectorAll('button');
        buttons.forEach(b => b.classList.remove('btn-active'));
        btn.classList.add('btn-active');
    }

    function syncNacional(reset = false) {
        const btn = reset ? document.getElementById('btnSyncReset') : document.getElementById('btnSyncNacional');
        if (!btn) return;
        
        const originalContent = btn.innerHTML;
        const msg = reset ? 
            'Deseja REIMPORTAR TODAS as notas do Ambiente Nacional?\nIsso pode demorar vários minutos e substituir dados existentes.' : 
            'Deseja consultar o Ambiente Nacional para importar novas notas emitidas?\nIsso pode levar alguns segundos.';

        if (!confirm(msg)) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Sincronizando...';
        
        const btnReset = document.getElementById('btnSyncReset');
        const btnSync = document.getElementById('btnSyncNacional');

        if (reset) {
            if (btnSync) btnSync.disabled = true;
        } else {
            if (btnReset) btnReset.disabled = true;
        }

        const body = new URLSearchParams();
        body.append('acao', 'sincronizar');
        if (reset) body.append('reset_nsu', 'true');

        fetch('index.php?r=nfse/importar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    if (btnSync) btnSync.disabled = false;
                    if (btnReset) btnReset.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro de comunicação com o servidor.');
                btn.disabled = false;
                btn.innerHTML = originalContent;
                if (btnSync) btnSync.disabled = false;
                if (btnReset) btnReset.disabled = false;
            });
    }

    const btnSync = document.getElementById('btnSyncNacional');
    if (btnSync) btnSync.addEventListener('click', () => syncNacional(false));
    
    const btnReset = document.getElementById('btnSyncReset');
    if (btnReset) btnReset.addEventListener('click', () => syncNacional(true));
</script>
