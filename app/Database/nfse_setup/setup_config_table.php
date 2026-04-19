<?php
// nfse/setup_config_table.php
require_once __DIR__ . '/../config.php'; // DB connection ($conn)

$sql = "
CREATE TABLE IF NOT EXISTS nfse_config (
    id INT PRIMARY KEY DEFAULT 1,
    ambiente VARCHAR(20) DEFAULT 'homologacao',
    
    h_url_wsdl VARCHAR(255) DEFAULT 'https://www.janelaunica.net.br:8443/wsju/v09/NfseWS?wsdl',
    h_url_service VARCHAR(255) DEFAULT 'https://www.janelaunica.net.br:8443/wsju/v09/NfseWS',
    h_usuario VARCHAR(100) DEFAULT '6001232645',
    h_senha VARCHAR(100) DEFAULT 'bml22122022',
    h_im VARCHAR(50) DEFAULT '3003410',
    
    p_url_wsdl VARCHAR(255) DEFAULT 'https://www.janelaunica.srv.br:8443/wsju/v09/NfseWS?wsdl',
    p_url_service VARCHAR(255) DEFAULT 'https://www.janelaunica.srv.br:8443/wsju/v09/NfseWS',
    p_usuario VARCHAR(100) DEFAULT '6001232645',
    p_senha VARCHAR(100) DEFAULT 'bml22122022',
    p_im VARCHAR(50) DEFAULT '3003410',
    
    cnpj_prestador VARCHAR(20) DEFAULT '35391309000185',
    codigo_municipio_prestador VARCHAR(10) DEFAULT '1506195',
    ultimo_rps INT DEFAULT 0,
    
    serie_rps VARCHAR(10) DEFAULT '1',
    tipo_rps INT DEFAULT 1
) ENGINE=InnoDB;
";

if ($conn->query($sql) === TRUE) {
    echo "Tabela nfse_config criada ou já existente.<br>";
} else {
    echo "Erro ao criar tabela: " . $conn->error . "<br>";
}

// Inserir registro padrão se não existir
$check = $conn->query("SELECT id FROM nfse_config WHERE id = 1");
if ($check->num_rows == 0) {
    $sqlInsert = "INSERT INTO nfse_config (id) VALUES (1)";
    if ($conn->query($sqlInsert) === TRUE) {
        echo "Configuração padrão inserida.<br>";
    } else {
        echo "Erro ao inserir configuração padrão: " . $conn->error . "<br>";
    }
} else {
    echo "Configuração já existe.<br>";
}
?>
