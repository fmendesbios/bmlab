<div class="max-w-2xl mx-auto p-6 bg-base-100 rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-base-content">Nova Transferência</h1>
        <a href="index.php?r=transferencias" class="btn btn-ghost gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            Voltar
        </a>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error shadow-lg mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span><?= htmlspecialchars($_GET['error']) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?r=transferencias/store" id="formTransfer" class="space-y-6">
        
        <div class="form-control">
            <label class="label">
                <span class="label-text">Conta Origem</span>
                <span class="label-text-alt" id="saldo-origem">Saldo: R$ 0,00</span>
            </label>
            <select name="origem_id" id="origem_id" class="select select-bordered w-full" required onchange="checkBalance(this.value)">
                <option value="">Selecione...</option>
                <?php foreach ($contas as $conta): ?>
                    <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['banco'] . ' - Ag: ' . $conta['agencia'] . ' - CC: ' . $conta['conta']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-control">
            <label class="label">
                <span class="label-text">Conta Destino</span>
            </label>
            <select name="destino_id" id="destino_id" class="select select-bordered w-full" required>
                <option value="">Selecione...</option>
                <?php foreach ($contas as $conta): ?>
                    <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['banco'] . ' - Ag: ' . $conta['agencia'] . ' - CC: ' . $conta['conta']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Valor (R$)</span>
                </label>
                <input type="text" name="valor" class="input input-bordered w-full" placeholder="0,00" required onkeyup="formatMoeda(this)">
            </div>

            <div class="form-control">
                <label class="label">
                    <span class="label-text">Data</span>
                </label>
                <input type="date" name="data" value="<?= date('Y-m-d') ?>" class="input input-bordered w-full" required>
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-8">
            <a href="index.php?r=transferencias" class="btn btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                    <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
                </svg>
                Confirmar Transferência
            </button>
        </div>
    </form>
</div>

<script>
function formatMoeda(i) {
    var v = i.value.replace(/\D/g,'');
    v = (v/100).toFixed(2) + '';
    v = v.replace(".", ",");
    v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    i.value = v;
}

function checkBalance(contaId) {
    if (!contaId) {
        document.getElementById('saldo-origem').textContent = 'Saldo: R$ 0,00';
        return;
    }

    fetch('index.php?r=transferencias/get_balance&id=' + contaId)
        .then(response => response.json())
        .then(data => {
            const saldo = parseFloat(data.balance).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('saldo-origem').textContent = 'Saldo: R$ ' + saldo;
            
            // Optional: style if negative
            const label = document.getElementById('saldo-origem');
            if (data.balance < 0) label.classList.add('text-error');
            else label.classList.remove('text-error');
        })
        .catch(err => console.error(err));
}

document.getElementById('formTransfer').addEventListener('submit', function(e) {
    const origem = document.getElementById('origem_id').value;
    const destino = document.getElementById('destino_id').value;
    
    if (origem === destino) {
        e.preventDefault();
        alert('A conta de origem e destino não podem ser iguais.');
        return false;
    }
});
</script>
