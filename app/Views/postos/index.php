<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-pin-map"></i> Postos de Coleta</h1>
    <?php if (temPermissao('postos.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Posto
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
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <form method="GET" action="index.php" class="flex gap-2 w-full md:w-auto">
                <input type="hidden" name="r" value="postos">
                <div class="join w-full max-w-sm">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome, código ou CEP...">
                    <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
                </div>
                <?php if (!empty($busca)): ?>
                    <a href="index.php?r=postos" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>

            <!-- Pagination Controls -->
            <?php
            $start = ($totalRecords > 0) ? ($page - 1) * $limit + 1 : 0;
            $end = min($page * $limit, $totalRecords);
            $prev = max(1, $page - 1);
            $next = min($totalPages, $page + 1);
            $q = !empty($busca) ? '&busca=' . urlencode($busca) : '';
            ?>
            <div class="flex items-center gap-2 bg-base-200 p-2 rounded-lg">
                <div class="join">
                    <!-- First -->
                    <a href="index.php?r=postos&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=postos&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=postos&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=postos&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=postos&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-forward-fill"></i>
                    </a>
                </div>
                <div class="text-sm font-mono border-l border-base-content/20 pl-2">
                    <?= $start ?> - <?= $end ?> / <?= number_format($totalRecords, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th class="w-16">Cód</th>
                        <th>Nome</th>
                        <th>Endereço</th>
                        <th>Município</th>
                        <th class="w-24 text-center">Status</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($postos) > 0): ?>
                        <?php foreach($postos as $p): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($p['codigo_posto'] ?? str_pad($p['id'], 2, '0', STR_PAD_LEFT)) ?></td>
                            <td class="font-bold"><?= htmlspecialchars($p['nome_posto']) ?></td>
                            <td class="text-sm">
                                <?= htmlspecialchars($p['endereco'] ?? '') ?><?= !empty($p['numero']) ? ', ' . $p['numero'] : '' ?>
                                <?php if(!empty($p['bairro'])): ?>
                                    <br><span class="text-xs opacity-70"><?= htmlspecialchars($p['bairro']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['municipio_nome'] ?? '-') ?> <span class="text-xs opacity-70"><?= htmlspecialchars($p['municipio_uf'] ?? '') ?></span></td>
                            <td class="text-center">
                                <?php if($p['ativo']): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('postos.alterar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($p)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('postos.deletar')): ?>
                                    <a href="index.php?r=postos/excluir&id=<?= $p['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este posto?')" title="Excluir">
                                        <i class="bi bi-trash text-error"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-base-content/50">
                                <i class="bi bi-inbox text-4xl block mb-2"></i>
                                Nenhum posto encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Exibindo <?= count($postos) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_posto" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-3xl">
        <h3 class="font-bold text-lg" id="modal_title">Novo Posto</h3>
        <form method="POST" action="index.php?r=postos/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Código (Auto) -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_posto" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Auto" />
                </div>

                <!-- Nome -->
                <div class="form-control md:col-span-9">
                    <label class="label"><span class="label-text font-bold">Nome do Posto *</span></label>
                    <input type="text" name="nome_posto" id="input_nome" class="input input-bordered w-full" required />
                </div>

                <!-- CEP -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">CEP</span></label>
                    <input type="text" name="cep" id="input_cep" class="input input-bordered w-full" maxlength="9" oninput="maskCep(this)" />
                </div>

                <!-- Endereço -->
                <div class="form-control md:col-span-6">
                    <label class="label"><span class="label-text">Endereço</span></label>
                    <input type="text" name="endereco" id="input_endereco" class="input input-bordered w-full" />
                </div>

                <!-- Número -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Número</span></label>
                    <input type="text" name="numero" id="input_numero" class="input input-bordered w-full" />
                </div>

                <!-- Complemento -->
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Complemento</span></label>
                    <input type="text" name="complemento" id="input_complemento" class="input input-bordered w-full" />
                </div>

                <!-- Bairro -->
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Bairro</span></label>
                    <input type="text" name="bairro" id="input_bairro" class="input input-bordered w-full" />
                </div>

                <!-- Município -->
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Município</span></label>
                    <select name="municipio_id" id="input_municipio" class="select select-bordered w-full">
                        <option value="">Selecione...</option>
                        <?php foreach($municipios as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?> (<?= $m['uf'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ativo -->
                <div class="form-control md:col-span-12">
                    <label class="cursor-pointer label justify-start gap-4">
                        <span class="label-text">Ativo</span>
                        <input type="checkbox" name="ativo" id="input_ativo" class="toggle toggle-primary" checked />
                    </label>
                </div>

            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_posto').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function openModal() {
    document.getElementById('input_id').value = '0';
    document.getElementById('input_codigo').value = '';
    document.getElementById('input_nome').value = '';
    document.getElementById('input_cep').value = '';
    document.getElementById('input_endereco').value = '';
    document.getElementById('input_numero').value = '';
    document.getElementById('input_complemento').value = '';
    document.getElementById('input_bairro').value = '';
    document.getElementById('input_municipio').value = '';
    document.getElementById('input_ativo').checked = true;
    
    // Fetch Next Code
    fetch('index.php?r=postos/next_code')
        .then(res => res.json())
        .then(data => {
            if(data.code) {
                document.getElementById('input_codigo').value = data.code;
            }
        });

    document.getElementById('modal_title').innerText = 'Novo Posto';
    document.getElementById('modal_posto').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_codigo').value = data.codigo_posto || '';
    document.getElementById('input_nome').value = data.nome_posto;
    document.getElementById('input_cep').value = data.cep || '';
    document.getElementById('input_endereco').value = data.endereco || '';
    document.getElementById('input_numero').value = data.numero || '';
    document.getElementById('input_complemento').value = data.complemento || '';
    document.getElementById('input_bairro').value = data.bairro || '';
    document.getElementById('input_municipio').value = data.municipio_id || '';
    document.getElementById('input_ativo').checked = (data.ativo == 1);
    
    document.getElementById('modal_title').innerText = 'Editar Posto';
    document.getElementById('modal_posto').showModal();
}

function maskCep(el) {
    let v = el.value.replace(/\D/g,"");
    if (v.length > 8) v = v.substring(0,8);
    if (v.length > 5) v = v.replace(/(\d{5})(\d)/, "$1-$2");
    el.value = v;

    if (v.replace(/\D/g,"").length === 8) {
        buscaMunicipioPorCep(v);
    }
}

function buscaMunicipioPorCep(cep) {
    let plainCep = cep.replace(/\D/g, '');
    
    // First try local DB
    fetch('index.php?r=postos/busca_municipio&cep=' + plainCep)
        .then(response => response.json())
        .then(data => {
            if (data && data.id) {
                const select = document.getElementById('input_municipio');
                select.value = data.id;
            } else {
                // Not found, try ViaCEP
                fetch(`https://viacep.com.br/ws/${plainCep}/json/`)
                    .then(r => r.json())
                    .then(viaData => {
                        if (!viaData.erro) {
                            document.getElementById('input_endereco').value = viaData.logradouro;
                            document.getElementById('input_bairro').value = viaData.bairro;
                            
                            // Try to match municipio by name
                            let select = document.getElementById('input_municipio');
                            let nome = normalizeText(viaData.localidade);
                            let uf = viaData.uf;
                            
                            for (let i = 0; i < select.options.length; i++) {
                                let optText = normalizeText(select.options[i].text);
                                // Option format: "Nome (UF)"
                                if (optText.includes(nome) && select.options[i].text.includes(`(${uf})`)) {
                                    select.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    });
            }
        })
        .catch(error => console.error('Erro ao buscar município:', error));
}

function normalizeText(text) {
    return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}
</script>
