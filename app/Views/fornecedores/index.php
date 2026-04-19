<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-box-seam"></i> Fornecedores</h1>
    <?php if (temPermissao('fornecedores.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Fornecedor
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
                <input type="hidden" name="r" value="fornecedores">
                <div class="join w-full max-w-sm">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Nome, CNPJ ou Código...">
                    <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
                </div>
                <?php if (!empty($busca)): ?>
                    <a href="index.php?r=fornecedores" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
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
                    <a href="index.php?r=fornecedores&page=1<?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-skip-backward-fill"></i>
                    </a>
                    <!-- Prev -->
                    <a href="index.php?r=fornecedores&page=<?= $prev ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page <= 1) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-left-fill"></i>
                    </a>
                    <!-- Refresh -->
                    <a href="index.php?r=fornecedores&page=<?= $page ?><?= $q ?>" class="join-item btn btn-sm btn-ghost">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                    <!-- Next -->
                    <a href="index.php?r=fornecedores&page=<?= $next ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                    <!-- Last -->
                    <a href="index.php?r=fornecedores&page=<?= $totalPages ?><?= $q ?>" class="join-item btn btn-sm btn-ghost <?= ($page >= $totalPages) ? 'btn-disabled' : '' ?>">
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
                        <th class="w-20">Cód</th>
                        <th>Nome</th>
                        <th>CNPJ / Contato</th>
                        <th>Localização</th>
                        <th class="text-center">Status</th>
                        <th class="w-32 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($fornecedores) > 0): ?>
                        <?php foreach($fornecedores as $f): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($f['codigo_fornecedor'] ?? str_pad($f['id'], 7, '0', STR_PAD_LEFT)) ?></td>
                            <td class="font-bold"><?= htmlspecialchars($f['nome']) ?></td>
                            <td class="text-sm">
                                <?php if($f['cnpj']): ?>
                                    <div class="font-mono text-xs"><?= htmlspecialchars($f['cnpj']) ?></div>
                                <?php endif; ?>
                                <?php if($f['celular']): ?>
                                    <div class="text-xs opacity-70"><i class="bi bi-telephone"></i> <?= htmlspecialchars($f['celular']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm">
                                <?= htmlspecialchars($f['municipio_nome'] ?? '-') ?> <span class="text-xs opacity-70"><?= htmlspecialchars($f['municipio_uf'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                                <?php if($f['ativo']): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('fornecedores.alterar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($f)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('fornecedores.deletar')): ?>
                                    <a href="index.php?r=fornecedores/excluir&id=<?= $f['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este fornecedor?')" title="Excluir">
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
                                Nenhum fornecedor encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Exibindo <?= count($fornecedores) ?> de <?= number_format($totalRecords, 0, ',', '.') ?> registros
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_fornecedor" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-4xl">
        <h3 class="font-bold text-lg" id="modal_title">Novo Fornecedor</h3>
        <form method="POST" action="index.php?r=fornecedores/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Código (Auto) -->
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_fornecedor" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Auto" />
                </div>

                <!-- Nome -->
                <div class="form-control md:col-span-7">
                    <label class="label"><span class="label-text font-bold">Nome do Fornecedor *</span></label>
                    <input type="text" name="nome" id="input_nome" class="input input-bordered w-full" required />
                </div>

                <!-- CNPJ -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">CNPJ</span></label>
                    <input type="text" name="cnpj" id="input_cnpj" class="input input-bordered w-full" oninput="maskCnpj(this)" maxlength="18" />
                </div>

                <!-- CEP -->
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">CEP</span></label>
                    <input type="text" name="cep" id="input_cep" class="input input-bordered w-full" maxlength="9" oninput="maskCep(this); buscarCep(this.value)" />
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

                <!-- Celular -->
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Celular</span></label>
                    <input type="text" name="celular" id="input_celular" class="input input-bordered w-full" oninput="maskPhone(this)" maxlength="15" />
                </div>

                <!-- Ativo -->
                <div class="form-control md:col-span-12">
                    <label class="cursor-pointer label justify-start gap-4 mt-4">
                        <span class="label-text">Ativo</span>
                        <input type="checkbox" name="ativo" id="input_ativo" class="toggle toggle-primary" checked />
                    </label>
                </div>

            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('modal_fornecedor').close()">Cancelar</button>
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
    document.getElementById('input_cnpj').value = '';
    document.getElementById('input_cep').value = '';
    document.getElementById('input_endereco').value = '';
    document.getElementById('input_numero').value = '';
    document.getElementById('input_bairro').value = '';
    document.getElementById('input_municipio').value = '';
    document.getElementById('input_celular').value = '';
    document.getElementById('input_ativo').checked = true;
    
    // Fetch Next Code
    fetch('index.php?r=fornecedores/next_code')
        .then(res => res.json())
        .then(data => {
            if(data.code) {
                document.getElementById('input_codigo').value = data.code;
            }
        });

    document.getElementById('modal_title').innerText = 'Novo Fornecedor';
    document.getElementById('modal_fornecedor').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_codigo').value = data.codigo_fornecedor || '';
    document.getElementById('input_nome').value = data.nome;
    document.getElementById('input_cnpj').value = data.cnpj || '';
    document.getElementById('input_cep').value = data.cep || '';
    document.getElementById('input_endereco').value = data.endereco || '';
    document.getElementById('input_numero').value = data.numero || '';
    document.getElementById('input_bairro').value = data.bairro || '';
    document.getElementById('input_municipio').value = data.municipio_id || '';
    document.getElementById('input_celular').value = data.celular || '';
    document.getElementById('input_ativo').checked = (data.ativo == 1);
    
    document.getElementById('modal_title').innerText = 'Editar Fornecedor';
    document.getElementById('modal_fornecedor').showModal();
}

function maskCep(el) {
    let v = el.value.replace(/\D/g,"");
    if (v.length > 8) v = v.substring(0,8);
    if (v.length > 5) v = v.replace(/(\d{5})(\d)/, "$1-$2");
    el.value = v;
}

function buscarCep(cep) {
    let plainCep = cep.replace(/\D/g, '');
    if (plainCep.length === 8) {
        // First try local DB
        fetch('index.php?r=fornecedores/busca_municipio&cep=' + plainCep)
            .then(r => r.json())
            .then(data => {
                if (data) {
                    // Found in local DB
                    document.getElementById('input_municipio').value = data.id;
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
            });
    }
}

function normalizeText(text) {
    return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function maskCnpj(el) {
    let v = el.value.replace(/\D/g,"");
    if (v.length > 14) v = v.substring(0,14);
    if (v.length > 12) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
    else if (v.length > 8) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})/, "$1.$2.$3/$4");
    else if (v.length > 5) v = v.replace(/^(\d{2})(\d{3})(\d{3})/, "$1.$2.$3");
    else if (v.length > 2) v = v.replace(/^(\d{2})(\d{3})/, "$1.$2");
    el.value = v;
}

function maskPhone(el) {
    let v = el.value.replace(/\D/g,"");
    if (v.length > 11) v = v.substring(0,11);
    if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
    else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3");
    else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5})/, "($1) $2");
    el.value = v;
}
</script>
