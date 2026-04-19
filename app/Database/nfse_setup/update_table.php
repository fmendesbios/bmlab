<?php
require_once __DIR__ . '/../config.php';

// Add columns if they don't exist
$colsToAdd = [
    'ultimo_rps' => 'INT DEFAULT 0',
    'cnpj_prestador' => 'VARCHAR(20) DEFAULT ""',
    'codigo_municipio_prestador' => 'VARCHAR(20) DEFAULT "0000000"' // IBGE Code of the lab
];

foreach ($colsToAdd as $col => $def) {
    try {
        $conn->query("ALTER TABLE nfse_config ADD COLUMN $col $def");
        echo "Coluna $col adicionada (ou erro se já existir).<br>";
    } catch (Exception $e) {
        // Ignore if exists
    }
}

echo "Atualização de tabela concluída.";
?>
