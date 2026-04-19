<?php
// app/Views/resultados/entry.php
?>
<div class="grid grid-cols-12 gap-6 h-[calc(100vh-8rem)]">
    <!-- Left Sidebar: Exam List -->
    <div class="col-span-12 md:col-span-3 bg-base-100 shadow-xl rounded-box overflow-y-auto">
        <div class="p-4 border-b">
            <h3 class="font-bold text-lg">Exames do Pedido <?= $pedido['codigo_pedido'] ?: $pedido['id'] ?></h3>
            <div class="text-sm mt-2">
                <div class="font-semibold"><?= htmlspecialchars($paciente['paciente_nome']) ?></div>
                <div class="text-gray-500"><?= $idade ?> • <?= $paciente['paciente_sexo'] ?></div>
            </div>
            <a href="index.php?r=resultados" class="btn btn-sm btn-ghost mt-2 w-full"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
        
        <ul class="menu w-full p-2">
            <?php foreach ($itens as $it): 
                $active = ($selectedItem && $selectedItem['id_amostra'] == $it['id_amostra']) ? 'active' : '';
                $statusColor = 'bg-gray-200';
                $statusIcon = 'bi-circle';
                
                if ($it['status_exame'] == 'D') { // Digitado
                    $statusColor = 'bg-yellow-200 text-yellow-800';
                    $statusIcon = 'bi-pencil-fill';
                } elseif ($it['status_exame'] == 'L') { // Liberado
                    $statusColor = 'bg-green-200 text-green-800';
                    $statusIcon = 'bi-check-circle-fill';
                }
            ?>
                <li class="mb-1">
                    <a href="index.php?r=resultados/entry&pedido_id=<?= $pedido['id'] ?>&item_id=<?= $it['id_amostra'] ?>" class="<?= $active ?> flex flex-col items-start gap-1 py-3">
                        <span class="font-medium"><?= htmlspecialchars($it['exame_nome']) ?></span>
                        <span class="badge badge-sm <?= $statusColor ?> border-0 gap-1">
                            <i class="bi <?= $statusIcon ?>"></i> <?= $it['status_exame'] == 'L' ? 'Liberado' : ($it['status_exame'] == 'D' ? 'Digitado' : 'Pendente') ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Right Content: Form -->
    <div class="col-span-12 md:col-span-9 bg-base-100 shadow-xl rounded-box overflow-y-auto p-6">
        <?php if ($selectedItem): ?>
            <?php if ($sucesso): ?>
                <div class="alert alert-success shadow-lg mb-4">
                    <div><i class="bi bi-check-circle"></i> <span>Resultados salvos com sucesso!</span></div>
                </div>
            <?php endif; ?>

            <!-- Header (Common) -->
            <div class="mb-6 pb-4 border-b">
                <h2 class="text-2xl font-bold"><?= htmlspecialchars($selectedItem['exame_nome']) ?></h2>
                <p class="text-sm text-gray-500">Método: <?= htmlspecialchars($selectedItem['exame_metodo'] ?? 'Não informado') ?></p>
            </div>

            <!-- Tabs -->
            <div class="tabs tabs-boxed mb-6 w-fit">
                <a class="tab tab-active" id="tab-digitacao-btn" onclick="switchTab('digitacao')">Resultado Digitado</a>
                <a class="tab" id="tab-arquivo-btn" onclick="switchTab('arquivo')">Resultado em Arquivo</a>
            </div>

            <!-- Content Digitacao -->
            <div id="tab-digitacao-content">
                <div class="flex justify-end gap-2 mb-4">
                    <?php if ($selectedItem['status_exame'] != 'L'): ?>
                        <button type="submit" form="formResultados" name="acao" value="salvar" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar
                        </button>
                        <button type="submit" form="formResultados" name="acao" value="liberar" class="btn btn-success text-white">
                            <i class="bi bi-check-lg"></i> Salvar e Liberar
                        </button>
                    <?php else: ?>
                        <div class="badge badge-success badge-lg p-4">Exame Liberado</div>
                        <button type="submit" form="formResultados" name="acao" value="salvar" class="btn btn-sm btn-outline btn-warning">
                            <i class="bi bi-pencil"></i> Retificar
                        </button>
                    <?php endif; ?>
                </div>

                <form id="formResultados" method="POST" action="index.php?r=resultados/store">

                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                <input type="hidden" name="pedido_item_id" value="<?= $selectedItem['id_amostra'] ?>">

                <div class="space-y-6 laudo-preview">
                    <?php if (empty($layout)): ?>
                        <div class="alert alert-warning">Layout não configurado para este exame.</div>
                    <?php else: ?>
                        <?php foreach ($layout as $sec): ?>
                            <div class="layout-section mb-6">
                                <?php foreach ($sec['rows'] as $row): ?>
                                    <div class="flex flex-wrap w-full mb-1 items-center min-h-[2rem]">
                                        <?php foreach ($row as $col): ?>
                                            <?php 
                                                // Styles
                                                $style = "";
                                                $cfg = $col['cfg'] ?? [];
                                                
                                                // Width
                                                $width = $cfg['largura'] ? "width: {$cfg['largura']}%;" : "flex: 1;";
                                                $style .= $width;
                                                
                                                // Font
                                                if (!empty($cfg['font_family'])) $style .= "font-family: '{$cfg['font_family']}';";
                                                if (!empty($cfg['font_size'])) $style .= "font-size: {$cfg['font_size']}pt;";
                                                if (!empty($cfg['font_color'])) $style .= "color: {$cfg['font_color']};";
                                                if (!empty($cfg['bold'])) $style .= "font-weight: bold;";
                                                if (!empty($cfg['italic'])) $style .= "font-style: italic;";
                                                if (!empty($cfg['underline'])) $style .= "text-decoration: underline;";
                                                
                                                // Alignment
                                                $align = $cfg['align_h'] ?? 'left';
                                                $style .= "text-align: {$align};";
                                                
                                                // Variable?
                                                $var = $col['variable'];
                                                $content = $col['content'];
                                            ?>
                                            <div class="px-2 py-1" style="<?= $style ?>">
                                                <?php if ($var): ?>
                                                    <?php 
                                                        $val = $var['tipo_resultado'] == 'numerico' ? $var['valor_numerico'] : $var['valor_texto'];
                                                        // Format numeric if needed (float to string BR)
                                                        if ($var['tipo_resultado'] == 'numerico' && $val !== null) {
                                                            $val = number_format((float)$val, $var['casas_decimais'] ?? 2, ',', '.');
                                                        }
                                                    ?>
                                                    <input type="hidden" name="tipo[<?= $var['id'] ?>]" value="<?= $var['tipo_resultado'] ?>">

                                                    <?php 
                                                        // Determine rendering type based on layout config (cfg) and variable type
                                                        $rtTipo = $cfg['rt_tipo'] ?? ''; // cadastrado, livre, sem
                                                        $isDropdown = (!empty($var['options']) || $rtTipo === 'cadastrado');
                                                        $isLongText = ($var['tipo_resultado'] == 'observacao_resultado' || $var['tipo_resultado'] == 'texto_longo');
                                                    ?>

                                                    <?php if ($isDropdown): ?>
                                                        <select name="valor[<?= $var['id'] ?>]" class="select select-bordered select-sm w-full">
                                                            <option value="">Selecione...</option>
                                                            <?php if (!empty($var['options'])): ?>
                                                                <?php foreach ($var['options'] as $opt): ?>
                                                                    <option value="<?= htmlspecialchars($opt['texto']) ?>" <?= ($val === $opt['texto']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($opt['texto']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </select>
                                                    <?php elseif ($isLongText): ?>
                                                        <textarea name="valor[<?= $var['id'] ?>]" class="textarea textarea-bordered w-full" rows="3"><?= htmlspecialchars($val ?? '') ?></textarea>
                                                    <?php else: ?>
                                                        <input type="text" name="valor[<?= $var['id'] ?>]" value="<?= htmlspecialchars($val ?? '') ?>" class="input input-bordered input-sm w-full max-w-xs">
                                                    <?php endif; ?>
                                                    
                                                <?php else: ?>
                                                    <?= nl2br(htmlspecialchars($content)) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </form>
            </div>

            <!-- Content Arquivo -->
            <div id="tab-arquivo-content" class="hidden">
                 <h2 class="text-xl font-bold mb-4">Importar Resultado (PDF)</h2>
                 
                 <?php if (!empty($selectedItem['arquivo_hex'])): ?>
                     <div class="alert alert-info shadow-lg mb-4">
                         <div>
                            <i class="bi bi-file-earmark-pdf text-2xl"></i>
                            <div>
                                <h3 class="font-bold">Arquivo Enviado</h3>
                                <a href="public/uploads/resultados/<?= $selectedItem['arquivo_hex'] ?>.pdf" target="_blank" class="link link-hover">Visualizar PDF Atual</a>
                            </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <form action="index.php?r=resultados/upload_pdf_resultado" method="POST" enctype="multipart/form-data" class="bg-base-200 p-6 rounded-box">
                     <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                     <input type="hidden" name="item_id" value="<?= $selectedItem['id_amostra'] ?>">
                     
                     <div class="form-control w-full max-w-md mb-4">
                        <label class="label">
                            <span class="label-text font-semibold">Selecione o arquivo PDF do laboratório de apoio</span>
                        </label>
                        <input type="file" name="resultado_pdf" accept="application/pdf" class="file-input file-input-bordered w-full" required />
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Apenas arquivos .pdf são permitidos</span>
                        </label>
                     </div>
                     
                     <div class="flex gap-2">
                         <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Salvar e Liberar
                         </button>
                     </div>
                 </form>
            </div>

            <script>
                function switchTab(mode) {
                    // Update Tabs
                    document.getElementById('tab-digitacao-btn').classList.toggle('tab-active', mode === 'digitacao');
                    document.getElementById('tab-arquivo-btn').classList.toggle('tab-active', mode === 'arquivo');
                    
                    // Update Content
                    document.getElementById('tab-digitacao-content').classList.toggle('hidden', mode !== 'digitacao');
                    document.getElementById('tab-arquivo-content').classList.toggle('hidden', mode !== 'arquivo');
                }
                
                <?php if (!empty($selectedItem['arquivo_hex'])): ?>
                    // Switch to file tab if file exists
                    switchTab('arquivo');
                <?php endif; ?>
            </script>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                <i class="bi bi-file-medical text-6xl mb-4"></i>
                <p class="text-lg">Selecione um exame ao lado para lançar resultados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
