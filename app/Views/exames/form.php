<div class="container mx-auto p-4">
    <style>
        .tabela-secao th {
            position: relative;
        }
        .col-resizer {
            position: absolute;
            top: 0;
            right: 0;
            width: 8px; /* Increased width for better grab area */
            height: 100%;
            cursor: col-resize;
            user-select: none;
            background: transparent;
            z-index: 10;
        }
        .col-resizer:hover, .col-resizer.resizing {
            background: rgba(59, 130, 246, 0.5); /* Blue highlight with opacity */
        }
        
        /* Context Menu Styles */
        #contextMenu, #colContextMenu {
            display: none;
            position: absolute;
            z-index: 1000;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            min-width: 150px;
        }
        
        #contextMenu ul, #colContextMenu ul {
            list-style: none;
            margin: 0;
            padding: 4px 0;
        }
        
        #contextMenu li, #colContextMenu li {
            padding: 8px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #374151;
            font-size: 0.875rem;
        }
        
        #contextMenu li:hover, #colContextMenu li:hover {
            background-color: #f3f4f6;
            color: #111827;
        }
        
        #contextMenu li i, #colContextMenu li i {
            font-size: 1rem;
        }
        
        #contextMenu .divider, #colContextMenu .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 4px 0;
            padding: 0;
        }
        
        .border-top-thick-dashed {
            border-top: 2px dashed #000 !important;
        }
        .border-bottom-thick-dashed {
            border-bottom: 2px dashed #000 !important;
        }
        
        /* Destaque para seção ativa */
        .secao-ativa {
            box-shadow: 0 0 0 2px #3b82f6 !important; /* Borda azul externa */
            z-index: 10 !important;
        }

        /* Custom Context Menu */
        .custom-ctx-menu {
            min-width: 240px;
        }
        .custom-ctx-menu .dropdown-item {
            padding: 8px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .custom-ctx-menu .dropdown-header {
            padding: 8px 20px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        .custom-ctx-menu .bi {
            font-size: 1.1em;
            color: #6b7280;
        }
        .custom-ctx-menu .dropdown-item:hover .bi {
            color: inherit;
        }
    </style>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-base-content">
            <?= $editando ? 'Editar Exame' : 'Novo Exame' ?>
        </h1>
        <a href="index.php?r=exames" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-2"></i> Voltar
        </a>

    </div>

    <?php if ($editando): ?>
    <div class="tabs tabs-boxed mb-4 bg-base-100">
        <a class="tab tab-active" data-tab="geral" onclick="switchTab('geral')">Geral</a>
        <a class="tab" data-tab="laudo" onclick="switchTab('laudo')">Configuração do Laudo</a>

    </div>
    <?php endif; ?>

    <!-- ABA GERAL -->
    <div id="tab-geral" class="tab-content block">
        <form id="formExame" action="index.php?r=exames/store" method="POST" class="bg-base-100 p-6 rounded-lg shadow-sm">
            <input type="hidden" name="id" value="<?= $exame['id'] ?>">
            <input type="hidden" name="form_origem" value="exame">
            
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                <div class="md:col-span-2">
                    <label class="label"><span class="label-text">ID</span></label>
                    <input type="text" class="input input-bordered input-sm w-full bg-base-200" value="<?= $exame['id'] ?>" disabled>
                </div>
                <div class="md:col-span-3">
                    <label class="label"><span class="label-text">Mnemônico <span class="text-error">*</span></span></label>
                    <input type="text" name="mnemonico" id="mnemonico" class="input input-bordered input-sm w-full" 
                           value="<?= htmlspecialchars($exame['mnemonico']) ?>" <?= $editando ? 'readonly' : 'required' ?>>
                    <span id="mnemonico_feedback" class="text-xs text-error mt-1 hidden"></span>
                </div>
                <div class="md:col-span-5">
                    <label class="label"><span class="label-text">Nome do Exame <span class="text-error">*</span></span></label>
                    <input type="text" name="nome" class="input input-bordered input-sm w-full" 
                           value="<?= htmlspecialchars($exame['nome']) ?>" required>
                </div>
                <div class="md:col-span-2 flex items-center pt-8">
                    <label class="cursor-pointer label">
                        <span class="label-text mr-3">Ativo</span>
                        <input type="checkbox" name="ativo" value="1" class="toggle toggle-primary" <?= $exame['ativo'] ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                <div class="md:col-span-6">
                    <label class="label"><span class="label-text">Sinonímia</span></label>
                    <input type="text" name="sinonimia" class="input input-bordered input-sm w-full" 
                           value="<?= htmlspecialchars($exame['sinonimia'] ?? '') ?>">
                </div>
                <div class="md:col-span-4">
                    <label class="label"><span class="label-text">Método</span></label>
                    <input type="text" name="metodo" class="input input-bordered input-sm w-full" 
                           value="<?= htmlspecialchars($exame['metodo'] ?? '') ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="label"><span class="label-text">Prazo Local (dias)</span></label>
                    <input type="number" name="prazo_execucao_local" class="input input-bordered input-sm w-full" min="0" 
                           value="<?= htmlspecialchars($exame['prazo_execucao_local'] ?? '') ?>">
                </div>
            </div>

            <div class="divider">Configurações Técnicas</div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="label"><span class="label-text">Setor <span class="text-error">*</span></span></label>
                    <select name="setor_id" id="setor_id" class="select select-bordered select-sm w-full" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $exame['setor_id'] == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['setor_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__novo_setor__" class="font-bold text-primary">+ Novo Setor</option>
                    </select>
                </div>
                <div>
                    <label class="label"><span class="label-text">Material Biológico <span class="text-error">*</span></span></label>
                    <select name="material_biologico_id" id="material_biologico_id" class="select select-bordered select-sm w-full" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($materiais as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $exame['material_biologico_id'] == $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['material_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__novo_material__" class="font-bold text-primary">+ Novo Material</option>
                    </select>
                </div>
                <div>
                    <label class="label"><span class="label-text">Recipiente <span class="text-error">*</span></span></label>
                    <select name="recipiente_id" id="recipiente_id" class="select select-bordered select-sm w-full" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($recipientes as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $exame['recipiente_id'] == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['recipiente_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__novo_recipiente__" class="font-bold text-primary">+ Novo Recipiente</option>
                    </select>
                </div>
                <div>
                    <label class="label"><span class="label-text">Laboratório Padrão <span class="text-error">*</span></span></label>
                    <select name="laboratorio_id" class="select select-bordered select-sm w-full" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($laboratorios as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $exame['laboratorio_id'] == $l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['laboratorio_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i> Salvar Geral
                </button>
            </div>
        </form>

        <?php if ($editando): ?>
            <div class="mt-8 bg-base-100 p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-bold mb-4">Laboratórios de Apoio</h3>
                
                <form action="index.php?r=exames/store" method="POST" class="mb-6 bg-base-200 p-4 rounded-lg">
                    <input type="hidden" name="id" value="<?= $exame['id'] ?>"> <!-- Keep main ID for redirect -->
                    <input type="hidden" name="exame_id" value="<?= $exame['id'] ?>">
                    <input type="hidden" name="form_origem" value="apoio">
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                        <div class="md:col-span-3">
                            <label class="label"><span class="label-text-alt">Laboratório</span></label>
                            <select name="laboratorio_id" class="select select-bordered select-xs w-full" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($laboratorios as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['laboratorio_nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="label"><span class="label-text-alt">Mnemônico Apoio</span></label>
                            <input type="text" name="mnemonico_apoio" class="input input-bordered input-xs w-full" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="label"><span class="label-text-alt">Custo (R$)</span></label>
                            <input type="text" name="custo_apoio" class="input input-bordered input-xs w-full" placeholder="0,00" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="label"><span class="label-text-alt">Prazo Logística</span></label>
                            <input type="number" name="prazo_logistica_apoio" class="input input-bordered input-xs w-full" value="0">
                        </div>
                        <div class="md:col-span-2">
                            <label class="label"><span class="label-text-alt">Prazo Execução</span></label>
                            <input type="number" name="prazo_execucao_apoio" class="input input-bordered input-xs w-full" value="0">
                        </div>
                        <div class="md:col-span-1">
                            <button type="submit" class="btn btn-success btn-xs w-full text-white">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-compact w-full">
                        <thead>
                            <tr>
                                <th>Laboratório</th>
                                <th>Mnemônico</th>
                                <th>Custo</th>
                                <th>Prazos (L+E=T)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($apoios)): ?>
                                <tr><td colspan="5" class="text-center text-sm text-gray-500 py-4">Nenhum laboratório de apoio vinculado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($apoios as $ap): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ap['laboratorio_nome']) ?></td>
                                        <td><?= htmlspecialchars($ap['mnemonico_apoio']) ?></td>
                                        <td>R$ <?= number_format($ap['custo_apoio'], 2, ',', '.') ?></td>
                                        <td><?= $ap['prazo_logistica'] ?> + <?= $ap['prazo_execucao'] ?> = <?= $ap['prazo_logistica'] + $ap['prazo_execucao'] ?> dias</td>
                                        <td>
                                            <a href="index.php?r=exames/delete_apoio&id=<?= $ap['id'] ?>&exame_id=<?= $exame['id'] ?>" 
                                               class="btn btn-ghost btn-xs text-error" onclick="return confirm('Remover apoio?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="divider"></div>
                <div class="flex justify-end gap-2">
                    <button type="submit" form="formExame" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Salvar Geral
                    </button>
                </div>
            </div>

            <div class="mt-8 bg-base-100 p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-bold mb-4">Preços por Convênio</h3>
                
                <form action="index.php?r=exames/store" method="POST" class="mb-6 bg-base-200 p-4 rounded-lg">
                    <input type="hidden" name="id" value="<?= $exame['id'] ?>">
                    <input type="hidden" name="exame_id" value="<?= $exame['id'] ?>">
                    <input type="hidden" name="form_origem" value="convenio_preco">
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                        <div class="md:col-span-4">
                            <label class="label"><span class="label-text-alt">Convênio</span></label>
                            <select name="convenio_id" class="select select-bordered select-xs w-full" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($convenios as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="label"><span class="label-text-alt">Valor (R$)</span></label>
                            <input type="text" name="valor_convenio" class="input input-bordered input-xs w-full" placeholder="0,00" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="cursor-pointer label">
                                <span class="label-text-alt mr-2">Ativo</span>
                                <input type="checkbox" name="ativo_preco" value="1" class="checkbox checkbox-xs" checked>
                            </label>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="btn btn-success btn-xs w-full text-white">
                                <i class="bi bi-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-compact w-full">
                        <thead>
                            <tr>
                                <th>Convênio</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($precos_convenios)): ?>
                                <tr><td colspan="4" class="text-center text-sm text-gray-500 py-4">Nenhum preço configurado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($precos_convenios as $pc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pc['convenio_nome']) ?></td>
                                        <td>R$ <?= number_format($pc['valor'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php if ($pc['ativo']): ?>
                                                <span class="badge badge-success badge-xs">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge badge-ghost badge-xs">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="index.php?r=exames/delete_preco&id=<?= $pc['id'] ?>&exame_id=<?= $exame['id'] ?>" 
                                               class="btn btn-ghost btn-xs text-error" onclick="return confirm('Remover preço?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <div class="divider"></div>
        <div class="flex justify-end gap-2 mt-4">
            <button type="submit" form="formExame" class="btn btn-primary">
                <i class="bi bi-save me-2"></i> Salvar Geral
            </button>
        </div>
    </div>
    <div id="tab-laudo" class="tab-content hidden">
        <?php if (!$editando): ?>
            <div class="alert alert-info shadow-lg">
                <div>
                    <i class="bi bi-info-circle"></i>
                    <span>Salve o exame primeiro para configurar o layout do laudo.</span>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-base-100 p-4 rounded-lg shadow-sm border border-base-200">
                <!-- ======= ABA: CONFIGURAÇÃO DO LAUDO ======= -->

                <!-- Toolbar -->
                <div class="laudo-toolbar d-flex flex-nowrap gap-1 mb-2 overflow-auto align-items-center">
                  <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddSecaoLaudo" title="Incluir Seção">
                    <i class="bi bi-plus-lg"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" id="btnExcluirSecaoLaudo" title="Excluir Seção">
                    <i class="bi bi-x-lg"></i>
                  </button>

                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddLinhaLaudo" title="Adicionar Linha">
                    <i class="bi bi-plus-square"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddColunaLaudo" title="Adicionar Coluna">
                    <i class="bi bi-layout-three-columns"></i>
                  </button>

                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnMoverSecaoUp" title="Mover para Cima">
                    <i class="bi bi-arrow-up"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnMoverSecaoDown" title="Mover para Baixo">
                    <i class="bi bi-arrow-down"></i>
                  </button>

                  <div class="border-end mx-1"></div>

                  <button type="button" class="btn btn-sm btn-outline-dark" id="btnCopiarConfig" title="Copiar Config">
                    <i class="bi bi-clipboard"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-dark" id="btnCopiarSecao" title="Copiar Seção">
                    <i class="bi bi-clipboard-plus"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-dark" id="btnColarConfig" title="Colar">
                    <i class="bi bi-clipboard-check"></i>
                  </button>

                  <button type="button" class="btn btn-sm btn-outline-info" id="btnImportarConfig" title="Importar">
                    <i class="bi bi-upload"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-info" id="btnCarregarConfig" title="Exportar">
                    <i class="bi bi-download"></i>
                  </button>

                  <button type="button" class="btn btn-sm btn-outline-success" id="btnVisualizarLaudo" title="Preview">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-success" id="btnVisualizarImpressao" title="Imprimir">
                    <i class="bi bi-printer"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-success" id="btnSalvarLayoutServer" title="Salvar Layout">
                    <i class="bi bi-save"></i>
                  </button>
                </div>

                <!-- Área do editor -->
                <div class="editor-canvas">
                    <div id="areaLayoutLaudo-v2"></div>
                </div>

                <!-- Inputs hidden que serão preenchidos no submit do form -->
                <form method="post" action="index.php?r=exames/save_layout" id="formLayoutLaudo">
                    <input type="hidden" name="form_origem" value="layout_editor">
                    <input type="hidden" name="exame_id" value="<?= $exame['id'] ?>">
                    <input type="hidden" id="layout_json" name="layout_json" value="">
                    <input type="hidden" id="colunas_cfg_json" name="colunas_cfg_json" value="">
                    <input type="hidden" id="celulas_cfg_json" name="celulas_cfg_json" value="">
                </form>

                <!-- Upload hidden para importar json -->
                <input type="file" id="fileConfigLaudo" accept="application/json" style="display:none">

                <!-- Valores dinâmicos do exame (para colunas dinâmicas) -->
                <input type="hidden" id="exame_nome_val" value="<?= htmlspecialchars($exame['nome'] ?? '') ?>">
                <input type="hidden" id="exame_mnemonico_val" value="<?= htmlspecialchars($exame['mnemonico'] ?? '') ?>">
                <input type="hidden" id="exame_metodo_val" value="<?= htmlspecialchars($exame['metodo'] ?? '') ?>">
                <input type="hidden" id="exame_prazo_val" value="<?= htmlspecialchars($exame['prazo_execucao_local'] ?? '') ?>">
                <input type="hidden" id="exame_material_biologico_val" value="<?= htmlspecialchars($exame['material_biologico_nome'] ?? '') ?>">
                <input type="hidden" id="exame_material_biologico_id_val" value="<?= htmlspecialchars($exame['material_biologico_id'] ?? '') ?>">
            </div>
        <?php endif; ?>
    </div>


</div>

<!-- Modais Auxiliares (Setor, Material, Recipiente) -->
<dialog id="modal_novo_setor" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Novo Setor</h3>
        <form id="formNovoSetor" method="POST" action="index.php?r=exames/store" class="ajax-form" data-target="setor_id">
            <input type="hidden" name="form_origem" value="novo_setor">
            <div class="form-control w-full mt-4">
                <label class="label"><span class="label-text">Nome do Setor</span></label>
                <input type="text" name="setor_nome" class="input input-bordered w-full" required>
            </div>
            <div class="modal-action">
                <button type="button" class="btn" onclick="modal_novo_setor.close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog id="modal_novo_material" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Novo Material Biológico</h3>
        <form id="formNovoMaterial" method="POST" action="index.php?r=exames/store" class="ajax-form" data-target="material_biologico_id">
            <input type="hidden" name="form_origem" value="novo_material">
            <div class="form-control w-full mt-4">
                <label class="label"><span class="label-text">Nome do Material</span></label>
                <input type="text" name="material_nome" class="input input-bordered w-full" required>
            </div>
            <div class="modal-action">
                <button type="button" class="btn" onclick="modal_novo_material.close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog id="modal_novo_recipiente" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Novo Recipiente</h3>
        <form id="formNovoRecipiente" method="POST" action="index.php?r=exames/store" class="ajax-form" data-target="recipiente_id">
            <input type="hidden" name="form_origem" value="novo_recipiente">
            <div class="form-control w-full mt-4">
                <label class="label"><span class="label-text">Nome do Recipiente</span></label>
                <input type="text" name="recipiente_nome" class="input input-bordered w-full" required>
            </div>
            <div class="modal-action">
                <button type="button" class="btn" onclick="modal_novo_recipiente.close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Config Coluna (Full Version) -->
<dialog id="modal_config_coluna" class="modal">
    <div class="modal-box w-11/12 max-w-4xl">
        <h3 class="font-bold text-lg mb-4">Configurar Coluna</h3>
        <form id="formConfigColuna">
            <input type="hidden" id="cfg_secao_id">
            <input type="hidden" id="cfg_row_idx">
            <input type="hidden" id="cfg_coluna_idx">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Tipo de Dado</span></label>
                        <select id="cfg_tipo" class="select select-bordered w-full select-sm">
                            <optgroup label="Ligado ao Banco de Dados">
                                <option value="exame_nome">Nome do Exame</option>
                                <option value="exame_mnemonico">Mnemônico</option>
                                <option value="material_biologico">Material Biológico</option>
                                <option value="exame_metodo">Método</option>
                                <option value="exame_prazo_local">Prazo Local</option>
                                <option value="resultado_num">Resultado Numérico</option>
                                <option value="calculo">Cálculo (Fórmula)</option>
                                <option value="resultado_texto">Resultado Texto</option>
                                <option value="resultado_texto_formatado">Resultado Texto Formatado</option>
                                <option value="unidade">Unidade</option>
                                <option value="nome_laboratorio">Nome Laboratório</option>
                                <option value="data_coleta">Data Coleta</option>
                                <option value="data_liberacao">Data Liberação</option>
                            </optgroup>
                            <optgroup label="Texto Fixo">
                                <option value="">Texto livre (Digitando)</option>
                                <option value="descricao">Descrição</option>
                                <option value="valor_referencia">Valor de referência</option>
                                <option value="observacao_resultado">Observação</option>
                            </optgroup>
                        </select>
                        <label class="label"><span class="label-text-alt text-gray-500">Define o conteúdo automático desta coluna.</span></label>
                    </div>

                    <div id="cfg_opts_numero" class="hidden border rounded p-2 bg-base-200">
                        <span class="label-text font-bold text-xs mb-2 block">Configuração Numérica</span>
                        <div class="grid grid-cols-2 gap-2">
                             <div class="form-control">
                                <label class="label py-0"><span class="label-text-alt">Digitos (Int)</span></label>
                                <input type="number" id="cfg_num_pos" class="input input-bordered input-xs w-full" min="0" max="12">
                            </div>
                            <div class="form-control">
                                <label class="label py-0"><span class="label-text-alt">Decimais</span></label>
                                <input type="number" id="cfg_num_dec" class="input input-bordered input-xs w-full" min="0" max="12">
                            </div>
                        </div>
                    </div>
                    
                    <div id="cfg_opts_calculo" class="hidden border rounded p-2 bg-base-200 mt-2">
                        <span class="label-text font-bold text-xs mb-2 block">Fórmula de Cálculo</span>
                        <div class="form-control">
                            <label class="label py-0"><span class="label-text-alt">Expressão</span></label>
                        <input type="text" id="cfg_num_formula" class="input input-bordered input-xs w-full" placeholder="Ex: @VAR1 + @VAR2">
                    </div>
                </div>

                <div class="form-control mt-2" id="cfg_alias_container">
                    <label class="label py-0"><span class="label-text-alt">Alias da Variável</span></label>
                    <input type="text" id="cfg_var_alias" class="input input-bordered input-xs w-full" placeholder="Nome interno da variável">
                </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Título da Coluna</span></label>
                        <input type="text" id="cfg_titulo" class="input input-bordered w-full input-sm" placeholder="Opcional">
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Largura (%)</span></label>
                        <input type="number" id="cfg_largura" class="input input-bordered w-full input-sm" min="1" max="100">
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Fonte & Estilo</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <select id="cfg_font_family" class="select select-bordered select-sm w-full">
                                <option value="">Padrão</option>
                                <option value="Arial">Arial</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                            </select>
                            <input type="number" id="cfg_font_size" class="input input-bordered input-sm w-full" placeholder="Tam. (pt)" min="6" max="72">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 items-center">
                        <div class="form-control w-full">
                             <label class="label"><span class="label-text-alt">Cor Texto</span></label>
                             <input type="color" id="cfg_font_color" class="input input-bordered input-sm w-full h-10 p-1">
                        </div>
                        <div class="flex gap-2 justify-end">
                            <label class="btn btn-sm btn-ghost btn-square" title="Negrito">
                                <input type="checkbox" id="cfg_bold" class="hidden peer">
                                <i class="bi bi-type-bold peer-checked:text-primary text-xl"></i>
                            </label>
                            <label class="btn btn-sm btn-ghost btn-square" title="Itálico">
                                <input type="checkbox" id="cfg_italic" class="hidden peer">
                                <i class="bi bi-type-italic peer-checked:text-primary text-xl"></i>
                            </label>
                            <label class="btn btn-sm btn-ghost btn-square" title="Sublinhado">
                                <input type="checkbox" id="cfg_underline" class="hidden peer">
                                <i class="bi bi-type-underline peer-checked:text-primary text-xl"></i>
                            </label>
                        </div>
                    </div>

                    <div class="divider my-2"></div>

                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Alinhamento</span></label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="label-text-alt block mb-1">Horizontal</span>
                                <div class="btn-group btn-group-sm">
                                    <input type="radio" name="align_h" value="left" class="btn" aria-label="Esq" checked />
                                    <input type="radio" name="align_h" value="center" class="btn" aria-label="Cen" />
                                    <input type="radio" name="align_h" value="right" class="btn" aria-label="Dir" />
                                    <input type="radio" name="align_h" value="justify" class="btn" aria-label="Jus" />
                                </div>
                            </div>
                            <div>
                                <span class="label-text-alt block mb-1">Vertical</span>
                                <div class="btn-group btn-group-sm">
                                    <input type="radio" name="align_v" value="top" class="btn" aria-label="Topo" />
                                    <input type="radio" name="align_v" value="middle" class="btn" aria-label="Meio" checked />
                                    <input type="radio" name="align_v" value="bottom" class="btn" aria-label="Base" />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-control mt-4">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="cfg_single_line" class="checkbox checkbox-sm">
                            <span class="label-text">Limitar a uma linha (Single Line)</span>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="cfg_custom_font" class="checkbox checkbox-sm">
                            <span class="label-text">Usar fonte personalizada</span>
                        </label>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="modal-action flex justify-between">
             <button type="button" class="btn btn-error btn-outline" id="btnRemoveColumn">
                <i class="bi bi-trash"></i> Remover Coluna
            </button>
            <div class="flex gap-2">
                <button type="button" class="btn" onclick="modal_config_coluna.close()">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarConfigColuna">
                    <i class="bi bi-check-lg"></i> Aplicar
                </button>
            </div>
        </div>
    </div>
</dialog>

<!-- Modal Config Célula (Cell Config) -->
<dialog id="modal_config_celula" class="modal">
    <div class="modal-box w-11/12 max-w-4xl">
        <h3 class="font-bold text-lg mb-4">Configurar Célula</h3>
        <form id="formConfigCelula">
            <input type="hidden" id="cel_secao_id">
            <input type="hidden" id="cel_row_idx">
            <input type="hidden" id="cel_col_idx">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Conteúdo Texto</span></label>
                        <textarea id="cel_valor" class="textarea textarea-bordered w-full" placeholder="Valor da célula"></textarea>
                    </div>

                    <div class="divider">Variável / Numérico</div>

                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Tipo Numérico</span></label>
                        <select id="cel_num_tipo" class="select select-bordered w-full select-sm">
                            <option value="">Texto (Padrão)</option>
                            <option value="numero">Numérico (Digitável)</option>
                            <option value="calculo">Cálculo (Fórmula)</option>
                        </select>
                    </div>

                    <div id="cel_opts_numero" class="grid grid-cols-2 gap-4 hidden">
                         <div class="form-control">
                            <label class="label"><span class="label-text-alt">Digitos (Int)</span></label>
                            <input type="number" id="cel_num_pos" class="input input-bordered input-sm w-full" min="0" max="12">
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text-alt">Decimais</span></label>
                            <input type="number" id="cel_num_dec" class="input input-bordered input-sm w-full" min="0" max="12">
                        </div>
                    </div>

                    <div id="cel_opts_calculo" class="form-control hidden">
                        <label class="label"><span class="label-text-alt">Fórmula</span></label>
                        <input type="text" id="cel_num_formula" class="input input-bordered input-sm w-full" placeholder="Ex: @VAR1 + @VAR2">
                        <label class="label"><span class="label-text-alt text-gray-500">Use Alias de variáveis</span></label>
                        <div id="cel_formula_legend" class="mt-2 rounded border border-base-300 bg-base-200 p-3 text-sm hidden">
                            <div class="font-bold mb-2">Legenda</div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <div class="text-success font-semibold">Operações Numéricas</div>
                                    <div>+ Adição</div>
                                    <div>- Subtração</div>
                                    <div>* Multiplicação</div>
                                    <div>/ Divisão</div>
                                </div>
                                <div>
                                    <div class="text-primary font-semibold">Variáveis</div>
                                    <div>Use [ALIAS] para referenciar valores</div>
                                    <div>Ex.: [HEMATOCRITO]/[HEMACIAS]*10</div>
                                </div>
                                <div>
                                    <div class="text-info font-semibold">Parênteses</div>
                                    <div>Ex.: ([A]+[B])/[C]</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Alias da Variável</span></label>
                        <input type="text" id="cel_var_alias" class="input input-bordered input-sm w-full" placeholder="Ex: HEMACIAS">
                        <label class="label"><span class="label-text-alt text-gray-500">Para uso em fórmulas</span></label>
                    </div>
                </div>

                <!-- Right Column (Styling) -->
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Fonte & Estilo (Célula)</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <select id="cel_font_family" class="select select-bordered select-sm w-full">
                                <option value="">Herdar Coluna</option>
                                <option value="Arial">Arial</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                            </select>
                            <input type="number" id="cel_font_size" class="input input-bordered input-sm w-full" placeholder="Tam. (pt)" min="6" max="72">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 items-center">
                        <div class="form-control w-full">
                             <label class="label"><span class="label-text-alt">Cor Texto</span></label>
                             <input type="color" id="cel_font_color" class="input input-bordered input-sm w-full h-10 p-1">
                        </div>
                        <div class="flex gap-2 justify-end">
                            <label class="btn btn-sm btn-ghost btn-square" title="Negrito">
                                <input type="checkbox" id="cel_bold" class="hidden peer">
                                <i class="bi bi-type-bold peer-checked:text-primary text-xl"></i>
                            </label>
                            <label class="btn btn-sm btn-ghost btn-square" title="Itálico">
                                <input type="checkbox" id="cel_italic" class="hidden peer">
                                <i class="bi bi-type-italic peer-checked:text-primary text-xl"></i>
                            </label>
                            <label class="btn btn-sm btn-ghost btn-square" title="Sublinhado">
                                <input type="checkbox" id="cel_underline" class="hidden peer">
                                <i class="bi bi-type-underline peer-checked:text-primary text-xl"></i>
                            </label>
                        </div>
                    </div>

                    <div class="divider my-2"></div>

                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Alinhamento</span></label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="label-text-alt block mb-1">Horizontal</span>
                                <div class="btn-group btn-group-sm">
                                    <input type="radio" name="cel_align_h" value="" class="btn" aria-label="Auto" checked />
                                    <input type="radio" name="cel_align_h" value="left" class="btn" aria-label="Esq" />
                                    <input type="radio" name="cel_align_h" value="center" class="btn" aria-label="Cen" />
                                    <input type="radio" name="cel_align_h" value="right" class="btn" aria-label="Dir" />
                                </div>
                            </div>
                            <div>
                                <span class="label-text-alt block mb-1">Vertical</span>
                                <div class="btn-group btn-group-sm">
                                    <input type="radio" name="cel_align_v" value="" class="btn" aria-label="Auto" checked />
                                    <input type="radio" name="cel_align_v" value="top" class="btn" aria-label="Topo" />
                                    <input type="radio" name="cel_align_v" value="middle" class="btn" aria-label="Meio" />
                                    <input type="radio" name="cel_align_v" value="bottom" class="btn" aria-label="Base" />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-control mt-4">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="cel_uppercase" class="checkbox checkbox-sm">
                            <span class="label-text">MAIÚSCULAS</span>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="cel_single_line" class="checkbox checkbox-sm">
                            <span class="label-text">Linha única (Single Line)</span>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" id="cel_custom_font" class="checkbox checkbox-sm">
                            <span class="label-text">Forçar fonte personalizada</span>
                        </label>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="modal-action">
            <button type="button" class="btn" onclick="modal_config_celula.close()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnSalvarConfigCelula">
                <i class="bi bi-check-lg"></i> Aplicar
            </button>
        </div>
    </div>
</dialog>

<!-- Modal Config Linha -->
<dialog id="modal_config_linha" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Configurar Linha</h3>
        <form id="formConfigLinha">
            <input type="hidden" id="lin_secao_id">
            <input type="hidden" id="lin_row_idx">
            
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Altura da Linha (px)</span></label>
                <input type="number" id="lin_altura" class="input input-bordered w-full" placeholder="Automático" min="0">
            </div>
            
            <div class="divider my-2">Opções de Impressão</div>
            
            <div class="form-control">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" id="lin_quebra_antes" class="checkbox">
                    <span class="label-text">Quebra de página ANTES desta linha</span>
                </label>
            </div>
            
            <div class="form-control">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" id="lin_quebra_depois" class="checkbox">
                    <span class="label-text">Quebra de página DEPOIS desta linha</span>
                </label>
            </div>
            
             <div class="form-control">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" id="lin_ocultar_vazia" class="checkbox">
                    <span class="label-text">Ocultar na impressão se estiver vazia</span>
                </label>
            </div>
        </form>
        
        <div class="modal-action">
            <button type="button" class="btn" onclick="modal_config_linha.close()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnSalvarConfigLinha">
                <i class="bi bi-check-lg"></i> Aplicar
            </button>
        </div>
    </div>
</dialog>

<!-- Modal Resultado Texto -->
<dialog id="modal_config_resultado_texto" class="modal">
    <div class="modal-box w-11/12 max-w-5xl">
        <h3 class="font-bold text-lg mb-4">
            <i class="bi bi-text-paragraph"></i> Resultado Texto
        </h3>
        
        <input type="hidden" id="rt_secao_id">
        <input type="hidden" id="rt_row_idx">
        <input type="hidden" id="rt_col_idx">

        <div class="flex flex-col md:flex-row gap-6">
            <!-- Coluna Esquerda: Configurações Gerais -->
            <div class="w-full md:w-1/3 flex flex-col gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Tipo</span></label>
                    <select id="rt_tipo_modal" class="select select-bordered w-full">
                        <option value="cadastrado">Texto cadastrado</option>
                        <option value="livre">Texto livre</option>
                        <option value="sem">Sem digitação</option>
                    </select>
                </div>
                
                <div class="divider"></div>

                <!-- Configurações de Estilo (Copiado de Celula) -->
                <div class="collapse collapse-arrow border border-base-300 bg-base-100 rounded-box mb-2">
                    <input type="checkbox" /> 
                    <div class="collapse-title text-sm font-medium">
                        Aparência e Fonte
                    </div>
                    <div class="collapse-content text-sm">
                        <div class="form-control w-full mb-2">
                            <label class="label py-1"><span class="label-text-alt">Fonte</span></label>
                            <select id="rt_font_family" class="select select-bordered select-xs w-full">
                                <option value="">Padrão</option>
                                <option value="Arial">Arial</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Verdana">Verdana</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div class="form-control w-full">
                                <label class="label py-1"><span class="label-text-alt">Tam. (px)</span></label>
                                <input type="number" id="rt_font_size" class="input input-bordered input-xs w-full" placeholder="Ex: 12">
                            </div>
                            <div class="form-control w-full">
                                <label class="label py-1"><span class="label-text-alt">Cor</span></label>
                                <input type="color" id="rt_font_color" class="input input-bordered input-xs w-full h-6 p-0">
                            </div>
                        </div>
                        <div class="flex justify-between mb-2 border rounded p-1">
                            <label class="cursor-pointer label p-1"><span class="label-text-alt mr-1">N</span> <input type="checkbox" id="rt_bold" class="checkbox checkbox-xs" /></label>
                            <label class="cursor-pointer label p-1"><span class="label-text-alt mr-1">I</span> <input type="checkbox" id="rt_italic" class="checkbox checkbox-xs" /></label>
                            <label class="cursor-pointer label p-1"><span class="label-text-alt mr-1">S</span> <input type="checkbox" id="rt_underline" class="checkbox checkbox-xs" /></label>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div class="form-control">
                                 <span class="label-text-alt block mb-1">Alinhamento H</span>
                                 <div class="btn-group btn-group-xs w-full flex">
                                    <input type="radio" name="rt_align_h" value="" class="btn flex-1" aria-label="A" checked />
                                    <input type="radio" name="rt_align_h" value="left" class="btn flex-1" aria-label="E" />
                                    <input type="radio" name="rt_align_h" value="center" class="btn flex-1" aria-label="C" />
                                    <input type="radio" name="rt_align_h" value="right" class="btn flex-1" aria-label="D" />
                                </div>
                            </div>
                            <div class="form-control">
                                 <span class="label-text-alt block mb-1">Alinhamento V</span>
                                 <div class="btn-group btn-group-xs w-full flex">
                                    <input type="radio" name="rt_align_v" value="" class="btn flex-1" aria-label="A" checked />
                                    <input type="radio" name="rt_align_v" value="top" class="btn flex-1" aria-label="T" />
                                    <input type="radio" name="rt_align_v" value="middle" class="btn flex-1" aria-label="M" />
                                    <input type="radio" name="rt_align_v" value="bottom" class="btn flex-1" aria-label="B" />
                                </div>
                            </div>
                        </div>

                         <div class="form-control">
                             <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="checkbox" id="rt_custom_font" class="checkbox checkbox-xs">
                                <span class="label-text-alt">Forçar fonte</span>
                            </label>
                             <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="checkbox" id="rt_uppercase" class="checkbox checkbox-xs">
                                <span class="label-text-alt">Maiúsculas</span>
                            </label>
                             <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="checkbox" id="rt_single_line" class="checkbox checkbox-xs">
                                <span class="label-text-alt">Linha única</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Gerenciamento de Textos -->
            <div class="w-full md:w-2/3 border rounded-lg p-4 bg-base-100 flex flex-col gap-3">
                
                <div class="flex gap-2">
                     <button type="button" class="btn btn-sm btn-success text-white" id="btn_rt_add">
                        <i class="bi bi-plus-lg"></i> Novo
                    </button>
                     <button type="button" class="btn btn-sm btn-outline-danger" id="btn_rt_excluir" disabled>
                        <i class="bi bi-trash"></i> Excluir
                    </button>
                     <button type="button" class="btn btn-sm btn-outline-primary" id="btn_rt_padrao" disabled>
                        <i class="bi bi-check-square"></i> Padrão
                    </button>
                     <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_rt_sem_padrao" disabled>
                        <i class="bi bi-square"></i> Sem Padrão
                    </button>
                </div>

                <div class="overflow-y-auto border rounded" style="height: 300px;">
                    <table class="table table-compact w-full" id="tabela_rt_textos">
                        <thead class="sticky top-0 bg-base-200">
                            <tr>
                                <th class="w-10"></th> <!-- Checkbox/Radio selection -->
                                <th class="w-24">Cód.</th>
                                <th>Texto</th>
                                <th class="w-16 text-center">Padrão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal-action">
            <button type="button" class="btn" onclick="modal_config_resultado_texto.close()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnSalvarConfigRT">
                <i class="bi bi-check-lg"></i> Confirmar
            </button>
        </div>
    </div>
</dialog>



<!-- Styles required for the Layout Editor to work (adapted from legacy) -->
<style>
    .secao-bloco {
        display: flex;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        margin-bottom: 8px;
        position: relative;
        overflow: hidden;
    }
    .secao-bloco.selecionada {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    .secao-sidebar {
        width: 40px;
        background-color: #f3f4f6;
        border-right: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        cursor: pointer;
        color: #6b7280;
        flex-shrink: 0;
    }
    .secao-sidebar:hover {
        background-color: #e5e7eb;
        color: #374151;
    }
    .secao-content {
        flex-grow: 1;
        overflow-x: auto;
    }
    .tabela-secao {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-family: inherit; /* Inherit A4 font */
    }
    .tabela-secao th {
        background-color: #f9fafb;
        font-size: 11pt; /* Match Print/Preview size */
        padding: 1px 2px; /* Match Print/Preview padding */
        text-align: center;
        border: 1px solid #e5e7eb;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-weight: bold;
    }
    .tabela-secao td {
        border: 1px solid #e5e7eb;
        padding: 1px 2px; /* Match Print/Preview padding */
        min-height: 24px;
        font-size: 11pt; /* Match Print/Preview size */
        line-height: 1.1;
    }
    .tabela-secao td[contenteditable="true"]:focus {
        outline: 2px solid #3b82f6;
        background-color: #eff6ff;
    }

    /* Editor Canvas mimicking A4 */
    .editor-canvas {
        background-color: #525659; /* Dark gray like PDF viewers */
        padding: 2rem;
        display: flex;
        justify-content: center;
        overflow-x: auto;
        border-radius: 8px;
        margin-top: 1rem;
    }
    
    #areaLayoutLaudo-v2 {
        width: 210mm;
        min-height: 297mm;
        background-color: white;
        padding: 12mm; /* A4 margins */
        box-shadow: 0 0 10px rgba(0,0,0,0.3);
        /* Reset styles from previous inline definitions if any */
        border: none;
        border-radius: 0;
    }

    /* Styles for Preview */
    .a4-page {
        width: 210mm;
        min-height: 297mm;
        padding: 20mm;
        margin: 0 auto;
        background: white;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .tabela-laudo {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed; /* Fix for column spacing consistency */
        font-size: 11pt; /* Match Print base size */
    }
    .tabela-laudo th, .tabela-laudo td {
        padding: 1px 2px; /* Match Print padding */
        line-height: 1.1;
        vertical-align: top;
        overflow-wrap: break-word;
        word-wrap: break-word;
    }
    .tabela-laudo.table-bordered th, .tabela-laudo.table-bordered td {
        border: 1px solid #000;
    }
    .pre-ln {
        min-height: 1em;
    }
    @media print {
        body * {
            visibility: hidden;
        }
        #previewLaudoContent, #previewLaudoContent * {
            visibility: visible;
        }
        #previewLaudoContent {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
            background: white;
        }
        .a4-page {
            box-shadow: none;
            margin: 0;
            width: 100%;
            height: auto;
        }
        .modal {
            position: static;
            overflow: visible;
        }
        .modal-box {
            max-width: none;
            max-height: none;
            box-shadow: none;
            background: transparent;
        }
    }

    /* Fix for dark inputs in preview (DaisyUI/Theme override) */
    #previewLaudoContent select,
    #previewLaudoContent input,
    #previewLaudoContent textarea {
        background-color: #ffffff !important;
        color: #333333 !important;
        border: 1px solid #ced4da !important;
    }
    #previewLaudoContent select option {
        background-color: #ffffff !important;
        color: #333333 !important;
    }
</style>

<!-- MODAL PREVIEW (Adapted to Dialog) -->
<dialog id="modalPreviewLaudo" class="modal">
    <div class="modal-box w-11/12 max-w-6xl h-full max-h-screen">
        <h3 class="font-bold text-lg">Pré-visualização do laudo</h3>
        <div class="py-4 bg-gray-100 overflow-y-auto h-[calc(100vh-200px)]">
            <div id="previewLaudoContent" class="flex justify-center"></div>
        </div>
        <div class="modal-action">
            <button type="button" class="btn" onclick="document.getElementById('modalPreviewLaudo').close()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnImprimirPreview">Imprimir</button>
        </div>
    </div>
</dialog>

<script>
    // Simple tab switching logic
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('block'));
        document.getElementById('tab-' + tabId).classList.remove('hidden');
        document.getElementById('tab-' + tabId).classList.add('block');
        
        document.querySelectorAll('.tab').forEach(el => el.classList.remove('tab-active'));
        document.querySelector(`.tab[data-tab="${tabId}"]`).classList.add('tab-active');

        // Load layout if switching to Laudo tab and not loaded yet
        if (tabId === 'laudo' && !window.layoutLoaded && <?= $editando ? 'true' : 'false' ?>) {
            loadLayout();
        }
    }

    // Modal helpers
    function abrirNovoSetor() {
        document.getElementById('modal_novo_setor').showModal();
    }
    function abrirNovoMaterial() {
        document.getElementById('modal_novo_material').showModal();
    }
    function abrirNovoRecipiente() {
        document.getElementById('modal_novo_recipiente').showModal();
    }

    document.getElementById('setor_id').addEventListener('change', function() {
        if(this.value === '__novo_setor__') {
            this.value = '';
            abrirNovoSetor();
        }
    });
    document.getElementById('material_biologico_id').addEventListener('change', function() {
        if(this.value === '__novo_material__') {
            this.value = '';
            abrirNovoMaterial();
        }
    });
    document.getElementById('recipiente_id').addEventListener('change', function() {
        if(this.value === '__novo_recipiente__') {
            this.value = '';
            abrirNovoRecipiente();
        }
    });

    // AJAX for quick stores
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const targetId = this.getAttribute('data-target');
            
            try {
                const res = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                // Robust JSON parsing (Legacy compatibility)
                const txt = await res.text();
                const cleaned = txt.replace(/\uFEFF/g, '').replace(/^[\u0000-\u001F]+/, '').trim();
                let data;
                try {
                    data = JSON.parse(cleaned);
                } catch {
                    console.error('JSON Parse Error', cleaned);
                    data = null;
                }
                
                if (data && data.sucesso) {
                    const select = document.getElementById(targetId);
                    if (select) {
                        const opt = document.createElement('option');
                        opt.value = data.id;
                        opt.text = data.nome;
                        opt.selected = true;
                        select.appendChild(opt);
                    }
                    
                    this.closest('dialog').close();
                    this.reset();
                } else {
                    alert((data && data.mensagem) ? data.mensagem : 'Erro ao salvar (resposta inválida).');
                }
            } catch (err) {
                alert('Erro ao salvar.');
                console.error(err);
            }
        });
    });

    // Layout Editor Logic Placeholder
    window.layoutLoaded = false;
    window.GLOBAL_EXAME_ID = <?= $exame['id'] ? $exame['id'] : 0 ?>;
    
    async function loadLayout() {
        const area = document.getElementById('areaLayoutLaudo-v2');
        const exameId = window.GLOBAL_EXAME_ID;
        
        if (exameId === 0) return;

        try {
            const fd = new FormData();
            fd.append('exame_id', exameId);
            
            const res = await fetch('index.php?r=exames/layout_fetch', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            if (data.sucesso) {
                window.layoutData = data;
                initLayoutEditor(data);
                window.layoutLoaded = true;
            } else {
                area.innerHTML = `<div class="text-error p-4">Erro ao carregar layout: ${data.mensagem}</div>`;
            }
        } catch (err) {
            area.innerHTML = `<div class="text-error p-4">Erro de conexão ao carregar layout.</div>`;
            console.error(err);
        }
    }

    function initLayoutEditor(data) {
        // This function is overwritten by public/js/exames_layout.js
        console.warn('Layout Editor JS not loaded yet.');
    }

    // --- PREVIEW LOGIC PORTED FROM bmlab_old ---

    function escHTML(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function escNoBreakHyphen(s) {
        const e = escHTML(s);
        return e.replace(/(\S)-(\S)/g, '$1&#8209;$2');
    }

    function labelPorTipo(tipo) {
        if (!tipo) return '';
        const map = {
            'descricao': 'Descrição',
            'material_biologico': 'Material biológico',
            'exame_nome': 'Nome do exame',
            'exame_mnemonico': 'Mnemônico local',
            'exame_metodo': 'Método',
            'exame_prazo_local': 'Prazo execução local',
            'nome_laboratorio': 'Nome do laboratório',
            'nome_posto': 'Nome do posto do pedido',
            'numero_amostra': 'Número da amostra',
            'resultado_num': 'Resultado numérico',
            'resultado_texto': 'Resultado texto',
            'resultado_texto_formatado': 'Resultado texto formatado',
            'resultado_foto': 'Resultado Foto',
            'resultado_img_dinamica': 'Resultado Imagem Dinâmica',
            'resultado_img_estatica': 'Resultado Imagem Estática',
            'observacao_resultado': 'Observação do Resultado',
            'valor_referencia': 'Valor de referência',
            'unidade': 'Unidade',
            'data_coleta': 'Data da coleta',
            'data_liberacao': 'Data da liberação',
            'resultado_anterior': 'Resultado anterior',
            'data_resultado_anterior': 'Data resultado anterior'
        };
        return map[tipo] || '';
    }

    function valorPorTipo(tipo) {
        if (!tipo) return '';
        
        // Mapped to current form IDs
        if (tipo === 'exame_nome') return document.getElementById('nome')?.value || '';
        if (tipo === 'exame_mnemonico') return document.getElementById('mnemonico')?.value || '';
        if (tipo === 'exame_metodo') return document.getElementById('metodo')?.value || '';
        if (tipo === 'exame_prazo_local') return document.getElementById('prazo_execucao_local')?.value || '';
        
        // Static or not mapped yet
        if (tipo === 'data_coleta') return '00/00/0000';
        if (tipo === 'data_liberacao') return '00/00/0000';
        if (tipo === 'resultado_num') return '0';
        if (tipo === 'resultado_texto_formatado') return '';
        if (tipo === 'resultado_foto') return '[FOTO]';
        if (tipo === 'resultado_img_dinamica') return '[IMAGEM DINÂMICA]';
        if (tipo === 'resultado_img_estatica') return '[IMAGEM ESTÁTICA]';
        if (tipo === 'observacao_resultado') return '';
        if (tipo === 'unidade') return '';
        if (tipo === 'valor_referencia') return '';
        if (tipo === 'resultado_texto') return '';
        
        if (tipo === 'material_biologico') {
            const sel = document.getElementById('material_biologico_id');
            return (sel && sel.selectedIndex >= 0) ? sel.options[sel.selectedIndex].text : '';
        }
        
        return '';
    }

    // Adapter to convert window.layoutEditor.data to legacy structure
    function getLegacySecoesStructure() {
        if (!window.layoutEditor || !window.layoutEditor.data) return [];
        const data = window.layoutEditor.data;
        
        return data.sections.map(sec => {
            // Determine max columns
            let maxCols = 0;
            sec.rows.forEach(r => maxCols = Math.max(maxCols, r.cells.length));
            
            const colunas = [];
            for(let i=1; i<=maxCols; i++) {
                const cfg = data.colConfigs.find(c => c.secao == sec.id && c.coluna == i) || {};
                colunas.push({
                    tipo: cfg.tipo,
                    titulo: cfg.titulo,
                    largura: cfg.largura,
                    alignH: cfg.align_h,
                    alignV: cfg.align_v,
                    bold: cfg.bold,
                    italic: cfg.italic,
                    customFont: cfg.custom_font,
                    fontFamily: cfg.font_family,
                    fontSize: cfg.font_size,
                    singleLine: cfg.single_line,
                    fontColor: cfg.font_color
                });
            }

            const linhas = sec.rows.map((r, rIdx) => ({
                cells: r.cells.map((val, idx) => {
                    const cIdx = idx + 1;
                    const cfg = data.cellConfigs.find(c => c.secao == sec.id && c.linha == (rIdx+1) && c.coluna == cIdx) || {};
                    return {
                        valor: val,
                        alignH: cfg.align_h,
                        alignV: cfg.align_v,
                        bold: cfg.bold,
                        italic: cfg.italic,
                        underline: cfg.underline,
                        fontFamily: cfg.font_family,
                        fontSize: cfg.font_size,
                        fontColor: cfg.font_color,
                        singleLine: cfg.single_line,
                        uppercase: cfg.uppercase,
                        numTipo: cfg.num_tipo,
                        numPos: cfg.num_pos,
                        numDec: cfg.num_dec,
                        numFormula: cfg.num_formula,
                        varAlias: cfg.var_alias
                    };
                })
            }));

            return {
                id: sec.id,
                colunas: colunas,
                linhas: linhas
            };
        });
    }

    function gerarHTMLPreview(opts) {
        const isPrint = opts === true || (opts && opts.mode === 'print');
        const cfg = {
            borderless: !!(isPrint || (opts && opts.borderless)),
            hideHeaders: !!(isPrint || (opts && opts.hideHeaders)),
            hideSectionNumbers: !!(isPrint || (opts && opts.hideSectionNumbers))
        };
        
        const secoesLaudo = getLegacySecoesStructure();
        
        let inner = '';
        secoesLaudo.forEach(secao => {
            inner += `<div style="margin-bottom:8px">`;
            const tableClass = cfg.borderless ? 'tabela-laudo' : 'tabela-laudo table-bordered';
            inner += `<table class="${tableClass}" style="width:100%">`;
            inner += `<colgroup>`;
            if (!cfg.hideSectionNumbers) inner += `<col style="width:38px">`;
            const colsCount = secao.colunas.length;
            secao.colunas.forEach(col => {
                const w = parseFloat(col.largura) || (100 / colsCount);
                inner += `<col style="width:${w}%">`;
            });
            inner += `</colgroup>`;
            if (!cfg.hideHeaders) {
                inner += `<thead><tr>`;
                if (!cfg.hideSectionNumbers) inner += `<th style="width:38px"></th>`;
                secao.colunas.forEach(col => {
                    const w = parseFloat(col.largura) || (100 / secao.colunas.length);
                    const lbl = col.tipo ? labelPorTipo(col.tipo) : (col.titulo || '');
                    let st = `width:${w}%`;
                    if (col.alignH) st += `;text-align:${col.alignH}`;
                    if (col.alignV) st += `;vertical-align:${col.alignV}`;
                    if (col.bold) st += `;font-weight:bold`;
                    if (col.italic) st += `;font-style:italic`;
                    if (col.customFont) {
                        if (col.fontFamily) st += `;font-family:${col.fontFamily}`;
                        if (col.fontSize) st += `;font-size:${col.fontSize}px`;
                    }
                    inner += `<th style="${st}">${lbl}</th>`;
                });
                inner += `</tr></thead>`;
            }
            inner += `<tbody>`;
            if (!secao.linhas || secao.linhas.length === 0) secao.linhas = [{
                cells: secao.colunas.map(() => ({
                    valor: ''
                }))
            }];
            secao.linhas.forEach((linha, rIdx) => {
                inner += `<tr>`;
                if (!cfg.hideSectionNumbers) inner += `<td></td>`;
                linha.cells.forEach((cell, idx) => {
                    const col = secao.colunas[idx];
                    const dynSetPrev = new Set(['material_biologico', 'exame_nome', 'exame_mnemonico', 'exame_metodo', 'exame_prazo_local']);
                    const vt = (col && col.tipo && dynSetPrev.has(col.tipo)) ? valorPorTipo(col.tipo) : null;
                    let v = vt !== null ? vt : (cell.valor || '');
                    v = String(v || '').replace(/\r/g, '\n');
                    if (cell && cell.uppercase) v = v.toUpperCase();
                    let st = '';
                    const singleLine = !!((cell && cell.singleLine) || (col && col.singleLine));
                    const alignH = (cell && cell.alignH) || (col && col.alignH) || '';
                    const alignV = (cell && cell.alignV) || (col && col.alignV) || '';
                    const bold = (cell && cell.bold != null) ? cell.bold : !!(col && col.bold);
                    const italic = (cell && cell.italic != null) ? cell.italic : !!(col && col.italic);
                    const underline = (cell && cell.underline != null) ? cell.underline : !!(col && col.underline);
                    const fontFamily = (cell && cell.fontFamily) || (col && col.fontFamily) || '';
                    const fontSize = (cell && cell.fontSize != null) ? cell.fontSize : ((col && col.fontSize != null) ? col.fontSize : null);
                    const fontColor = (cell && cell.fontColor) || (col && col.fontColor) || '';
                    if (singleLine) st += 'white-space:nowrap;overflow:hidden;';
                    else st += '';
                    if (alignH) st += `text-align:${alignH};`;
                    if (alignV) st += `vertical-align:${alignV};`;
                    if (bold) st += 'font-weight:bold;';
                    if (italic) st += 'font-style:italic;';
                    if (underline) st += 'text-decoration:underline;';
                    if (fontFamily) st += `font-family:${fontFamily};`;
                    if (fontSize != null) st += `font-size:${fontSize}px;`;
                    if (fontColor) st += `color:${fontColor};`;
                    
                    let content = '';
                    const isTextual = !!(col && (col.tipo === 'resultado_texto' || col.tipo === 'resultado_texto_formatado' || col.tipo === 'observacao_resultado'));
                    const isNum = !!(col && col.tipo === 'resultado_num');
                    
                    if (!isPrint && isTextual) {
                         content = `<input type="text" class="input input-xs input-bordered w-full" value="${escHTML(v)}" readonly>`;
                    } else if (!isPrint && isNum) {
                         const nt = (cell && cell.numTipo) ? cell.numTipo : 'numero';
                         if (nt === 'calculo') {
                             content = `<span class="badge badge-sm badge-info">Fórmula</span>`;
                         } else {
                             content = `<input type="text" class="input input-xs input-bordered w-full" disabled>`;
                         }
                    } else {
                        content = singleLine ? escNoBreakHyphen(v) : v.split(/\n/).map(part => `<div class="pre-ln">${escNoBreakHyphen(part)}</div>`).join('');
                    }
                    inner += `<td style="${st}">${content}</td>`;
                });
                inner += `</tr>`;
            });
            inner += `</tbody></table></div>`;
        });
        return `<div class="a4-page">${inner}</div>`;
    }

    let rtOpcoesCache = null;
    async function carregarOpcoesRT() {
        if (rtOpcoesCache) return rtOpcoesCache;
        try {
            const r = await fetch('index.php?r=resultados_texto/get_all_json');
            const j = await r.json();
            const arr = Array.isArray(j) ? j : [];
            rtOpcoesCache = arr;
            return arr;
        } catch (e) {
            rtOpcoesCache = [];
            return [];
        }
    }

    function initPreviewRT() {
        // Ignora selects já populados pelo exames_layout.js (que possuem a classe populated-by-layout)
        const sels = document.querySelectorAll('#previewLaudoContent .preview-rt-select:not(.populated-by-layout)');
        if (!sels.length) return;
        carregarOpcoesRT().then(arr => {
            sels.forEach(sel => {
                sel.innerHTML = '';
                const opt0 = document.createElement('option');
                opt0.value = '';
                opt0.textContent = 'Selecionar texto cadastrado...';
                sel.appendChild(opt0);
                arr.forEach(it => {
                    const o = document.createElement('option');
                    o.value = String(it.id);
                    o.textContent = String(it.texto || '').substring(0, 120);
                    sel.appendChild(o);
                });
                const rid = sel.getAttribute('data-rt-id') || '';
                if (rid) sel.value = String(rid);
            });
        });
    }

    function initPreviewNUM() {
        const ins = Array.from(document.querySelectorAll('#previewLaudoContent .preview-num-input'));
        const calcs = Array.from(document.querySelectorAll('#previewLaudoContent .preview-num-calc'));
        if (!ins.length && !calcs.length) return;

        const parseVal = (s) => {
            if (s == null) return 0;
            const t = String(s).trim().replace(/\./g, '').replace(/,/g, '.');
            const n = parseFloat(t);
            return isNaN(n) ? 0 : n;
        };
        const formatVal = (n, decs) => {
            const v = (typeof n === 'number' && isFinite(n)) ? n : 0;
            const fixed = v.toFixed(Math.max(0, Math.min(12, decs)));
            const parts = fixed.split('.');
            return parts.length === 2 ? (parts[0] + ',' + parts[1]) : parts[0];
        };
        const valByKey = new Map();
        const valByAlias = new Map();
        const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '');
        const secFromKey = (k) => {
            const parts = String(k || '').split('|');
            return parts.length ? parts[0] : '';
        };
        const rebuildMaps = () => {
            valByKey.clear();
            valByAlias.clear();
            ins.forEach(inp => {
                const key = inp.getAttribute('data-key') || '';
                const alias = (inp.getAttribute('data-alias') || '').trim();
                const v = parseVal(inp.value || '');
                if (key) valByKey.set(key, v);
                const secId = secFromKey(key);
                const addAlias = (a) => {
                    const na = norm(a);
                    if (na) valByAlias.set(secId + '|' + na, v);
                };
                if (alias) addAlias(alias);
                else {
                    const tr = inp.closest('tr');
                    let desc = '';
                    if (tr) {
                        const tds = Array.from(tr.querySelectorAll('td'));
                        for (const td of tds) {
                            const hasWidget = td.querySelector('.preview-num-input, .preview-num-calc, .preview-rt-select, .preview-rt-input');
                            const txt = String(td.textContent || '').trim();
                            if (!hasWidget && txt) {
                                desc = txt;
                                break;
                            }
                        }
                    }
                    addAlias(desc);
                }
            });
        };
        const evalCalc = (span) => {
            try {
                let formula = String(span.getAttribute('data-formula') || '').trim();
                let dec = parseInt(span.getAttribute('data-dec') || '2', 10);
                const key = span.getAttribute('data-key') || '';
                if (!formula) {
                    span.textContent = '[Cálculo]';
                    return;
                }
                if (isNaN(dec)) dec = 2;
                const refs = formula.match(/\[[^\]]+\]/g) || [];
                let expr = formula;
                refs.forEach(r => {
                    const name = r.replace(/^\[/, '').replace(/\]$/, '');
                    let val = 0;
                    if (/^\d+\|\d+\|\d+$/.test(name)) {
                        val = valByKey.get(name) || 0;
                    } else {
                        const spanKey = span.getAttribute('data-key') || '';
                        const secId = secFromKey(spanKey);
                        const n = norm(name);
                        val = valByAlias.get(secId + '|' + n) || 0;
                    }
                    expr = expr.replace(r, String(val));
                });
                if (!/^[-+*/().0-9\s]+$/.test(expr)) {
                    span.textContent = '[Cálculo]';
                    return;
                }
                let res = 0;
                try {
                    res = Function('return (' + expr + ')')();
                } catch {
                    res = 0;
                }
                span.textContent = formatVal(res, dec);
            } catch {
                span.textContent = '[Cálculo]';
            }
        };
        const recompute = () => {
            rebuildMaps();
            calcs.forEach(evalCalc);
        };
        ins.forEach(inp => {
            const p = parseInt(inp.getAttribute('data-pos') || '3', 10);
            const d = parseInt(inp.getAttribute('data-dec') || '0', 10);
            const pos = isNaN(p) ? 3 : Math.max(0, Math.min(12, p));
            const decs = isNaN(d) ? 0 : Math.max(0, Math.min(12, d));
            const sample = '123456789012';
            const left = sample.slice(0, Math.max(0, Math.min(sample.length, pos))) || '0';
            const right = (decs > 0) ? sample.slice(0, Math.min(sample.length, decs)) : '';
            const s = right ? (left + ',' + right) : left;
            const ex = inp.parentElement && inp.parentElement.querySelector('.preview-num-exemplo');
            if (ex) ex.textContent = 'Exemplo: ' + s;
            inp.addEventListener('input', recompute);
        });
        recompute();
    }

    // Event Listeners for Toolbar Actions
    document.addEventListener('DOMContentLoaded', () => {
        /* 
        // MOVIDO PARA exames_layout.js para evitar conflito e garantir carregamento de dados
        const btnVisualizarLaudo = document.getElementById('btnVisualizarLaudo');
        if (btnVisualizarLaudo) {
            btnVisualizarLaudo.addEventListener('click', () => {
                const html = gerarHTMLPreview({
                    borderless: true,
                    hideHeaders: true,
                    hideSectionNumbers: true
                });
                document.getElementById('previewLaudoContent').innerHTML = html;
                document.getElementById('modalPreviewLaudo').showModal();
                initPreviewRT();
                initPreviewNUM();
            });
        }
        */
        
        const btnImprimirPreview = document.getElementById('btnImprimirPreview');
        if (btnImprimirPreview) {
            btnImprimirPreview.addEventListener('click', () => {
                const w = window.open('', '_blank');
                if (!w) return;
                const html = document.getElementById('previewLaudoContent').innerHTML;
                const style = `
                    <style>
                    @page { size: A4; margin: 12mm; }
                    html, body { height: 100%; }
                    body { background: #f4f5f7; padding: 16px; }
                    .a4-page { width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; box-shadow: 0 0 2mm rgba(0,0,0,0.2); padding: 12mm; font-size: 11pt; }
                    .table { width: 100%; }
                    .table th, .table td { border: none !important; }
                    .tabela-laudo { table-layout: fixed; border-collapse: collapse; border-spacing: 0; }
                    .tabela-laudo td { padding-top: 0; padding-bottom: 0; padding-left: 0; padding-right: 0; line-height: 1; vertical-align: top; }
                    .tabela-laudo tr { line-height: 1; height: 1em; }
                    .pre-ln { white-space: pre-wrap; margin: 0; }
                    table { page-break-inside: avoid; }
                    .page-break { page-break-after: always; }
                    @media print { body { padding: 0; background: #fff; } .a4-page { box-shadow: none; width: auto; min-height: auto; padding: 0; } }
                    </style>`;
                w.document.write(`<html><head><title>Impressão</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">${style}</head><body>${html}<script>window.print();<\\/script></body></html>`);
                w.document.close();
            });
        }

        const btnVisualizarImpressao = document.getElementById('btnVisualizarImpressao');
        if (btnVisualizarImpressao) {
            btnVisualizarImpressao.addEventListener('click', () => {
                const w = window.open('', '_blank');
                if (!w) return;
                const html = gerarHTMLPreview(true);
                const style = `
                    <style>
                    @page { size: A4; margin: 12mm; }
                    html, body { height: 100%; }
                    body { background: #f4f5f7; padding: 16px; }
                    .a4-page { width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; box-shadow: 0 0 2mm rgba(0,0,0,0.2); padding: 12mm; font-size: 11pt; }
                    .table { width: 100%; }
                    .table th, .table td { border: none !important; }
                    .tabela-laudo { table-layout: fixed; border-collapse: collapse; border-spacing: 0; }
                    .tabela-laudo td { padding-top: 0; padding-bottom: 0; padding-left: 0; padding-right: 0; line-height: 1; vertical-align: top; }
                    .tabela-laudo tr { line-height: 1; height: 1em; }
                    .pre-ln { white-space: pre-wrap; margin: 0; }
                    table { page-break-inside: avoid; }
                    .page-break { page-break-after: always; }
                    @media print { body { padding: 0; background: #fff; } .a4-page { box-shadow: none; width: auto; min-height: auto; padding: 0; } }
                    </style>`;
                w.document.write(`<html><head><title>Impressão</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">${style}</head><body>${html}<script>window.print();<\\/script></body></html>`);
                w.document.close();
            });
        }
    });

</script>
<script src="public/js/exames_layout.js"></script>
