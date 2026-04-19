<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content"><i class="bi bi-briefcase"></i> Parceiros</h1>
    <?php if (temPermissao('parceiros.novo')): ?>
    <button class="btn btn-primary" onclick="openModal()">
        <i class="bi bi-plus-lg"></i> Novo Parceiro
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
            <input type="hidden" name="r" value="parceiros">
            <div class="join w-full max-w-sm">
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="input input-bordered join-item w-full" placeholder="Buscar por nome, código ou CNPJ...">
                <button type="submit" class="btn btn-primary join-item"><i class="bi bi-search"></i></button>
            </div>
            <?php if (!empty($busca)): ?>
                <a href="index.php?r=parceiros" class="btn btn-ghost" title="Limpar filtro"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>

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
                    <?php if (count($parceiros) > 0): ?>
                        <?php foreach($parceiros as $p): ?>
                        <tr class="hover">
                            <td class="font-mono"><?= htmlspecialchars($p['codigo_parceiro'] ?? str_pad($p['id'], 7, '0', STR_PAD_LEFT)) ?></td>
                            <td class="font-bold">
                                <?= htmlspecialchars($p['nome']) ?>
                                <?php if($p['desconto_permitido'] > 0): ?>
                                    <span class="badge badge-info badge-xs ml-2" title="Desconto Máx: <?= number_format($p['desconto_permitido'], 2, ',', '.') ?>%">Desc <?= number_format($p['desconto_permitido'], 0) ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm">
                                <?php if($p['cnpj']): ?>
                                    <div class="font-mono text-xs"><?= htmlspecialchars($p['cnpj']) ?></div>
                                <?php endif; ?>
                                <?php if($p['celular']): ?>
                                    <div class="text-xs opacity-70"><i class="bi bi-telephone"></i> <?= htmlspecialchars($p['celular']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm">
                                <?= htmlspecialchars($p['municipio_nome'] ?? '-') ?> <span class="text-xs opacity-70"><?= htmlspecialchars($p['municipio_uf'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                                <?php if($p['ativo']): ?>
                                    <div class="badge badge-success badge-sm">Ativo</div>
                                <?php else: ?>
                                    <div class="badge badge-ghost badge-sm">Inativo</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="join">
                                    <?php if (temPermissao('parceiros.alterar')): ?>
                                    <button class="btn btn-sm btn-ghost join-item" onclick="edit(<?= htmlspecialchars(json_encode($p)) ?>)" title="Editar">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (temPermissao('parceiros.deletar')): ?>
                                    <a href="index.php?r=parceiros/excluir&id=<?= $p['id'] ?>" class="btn btn-sm btn-ghost join-item" onclick="return confirm('Tem certeza que deseja excluir este parceiro?')" title="Excluir">
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
                                Nenhum parceiro encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-xs text-center mt-4 text-base-content/50">
            Total de registros: <?= count($parceiros) ?>
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_parceiro" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-4xl">
        <h3 class="font-bold text-lg" id="modal_title">Novo Parceiro</h3>
        <form method="POST" action="index.php?r=parceiros/salvar">
            <input type="hidden" name="id" id="input_id" value="0">
            
            <div class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Código (Auto) -->
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_parceiro" id="input_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Auto" />
                </div>

                <!-- Nome -->
                <div class="form-control md:col-span-7">
                    <label class="label"><span class="label-text font-bold">Nome do Parceiro *</span></label>
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

                <!-- Desconto -->
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Desconto Máx Permitido (%)</span></label>
                    <input type="number" step="0.01" min="0" max="100" name="desconto_permitido" id="input_desconto" class="input input-bordered w-full" />
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
                <button type="button" class="btn" onclick="document.getElementById('modal_parceiro').close()">Cancelar</button>
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
    document.getElementById('input_desconto').value = '0';
    document.getElementById('input_ativo').checked = true;
    
    // Fetch next code
    fetch('index.php?r=parceiros/next_code')
        .then(response => response.json())
        .then(data => {
            if (data.code) {
                document.getElementById('input_codigo').value = data.code;
            }
        })
        .catch(err => console.error('Erro ao buscar código:', err));

    document.getElementById('modal_title').innerText = 'Novo Parceiro';
    document.getElementById('modal_parceiro').showModal();
}

function edit(data) {
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_codigo').value = data.codigo_parceiro || '';
    document.getElementById('input_nome').value = data.nome;
    document.getElementById('input_cnpj').value = data.cnpj || '';
    document.getElementById('input_cep').value = data.cep || '';
    document.getElementById('input_endereco').value = data.endereco || '';
    document.getElementById('input_numero').value = data.numero || '';
    document.getElementById('input_bairro').value = data.bairro || '';
    document.getElementById('input_municipio').value = data.municipio_id || '';
    document.getElementById('input_celular').value = data.celular || '';
    document.getElementById('input_desconto').value = data.desconto_permitido || '0';
    document.getElementById('input_ativo').checked = (data.ativo == 1);
    
    document.getElementById('modal_title').innerText = 'Editar Parceiro';
    document.getElementById('modal_parceiro').showModal();
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
    fetch('index.php?r=parceiros/busca_municipio&cep=' + cep)
        .then(response => response.json())
        .then(data => {
            if (data && data.id) {
                const select = document.getElementById('input_municipio');
                select.value = data.id;
            }
        })
        .catch(error => console.error('Erro ao buscar município:', error));
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
