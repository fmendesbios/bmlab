<div class="container mx-auto max-w-2xl mt-10">
    <div class="card bg-base-100 shadow-xl border border-error">
        <div class="card-body">
            <h2 class="card-title text-error mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i> Cancelamento de NFS-e
            </h2>

            <div class="alert alert-warning text-sm shadow-sm mb-6">
                <span>
                    <i class="bi bi-info-circle"></i> Atenção: O cancelamento é irreversível e deve ser realizado apenas em casos específicos permitidos pela legislação.
                </span>
            </div>

            <div class="bg-base-200 p-4 rounded-lg mb-6 text-sm">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="block opacity-70">Número NFS-e</span>
                        <span class="font-bold"><?= $nota['numero_nfse'] ?? $nota['nfse_numero'] ?></span>
                    </div>
                    <div>
                        <span class="block opacity-70">Série</span>
                        <span class="font-bold">1</span> <!-- Série fixa por enquanto -->
                    </div>
                    <div class="col-span-2">
                        <span class="block opacity-70">Chave de Acesso</span>
                        <span class="font-mono break-all select-all"><?= $chave_acesso ?></span>
                    </div>
                    <div>
                        <span class="block opacity-70">Tomador</span>
                        <span class="font-bold"><?= $nota['paciente_nome'] ?? $nota['tomador_nome'] ?></span>
                    </div>
                    <div>
                        <span class="block opacity-70">Valor</span>
                        <span class="font-bold text-success">R$ <?= number_format($nota['total_liquido'] ?? $nota['valor_liquido'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <form action="index.php?r=nfse/cancelar&id=<?= $nota['id'] ?>&source=<?= $source ?>" method="POST" onsubmit="return confirm('Confirma o cancelamento definitivo desta nota?');">
                <input type="hidden" name="confirmar" value="1">
                
                <div class="form-control w-full mb-4">
                    <label class="label">
                        <span class="label-text font-bold">Código de Cancelamento</span>
                    </label>
                    <select name="codigo_cancelamento" class="select select-bordered w-full" required>
                        <option value="" disabled selected>Selecione o motivo...</option>
                        <option value="1">1 - Erro na emissão</option>
                        <option value="2">2 - Serviço não prestado</option>
                        <option value="3">3 - Erro de assinatura</option>
                        <option value="4">4 - Duplicidade da nota</option>
                        <option value="9">9 - Outros</option>
                    </select>
                </div>

                <div class="form-control w-full mb-6">
                    <label class="label">
                        <span class="label-text font-bold">Justificativa</span>
                        <span class="label-text-alt text-error">* Obrigatório (mín. 15 caracteres)</span>
                    </label>
                    <textarea name="motivo" class="textarea textarea-bordered h-24" placeholder="Descreva o motivo do cancelamento..." required minlength="15"></textarea>
                </div>

                <div class="card-actions justify-end gap-2">
                    <a href="index.php?r=nfse/notas_expedidas" class="btn btn-ghost">Voltar</a>
                    <button type="submit" class="btn btn-error text-white">
                        <i class="bi bi-x-circle"></i> Confirmar Cancelamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>