<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-people"></i> Pacientes em Campanhas/Programas</h1>
    <?php if (temPermissao('campanhas_programas.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Nova Associação
    </button>
    <?php endif; ?>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_success'])): ?>
    <div role="alert" class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i>
        <span><?= $_SESSION['flash_success'] ?></span>
        <?php unset($_SESSION['flash_success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div role="alert" class="alert alert-error mb-4">
        <i class="bi bi-exclamation-circle"></i>
        <span><?= $_SESSION['flash_error'] ?></span>
        <?php unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>

<!-- Search & Table -->
<div class="card bg-base-100 shadow-xl">
    <div class="card-body p-4 md:p-6">
        <!-- Search Form -->
        <form method="GET" action="index.php" class="flex gap-2 mb-6">
            <input type="hidden" name="r" value="pacientes_campanhas">
            <div class="join w-full max-w-sm">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar paciente ou campanha..." class="input input-bordered join-item w-full" />
                <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Campanha/Programa</th>
                        <th>Data Associação</th>
                        <th class="text-center" width="100">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($associacoes)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">Nenhuma associação encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($associacoes as $assoc): ?>
                            <tr>
                                <td>
                                    <div class="font-bold"><?= htmlspecialchars($assoc['paciente_nome']) ?></div>
                                    <div class="text-xs opacity-50"><?= htmlspecialchars($assoc['codigo_paciente'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="font-bold"><?= htmlspecialchars($assoc['nome_campanha_programa']) ?></div>
                                    <div class="text-xs opacity-50"><?= htmlspecialchars($assoc['codigo_campanha_programa'] ?? '') ?></div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($assoc['criado_em'])) ?></td>
                                <td class="text-center">
                                    <?php if (temPermissao('campanhas_programas.excluir')): ?>
                                        <a href="index.php?r=pacientes_campanhas/delete&id=<?= $assoc['id'] ?>" 
                                           class="btn btn-ghost btn-xs text-error tooltip" 
                                           data-tip="Remover Associação"
                                           onclick="return confirm('Tem certeza que deseja remover esta associação?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="join flex justify-center mt-6">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="index.php?r=pacientes_campanhas&page=<?= $i ?>&busca=<?= urlencode($busca) ?>" 
                       class="join-item btn <?= $page == $i ? 'btn-active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nova Associação -->
<dialog id="modal_associacao" class="modal">
    <div class="modal-box w-11/12 max-w-2xl overflow-visible">
        <h3 class="font-bold text-lg mb-4">Nova Associação</h3>
        <form id="formAssociacao" onsubmit="salvarAssociacao(event)">
            <div class="grid grid-cols-1 gap-4">
                
                <!-- Campanha -->
                <div class="form-control">
                    <label class="label"><span class="label-text">Campanha/Programa</span></label>
                    <select name="campanha_id" id="campanha_id" class="select select-bordered w-full" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($campanhas as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_campanha_programa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Paciente Search -->
                <div class="form-control relative">
                    <label class="label"><span class="label-text">Paciente</span></label>
                    <input type="hidden" name="paciente_id" id="paciente_id" required>
                    <div class="join w-full">
                        <input type="text" id="paciente_search" 
                               class="input input-bordered w-full join-item" 
                               placeholder="Digite o nome, CPF ou código do paciente..." 
                               autocomplete="off">
                        <button type="button" class="btn btn-square join-item" onclick="clearPaciente()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <!-- Results Dropdown -->
                    <ul id="paciente_results" class="menu bg-base-100 w-full rounded-box shadow-xl absolute top-full left-0 z-50 hidden max-h-60 overflow-y-auto border border-base-300">
                        <!-- Items will be injected here -->
                    </ul>
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
let searchTimeout = null;

function openModal() {
    document.getElementById('modal_associacao').showModal();
    document.getElementById('formAssociacao').reset();
    clearPaciente();
}

function closeModal() {
    document.getElementById('modal_associacao').close();
}

function clearPaciente() {
    document.getElementById('paciente_id').value = '';
    document.getElementById('paciente_search').value = '';
    document.getElementById('paciente_results').classList.add('hidden');
    document.getElementById('paciente_search').disabled = false;
}

// Search Logic
document.getElementById('paciente_search').addEventListener('input', function(e) {
    const term = e.target.value.trim();
    const resultsList = document.getElementById('paciente_results');
    
    if (searchTimeout) clearTimeout(searchTimeout);
    
    if (term.length < 3) {
        resultsList.classList.add('hidden');
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch(`index.php?r=pacientes_campanhas/search_pacientes&q=${encodeURIComponent(term)}`)
            .then(res => res.json())
            .then(data => {
                resultsList.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.innerHTML = `<div class="flex flex-col">
                                        <span class="font-bold">${p.text}</span>
                                        <span class="text-xs opacity-70">Nasc: ${formatDate(p.data.nascimento)}</span>
                                       </div>`;
                        a.onclick = () => selectPaciente(p);
                        li.appendChild(a);
                        resultsList.appendChild(li);
                    });
                    resultsList.classList.remove('hidden');
                } else {
                    resultsList.classList.add('hidden');
                }
            })
            .catch(err => console.error(err));
    }, 300);
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return dateStr;
}

function selectPaciente(paciente) {
    document.getElementById('paciente_id').value = paciente.id;
    document.getElementById('paciente_search').value = paciente.text;
    document.getElementById('paciente_search').disabled = true; // Lock input
    document.getElementById('paciente_results').classList.add('hidden');
}

function salvarAssociacao(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch('index.php?r=pacientes_campanhas/store', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Erro ao salvar.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro de conexão.');
    });
}
</script>
