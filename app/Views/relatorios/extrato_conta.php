<div class="card bg-base-100 shadow-xl mb-6">
    <div class="card-body">
        <div class="flex justify-between items-center mb-4">
            <h2 class="card-title text-2xl">Extrato de Conta</h2>
            <button type="button" class="btn btn-primary" id="btnImprimir">
                <i class="fas fa-print mr-2"></i>Imprimir
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Conta</span></label>
                <select class="select select-bordered w-full" id="selConta">
                    <?php foreach ($contas as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars(($c['banco'] ?? '') . ' - ' . ($c['agencia'] ?? '') . '/' . ($c['conta'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Data Inicial</span></label>
                <input type="date" class="input input-bordered w-full" id="dtIni">
            </div>
            <div class="form-control w-full">
                <label class="label"><span class="label-text">Data Final</span></label>
                <input type="date" class="input input-bordered w-full" id="dtFim">
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        function hojeISO() {
            var d = new Date();
            return d.toISOString().slice(0, 10);
        }

        function primeiroDoMesISO() {
            var d = new Date();
            d.setDate(1);
            return d.toISOString().slice(0, 10);
        }
        var ini = document.getElementById('dtIni');
        var fim = document.getElementById('dtFim');
        ini.value = primeiroDoMesISO();
        fim.value = hojeISO();
        var btn = document.getElementById('btnImprimir');
        var conta = document.getElementById('selConta');
        btn.addEventListener('click', function() {
            var cid = conta ? parseInt(conta.value, 10) : 0;
            var di = ini ? ini.value : '';
            var df = fim ? fim.value : '';
            if (!cid || cid <= 0) {
                alert('Selecione uma conta.');
                return;
            }
            if (!di || !df) {
                alert('Informe o período.');
                return;
            }
            var u = 'relatorios/extrato_conta_pdf.php?conta_id=' + cid + '&ini=' + encodeURIComponent(di) + '&fim=' + encodeURIComponent(df);
            window.open(u, '_blank');
        });
    })();
</script>
