<?php
require_once '../config.php';
require_once '../auth_permissoes.php';
verificaLogin();

$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

// Contar total
$total_regs = 0;
if ($res = $conn->query("SELECT COUNT(*) FROM logs_envio")) {
    $total_regs = $res->fetch_row()[0];
}
$total_paginas = ceil($total_regs / $limite);

// Buscar logs
$logs = [];
if ($res = $conn->query("SELECT * FROM logs_envio ORDER BY criado_em DESC LIMIT $offset, $limite")) {
    $logs = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="bmlab">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Envio</title>
    <link href="../public/css/app.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-base-100 p-4">

<div class="overflow-x-auto">
    <table class="table table-xs table-zebra w-full">
        <thead>
            <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Nome</th>
                <th>Telefone</th>
                <th>Status</th>
                <th>Retorno</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" class="text-center text-gray-500">Nenhum log encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></td>
                        <td><?= htmlspecialchars($log['tipo']) ?></td>
                        <td><?= htmlspecialchars($log['nome']) ?></td>
                        <td><?= htmlspecialchars($log['telefone']) ?></td>
                        <td>
                            <?php if ($log['status'] === 'sucesso'): ?>
                                <span class="badge badge-success badge-xs">Sucesso</span>
                            <?php else: ?>
                                <span class="badge badge-error badge-xs"><?= htmlspecialchars($log['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="max-w-xs truncate text-xs" title="<?= htmlspecialchars($log['retorno']) ?>">
                            <?= htmlspecialchars($log['retorno']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_paginas > 1): ?>
    <div class="flex justify-center mt-4">
        <div class="join">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?pagina=<?= $i ?>" class="join-item btn btn-sm <?= $i == $pagina ? 'btn-active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
