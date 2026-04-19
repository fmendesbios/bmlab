<?php
// app/Views/orcamentos/crm_content.php
// Expects $history (array) and $orcamento (array)
$statusMap = [
    'N' => ['label' => 'Novo', 'class' => 'badge-info'],
    'NE' => ['label' => 'Negociação', 'class' => 'badge-warning'],
    'P' => ['label' => 'Perdemos', 'class' => 'badge-error'],
    'G' => ['label' => 'Ganhamos', 'class' => 'badge-success'],
];
$st = $orcamento['status'] ?? 'N';
$curStatus = $statusMap[$st] ?? ['label' => $st, 'class' => 'badge-ghost'];
?>

<div class="flex justify-between items-center mb-4 p-4 bg-base-200 rounded-lg">
    <div>
        <h4 class="font-bold">Orçamento #<?= htmlspecialchars($orcamento['codigo_orcamento'] ?? $orcamento['id']) ?></h4>
        <span class="badge <?= $curStatus['class'] ?>"><?= $curStatus['label'] ?></span>
    </div>
</div>

<div class="divider">Histórico</div>

<div class="h-64 overflow-y-auto mb-4 border rounded p-2 bg-base-100">
    <?php if (empty($history)): ?>
        <p class="text-center text-gray-500 mt-4">Nenhum registro encontrado.</p>
    <?php else: ?>
        <?php foreach ($history as $h): ?>
            <div class="chat chat-start">
                <div class="chat-header">
                    <?= htmlspecialchars($h['usuario_nome'] ?? 'Sistema') ?>
                    <time class="text-xs opacity-50"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></time>
                </div>
                <div class="chat-bubble <?= ($h['tipo'] === 'S' ? 'chat-bubble-warning' : 'chat-bubble-primary') ?>">
                    <?= nl2br(htmlspecialchars($h['mensagem'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<form id="formCRM" onsubmit="event.preventDefault();">
    <input type="hidden" name="orcamento_id" value="<?= (int)$orcamento['id'] ?>">
    <input type="hidden" name="acao" id="acaoCRM" value="comentario">
    
    <div class="form-control mb-4">
        <label class="label"><span class="label-text">Nova Anotação</span></label>
        <textarea class="textarea textarea-bordered h-24" name="mensagem" id="mensagemCRM" placeholder="Digite aqui..."></textarea>
    </div>

    <div class="form-control mb-4 hidden" id="divMotivo">
        <label class="label"><span class="label-text text-error">Motivo da Perda *</span></label>
        <select name="motivo_id" id="motivoCRM" class="select select-bordered select-error w-full">
            <option value="">Selecione um motivo...</option>
            <?php if (!empty($motivos)): ?>
                <?php foreach ($motivos as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['motivo']) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="flex justify-between items-center">
        <button type="button" class="btn" onclick="document.getElementById('modal_crm').close()">Fechar</button>

        <div class="flex gap-2">
            <button type="button" class="btn btn-primary" onclick="submitCRM('comentario')">
                    <i class="bi bi-save"></i> Salvar Anotação
                </button>
                
                <?php if ($st === 'P'): ?>
                    <button type="button" class="btn btn-outline btn-warning" onclick="clickContinuar()">
                        <i class="bi bi-arrow-counterclockwise"></i> Continuar Negociação
                    </button>
                <?php endif; ?>
                
                <?php if ($st !== 'P' && $st !== 'G'): ?>
                    <button type="button" class="btn btn-outline btn-error" onclick="clickPerdemos()">
                        <i class="bi bi-hand-thumbs-down"></i> Perdemos
                    </button>
                <?php endif; ?>
        </div>
    </div>
</form>
