<?php
// app/Views/relatorios/situacao_pedidos.php
?>
<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title text-primary mb-4">
            <i class="bi bi-file-earmark-text me-2"></i>Situação dos Pedidos
        </h2>

        <div class="flex flex-wrap items-end gap-4">
            <div class="form-control w-full max-w-xs">
                <label class="label"><span class="label-text">Entrada Inicial</span></label>
                <input type="date" class="input input-bordered w-full" id="dtIni">
            </div>
            
            <div class="form-control w-full max-w-xs">
                <label class="label"><span class="label-text">Entrada Final</span></label>
                <input type="date" class="input input-bordered w-full" id="dtFim">
            </div>

            <button type="button" class="btn btn-primary" id="btnImprimir">
                <i class="bi bi-printer me-2"></i>Imprimir
            </button>
        </div>
    </div>
</div>

<script>
    (function(){
        function hojeISO(){ var d=new Date(); return d.toISOString().slice(0,10); }
        function primeiroDoMesISO(){ var d=new Date(); d.setDate(1); return d.toISOString().slice(0,10); }
        
        var ini = document.getElementById('dtIni');
        var fim = document.getElementById('dtFim');
        
        if (ini) ini.value = primeiroDoMesISO();
        if (fim) fim.value = hojeISO();
        
        var btn = document.getElementById('btnImprimir');
        if (btn) {
            btn.addEventListener('click', function(){
                var di = ini ? ini.value : '';
                var df = fim ? fim.value : '';
                if (!di || !df) {
                    alert('Informe o período.');
                    return;
                }
                // Use the MVC Controller route
                var u = 'index.php?r=relatorios/pedidos_situacao_pdf&ini=' + encodeURIComponent(di) + '&fim=' + encodeURIComponent(df);
                window.open(u, '_blank');
            });
        }
    })();
</script>
