<div class="p-6 bg-base-100 rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-base-content">Resultados Texto</h1>
        <button onclick="modal_resultado.showModal(); limparForm()" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo
        </button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success shadow-lg mb-6">
            <i class="bi bi-check-circle text-xl"></i>
            <span><?= htmlspecialchars($_GET['success']) ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error shadow-lg mb-6">
            <i class="bi bi-exclamation-triangle text-xl"></i>
            <span><?= htmlspecialchars($_GET['error']) ?></span>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <th class="w-16">ID</th>
                    <th class="w-24">Código</th>
                    <th>Texto</th>
                    <th class="w-32 text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lista)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-8 text-base-content/70">
                            Nenhum registro encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lista as $row): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= htmlspecialchars($row['codigo_texto'] ?? '') ?></td>
                            <td class="whitespace-pre-wrap"><?= htmlspecialchars($row['texto']) ?></td>
                            <td class="text-center">
                                <div class="join">
                                    <button 
                                        type="button"
                                        class="btn btn-sm btn-ghost join-item tooltip" 
                                        data-tip="Editar"
                                        onclick='editarRegistro(<?= json_encode($row) ?>)'
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="index.php?r=resultados_texto/delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-ghost text-error join-item tooltip" data-tip="Excluir" onclick="return confirm('Tem certeza que deseja excluir este registro?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<dialog id="modal_resultado" class="modal">
    <div class="modal-box w-11/12 max-w-2xl">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        
        <h3 class="font-bold text-lg mb-4" id="modal_titulo">Novo Resultado Texto</h3>
        
        <form method="POST" action="index.php?r=resultados_texto/store">
            <input type="hidden" name="id" id="form_id" value="0">
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Código</span>
                </label>
                <input type="text" name="codigo_texto" id="form_codigo" class="input input-bordered w-full bg-base-200" readonly placeholder="Gerado Automaticamente">
                <label class="label">
                    <span class="label-text-alt text-base-content/60">O código será gerado automaticamente ao salvar (sequencial).</span>
                </label>
            </div>

            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Texto Padrão</span>
                </label>
                <input type="text" name="texto" id="form_texto" class="input input-bordered w-full" required placeholder="Digite o texto padrão...">
                <label class="label">
                    <span class="label-text-alt text-base-content/60">Este texto aparecerá nas opções de preenchimento de resultados.</span>
                </label>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="modal_resultado.close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Salvar
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>fechar</button>
    </form>
</dialog>

<script>
function formatarCodigo(el) {
    let val = el.value.trim();
    if (val && /^\d+$/.test(val)) {
        el.value = val.padStart(4, '0');
    }
}

function limparForm() {
    document.getElementById('modal_titulo').innerText = 'Novo Resultado Texto';
    document.getElementById('form_id').value = '0';
    document.getElementById('form_codigo').value = 'Auto';
    document.getElementById('form_texto').value = '';
}

function editarRegistro(dados) {
    document.getElementById('modal_titulo').innerText = 'Editar Resultado Texto';
    document.getElementById('form_id').value = dados.id;
    document.getElementById('form_codigo').value = dados.codigo_texto || '';
    document.getElementById('form_texto').value = dados.texto;
    
    modal_resultado.showModal();
}

// Auto-open if URL has ID (legacy support / direct link)
<?php if (isset($registroAtual) && $registroAtual): ?>
    document.addEventListener('DOMContentLoaded', function() {
        editarRegistro(<?= json_encode($registroAtual) ?>);
    });
<?php endif; ?>
</script>
