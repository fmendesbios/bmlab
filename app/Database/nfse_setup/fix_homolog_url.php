<?php
require_once 'd:/xampp/htdocs/bmlab/config.php';

if (isset($conn) && $conn instanceof mysqli) {
    $sql = "UPDATE nfse_config SET 
            h_url_wsdl = 'https://www.janelaunica.net.br:8443/wsju/v09/NfseWS?wsdl', 
            h_url_service = 'https://www.janelaunica.net.br:8443/wsju/v09/NfseWS' 
            WHERE id = 1 AND ambiente = 'homologacao' AND h_url_wsdl LIKE '%srv.br%'";
            
    if ($conn->query($sql) === TRUE) {
        if ($conn->affected_rows > 0) {
            echo "URL de homologação corrigida no banco de dados.\n";
        } else {
            echo "Nenhuma alteração necessária no banco de dados (URLs já estão corretas ou ambiente não é homologação).\n";
        }
    } else {
        echo "Erro ao atualizar banco: " . $conn->error . "\n";
    }
} else {
    echo "Erro de conexão com banco de dados.\n";
}
?>