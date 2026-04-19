<?php
// app/Views/pacientes/form.php
$isEdit = !empty($paciente['id']);

// Parse Celular to split Country Code
$celularFull = $paciente['celular'] ?? '';
$celularPais = '+55'; // Default
$celularNum = $celularFull;

// Simple heuristic: if starts with +, try to match known codes
// Or just let JS handle masking if we populate the value correctly.
// The JS logic in index.php was: `formData.set('celular', country + rawCel);`
// So in the form we likely want to show the number without country code if possible, or just the full number if we can't parse easily.
// Given the list of codes in index.php (+55, +1, etc.), we can try to extract.
$knownCodes = ['+55', '+1', '+351', '+34', '+33', '+49', '+39', '+44', '+52', '+54', '+56', '+57', '+51', '+595', '+598', '+61', '+81'];
foreach ($knownCodes as $code) {
    if (strpos($celularFull, $code) === 0) {
        $celularPais = $code;
        $celularNum = substr($celularFull, strlen($code));
        break;
    }
}

// Parse Gestante from Observacoes
$obs = $paciente['observacoes'] ?? '';
$isGestante = false;
$gestanteDpp = '';
if (preg_match('/\[GESTANTE(?:\s+DPP:(\d{4}-\d{2}-\d{2}))?\]/i', $obs, $matches)) {
    $isGestante = true;
    if (isset($matches[1])) {
        $gestanteDpp = $matches[1];
    }
    // Remove tag from display obs
    $obs = trim(str_replace($matches[0], '', $obs));
}

// Age Calc
$idadeDisplay = '0A 0M 0D';
if (!empty($paciente['nascimento'])) {
    try {
        $nasc = new DateTime($paciente['nascimento']);
        $hoje = new DateTime();
        $diff = $hoje->diff($nasc);
        $idadeDisplay = "{$diff->y}A {$diff->m}M {$diff->d}D";
    } catch (Exception $e) {}
}
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h1 class="text-2xl font-bold text-base-content">
        <i class="bi bi-person-heart"></i> <?= $isEdit ? 'Editar Paciente' : 'Novo Paciente' ?>
    </h1>
    <a href="index.php?r=pacientes" class="btn btn-outline gap-2">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="card bg-base-100 shadow-xl mb-6">
    <div class="card-body">
        
        <!-- Error Alert -->
        <?php if (!empty($_GET['erro'])): ?>
        <div role="alert" class="alert alert-error mb-4">
            <i class="bi bi-exclamation-circle"></i>
            <span><?= htmlspecialchars($_GET['erro']) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="index.php?r=pacientes/store" id="form_paciente">
            <input type="hidden" name="id" value="<?= $paciente['id'] ?>">
            <!-- If we rely on standard POST, we might need to handle the celular combination via JS before submit or stick to simple inputs. 
                 Let's use the JS submit handler but remove the AJAX part, just manipulate data and submit? 
                 Actually, standard POST is simpler. We can combine in PHP controller if we send two fields.
                 Let's change inputs to 'celular_pais' and 'celular_numero' and handle in controller. 
                 Or keep 'celular' input and prepend country via JS on submit.
            -->

            <!-- ROW 1: Código, Ativo, Gestante, (DPP), Nome -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4 items-end">
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Código</span></label>
                    <input type="text" name="codigo_paciente" class="input input-bordered w-full bg-base-200" readonly 
                           value="<?= htmlspecialchars($paciente['codigo_paciente'] ?? '') ?>" placeholder="Auto" />
                </div>
                
                <div class="form-control md:col-span-2">
                    <label class="cursor-pointer label justify-start gap-2">
                        <input type="checkbox" name="ativo" class="toggle toggle-primary" <?= (!isset($paciente['ativo']) || $paciente['ativo']) ? 'checked' : '' ?> />
                        <span class="label-text">Ativo</span>
                    </label>
                </div>

                <div class="form-control md:col-span-2">
                    <label class="cursor-pointer label justify-start gap-2">
                        <input type="checkbox" name="gestante_programa" id="input_gestante_programa" class="toggle toggle-secondary" onchange="toggleGestante()" <?= $isGestante ? 'checked' : '' ?> />
                        <span class="label-text">Gestante</span>
                    </label>
                </div>

                <div class="form-control md:col-span-2 <?= $isGestante ? '' : 'hidden' ?>" id="div_gestante_dpp">
                    <label class="label"><span class="label-text" title="Data Provável do Parto">DPP</span></label>
                    <input type="date" name="gestante_dpp" id="input_gestante_dpp" class="input input-bordered w-full" value="<?= $gestanteDpp ?>" />
                </div>

                <!-- Nome occupies remaining space -->
                <div class="form-control md:col-span-4 flex-grow">
                    <label class="label"><span class="label-text font-bold">Nome *</span></label>
                    <input type="text" name="nome" class="input input-bordered w-full" required value="<?= htmlspecialchars($paciente['nome'] ?? '') ?>" />
                </div>
            </div>

            <!-- ROW 2: Nascimento, Idade, Sexo, RG, Órgão RG -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Nascimento *</span></label>
                    <input type="date" name="nascimento" id="input_nascimento" class="input input-bordered w-full" required onchange="checkIdade()" value="<?= $paciente['nascimento'] ?? '' ?>" />
                </div>
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Idade</span></label>
                    <input type="text" id="input_idade" class="input input-bordered w-full bg-base-200" readonly placeholder="0A 0M 0D" value="<?= $idadeDisplay ?>" />
                </div>
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Sexo *</span></label>
                    <select name="sexo" class="select select-bordered w-full" required>
                        <option value="" disabled <?= empty($paciente['sexo']) ? 'selected' : '' ?>>Selecione...</option>
                        <option value="Masculino" <?= ($paciente['sexo'] ?? '') === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                        <option value="Feminino" <?= ($paciente['sexo'] ?? '') === 'Feminino' ? 'selected' : '' ?>>Feminino</option>
                    </select>
                </div>
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">RG</span></label>
                    <input type="text" name="rg" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['rg'] ?? '') ?>" />
                </div>
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Órgão RG</span></label>
                    <input type="text" name="orgao_rg" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['orgao_rg'] ?? '') ?>" />
                </div>
            </div>

            <!-- ROW 3: CPF, País, Celular, Email -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">CPF</span></label>
                    <input type="text" name="cpf" id="input_cpf" class="input input-bordered w-full" oninput="maskCpf(this); checkCpf(this.value)" maxlength="14" value="<?= htmlspecialchars($paciente['cpf'] ?? '') ?>" />
                    <label class="label"><span class="label-text-alt text-error hidden" id="cpf_error">CPF já cadastrado!</span></label>
                </div>
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">País</span></label>
                    <select id="input_pais" name="celular_pais" class="select select-bordered w-full">
                        <?php foreach ($knownCodes as $code): ?>
                            <option value="<?= $code ?>" <?= $celularPais === $code ? 'selected' : '' ?>><?= $code ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Celular</span></label>
                    <input type="text" name="celular_numero" id="input_celular" class="input input-bordered w-full" oninput="maskPhone(this)" maxlength="20" value="<?= htmlspecialchars($celularNum) ?>" />
                    <!-- Hidden input to store combined value if we use JS, OR we handle separate fields in controller. 
                         I will handle separate fields in Controller for robustness. -->
                </div>
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">E-mail</span></label>
                    <input type="email" name="email" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['email'] ?? '') ?>" />
                </div>
            </div>

            <!-- LOGIC SECTION: Alert & Toggle -->
            <div id="nf_alert" class="alert alert-warning mb-4 hidden">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Paciente menor de 18 anos. Informe responsável para nota fiscal.</span>
            </div>

            <div class="form-control mb-4">
                <label class="cursor-pointer label justify-start gap-2">
                    <input type="checkbox" name="nf_outro_nome_ativo" id="toggle_nf_outro_nome" class="toggle" onchange="toggleResponsavel()" 
                    <?= (!empty($paciente['nf_responsavel_nome'])) ? 'checked' : '' ?> />
                    <span class="label-text">Emitir nota em outro nome</span>
                </label>
            </div>

            <!-- ROW 4: Responsável (Hidden) -->
            <div id="div_responsavel" class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4 <?= (!empty($paciente['nf_responsavel_nome'])) ? '' : 'hidden' ?>">
                <div class="form-control md:col-span-6">
                    <label class="label"><span class="label-text">Responsável - Nome</span></label>
                    <input type="text" name="nf_responsavel_nome" id="input_nf_nome" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['nf_responsavel_nome'] ?? '') ?>" />
                </div>
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Resp. Nascimento</span></label>
                    <input type="date" name="nf_responsavel_nascimento" id="input_nf_nascimento" class="input input-bordered w-full" value="<?= $paciente['nf_responsavel_nascimento'] ?? '' ?>" />
                </div>
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Resp. CPF</span></label>
                    <input type="text" name="nf_responsavel_cpf" id="input_nf_cpf" class="input input-bordered w-full" oninput="maskCpf(this); validateRespCpf(this.value)" maxlength="14" value="<?= htmlspecialchars($paciente['nf_responsavel_cpf'] ?? '') ?>" />
                    <label class="label"><span class="label-text-alt text-error hidden" id="nf_cpf_error">CPF Inválido</span></label>
                </div>
            </div>

            <!-- ROW 5: Endereço -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">CEP</span></label>
                    <input type="text" name="cep" id="input_cep" class="input input-bordered w-full" oninput="maskCep(this)" maxlength="9" value="<?= htmlspecialchars($paciente['cep'] ?? '') ?>" />
                </div>
                <div class="form-control md:col-span-5">
                    <label class="label"><span class="label-text">Endereço</span></label>
                    <input type="text" name="endereco" id="input_endereco" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['endereco'] ?? '') ?>" />
                </div>
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">Número</span></label>
                    <input type="text" name="numero" id="input_numero" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['numero'] ?? '') ?>" />
                </div>
                <div class="form-control md:col-span-3">
                    <label class="label"><span class="label-text">Complemento</span></label>
                    <input type="text" name="complemento" id="input_complemento" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['complemento'] ?? '') ?>" />
                </div>
            </div>

            <!-- ROW 6: Localização / Obs -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Bairro</span></label>
                    <input type="text" name="bairro" id="input_bairro" class="input input-bordered w-full" value="<?= htmlspecialchars($paciente['bairro'] ?? '') ?>" />
                </div>
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Município</span></label>
                    <select name="municipio_id" id="input_municipio" class="select select-bordered w-full">
                        <option value="">Selecione...</option>
                        <?php foreach ($municipios as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($paciente['municipio_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?> (<?= $m['uf'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-control md:col-span-4">
                    <label class="label"><span class="label-text">Observações</span></label>
                    <input type="text" name="observacoes" class="input input-bordered w-full" value="<?= htmlspecialchars($obs) ?>" />
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <a href="index.php?r=pacientes" class="btn">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="btn_save">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Include Masks and Helpers inline since they were in index.php
    function maskCpf(el) {
        let v = el.value.replace(/\D/g, '');
        if (v.length > 11) v = v.slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        el.value = v;
    }

    function maskCep(el) {
        let v = el.value.replace(/\D/g, '');
        if (v.length > 8) v = v.slice(0, 8);
        v = v.replace(/(\d{5})(\d)/, '$1-$2');
        el.value = v;
        if (v.length === 9) buscaCep(v);
    }

    function maskPhone(el) {
        let v = el.value.replace(/\D/g, '');
        // Simple mask logic or just let it be numbers
        // Logic from index.php was handled in 'oninput' but implementation was in script block.
        // I'll leave it simple or just allow numbers.
        el.value = v;
    }

    function toggleGestante() {
        const chk = document.getElementById('input_gestante_programa');
        const div = document.getElementById('div_gestante_dpp');
        if (chk.checked) div.classList.remove('hidden');
        else div.classList.add('hidden');
    }

    function toggleResponsavel() {
        const chk = document.getElementById('toggle_nf_outro_nome');
        const div = document.getElementById('div_responsavel');
        if (chk.checked) div.classList.remove('hidden');
        else div.classList.add('hidden');
    }

    function checkIdade() {
        const nasc = document.getElementById('input_nascimento').value;
        const alertDiv = document.getElementById('nf_alert');
        const toggle = document.getElementById('toggle_nf_outro_nome');
        
        if (!nasc) return;

        const d = new Date(nasc);
        const today = new Date();
        let age = today.getFullYear() - d.getFullYear();
        const m = today.getMonth() - d.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < d.getDate())) {
            age--;
        }

        // Update Idade display
        let diffTime = Math.abs(today - d);
        let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
        // Simple approx for display if needed, but PHP handled initial load. 
        // JS update:
        document.getElementById('input_idade').value = age + ' anos'; // Simplified for JS

        if (age < 18) {
            alertDiv.classList.remove('hidden');
            if (!toggle.checked) {
                toggle.checked = true;
                toggleResponsavel();
            }
        } else {
            alertDiv.classList.add('hidden');
        }
    }
    
    // Initial check on load
    checkIdade();

    function buscaCep(cep) {
        fetch('index.php?r=pacientes/busca_municipio&cep=' + cep)
            .then(res => res.json())
            .then(data => {
                if (data) {
                    // Populate address fields if returned (though API currently only returns municipio?)
                    // The Controller busca_municipio returns id, nome, uf.
                    // It doesn't seem to return address.
                    // But if it did...
                    if (data.id) {
                        document.getElementById('input_municipio').value = data.id;
                    }
                }
            });
    }

    function checkCpf(cpf) {
        const id = document.querySelector('input[name="id"]').value;
        if (cpf.length < 14) return;
        
        fetch(`index.php?r=pacientes/checkCpf&cpf=${cpf}&id=${id}`)
            .then(res => res.json())
            .then(data => {
                const err = document.getElementById('cpf_error');
                if (data.exists) err.classList.remove('hidden');
                else err.classList.add('hidden');
            });
    }
    
    function validateRespCpf(cpf) {
        // Client side simple validation if needed
    }
</script>
