<?php
// app/Views/triagem/show.php
$msgSuccess = isset($_GET['success']) ? 'Operação realizada com sucesso.' : '';
$msgError = isset($_GET['error']) ? 'Erro ao processar solicitação. Verifique se selecionou itens.' : '';
?>
<div class="mb-6 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="index.php?r=triagem" class="btn btn-circle btn-ghost">
            <i class="bi bi-arrow-left text-xl"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold">Triagem do Pedido <?= $pedido['codigo_pedido'] ?: $pedido['id'] ?></h1>
            <div class="text-sm opacity-50">Entrada: <?= date('d/m/Y H:i', strtotime($pedido['entrada'])) ?></div>
        </div>
    </div>
</div>

<?php if ($msgSuccess): ?>
    <div class="alert alert-success shadow-lg mb-6">
        <i class="bi bi-check-circle"></i>
        <span><?= $msgSuccess ?></span>
    </div>
<?php endif; ?>

<?php if ($msgError): ?>
    <div class="alert alert-error shadow-lg mb-6">
        <i class="bi bi-x-circle"></i>
        <span><?= $msgError ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Patient Info -->
    <div class="lg:col-span-1">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-sm uppercase text-gray-500">Dados do Paciente</h2>
                <div class="flex flex-col gap-2 mt-2">
                    <div class="font-bold text-lg"><?= htmlspecialchars($pedido['paciente_nome']) ?></div>
                    <div class="text-sm">
                        <span class="font-semibold">Sexo:</span> <?= $pedido['paciente_sexo'] ?>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Nascimento:</span> <?= date('d/m/Y', strtotime($pedido['paciente_nascimento'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exams List -->
    <div class="lg:col-span-2">
        <form action="index.php?r=triagem/store" method="POST" id="formTriagem">
            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
            
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body p-0">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h2 class="card-title text-sm uppercase text-gray-500">Exames Solicitados</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">
                                        <input type="checkbox" class="checkbox checkbox-sm" id="selectAll" aria-label="Selecionar todos">
                                    </th>
                                    <th>Amostra</th>
                                    <th>Exame / Material</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php 
                                        $isTriado = $item['status_exame'] === 'T';
                                        $isPendente = $item['pendencia_material_triagem'] === 'S';
                                        $rowClass = $isTriado ? 'opacity-50 bg-base-200' : ($isPendente ? 'bg-warning/10' : '');
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="text-center">
                                            <?php if (!$isTriado): ?>
                                                <input type="checkbox" name="item_ids[]" value="<?= $item['id_amostra'] ?>" class="checkbox checkbox-sm item-check">
                                            <?php else: ?>
                                                <i class="bi bi-check-lg text-success"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-mono"><?= $item['id_amostra'] ?></td>
                                        <td>
                                            <div class="font-bold"><?= htmlspecialchars($item['mnemonico']) ?> - <?= htmlspecialchars($item['exame_nome']) ?></div>
                                            <div class="text-xs opacity-70">
                                                <?= htmlspecialchars($item['material_biologico']) ?> • 
                                                <?= htmlspecialchars($item['laboratorio_nome']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isTriado): ?>
                                                <span class="badge badge-success badge-sm">Triado</span>
                                                <div class="text-[10px] opacity-70 mt-1">
                                                    <?= date('d/m H:i', strtotime($item['data_triagem'] . ' ' . $item['hora_triagem'])) ?>
                                                </div>
                                            <?php elseif ($isPendente): ?>
                                                <span class="badge badge-warning badge-sm">Pendência Material</span>
                                                <button type="button" class="btn btn-xs btn-ghost text-primary ml-2" onclick='openPendenciaModal(<?= json_encode([
                                                    "pedido_id" => $pedido["id"],
                                                    "id_amostra" => $item["id_amostra"],
                                                    "data" => $item["data_entrega_material_triagem"] ?? "",
                                                    "obs" => $item["obs_pendencia_triagem"] ?? ""
                                                ]) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if (!empty($item['data_entrega_material_triagem'])): ?>
                                                    <div class="text-[10px] text-warning font-bold mt-1">Prev: <?= date('d/m/Y', strtotime($item['data_entrega_material_triagem'])) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-ghost badge-sm">Aguardando</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t bg-base-100 rounded-b-box flex justify-end gap-2">
                        <button type="submit" name="action" value="marcar_pendencia" class="btn btn-warning btn-outline">
                            <i class="bi bi-exclamation-triangle"></i> Marcar Pendência
                        </button>
                        <button type="submit" name="action" value="confirmar_triagem" class="btn btn-success text-white">
                            <i class="bi bi-check-lg"></i> Confirmar Triagem
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Pendência -->
<dialog id="modal_pendencia" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Editar Pendência</h3>
        <form method="post" action="index.php?r=triagem/update_pendencia">
            <input type="hidden" name="pedido_id" id="pendencia_pedido_id" value="">
            <input type="hidden" name="id_amostra" id="pendencia_id_amostra" value="">
            
            <div class="form-control w-full mb-4">
                <label class="label"><span class="label-text">Previsão de Entrega</span></label>
                <input type="date" name="data_entrega" id="pendencia_data" class="input input-bordered w-full">
            </div>

            <div class="form-control w-full mb-6">
                <label class="label"><span class="label-text">Observação</span></label>
                <textarea name="obs" id="pendencia_obs" class="textarea textarea-bordered h-24 w-full"></textarea>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_pendencia').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
    document.getElementById('selectAll').addEventListener('change', function() {
        const checks = document.querySelectorAll('.item-check');
        checks.forEach(c => c.checked = this.checked);
    });
    
    document.getElementById('formTriagem').addEventListener('submit', function(e) {
        const action = e.submitter ? e.submitter.value : '';
        const checked = document.querySelectorAll('.item-check:checked');
        
        if (checked.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um item para processar.');
        }
    });

    function openPendenciaModal(data) {
        document.getElementById('pendencia_pedido_id').value = data.pedido_id;
        document.getElementById('pendencia_id_amostra').value = data.id_amostra;
        document.getElementById('pendencia_data').value = data.data;
        document.getElementById('pendencia_obs').value = data.obs;
        document.getElementById('modal_pendencia').showModal();
    }
</script>
