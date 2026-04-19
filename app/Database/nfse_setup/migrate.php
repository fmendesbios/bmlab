<?php
// nfse/migrate.php

require_once __DIR__ . '/../config.php'; // Carrega config principal do BMLab para pegar DB

// Reusando a conexão $conn do config.php
if (!isset($conn) || $conn->connect_error) {
    die("Falha na conexão (config.php): " . ($conn->connect_error ?? 'Variável $conn não definida'));
}

echo "Conectado ao banco de dados via config.php.\n";

// Colunas a adicionar
$columns = [
    "ADD COLUMN nfse_numero VARCHAR(20) DEFAULT NULL AFTER observacoes",
    "ADD COLUMN nfse_serie VARCHAR(5) DEFAULT NULL AFTER nfse_numero",
    "ADD COLUMN nfse_lote VARCHAR(20) DEFAULT NULL AFTER nfse_serie",
    "ADD COLUMN nfse_codigo_verificacao VARCHAR(50) DEFAULT NULL AFTER nfse_lote",
    "ADD COLUMN nfse_status ENUM('PENDENTE', 'ENVIADO', 'AUTORIZADO', 'CANCELADO', 'ERRO') DEFAULT 'PENDENTE' AFTER nfse_codigo_verificacao",
    "ADD COLUMN nfse_data_emissao DATETIME DEFAULT NULL AFTER nfse_status",
    "ADD COLUMN nfse_xml_retorno TEXT DEFAULT NULL AFTER nfse_data_emissao"
];

foreach ($columns as $colSql) {
    // Verifica se a coluna já existe para evitar erro
    // Extrai o nome da coluna da string SQL
    preg_match('/ADD COLUMN (\w+)/', $colSql, $matches);
    $colName = $matches[1] ?? '';
    
    if ($colName) {
        $check = $conn->query("SHOW COLUMNS FROM pedidos LIKE '$colName'");
        if ($check && $check->num_rows > 0) {
            echo "Coluna '$colName' já existe. Pulando.\n";
            continue;
        }
    }

    $sql = "ALTER TABLE pedidos $colSql";
    if ($conn->query($sql) === TRUE) {
        echo "Sucesso: $colSql\n";
    } else {
        echo "Erro ao executar: $colSql\n" . $conn->error . "\n";
    }
}

$conn->close();
echo "Migração concluída.\n";
