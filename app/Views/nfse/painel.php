<div class="container mx-auto max-w-5xl">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-primary border-b pb-2 mb-4 flex items-center gap-2">
                <i class="bi bi-gear"></i> Configurações NFSe Nacional (SEFIN)
            </h2>

            <?php if ($mensagem): ?>
                <div role="alert" class="alert alert-success mb-4">
                    <i class="bi bi-check-circle"></i>
                    <span><?= $mensagem ?></span>
                </div>
            <?php endif; ?>
            <?php if ($erro): ?>
                <div role="alert" class="alert alert-error mb-4">
                    <i class="bi bi-x-circle"></i>
                    <span><?= $erro ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" action="index.php?r=nfse/salvar_config">
                <!-- Ambiente fixo em Produção -->
                <input type="hidden" name="ambiente" value="producao">

                <!-- Dados do Prestador -->
                <h3 class="font-bold text-lg mb-4 border-b pb-2">Dados do Prestador (Laboratório)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">CNPJ Prestador</span></label>
                        <input type="text" class="input input-bordered w-full" name="cnpj_prestador" value="<?= htmlspecialchars($dados['cnpj_prestador'] ?? '') ?>" placeholder="Apenas números">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Cód. Município (IBGE)</span></label>
                        <input type="text" class="input input-bordered w-full" name="codigo_municipio_prestador" value="<?= htmlspecialchars($dados['codigo_municipio_prestador'] ?? '') ?>" placeholder="Ex: 1234567">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Último DPS Utilizado</span></label>
                        <input type="number" class="input input-bordered w-full" name="ultimo_rps" value="<?= htmlspecialchars($dados['ultimo_rps'] ?? 0) ?>">
                        <label class="label"><span class="label-text-alt">O sistema incrementará automaticamente.</span></label>
                    </div>
                </div>

                <!-- URLs dos Ambientes -->
                <h3 class="font-bold text-lg mb-4 border-b pb-2">Endereços dos Serviços (URLs)</h3>
                <div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-6">
                    <input type="hidden" name="h_url_service" value="<?= htmlspecialchars($dados['h_url_service'] ?? '') ?>">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">URL Produção (Oficial)</span></label>
                        <input type="text" class="input input-bordered w-full" name="p_url_service" id="p_url_service" value="<?= htmlspecialchars($dados['p_url_service'] ?? 'https://sefin.nfse.gov.br/SefinNacional') ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <h3 class="font-bold text-lg mb-4 text-secondary flex items-center gap-2">
                        <i class="bi bi-shield-lock-fill"></i> Certificado Digital A1
                    </h3>
                    <div class="p-4 bg-base-200 rounded-box border border-secondary">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label"><span class="label-text font-bold">Arquivo do Certificado (.pfx)</span></label>
                                <input type="text" class="input input-bordered w-full" name="cert_arquivo" id="cert_arquivo" value="<?= htmlspecialchars($dados['cert_arquivo'] ?? '') ?>" placeholder="Caminho relativo (ex: certs/arquivo.pfx)">
                                <label class="label"><span class="label-text-alt text-warning">Deve estar na pasta do servidor</span></label>
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text font-bold">Senha do Certificado</span></label>
                                <input type="password" class="input input-bordered w-full" name="cert_senha" id="cert_senha" value="<?= htmlspecialchars($dados['cert_senha'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mt-4 flex justify-end">
                            <button type="button" id="btnTestarSefin" class="btn btn-secondary btn-sm gap-2">
                                <i class="bi bi-wifi"></i> Testar Conexão com SEFIN
                            </button>
                        </div>
                        <div id="resultadoTesteSefin" class="mt-4 hidden alert"></div>
                    </div>
                </div>
                
                <div class="mt-8 pt-4 border-t text-end">
                    <button type="submit" class="btn btn-success text-white px-8"><i class="bi bi-save"></i> Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('btnTestarSefin').addEventListener('click', function() {
    const btn = this;
    const resultDiv = document.getElementById('resultadoTesteSefin');
    const certFileInput = document.getElementById('cert_arquivo');
    const certPassInput = document.getElementById('cert_senha');
    const pUrlInput = document.getElementById('p_url_service');
    const hUrlInput = document.querySelector('input[name="h_url_service"]');

    const certFile = certFileInput ? certFileInput.value : '';
    const certPass = certPassInput ? certPassInput.value : '';
    const pUrl = pUrlInput ? pUrlInput.value : '';
    const hUrl = hUrlInput ? hUrlInput.value : '';

    const ambiente = 'producao';
    
    if (!certFile || !certPass) {
        alert('Por favor, preencha o arquivo e a senha do certificado para testar.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner"></span> Testando...';
    resultDiv.classList.add('hidden');
    resultDiv.className = 'mt-4 hidden alert';

    fetch('index.php?r=nfse/testar_sefin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'cert_arquivo=' + encodeURIComponent(certFile) + 
              '&cert_senha=' + encodeURIComponent(certPass) +
              '&h_url_service=' + encodeURIComponent(hUrl) +
              '&p_url_service=' + encodeURIComponent(pUrl) +
              '&ambiente=' + encodeURIComponent(ambiente)
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.classList.remove('hidden');
        if (data.success) {
            resultDiv.classList.add('alert-success');
            resultDiv.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
        } else {
            resultDiv.classList.add('alert-error');
            resultDiv.innerHTML = '<i class="bi bi-x-circle"></i> ' + data.message;
        }
    })
    .catch(error => {
        resultDiv.classList.remove('hidden');
        resultDiv.classList.add('alert-error');
        resultDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Erro na requisição: ' + error;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-wifi"></i> Testar Conexão com SEFIN';
    });
});
</script>
