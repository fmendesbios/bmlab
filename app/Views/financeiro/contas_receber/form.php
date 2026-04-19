<?php
// app/Views/financeiro/contas_receber/form.php
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold">Novo Lançamento (Receber)</h1>
        <div class="text-sm breadcrumbs">
            <ul>
                <li><a href="index.php?r=home">Home</a></li>
                <li><a href="index.php?r=contas_receber">Contas a Receber</a></li>
                <li>Novo</li>
            </ul>
        </div>
    </div>
</div>

<div class="card bg-base-100 shadow-xl max-w-4xl mx-auto">
    <div class="card-body">
        <form action="index.php?r=contas_receber/store" method="POST">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text font-bold">Tipo de Devedor</span></label>
                <div class="flex gap-6">
                    <label class="cursor-pointer label gap-2">
                        <input type="radio" name="tipo" value="PACIENTE" class="radio radio-primary" checked onchange="toggleTipo()">
                        <span class="label-text">Paciente</span>
                    </label>
                    <label class="cursor-pointer label gap-2">
                        <input type="radio" name="tipo" value="FORNECEDOR" class="radio radio-primary" onchange="toggleTipo()">
                        <span class="label-text">Fornecedor/Outro</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div class="form-control" id="divPaciente">
                    <label class="label"><span class="label-text">Paciente</span></label>
                    <select name="paciente_id" class="select select-bordered w-full">
                        <option value="">Selecione...</option>
                        <?php foreach ($pacientes as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control hidden" id="divFornecedor">
                    <label class="label"><span class="label-text">Fornecedor</span></label>
                    <select name="fornecedor_id" class="select select-bordered w-full">
                        <option value="">Selecione...</option>
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text">Descrição</span></label>
                    <input type="text" name="descricao" class="input input-bordered w-full" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="form-control">
                    <label class="label"><span class="label-text">Data Emissão</span></label>
                    <input type="date" name="emissao" value="<?= date('Y-m-d') ?>" class="input input-bordered w-full" required>
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text">Data Vencimento</span></label>
                    <input type="date" name="vencimento" value="<?= date('Y-m-d') ?>" class="input input-bordered w-full" required>
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text">Valor (R$)</span></label>
                    <input type="text" name="valor" class="input input-bordered w-full text-right" placeholder="0,00" required onkeyup="formatMoeda(this)">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="index.php?r=contas_receber" class="btn btn-ghost">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar Lançamento</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleTipo() {
        const tipo = document.querySelector('input[name="tipo"]:checked').value;
        if (tipo === 'PACIENTE') {
            document.getElementById('divPaciente').classList.remove('hidden');
            document.getElementById('divFornecedor').classList.add('hidden');
        } else {
            document.getElementById('divPaciente').classList.add('hidden');
            document.getElementById('divFornecedor').classList.remove('hidden');
        }
    }

    function formatMoeda(i) {
        var v = i.value.replace(/\D/g,'');
        v = (v/100).toFixed(2) + '';
        v = v.replace(".", ",");
        v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        i.value = v;
    }
</script>
