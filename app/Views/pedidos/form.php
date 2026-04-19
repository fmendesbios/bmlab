<?php
// app/Views/pedidos/form.php
$isEdit = !empty($pedido['id']);
$viewOnly = $viewOnly ?? false;
$disabled = $viewOnly ? 'disabled' : '';
$readonlyClass = $viewOnly ? 'bg-base-200' : '';

// Calculate Age in PHP for Initial Load
$idadeDisplay = '';
if (!empty($paciente['nascimento'])) {
    try {
        $nasc = new DateTime($paciente['nascimento']);
        $hoje = new DateTime();
        $diff = $hoje->diff($nasc);
        $idadeDisplay = "";
        if ($diff->y > 0) $idadeDisplay .= $diff->y . "A ";
        if ($diff->m > 0) $idadeDisplay .= $diff->m . "M ";
        if ($diff->d > 0 || $idadeDisplay === "") $idadeDisplay .= $diff->d . "D";
        $idadeDisplay = trim($idadeDisplay);
    } catch (Exception $e) {
    }
}
?>
<div class="card bg-base-100 shadow-xl mb-6">
    <div class="card-body">
        <h2 class="card-title text-2xl mb-4">
            <?php if ($viewOnly): ?>
            <?php else: ?>
                <?= $isEdit ? 'Editar Pedido' : 'Novo Pedido' ?>
            <?php endif; ?>
        </h2>

        <form id="formPedido" method="POST" action="index.php?r=pedidos/store">
            <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
            <?php if (!empty($pedido['orcamento_origem_id'])): ?>
                <input type="hidden" name="orcamento_origem_id" value="<?= $pedido['orcamento_origem_id'] ?>">
            <?php endif; ?>

            <fieldset>
                <!-- Row 1: Código, Paciente, Sexo, Nascimento, Idade -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Código</span></label>
                        <input type="text" class="input input-bordered w-full bg-base-200" readonly
                            value="<?= $isEdit ? $pedido['codigo_pedido'] : ('01' . str_pad((string)$proximoId, 7, '0', STR_PAD_LEFT)) ?>">
                    </div>

                    <div class="md:col-span-4 relative">
                        <label class="label"><span class="label-text">Paciente</span></label>
                        <?php if ($viewOnly): ?>
                            <input type="text" class="input input-bordered w-full <?= $readonlyClass ?>" readonly value="<?= $pedido['paciente_nome'] ?? '' ?>">
                            <input type="hidden" name="paciente_id" value="<?= $pedido['paciente_id'] ?? '' ?>">
                        <?php else: ?>
                            <div class="relative">
                                <input type="hidden" name="paciente_id" id="pedido_paciente_id" value="<?= $pedido['paciente_id'] ?? '' ?>">
                                <input type="text" id="busca_paciente" class="input input-bordered w-full" placeholder="Digite para buscar..." autocomplete="off" value="<?= htmlspecialchars($pedido['paciente_nome'] ?? '') ?>">
                                <ul id="lista_paciente" class="dropdown-content absolute z-[10] menu p-2 shadow bg-base-100 rounded-box w-full max-h-60 overflow-y-auto hidden"></ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Sexo</span></label>
                        <input type="text" id="pacienteSexo" class="input input-bordered w-full bg-base-200" readonly
                            value="<?= $paciente['sexo'] ?? '' ?>">
                    </div>

                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Nascimento</span></label>
                        <input type="text" id="pacienteNasc" class="input input-bordered w-full bg-base-200" readonly
                            value="<?= $paciente ? date('d/m/Y', strtotime($paciente['nascimento'])) : '' ?>">
                        <input type="hidden" id="pacienteNascRaw" value="<?= substr($paciente['nascimento'] ?? '', 0, 10) ?>">
                    </div>

                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Idade</span></label>
                        <input type="text" id="pacienteIdade" class="input input-bordered w-full bg-base-200" readonly value="<?= $idadeDisplay ?>">
                    </div>
                </div>

                <!-- Row 2: Posto, Convênio, Médico -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Posto de Coleta</span></label>
                        <select name="posto_id" class="select select-bordered w-full <?= $readonlyClass ?>" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($postos as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $pedido['posto_id'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome_posto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Convênio</span></label>
                        <select name="convenio_id" id="convenioSelect" class="select select-bordered w-full <?= $readonlyClass ?>" required onchange="updatePrices()">
                            <option value="">Selecione...</option>
                            <?php foreach ($convenios as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $pedido['convenio_id'] == $c['id'] ? 'selected' : '' ?> data-faturavel="<?= $c['faturavel'] ?>" data-tipo="<?= $c['tipo'] ?>">
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="label"><span class="label-text">Médico</span></label>
                        <?php if ($viewOnly): ?>
                             <input type="text" class="input input-bordered w-full <?= $readonlyClass ?>" readonly value="<?= $pedido['medico_nome'] ?? '' ?>">
                             <input type="hidden" name="medico_id" value="<?= $pedido['medico_id'] ?? '' ?>">
                        <?php else: ?>
                            <div class="relative">
                                <input type="hidden" name="medico_id" id="pedido_medico_id" value="<?= $pedido['medico_id'] ?? '' ?>">
                                <input type="text" id="busca_medico" class="input input-bordered w-full" placeholder="Digite para buscar..." autocomplete="off" required value="<?= htmlspecialchars($pedido['medico_nome'] ?? '') ?>">
                                <ul id="lista_medico" class="dropdown-content absolute z-[10] menu p-2 shadow bg-base-100 rounded-box w-full max-h-60 overflow-y-auto hidden"></ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 2b: Parceiro, Campanha -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-6">
                        <label class="label"><span class="label-text">Parceiro</span></label>
                        <select name="parceiro_id" id="parceiroSelect" class="select select-bordered w-full <?= $readonlyClass ?>" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($parceiros as $p): ?>
                                <option value="<?= $p['id'] ?>" data-desconto="<?= $p['desconto_permitido'] ?>" <?= $pedido['parceiro_id'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-6">
                        <label class="label"><span class="label-text">Campanha/Programa</span></label>
                        <select name="campanha_programa_id" id="campanhaSelect" class="select select-bordered w-full <?= $readonlyClass ?>">
                            <option value="">Selecione...</option>
                            <?php foreach ($campanhas as $c): ?>
                                <option value="<?= $c['id'] ?>" data-desconto="<?= $c['desconto_permitido'] ?>" <?= ($pedido['campanha_programa_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome_campanha_programa']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 3: Entrada Data, Entrada Hora, Previsão -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Entrada - Data</span></label>
                        <?php
                            $tsEntrada = strtotime($pedido['entrada'] ?? '');
                            $entradaValida = ($tsEntrada !== false && $tsEntrada > 0 && date('Y', $tsEntrada) > 1900);
                            // Input date uses Y-m-d
                            $dataExibicao = $entradaValida ? date('Y-m-d', $tsEntrada) : '';
                            $horaExibicao = $entradaValida ? date('H:i', $tsEntrada) : '';
                            
                            // Se for novo pedido e não tiver data válida (improvável), usa atual
                            if (!$entradaValida && empty($pedido['id'])) {
                                $dataExibicao = date('Y-m-d');
                                $horaExibicao = date('H:i');
                            }

                            $entReadonly = $viewOnly ? 'readonly' : '';
                            $entClass = $viewOnly ? 'bg-base-200' : '';
                        ?>
                        <input type="date" name="entrada_data" class="input input-bordered w-full <?= $entClass ?>" <?= $entReadonly ?>
                            value="<?= $dataExibicao ?>" required>
                    </div>

                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Entrada - Hora</span></label>
                        <input type="time" name="entrada_hora" class="input input-bordered w-full <?= $entClass ?>" <?= $entReadonly ?>
                            value="<?= $horaExibicao ?>" required>
                    </div>

                    <div class="md:col-span-3">
                        <label class="label"><span class="label-text">Previsão de Entrega</span></label>
                        <input type="text" id="previsaoEntrega" class="input input-bordered w-full bg-base-200 font-bold text-primary" readonly placeholder="--/--/----">
                    </div>
                </div>

                <div class="divider">Exames</div>

                <!-- Exame Search -->
                <?php if (!$viewOnly): ?>
                    <div class="mb-4">
                        <div class="form-control">
                            <label class="label"><span class="label-text">Buscar exame (mnemônico ou nome)</span></label>
                            <div class="relative">
                                <input type="text" id="searchExame" class="input input-bordered w-full" placeholder="Ex.: HMG, GLICOSE..." autocomplete="off">
                                <div id="exameResults" class="absolute z-10 w-full bg-base-100 shadow-xl rounded-box mt-1 hidden max-h-60 overflow-y-auto border border-base-200"></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Exame Table -->
                <div class="overflow-x-auto mb-4 border rounded-lg max-h-96">
                    <table class="table table-xs table-pin-rows" id="tableItens">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Ação</th>
                                <th style="width: 100px;">Mnemônico</th>
                                <th>Exame</th>
                                <th>Material</th>
                                <th>Laboratório</th>
                                <th style="width: 120px;">Valor (R$)</th>
                                <th style="width: 120px;">Data Coleta</th>
                                <th style="width: 100px;">Hora Coleta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- JS will populate this -->
                        </tbody>
                    </table>
                </div>

                <hr class="mb-4">

                <!-- Totals -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Total Pedido (R$)</span></label>
                        <input type="text" id="displayBruto" class="input input-bordered text-right font-mono bg-base-200" readonly value="0,00">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Desconto (%)</span></label>
                        <input type="number" name="desconto_percentual" id="descPerc" value="<?= $pedido['desconto_percentual'] ?>" class="input input-bordered text-right font-mono <?= $readonlyClass ?>" min="0" max="100" step="0.01">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Desconto (R$)</span></label>
                        <input type="number" name="desconto_valor" id="descVal" value="<?= $pedido['desconto_valor'] ?>" class="input input-bordered text-right font-mono <?= $readonlyClass ?>" min="0" step="0.01">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Total Líquido (R$)</span></label>
                        <input type="text" id="displayLiquido" class="input input-bordered text-right font-mono font-bold bg-base-200" readonly value="0,00">
                    </div>
                </div>
                <div id="alertDesconto" class="alert alert-error text-sm mt-2 hidden">
                    <i class="bi bi-exclamation-triangle-fill"></i> Desconto maior que o permitido pelo parceiro!
                </div>
            </fieldset>

            <div class="modal-action justify-end mt-6">
                <?php if (!isset($layout) || $layout !== 'modal'): ?>
                    <a href="index.php?r=pedidos" class="btn">Voltar</a>
                <?php endif; ?>
                <?php if (!$viewOnly): ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Pedido
                    </button>
                <?php endif; ?>
            </div>

            <!-- Hidden inputs for form submission -->
            <input type="hidden" name="itens" id="inputItens">
        </form>
    </div>
</div>

<!-- Modal Selecao Lab Apoio Pedido -->
<dialog id="modal_selecao_lab_pedido" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Selecione o Laboratório de Apoio</h3>
        <p class="mb-4">Este exame possui opções de laboratórios de apoio. Escolha um:</p>
        <div id="lista_labs_apoio_pedido" class="flex flex-col gap-2">
            <!-- Buttons injected by JS -->
        </div>
        <div class="modal-action">
            <button class="btn" type="button" onclick="document.getElementById('modal_selecao_lab_pedido').close()">Cancelar</button>
        </div>
    </div>
</dialog>

<script>
    // State
    let itens = [];
    const initialItens = <?= json_encode($itens) ?>;
    const isViewOnly = <?= $viewOnly ? 'true' : 'false' ?>;
    let pendingExame = null;

    // Dropdown Data
    const listaPacientes = <?= json_encode(array_map(function($p) {
        return [
            'id' => $p['id'],
            'nome' => $p['nome'],
            'sexo' => $p['sexo'] ?? '',
            'nascimento' => substr($p['nascimento'] ?? '', 0, 10),
            'campanha_padrao_id' => $p['campanha_padrao_id'] ?? ''
        ];
    }, $pacientes)) ?>;

    const listaMedicos = <?= json_encode(array_map(function($m) {
        return ['id' => $m['id'], 'nome' => $m['nome']];
    }, $medicos)) ?>;
    
    // Dropdown Search Helper
    function setupDropdownSearch(inputId, hiddenId, listId, dataSource, onSelect = null) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const list = document.getElementById(listId);

        if (!input || !hidden || !list) return;

        function filter(showAll = false) {
            const val = input.value.toLowerCase();
            list.innerHTML = '';
            const filtered = showAll ? dataSource : dataSource.filter(item => (item.nome).toLowerCase().includes(val));
            
            // Limit results for performance
            const limit = 50;
            const displayList = filtered.slice(0, limit);

            if (displayList.length === 0) {
                list.classList.add('hidden');
                return;
            }

            displayList.forEach((item) => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.textContent = item.nome;
                a.onclick = () => {
                    selectItem(item);
                };
                li.appendChild(a);
                list.appendChild(li);
            });
            
            list.classList.remove('hidden');
        }

        function selectItem(item) {
            input.value = item.nome;
            hidden.value = item.id;
            list.classList.add('hidden');
            if (onSelect) onSelect(item);
        }

        input.addEventListener('input', () => {
            hidden.value = ''; // Clear selection on type
            filter();
        });
        
        input.addEventListener('focus', () => {
            filter(true); // Show all
        });
        
        // Hide on click outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !list.contains(e.target)) {
                list.classList.add('hidden');
            }
        });

        // Return controller
        return {
            setValue: (id, triggerCallback = true) => {
                const item = dataSource.find(i => String(i.id) === String(id));
                if (item) {
                    input.value = item.nome;
                    hidden.value = item.id;
                    if (triggerCallback && onSelect) onSelect(item);
                } else {
                    input.value = '';
                    hidden.value = '';
                }
            }
        };
    }


    function mostrarSelecaoLabPedido(ex) {
        const div = document.getElementById('lista_labs_apoio_pedido');
        div.innerHTML = '';
        const apoios = ex.laboratorios_apoio;
        
        apoios.forEach(lab => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline justify-between normal-case h-auto py-2';
            btn.innerHTML = `
                <div class="flex flex-col items-start">
                    <span class="font-bold">${lab.laboratorio_nome}</span>
                    <span class="text-xs">Cód: ${lab.codigo_no_laboratorio || '-'}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="badge badge-sm badge-ghost">${lab.prazo} dias</span>
                </div>
            `;
            btn.onclick = (e) => {
                e.preventDefault();
                document.getElementById('modal_selecao_lab_pedido').close();
                // Update pendingExame with selected lab info
                const exUpdated = { ...pendingExame };
                exUpdated.laboratorio_id = lab.laboratorio_id;
                exUpdated.laboratorio_nome = lab.laboratorio_nome;
                exUpdated.prazo_execucao_local = parseInt(lab.prazo);
                
                addItem(exUpdated, true); // true = force add
            };
            div.appendChild(btn);
        });
        document.getElementById('modal_selecao_lab_pedido').showModal();
    }


    // Format helpers
    function formatMoney(v) {
        return v.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(iso) {
        if (!iso) return '';
        const [y, m, d] = iso.split('-');
        return `${d}/${m}/${y}`;
    }

    // Debounce
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Init
    let pacienteDropdown, medicoDropdown;

    document.addEventListener('DOMContentLoaded', () => {
        // Init Dropdowns
        if (!isViewOnly) {
             pacienteDropdown = setupDropdownSearch('busca_paciente', 'pedido_paciente_id', 'lista_paciente', listaPacientes, (item) => {
                 updatePacienteInfo(item);
             });
             
             medicoDropdown = setupDropdownSearch('busca_medico', 'pedido_medico_id', 'lista_medico', listaMedicos);

             // Set initial values
             const initialPacId = document.getElementById('pedido_paciente_id').value;
             if(initialPacId) pacienteDropdown.setValue(initialPacId, false);
             
             const initialMedId = document.getElementById('pedido_medico_id').value;
             if(initialMedId) medicoDropdown.setValue(initialMedId, false);
        }

        // Load initial items
        initialItens.forEach(it => {
            addItem({
                id: it.exame_id,
                mnemonico_local: it.mnemonico_local,
                nome: it.exame_nome,
                valor: parseFloat(it.valor),
                data_coleta: it.data_coleta,
                hora_coleta: it.hora_coleta,
                material_nome: it.material_nome, // Ensure these keys exist in PHP output
                laboratorio_nome: it.laboratorio_nome,
                prazo_execucao_local: it.prazo_execucao_local
            }, false);
        });

        // Listeners
        const searchInput = document.getElementById('searchExame');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(searchExame, 300));
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    quickAddExame(this.value.trim());
                }
            });
        }

        document.getElementById('descPerc').addEventListener('input', updateTotalsFromPerc);
        document.getElementById('descVal').addEventListener('input', updateTotalsFromVal);
        
        const parceiroSel = document.getElementById('parceiroSelect');
        if (parceiroSel) parceiroSel.addEventListener('change', checkDiscount);
        
        const campanhaSel = document.getElementById('campanhaSelect');
        if (campanhaSel) campanhaSel.addEventListener('change', checkDiscount);

        // Hide results on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#searchExame')) document.getElementById('exameResults').classList.add('hidden');
        });

        // Calculate initial totals and info
        calculateTotals();
        calculateAge();
        calculateForecast();

        // Disable fields if view only
        if (isViewOnly) {
            const form = document.getElementById('formPedido');
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(el => {
                el.disabled = true;
            });
        }
    });

    // Patient Selection
    function updatePacienteInfo(pac) {
        if (!pac) {
            document.getElementById('pacienteSexo').value = '';
            document.getElementById('pacienteNasc').value = '';
            document.getElementById('pacienteNascRaw').value = '';
            document.getElementById('pacienteIdade').value = '';
            return;
        }

        const sexo = pac.sexo;
        const nasc = pac.nascimento;
        const campanhaPadrao = pac.campanha_padrao_id;

        document.getElementById('pacienteSexo').value = sexo || '';
        document.getElementById('pacienteNascRaw').value = nasc || '';
        document.getElementById('pacienteNasc').value = formatDate(nasc);

        // Auto-select campaign
        const campanhaSel = document.getElementById('campanhaSelect');
        if (campanhaSel) {
            campanhaSel.value = campanhaPadrao || '';
            // Trigger change event to apply any discounts
            campanhaSel.dispatchEvent(new Event('change'));
        }

        calculateAge();
    }

    function calculateAge() {
        const nascRaw = document.getElementById('pacienteNascRaw').value;
        const idadeInput = document.getElementById('pacienteIdade');

        if (!nascRaw) {
            idadeInput.value = '';
            return;
        }

        const [y, m, d] = nascRaw.split('-').map(Number);
        const birth = new Date(y, m - 1, d);
        const today = new Date();

        let years = today.getFullYear() - birth.getFullYear();
        let months = today.getMonth() - birth.getMonth();
        let days = today.getDate() - birth.getDate();

        if (days < 0) {
            months--;
            const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            days += lastMonth.getDate();
        }
        if (months < 0) {
            years--;
            months += 12;
        }

        let text = '';
        if (years > 0) text += `${years}A `;
        if (months > 0) text += `${months}M `;
        if (days > 0 || text === '') text += `${days}D`;

        idadeInput.value = text.trim();
    }

    // Exam Search
    function quickAddExame(q) {
        const convId = document.getElementById('convenioSelect').value;
        if (!q || q.length < 1) return;
        if (!convId) {
            alert('Selecione um convênio primeiro.');
            return;
        }

        fetch(`index.php?r=pedidos/search_exames&q=${encodeURIComponent(q)}&convenio_id=${convId}`)
            .then(r => r.json())
            .then(data => {
                let match = null;

                // 1. Exact mnemonic match
                match = data.find(ex => ex.mnemonico_local.toUpperCase() === q.toUpperCase());

                // 2. If single result returned, take it
                if (!match && data.length === 1) {
                    match = data[0];
                }

                if (match) {
                    addItem(match);
                    document.getElementById('searchExame').value = '';
                    document.getElementById('exameResults').classList.add('hidden');
                    document.getElementById('searchExame').focus();
                }
            });
    }

    function searchExame(e) {
        const q = e.target.value;
        const convId = document.getElementById('convenioSelect').value;

        if (q.length < 2) return;

        if (!convId) {
            alert('Selecione um convênio primeiro.');
            e.target.value = '';
            return;
        }

        fetch(`index.php?r=pedidos/search_exames&q=${encodeURIComponent(q)}&convenio_id=${convId}`)
            .then(r => r.json())
            .then(data => {
                const res = document.getElementById('exameResults');
                res.innerHTML = '';
                res.classList.remove('hidden');

                if (data.length === 0) {
                    res.innerHTML = '<div class="p-3 text-sm text-gray-500">Nenhum exame encontrado.</div>';
                    return;
                }

                data.forEach(ex => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-base-200 cursor-pointer border-b last:border-0 flex justify-between items-center';
                    div.innerHTML = `
                        <div>
                            <div class="font-bold">${ex.mnemonico_local} - ${ex.nome}</div>
                            <div class="text-xs text-gray-500">${ex.material_nome || ''} • ${ex.laboratorio_nome || ''}</div>
                        </div>
                        <div class="font-mono font-bold text-primary">R$ ${formatMoney(ex.preco)}</div>
                    `;
                    div.onclick = () => {
                        addItem(ex);
                        document.getElementById('exameResults').classList.add('hidden');
                        document.getElementById('searchExame').value = '';
                        document.getElementById('searchExame').focus();
                    };
                    res.appendChild(div);
                });
            });
    }

    function addItem(ex, force = false) {
        // Check duplicate
        if (itens.find(i => i.id == ex.id)) {
            alert('Exame já adicionado.');
            return;
        }

        // Check support labs
        if (!force && ex.laboratorios_apoio && ex.laboratorios_apoio.length > 1) {
             pendingExame = ex;
             mostrarSelecaoLabPedido(ex);
             return;
        }

        const item = {
            id: ex.id,
            mnemonico: ex.mnemonico_local || ex.mnemonico,
            nome: ex.nome,
            valor: parseFloat(ex.valor || ex.preco || 0),
            data_coleta: ex.data_coleta || '<?= date('Y-m-d') ?>',
            hora_coleta: ex.hora_coleta || '<?= date('H:i') ?>',
            material: ex.material_nome || ex.material || '',
            laboratorio: ex.laboratorio_nome || ex.laboratorio || '',
            prazo: parseInt(ex.prazo_execucao_local || 0),
            laboratorio_id: ex.laboratorio_id || null
        };

        itens.push(item);
        renderTable();
        calculateTotals();
        calculateForecast();
    }

    function removeItem(id) {
        itens = itens.filter(i => i.id != id);
        renderTable();
        calculateTotals();
        calculateForecast();
    }

    function renderTable() {
        const tbody = document.querySelector('#tableItens tbody');
        tbody.innerHTML = '';

        const disabledAttr = isViewOnly ? 'disabled' : '';
        const bgClass = isViewOnly ? 'bg-base-200' : '';

        itens.forEach((it, idx) => {
            const tr = document.createElement('tr');
            const btnHtml = isViewOnly ? '' : `
                <button type="button" class="btn btn-ghost btn-xs text-error" onclick="removeItem(${it.id})">
                    <i class="bi bi-trash"></i>
                </button>
            `;

            tr.innerHTML = `
                <td class="text-center">
                    ${btnHtml}
                </td>
                <td class="font-mono text-xs">${it.mnemonico}</td>
                <td class="text-sm">${it.nome}</td>
                <td class="text-xs text-gray-500">${it.material}</td>
                <td class="text-xs text-gray-500">${it.laboratorio}</td>
                <td class="font-mono text-right">${formatMoney(it.valor)}</td>
                <td><input type="date" class="input input-bordered input-xs w-full ${bgClass}" value="${it.data_coleta}" onchange="updateItem(${idx}, 'data_coleta', this.value)" ${disabledAttr}></td>
                <td><input type="time" class="input input-bordered input-xs w-full ${bgClass}" value="${it.hora_coleta}" onchange="updateItem(${idx}, 'hora_coleta', this.value)" ${disabledAttr}></td>
            `;
            tbody.appendChild(tr);
        });

        // Update hidden input for submission
        document.getElementById('inputItens').value = JSON.stringify(itens.map(it => ({
            exame_id: it.id,
            valor: it.valor,
            data_coleta: it.data_coleta,
            hora_coleta: it.hora_coleta,
            laboratorio_id: it.laboratorio_id
        })));
    }

    function updateItem(idx, field, val) {
        itens[idx][field] = val;
        document.getElementById('inputItens').value = JSON.stringify(itens.map(it => ({
            exame_id: it.id,
            valor: it.valor,
            data_coleta: it.data_coleta,
            hora_coleta: it.hora_coleta,
            laboratorio_id: it.laboratorio_id
        })));
        if (field === 'data_coleta') calculateForecast();
    }

    // Totals Logic
    function calculateTotals() {
        let total = 0;
        itens.forEach(i => total += i.valor);

        document.getElementById('displayBruto').value = formatMoney(total);

        const perc = parseFloat(document.getElementById('descPerc').value) || 0;
        let descVal = parseFloat(document.getElementById('descVal').value) || 0;

        // Priority to % if changed recently? Simple logic: if called from perc, update val. If from val, update perc.
        // But here we just recalc liquid based on current inputs.
        // We need to know which one drove the change.
        // For simplicity in this function, we assume the inputs are consistent.

        let liquid = total - descVal;
        if (liquid < 0) liquid = 0;

        document.getElementById('displayLiquido').value = formatMoney(liquid);

        checkDiscount();
    }

    function checkDiscount() {
        const perc = parseFloat(document.getElementById('descPerc').value) || 0;
        
        const parceiroSelect = document.getElementById('parceiroSelect');
        const campanhaSelect = document.getElementById('campanhaSelect');
        
        let maxDescParceiro = 0;
        let hasParceiro = false;
        if (parceiroSelect && parceiroSelect.value) {
            hasParceiro = true;
            maxDescParceiro = parseFloat(parceiroSelect.options[parceiroSelect.selectedIndex].getAttribute('data-desconto')) || 0;
        }
        
        let maxDescCampanha = 0;
        let hasCampanha = false;
        if (campanhaSelect && campanhaSelect.value) {
            hasCampanha = true;
            maxDescCampanha = parseFloat(campanhaSelect.options[campanhaSelect.selectedIndex].getAttribute('data-desconto')) || 0;
        }

        const alertDiv = document.getElementById('alertDesconto');
        
        // Logic: If any entity is selected, we check against the higher limit of the selected ones.
        // If neither is selected, maybe we shouldn't allow any discount? Or default behavior?
        // Assuming default behavior is no limit check if no partner/campaign (or manual override).
        // But if partner/campaign IS selected, we enforce limit.
        
        if (hasParceiro || hasCampanha) {
            const limit = Math.max(hasParceiro ? maxDescParceiro : 0, hasCampanha ? maxDescCampanha : 0);
            if (perc > limit) {
                alertDiv.classList.remove('hidden');
                alertDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Desconto maior que o permitido! (Limite: ${limit}%)`;
            } else {
                alertDiv.classList.add('hidden');
            }
        } else {
            alertDiv.classList.add('hidden');
        }
    }

    function updateTotalsFromPerc() {
        const total = itens.reduce((acc, i) => acc + i.valor, 0);
        const perc = parseFloat(document.getElementById('descPerc').value) || 0;
        const val = total * (perc / 100);
        document.getElementById('descVal').value = val.toFixed(2);
        calculateTotals();
    }

    function updateTotalsFromVal() {
        const total = itens.reduce((acc, i) => acc + i.valor, 0);
        const val = parseFloat(document.getElementById('descVal').value) || 0;
        const perc = total > 0 ? (val / total) * 100 : 0;
        document.getElementById('descPerc').value = perc.toFixed(2);
        calculateTotals();
    }

    function updatePrices() {
        // If covenant changes, we might want to reload prices?
        // For now, just clear items or warn.
        if (itens.length > 0) {
            if (confirm('Ao mudar o convênio, os preços podem mudar. Deseja limpar os exames?')) {
                itens = [];
                renderTable();
                calculateTotals();
            } else {
                // Revert selection?
            }
        }
    }

    function calculateForecast() {
        const input = document.getElementById('previsaoEntrega');
        if (!itens.length) {
            input.value = '';
            return;
        }

        let maxDate = null;

        itens.forEach(it => {
            if (!it.prazo) return;

            // Base date is collection date or today
            let baseDateStr = it.data_coleta || '<?= date('Y-m-d') ?>';
            const base = new Date(baseDateStr + 'T00:00:00');

            // Add days
            base.setDate(base.getDate() + it.prazo);

            if (!maxDate || base > maxDate) {
                maxDate = base;
            }
        });

        if (maxDate) {
            const d = String(maxDate.getDate()).padStart(2, '0');
            const m = String(maxDate.getMonth() + 1).padStart(2, '0');
            const y = maxDate.getFullYear();
            input.value = `${d}/${m}/${y}`;
        } else {
            input.value = '';
        }
    }
</script>