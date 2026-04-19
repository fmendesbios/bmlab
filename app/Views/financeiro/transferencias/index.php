<div class="p-6 bg-base-100 rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-base-content">Transferências Bancárias</h1>
        <a href="index.php?r=transferencias/create" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-right" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 11.5a.5.5 0 0 0 .5.5h11.793l-3.147 3.146a.5.5 0 0 0 .708.708l4-4a.5.5 0 0 0 0-.708l-4-4a.5.5 0 0 0-.708.708L13.293 11H1.5a.5.5 0 0 0-.5.5zm14-7a.5.5 0 0 0-.5-.5H2.707l3.147 3.146a.5.5 0 1 0 .708.708l-4-4a.5.5 0 0 0 0-.708l4-4a.5.5 0 1 0-.708.708L2.707 4H14.5a.5.5 0 0 0 .5-.5z"/>
            </svg>
            Nova Transferência
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success shadow-lg mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>Transferência registrada com sucesso.</span>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th class="text-right">Valor</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lista)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-8 text-base-content/70">
                            Nenhuma transferência registrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lista as $item): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($item['data'])) ?></td>
                            <td><?= htmlspecialchars($item['origem']) ?></td>
                            <td><?= htmlspecialchars($item['destino']) ?></td>
                            <td class="text-right font-medium text-primary">R$ <?= number_format($item['valor'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge badge-success badge-sm"><?= htmlspecialchars($item['status']) ?></span>
                            </td>
                            <td class="text-center">
                                <button type="button" onclick="estornar('<?= $item['base'] ?>')" class="btn btn-sm btn-ghost text-error tooltip" data-tip="Estornar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function estornar(base) {
    if (!confirm('Deseja realmente estornar esta transferência?')) return;
    
    const formData = new FormData();
    formData.append('base', base);
    
    fetch('index.php?r=transferencias/reverse', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            window.location.reload();
        } else {
            alert('Erro: ' + (data.erro || 'Falha desconhecida'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erro de conexão');
    });
}
</script>
