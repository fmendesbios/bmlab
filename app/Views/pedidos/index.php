<?php
// app/Views/pedidos/index.php
?>
<style>
    /* Estilos Legacy para Badges e Mnemonicos */
    .tr-mnemonicos td {
        background-color: #f8f9fa;
        border-top: none !important;
        padding-top: 0.4rem;
        padding-bottom: 0.6rem;
    }
    .mnems-wrapper {
        display: flex;
        align-items: center;
        gap: .25rem;
    }
    .mnems-scroll {
        flex: 1;
        overflow-x: auto;
        white-space: nowrap;
    }
    .mnems-inline {
        margin-top: 0.15rem;
    }
    .badge-exame-status {
        display: inline-block;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 500;
        color: #fff;
        margin: 0 0.25rem 0.25rem 0;
    }
    .badge-status-C { background-color: #c38cf5; }
    .badge-status-T { background-color: #f78fb3; }
    .badge-status-D { background-color: #4dabf7; }
    .badge-status-L { background-color: #51cf66; }
    .badge-status-I { background-color: #ffa94d; }
    .badge-status-E { background-color: #8d6e63; }
    .badge-status-A { background-color: #dc3545; } /* Recoleta */

    /* Ajustes finos para simular o layout legacy */
    .table-legacy th {
        font-weight: 600;
        font-size: 0.875rem;
        color: #495057;
        background-color: #fff;
        border-bottom: 2px solid #dee2e6;
    }
    .table-legacy td {
        vertical-align: middle;
        font-size: 0.9rem;
        color: #212529;
    }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-file-earmark-medical"></i> Pedidos</h1>
    <a href="index.php?r=pedidos/create" class="btn btn-primary gap-2">
        <i class="bi bi-plus-lg"></i> Novo Pedido
    </a>
</div>

<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-4 md:p-6">
        
        <!-- Filters and Pagination -->
        <form method="GET" action="index.php" class="mb-6 bg-base-200 p-4 rounded-lg">
            <input type="hidden" name="r" value="pedidos">
            <div class="flex flex-wrap gap-4 items-end w-full">
                <!-- Busca -->
                <div class="form-control w-full max-w-xs">
                    <label class="label"><span class="label-text">Buscar</span></label>
                    <input type="text" name="busca" value="<?= htmlspecialchars($filters['busca']) ?>" class="input input-bordered w-full" placeholder="Nome ou Código">
                </div>

                <!-- Data Ini -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Data Inicial</span></label>
                    <input type="date" name="data_ini" value="<?= htmlspecialchars($filters['data_ini']) ?>" class="input input-bordered">
                </div>

                <!-- Data Fim -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Data Final</span></label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($filters['data_fim']) ?>" class="input input-bordered">
                </div>

                <!-- Pagination Controls -->
                <?php
                $limit = $limit ?? 10;
                $totalRecords = $totalRecords ?? 0;
                $page = $filters['page'] ?? 1;
                $total_paginas = $filters['total_pages'] ?? 1;

                $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
                $end = min($page * $limit, $totalRecords);
                $prev = max(1, $page - 1);
                $next = min($total_paginas, $page + 1);
                
                $qs = $_GET;
                unset($qs['page']);
                if (!isset($qs['r'])) $qs['r'] = 'pedidos';
                
                if (!function_exists('_ped_page_link')) {
                    function _ped_page_link($p, $qs) { 
                        $qs['page'] = $p; 
                        return 'index.php?' . http_build_query($qs); 
                    }
                }
                ?>

                <?php if ($totalRecords > 0): ?>
                    <div class="flex items-center gap-2 bg-base-100 p-2 rounded-lg shadow-sm">
                        <div class="join">
                            <!-- First -->
                            <a href="<?= _ped_page_link(1, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-skip-backward-fill"></i>
                            </a>
                            <!-- Prev -->
                            <a href="<?= _ped_page_link($prev, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-caret-left-fill"></i>
                            </a>
                            <!-- Refresh/Current -->
                            <a href="<?= _ped_page_link($page, $qs) ?>" class="join-item btn btn-sm btn-ghost">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                            <!-- Next -->
                            <a href="<?= _ped_page_link($next, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $total_paginas) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-caret-right-fill"></i>
                            </a>
                            <!-- Last -->
                            <a href="<?= _ped_page_link($total_paginas, $qs) ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $total_paginas) ? 'btn-disabled' : '' ?>">
                                <i class="bi bi-skip-forward-fill"></i>
                            </a>
                        </div>
                        <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                            <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Posto -->
                <div class="form-control w-full max-w-xs">
                    <label class="label"><span class="label-text">Posto</span></label>
                    <select name="posto_filtro" class="select select-bordered w-full">
                        <option value="0">Todos</option>
                        <?php foreach ($postos as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filters['posto_filtro'] == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome_posto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Convênio -->
                <div class="form-control w-full max-w-xs">
                    <label class="label"><span class="label-text">Convênio</span></label>
                    <select name="convenio_filtro" class="select select-bordered w-full">
                        <option value="0">Todos</option>
                        <?php foreach ($convenios as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filters['convenio_filtro'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Pagamento -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Pagamento</span></label>
                    <select name="status_pagamento" class="select select-bordered w-full">
                        <option value="">Todos</option>
                        <option value="aberto" <?= $filters['status_pagamento'] === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                        <option value="pago" <?= $filters['status_pagamento'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <?php if (!empty($filters['busca']) || !empty($filters['data_ini']) || !empty($filters['data_fim']) || $filters['posto_filtro'] > 0 || $filters['convenio_filtro'] > 0 || !empty($filters['status_pagamento'])): ?>
                    <a href="index.php?r=pedidos" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Messages -->
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success py-2 mb-3 rounded-md text-sm">
                <span>Pedido salvo com sucesso.</span>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table w-full table-legacy">
                <thead>
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Paciente</th>
                        <th class="text-center">Convênio</th>
                        <th class="text-center">Entrada</th>
                        <th class="text-center">Previsão de Entrega</th>
                        <th class="text-center">Receb.</th>
                        <th class="text-center" style="width: 240px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Nenhum pedido encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <?php 
                                $isBlocked = ($p['bloqueio_desconto'] === 'S');
                                $isPaid = ($p['recebido_status'] === 'R');
                                $valorPago = (float)$p['valor_recebido'];
                                $totalLiq = (float)$p['total_liquido'];
                                $faltaPagar = max(0, $totalLiq - $valorPago);
                                
                                // Logic for payment icon
                                $recIcon = '<i class="bi bi-currency-dollar text-red-500 text-2xl" title="Não recebido"></i>';
                                if (($valorPago > 0 && $valorPago + 0.001 >= $totalLiq) || $isPaid) {
                                    $recIcon = '<i class="bi bi-currency-dollar text-green-500 text-2xl" title="Recebido"></i>';
                                }
                                if ($p['convenio_tipo'] === 'cortesia') {
                                    $recIcon = '<span class="text-gray-400">—</span>';
                                }

                                $hasMnems = !empty($p['itens']);
                            ?>
                            <tr class="<?= $hasMnems ? 'border-b-0' : '' ?> hover:bg-gray-50">
                                <td class="font-mono text-sm">
                                    <?= htmlspecialchars(($p['codigo_pedido'] !== '' ? $p['codigo_pedido'] : '01' . str_pad($p['id'], 7, '0', STR_PAD_LEFT))) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['paciente_nome']) ?>
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($p['convenio_nome']) ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                        $ts = strtotime($p['entrada'] ?? '');
                                        if ($ts && $ts > 0 && date('Y', $ts) > 1900): 
                                    ?>
                                        <?= date('d/m/Y H:i', $ts) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($p['previsao_entrega']) ?>
                                </td>
                                <td class="text-center">
                                    <?= $recIcon ?>
                                </td>
                                <td class="text-center">
                                    <div class="flex justify-center gap-1">
                                        <?php if (temPermissao('pedidos.acesso')): ?>
                                            <button type="button" class="btn btn-xs btn-info text-white" onclick="openVisualizar(<?= $p['id'] ?>)" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (temPermissao('pedidos.alterar')): ?>
                                            <a href="index.php?r=pedidos/edit&id=<?= $p['id'] ?>" class="btn btn-xs btn-warning text-white" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Financeiro/Bloqueio -->
                                        <?php if ($isBlocked): ?>
                                            <?php if (temPermissao('pedidos.desbloquear')): ?>
                                                <button type="button" class="btn btn-xs btn-error text-white" onclick="openDesbloqueio(<?= $p['id'] ?>)" title="Desbloquear">
                                                    <i class="bi bi-unlock"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="btn btn-xs btn-disabled" title="Bloqueado"><i class="bi bi-lock-fill"></i></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (temPermissao('pedidos.receber')): ?>
                                                <?php 
                                                    $clsReceb = $isPaid ? 'btn-success' : 'btn-outline btn-success';
                                                    $titleReceb = $isPaid ? 'Recebido/Editar' : 'Receber';
                                                ?>
                                                <button type="button" class="btn btn-xs <?= $clsReceb ?> text-white" 
                                                    onclick="openReceber(<?= $p['id'] ?>, <?= $p['total_bruto'] ?? 0 ?>, <?= $p['desconto_percentual'] ?? 0 ?>, <?= $p['desconto_valor'] ?? 0 ?>, <?= $p['total_liquido'] ?? 0 ?>, <?= $p['forma_pagamento_id'] ?? 0 ?>)" 
                                                    title="<?= $titleReceb ?>">
                                                    <i class="bi bi-cash-coin"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (temPermissao('pedidos.acesso')): ?>
                                            <button type="button" class="btn btn-xs btn-ghost text-primary border-primary" onclick="openRecibo(<?= $p['id'] ?>, <?= $p['valor_recebido'] ?? 0 ?>, '<?= addslashes($p['paciente_nome'] ?? '') ?>')" title="Recibo">
                                                <i class="bi bi-receipt"></i>
                                            </button>
                                            <button type="button" class="btn btn-xs btn-ghost text-info border-info" onclick="openAtestado(<?= $p['id'] ?>, '<?= addslashes($p['paciente_nome'] ?? '') ?>')" title="Atestado">
                                                <i class="bi bi-journal-medical"></i>
                                            </button>
                                            <a href="relatorios/guia_coleta_producao.php?pedido_id=<?= $p['id'] ?>" target="_blank" class="btn btn-xs btn-ghost text-secondary border-secondary" title="Protocolo / Guia">
                                                <i class="bi bi-card-checklist"></i>
                                            </a>
                                            <a href="index.php?r=pedidos/etiquetas&id=<?= $p['id'] ?>" target="_blank" class="btn btn-xs btn-secondary text-white" title="Etiquetas">
                                                <i class="bi bi-upc-scan"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (empty($p['nfse_numero'])): ?>
                                            <?php if (temPermissao('pedidos.alterar')): ?>
                                                <a href="index.php?r=nfse/emitir&id=<?= $p['id'] ?>" class="btn btn-xs btn-ghost text-secondary border-secondary" title="Emitir NFS-e" onclick="return confirm('Confirmar emissão de NFS-e para este pedido?')">
                                                    <i class="bi bi-file-earmark-plus"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="dropdown dropdown-end dropdown-hover">
                                                <label tabindex="0" class="btn btn-xs btn-ghost text-success border-success" title="NFS-e <?= htmlspecialchars($p['nfse_numero']) ?>">
                                                    <i class="bi bi-check-circle"></i>
                                                </label>
                                                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52 z-[1]">
                                                    <li><a href="index.php?r=nfse/visualizarDanfse&id=<?= $p['id'] ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Visualizar PDF</a></li>
                                                    <li><a href="index.php?r=nfse/downloadXml&id=<?= $p['id'] ?>" target="_blank"><i class="bi bi-file-code"></i> Baixar XML</a></li>
                                                    <li>
                                                        <a href="index.php?r=nfse/cancelar&id=<?= $p['id'] ?>&source=pedidos" class="text-error" onclick="return confirm('Deseja realmente iniciar o cancelamento desta nota?');">
                                                            <i class="bi bi-x-circle"></i> Cancelar NFS-e
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (temPermissao('resultados.acesso')): ?>
                                            <a href="index.php?r=resultados/print_laudo&pedido_id=<?= $p['id'] ?>" target="_blank" class="btn btn-xs btn-ghost text-success border-success" title="Imprimir Laudo">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (temPermissao('pedidos.deletar')): ?>
                                            <button type="button" class="btn btn-xs btn-error text-white" onclick="confirmarExclusao(<?= $p['id'] ?>)" title="Excluir">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($hasMnems): ?>
                                <tr class="tr-mnemonicos">
                                    <td colspan="7">
                                        <div class="mnems-wrapper pl-2">
                                            <div class="mnems-scroll">
                                                <div class="mnems-inline">
                                                    <?php foreach ($p['itens'] as $mn): ?>
                                                        <?php
                                                        $status = $mn['status_exame'] ?: 'C';
                                                        $mnemo  = $mn['mnemonico_local'] ?? '';
                                                        $badgeKey = ($status === 'R') ? 'A' : $status;
                                                        $titulo = match($status) {
                                                            'T' => 'Triado',
                                                            'D' => 'Digitado',
                                                            'L' => 'Liberado',
                                                            'I' => 'Impresso',
                                                            'E' => 'Entregue',
                                                            'R' => 'Recoleta',
                                                            default => 'Coletado'
                                                        };
                                                        ?>
                                                        <span class="badge-exame-status badge-status-<?= htmlspecialchars($badgeKey) ?>" title="<?= htmlspecialchars($titulo) ?>">
                                                            <?= htmlspecialchars($mnemo) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Visualizar Pedido -->
<dialog id="modal_visualizar" class="modal">
    <div class="modal-box w-11/12 max-w-6xl h-[90vh] p-0 flex flex-col bg-base-100">
        <div class="p-3 flex justify-between items-center bg-base-200 border-b">
            <h3 class="font-bold text-lg"><i class="bi bi-eye text-info mr-2"></i>Visualizar Pedido</h3>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost">✕</button>
            </form>
        </div>
        <div class="flex-1 overflow-hidden bg-base-100">
            <iframe id="iframe_visualizar" src="" class="w-full h-full border-0"></iframe>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
    function confirmarExclusao(id) {
        if(confirm('Tem certeza que deseja excluir este pedido?')) {
            window.location.href = 'index.php?r=pedidos/delete&id=' + id;
        }
    }

    function openVisualizar(id) {
        const iframe = document.getElementById('iframe_visualizar');
        // Clear src first to avoid showing previous content
        iframe.src = 'about:blank';
        setTimeout(() => {
            iframe.src = 'index.php?r=pedidos/ver_modal&id=' + id;
            document.getElementById('modal_visualizar').showModal();
        }, 50);
    }

    // openAtestado e openRecibo movidos para o final do arquivo para evitar duplicidade
</script>
<dialog id="modal_atestado" class="modal">
    <div class="modal-box">
        <div class="flex items-center gap-2 mb-4">
             <i class="bi bi-file-earmark-check text-xl"></i>
             <h3 class="font-bold text-lg">Atestado de Comparecimento</h3>
             <form method="dialog" class="ml-auto">
                <button class="btn btn-sm btn-circle btn-ghost">✕</button>
            </form>
        </div>
        
        <form method="GET" action="relatorios/atestado_comparecimento.php" target="_blank">
            <input type="hidden" name="pedido_id" id="atestado_pedido_id">
            
            <div class="form-control w-full mb-3">
                <label class="label"><span class="label-text">Paciente</span></label>
                <input type="text" name="paciente" id="atestado_paciente_nome" class="input input-bordered w-full" placeholder="Nome do Paciente">
            </div>

            <div class="grid grid-cols-3 gap-3 mb-2">
                <div class="form-control">
                    <label class="label"><span class="label-text">Data</span></label>
                    <input type="date" name="data" id="atestado_data" class="input input-bordered w-full">
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">Hora Inicial</span></label>
                    <input type="time" name="hora_ini" id="atestado_inicio" class="input input-bordered w-full">
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">Hora Final</span></label>
                    <input type="time" name="hora_fim" id="atestado_fim" class="input input-bordered w-full">
                </div>
            </div>
            
            <div class="text-xs text-gray-500 mb-6">
                Se vazio, usa os dados do pedido selecionado.
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-neutral" onclick="document.getElementById('modal_atestado').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary gap-2" onclick="document.getElementById('modal_atestado').close()">
                    <i class="bi bi-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Modal Recibo -->
<dialog id="modal_recibo" class="modal">
    <div class="modal-box">
        <div class="flex items-center gap-2 mb-4">
             <i class="bi bi-receipt text-xl"></i>
             <h3 class="font-bold text-lg">Recibo de Pagamento</h3>
             <form method="dialog" class="ml-auto">
                <button class="btn btn-sm btn-circle btn-ghost">✕</button>
            </form>
        </div>

        <form method="GET" action="relatorios/recibo_pagamento.php" target="_blank">
            <input type="hidden" name="pedido_id" id="recibo_pedido_id">
            
            <div class="form-control w-full mb-3">
                <label class="label"><span class="label-text">Paciente</span></label>
                <input type="text" name="paciente" id="recibo_paciente_nome" class="input input-bordered w-full" placeholder="Nome do Paciente">
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="form-control">
                    <label class="label"><span class="label-text">Valor (R$)</span></label>
                    <input type="number" step="0.01" name="valor" id="recibo_valor" class="input input-bordered w-full" required>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">Data</span></label>
                    <input type="date" name="data" id="recibo_data" class="input input-bordered w-full" required>
                </div>
            </div>

            <div class="form-control w-full mb-2">
                <label class="label"><span class="label-text">Descrição</span></label>
                <input type="text" name="descricao" id="recibo_referente" value="exames clínicos" class="input input-bordered w-full">
            </div>

            <div class="text-xs text-gray-500 mb-6">
                Se vazio, usa os dados do pedido selecionado.
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-neutral" onclick="document.getElementById('modal_recibo').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary gap-2" onclick="document.getElementById('modal_recibo').close()">
                    <i class="bi bi-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- MODAL: RECEBIMENTO -->
<dialog id="modal_receber" class="modal">
    <div class="modal-box max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <i class="bi bi-cash-coin text-success"></i> Recebimento
            </h3>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost">✕</button>
            </form>
        </div>

        <form id="form_receber">
            <input type="hidden" id="receb_pedido_id" name="pedido_id">
            
            <!-- Info Section -->
            <div class="bg-[#f7fffb] border border-[#d1f2e8] rounded-lg p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-control">
                        <label class="label py-0 mb-1">
                            <span class="label-text text-xs font-semibold flex items-center gap-1">
                                <i class="bi bi-file-earmark-medical text-secondary"></i> Total Pedido (R$)
                            </span>
                        </label>
                        <input type="text" id="receb_total_bruto" class="input input-bordered input-sm text-right bg-base-200" readonly>
                    </div>
                    <div class="form-control">
                        <label class="label py-0 mb-1">
                            <span class="label-text text-xs font-semibold flex items-center gap-1">
                                <i class="bi bi-percent text-warning"></i> Desconto (%)
                            </span>
                        </label>
                        <input type="text" id="receb_desconto_percentual" name="desconto_percentual" class="input input-bordered input-sm text-right" placeholder="0,00">
                    </div>
                    <div class="form-control">
                        <label class="label py-0 mb-1">
                            <span class="label-text text-xs font-semibold flex items-center gap-1">
                                <i class="bi bi-ticket-perforated text-warning"></i> Desconto (R$)
                            </span>
                        </label>
                        <input type="text" id="receb_desconto_valor" name="desconto_valor" class="input input-bordered input-sm text-right" placeholder="0,00">
                    </div>
                    <div class="form-control">
                        <label class="label py-0 mb-1">
                            <span class="label-text text-xs font-semibold flex items-center gap-1">
                                <i class="bi bi-cash-stack text-success"></i> Total Líquido (R$)
                            </span>
                        </label>
                        <input type="text" id="receb_total_liquido" class="input input-bordered input-sm text-right font-bold text-[#0f9d82] bg-base-200" readonly>
                    </div>
                </div>
            </div>

            <!-- Action Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text flex items-center gap-1">
                            <i class="bi bi-cash-coin text-success"></i> Valor Recebido (R$)
                        </span>
                    </label>
                    <div class="join w-full">
                        <span class="join-item btn btn-success btn-sm no-animation cursor-default text-white">
                            <i class="bi bi-cash"></i>
                        </span>
                        <input type="text" id="receb_valor" name="valor" class="join-item input input-bordered input-sm w-full text-right">
                    </div>
                </div>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text flex items-center gap-1">
                            <i class="bi bi-ui-checks-grid text-primary"></i> Forma de Pagamento
                        </span>
                    </label>
                    <select id="receb_tipo_id" name="tipo_id" class="select select-bordered select-sm w-full">
                        <?php foreach ($tipos_pagamento as $tp): ?>
                            <option value="<?= $tp['id'] ?>"><?= $tp['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-action mt-6">
                <button type="button" class="btn" onclick="document.getElementById('modal_receber').close()">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-success text-white">
                    <i class="bi bi-check2-circle"></i> Receber
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Desbloqueio -->
<dialog id="modal_desbloqueio" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Autorizar Desbloqueio</h3>
        <p class="mb-4 text-sm opacity-70">Insira as credenciais de um usuário com permissão.</p>
        
        <form id="form_desbloqueio">
            <input type="hidden" name="pedido_id" id="desb_pedido_id">
            
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Usuário</span></label>
                <input type="text" name="usuario_login" class="input input-bordered w-full" required>
            </div>

            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Senha</span></label>
                <input type="password" name="senha_login" class="input input-bordered w-full" required>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_desbloqueio').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Autorizar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
    function openAtestado(id, pacienteNome) {
        document.getElementById('atestado_pedido_id').value = id;
        document.getElementById('atestado_paciente_nome').value = pacienteNome;
        
        // Set defaults
        const now = new Date();
        const dateStr = now.toISOString().split('T')[0];
        const timeStr = now.toTimeString().split(' ')[0].substring(0, 5);
        
        document.getElementById('atestado_data').value = dateStr;
        document.getElementById('atestado_inicio').value = timeStr;
        document.getElementById('atestado_fim').value = timeStr; // User adjusts end time
        
        document.getElementById('modal_atestado').showModal();
    }

    function openRecibo(id, valorPago, pacienteNome) {
        document.getElementById('recibo_pedido_id').value = id;
        document.getElementById('recibo_valor').value = valorPago > 0 ? valorPago.toFixed(2) : '';
        if(pacienteNome) document.getElementById('recibo_paciente_nome').value = pacienteNome;
        
        const now = new Date();
        const dateStr = now.toISOString().split('T')[0];
        document.getElementById('recibo_data').value = dateStr;
        
        document.getElementById('modal_recibo').showModal();
    }

    // Helper para formatar moeda
    function formatMoeda(valor) {
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Helper para parsing moeda
    function parseMoeda(valorStr) {
        if (!valorStr) return 0;
        return parseFloat(valorStr.replace(/\./g, '').replace(',', '.'));
    }

    function openReceber(id, totalBruto, descPerc, descValor, totalLiq, tipoRecebimento) {
        document.getElementById('receb_pedido_id').value = id;
        
        // Populate fields
        document.getElementById('receb_total_bruto').value = formatMoeda(totalBruto);
        document.getElementById('receb_desconto_percentual').value = formatMoeda(descPerc);
        document.getElementById('receb_desconto_valor').value = formatMoeda(descValor);
        document.getElementById('receb_total_liquido').value = formatMoeda(totalLiq);
        
        // Default payment amount is the total liquid (assuming paying in full)
        // Or if previously paid partially, this logic might need adjustment, but usually "Receber" modal 
        // in this context (legacy) implies receiving the full remaining amount or updating the payment.
        // The legacy code shows it updates 'valor_recebido' by adding the new amount.
        // However, if we are editing the discount, we are effectively recalculating the whole deal.
        // Let's stick to: suggest the Total Liquid as the amount to receive.
        document.getElementById('receb_valor').value = formatMoeda(totalLiq);
        
        if (tipoRecebimento > 0) {
            document.getElementById('receb_tipo_id').value = tipoRecebimento;
        }

        // Store raw total bruto for calculations
        document.getElementById('receb_total_bruto').dataset.raw = totalBruto;

        document.getElementById('modal_receber').showModal();
    }
    
    // Discount calculation logic
    function calcRecebimento(origem) {
        const totalBruto = parseFloat(document.getElementById('receb_total_bruto').dataset.raw || 0);
        let descPerc = parseMoeda(document.getElementById('receb_desconto_percentual').value);
        let descValor = parseMoeda(document.getElementById('receb_desconto_valor').value);
        
        if (origem === 'percentual') {
            if (descPerc > 100) descPerc = 100;
            if (descPerc < 0) descPerc = 0;
            descValor = totalBruto * (descPerc / 100);
            document.getElementById('receb_desconto_valor').value = formatMoeda(descValor);
        } else if (origem === 'valor') {
            if (descValor > totalBruto) descValor = totalBruto;
            if (descValor < 0) descValor = 0;
            descPerc = totalBruto > 0 ? (descValor / totalBruto) * 100 : 0;
            document.getElementById('receb_desconto_percentual').value = formatMoeda(descPerc);
        }
        
        const totalLiq = totalBruto - descValor;
        document.getElementById('receb_total_liquido').value = formatMoeda(totalLiq);
        document.getElementById('receb_valor').value = formatMoeda(totalLiq); // Auto-update payment amount
    }

    // Add listeners for real-time calculation
    document.getElementById('receb_desconto_percentual').addEventListener('input', () => calcRecebimento('percentual'));
    document.getElementById('receb_desconto_valor').addEventListener('input', () => calcRecebimento('valor'));

    function openDesbloqueio(id) {
        document.getElementById('desb_pedido_id').value = id;
        document.getElementById('modal_desbloqueio').showModal();
    }

    document.getElementById('form_receber').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="loading loading-spinner"></span> Processando...';
        
        fetch('index.php?r=pedidos/receber', {
            method: 'POST',
            body: formData
        })
        .then(r => {
             if (!r.ok) throw new Error('Erro na requisição: ' + r.status);
             return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Resposta inválida:', text);
                throw new Error('Resposta do servidor inválida (não é JSON). Verifique o console.');
            }
        })
        .then(data => {
            if(data.ok) {
                alert('Recebimento registrado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + (data.msg || 'Desconhecido'));
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            alert('Erro: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });

    document.getElementById('form_desbloqueio').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('index.php?r=pedidos/desbloquear', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if(data.ok) {
                alert('Desbloqueado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + (data.erro || 'Desconhecido'));
            }
        });
    });
</script>