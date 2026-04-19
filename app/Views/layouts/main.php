<!DOCTYPE html>
<html lang="pt-br" data-theme="bmlab">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMLAB</title>
    <link href="public/css/app.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" type="image/png" href="img/icon_braga_mendes.png">
    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'bmlab';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="bg-base-200 min-h-screen flex flex-col">
    <!-- Navbar -->
    <div class="navbar bg-primary text-primary-content shadow-lg z-50 sticky top-0">
        <div class="flex-none lg:hidden">
            <label for="my-drawer-2" class="btn btn-square btn-ghost">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-6 h-6 stroke-current">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </label>
        </div>
        <div class="flex-1 px-2 mx-2 flex flex-col items-start md:items-center md:flex-row">
            <span class="hidden md:flex text-sm text-white gap-2 items-center" id="clock-container">
                <i class="bi bi-calendar-event"></i> <span id="clock-date"></span>
                <i class="bi bi-clock ml-2"></i> <span id="clock-time"></span>
            </span>
        </div>
        <div class="flex-none hidden lg:block">
            <!-- Theme Toggle -->
            <button class="btn btn-ghost btn-circle" onclick="toggleTheme()" title="Alternar Tema">
                <i class="bi bi-moon-fill text-xl" id="theme-icon"></i>
            </button>

            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar placeholder">
                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                        <span><i class="bi bi-person-fill text-xl"></i></span>
                    </div>
                </div>
                <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-52 text-base-content">
                    <li><a class="font-bold">Olá, <?= htmlspecialchars($_SESSION['nome'] ?? 'Usuário') ?></a></li>
                    <li><span class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['grupo_nome'] ?? '') ?></span></li>
                    <li><a href="index.php?r=alterar_senha"><i class="bi bi-key"></i> Alterar Senha</a></li>
                    <li><a href="index.php?r=logout" class="text-error"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Drawer for Sidebar -->
    <div class="drawer lg:drawer-open flex-1">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content flex flex-col p-4 md:p-6 overflow-x-hidden">
            <!-- Page Content -->
            <?php
            if (isset($contentView) && file_exists($contentView)) {
                include $contentView;
            } else {
                echo '<div class="alert alert-error">Conteúdo não encontrado.</div>';
            }
            ?>
        </div>
        <div class="drawer-side z-40">
            <label for="my-drawer-2" class="drawer-overlay"></label>
            <aside class="menu p-4 w-80 min-h-full bg-base-100 text-base-content shadow-xl flex flex-col">
                <div class="mb-6 text-center">
                    <img src="img/logo_oficial_braga_mendes_hd.png" alt="Logo" class="max-w-[180px] mx-auto">
                </div>

                <ul class="menu w-full rounded-box flex-1">
                    <li><a href="index.php?r=home" class="<?= ($route ?? '') == 'home' ? 'active' : '' ?>"><i class="bi bi-house"></i> Dashboard</a></li>

                    <!-- Cadastros -->
                    <?php if (temPermissao('cadastros.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['municipios', 'postos', 'convenios', 'parceiros', 'laboratorios', 'medicos', 'pacientes', 'fornecedores', 'exames', 'setores', 'materiais_biologicos', 'recipientes_coleta', 'conservacao_reagentes', 'resultados_texto']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-folder"></i> Cadastros</summary>
                                <ul>
                                    <?php if (temPermissao('municipios.menu')): ?>
                                        <li><a href="index.php?r=municipios" class="<?= ($route ?? '') == 'municipios' ? 'active' : '' ?>"><i class="bi bi-geo-alt"></i> Municípios</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('postos.menu')): ?>
                                        <li><a href="index.php?r=postos" class="<?= ($route ?? '') == 'postos' ? 'active' : '' ?>"><i class="bi bi-pin-map"></i> Postos de Coleta</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('convenios.menu')): ?>
                                        <li><a href="index.php?r=convenios" class="<?= ($route ?? '') == 'convenios' ? 'active' : '' ?>"><i class="bi bi-journal-medical"></i> Convênios</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('parceiros.menu')): ?>
                                        <li><a href="index.php?r=parceiros" class="<?= ($route ?? '') == 'parceiros' ? 'active' : '' ?>"><i class="bi bi-briefcase"></i> Parceiros</a></li>
                                    <?php endif; ?>

                                    

                                    <?php if (temPermissao('laboratorios.menu')): ?>
                                        <li><a href="index.php?r=laboratorios" class="<?= ($route ?? '') == 'laboratorios' ? 'active' : '' ?>"><i class="bi bi-building"></i> Laboratórios</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('medicos.menu')): ?>
                                        <li><a href="index.php?r=medicos" class="<?= ($route ?? '') == 'medicos' ? 'active' : '' ?>"><i class="bi bi-people"></i> Médicos</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('pacientes.menu')): ?>
                                        <li><a href="index.php?r=pacientes" class="<?= ($route ?? '') == 'pacientes' ? 'active' : '' ?>"><i class="bi bi-people"></i> Pacientes</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('fornecedores.menu')): ?>
                                        <li><a href="index.php?r=fornecedores" class="<?= ($route ?? '') == 'fornecedores' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Fornecedores</a></li>
                                    <?php endif; ?>

                                    <!-- Submenu Exames -->
                                    <li>
                                        <details <?= in_array($route ?? '', ['exames', 'setores', 'materiais_biologicos', 'recipientes_coleta', 'conservacao_reagentes', 'resultados_texto']) ? 'open' : '' ?>>
                                            <summary><i class="bi bi-capsule-pill"></i> Exames</summary>
                                            <ul>
                                                <?php if (temPermissao('exames.menu')): ?>
                                                    <li><a href="index.php?r=exames" class="<?= ($route ?? '') == 'exames' ? 'active' : '' ?>"><i class="bi bi-file-medical"></i> Exame</a></li>
                                                <?php endif; ?>

                                                <?php if (temPermissao('setores.menu')): ?>
                                                    <li><a href="index.php?r=setores" class="<?= ($route ?? '') == 'setores' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i> Setor</a></li>
                                                <?php endif; ?>

                                                <?php if (temPermissao('materiais.menu')): ?>
                                                    <li><a href="index.php?r=materiais_biologicos" class="<?= ($route ?? '') == 'materiais_biologicos' ? 'active' : '' ?>"><i class="bi bi-droplet"></i> Material Biológico</a></li>
                                                <?php endif; ?>

                                                <?php if (temPermissao('recipientes.menu')): ?>
                                                    <li><a href="index.php?r=recipientes_coleta" class="<?= ($route ?? '') == 'recipientes_coleta' ? 'active' : '' ?>"><i class="bi bi-box-seam"></i> Recipiente</a></li>
                                                <?php endif; ?>

                                                <?php if (temPermissao('conservacao_reagentes.menu')): ?>
                                                    <li><a href="index.php?r=conservacao_reagentes" class="<?= ($route ?? '') == 'conservacao_reagentes' ? 'active' : '' ?>"><i class="bi bi-thermometer"></i> Conservação</a></li>
                                                <?php endif; ?>

                                                <?php if (temPermissao('resultados.menu')): ?>
                                                    <li><a href="index.php?r=resultados_texto" class="<?= ($route ?? '') == 'resultados_texto' ? 'active' : '' ?>"><i class="bi bi-card-text"></i> Resultado Texto</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </details>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>

                    <?php if (temPermissao('campanhas_programas.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['campanhas_programas', 'pacientes_campanhas']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-megaphone"></i> Campanhas/Programas</summary>
                                <ul>
                                    <li><a href="index.php?r=campanhas_programas" class="<?= ($route ?? '') == 'campanhas_programas' ? 'active' : '' ?>"><i class="bi bi-megaphone"></i> Campanhas/Programas</a></li>
                                    <li><a href="index.php?r=pacientes_campanhas" class="<?= ($route ?? '') == 'pacientes_campanhas' ? 'active' : '' ?>"><i class="bi bi-people"></i> Associar Pacientes</a></li>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>

                    <?php if (temPermissao('campanhas_programas.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['crm_motivos']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-graph-down-arrow"></i> CRM</summary>
                                <ul>
                                    <li><a href="index.php?r=crm_motivos" class="<?= ($route ?? '') == 'crm_motivos' ? 'active' : '' ?>"><i class="bi bi-list-check"></i> Motivos Perdemos</a></li>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>

                    <!-- Movimentação -->
                    <?php if (temPermissao('movimentacao.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['orcamentos', 'pedidos', 'triagem', 'resultados', 'fechamento_caixa']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-arrow-left-right"></i> Movimentação</summary>
                                <ul>
                                    <?php if (temPermissao('orcamentos.menu')): ?>
                                        <li><a href="index.php?r=orcamentos" class="<?= ($route ?? '') == 'orcamentos' ? 'active' : '' ?>"><i class="bi bi-receipt-cutoff"></i> Orçamentos</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('pedidos.menu')): ?>
                                        <li><a href="index.php?r=pedidos" class="<?= ($route ?? '') == 'pedidos' ? 'active' : '' ?>"><i class="bi bi-file-earmark-medical"></i> Pedidos</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('triagem.menu')): ?>
                                        <li><a href="index.php?r=triagem" class="<?= ($route ?? '') == 'triagem' ? 'active' : '' ?>"><i class="bi bi-clipboard-pulse"></i> Triagem</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('resultados.menu')): ?>
                                        <li><a href="index.php?r=resultados" class="<?= ($route ?? '') == 'resultados' ? 'active' : '' ?>"><i class="bi bi-bar-chart-line"></i> Resultados</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('financeiro_fechamento.menu')): ?>
                                        <li><a href="index.php?r=fechamento_caixa" class="<?= ($route ?? '') == 'fechamento_caixa' ? 'active' : '' ?>"><i class="bi bi-wallet"></i> Fechamento de Caixa</a></li>
                                    <?php endif; ?>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>

                    <!-- Agendamento -->
                    <?php if (temPermissao('agendamento.menu')): ?>
                        <li>
                            <a href="index.php?r=agendamento" class="<?= ($route ?? '') == 'agendamento' ? 'active' : '' ?>">
                                <i class="bi bi-calendar-check"></i> Agendamento
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Grupo: Fiscal / NFSe -->
                    <li>
                        <details <?= in_array($route ?? '', ['nfse/notas_expedidas', 'nfse/painel']) ? 'open' : '' ?>>
                            <summary><i class="bi bi-receipt"></i> NFSe</summary>
                            <ul>
                                <li>
                                    <a href="index.php?r=nfse/notas_expedidas" class="<?= ($route ?? '') == 'nfse/notas_expedidas' ? 'active' : '' ?>">
                                        <i class="bi bi-receipt-cutoff"></i> Notas Expedidas
                                    </a>
                                </li>
                                <?php if (temPermissao('configuracoes.menu') || (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin')): ?>
                                    <li>
                                        <a href="index.php?r=nfse/painel" class="<?= ($route ?? '') == 'nfse/painel' ? 'active' : '' ?>">
                                            <i class="bi bi-gear"></i> Configurações / Painel
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </details>
                    </li>

                    <!-- Financeiro -->
                    <?php if (temPermissao('financeiro.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['contas_receber', 'contas_pagar', 'transferencias_bancarias', 'contas_bancarias', 'tipos_recebimentos']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-cash-stack"></i> Financeiro</summary>
                                <ul>
                                    <?php if (temPermissao('financeiro_receber.menu')): ?>
                                        <li><a href="index.php?r=contas_receber" class="<?= ($route ?? '') == 'contas_receber' ? 'active' : '' ?>"><i class="bi bi-receipt"></i> Contas a Receber</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('financeiro_pagar.menu')): ?>
                                        <li><a href="index.php?r=contas_pagar" class="<?= ($route ?? '') == 'contas_pagar' ? 'active' : '' ?>"><i class="bi bi-journal-check"></i> Contas a Pagar</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('financeiro_transferencias.menu')): ?>
                                        <li><a href="index.php?r=transferencias_bancarias" class="<?= ($route ?? '') == 'transferencias_bancarias' ? 'active' : '' ?>"><i class="bi bi-bank"></i> Transferências</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('bancos.menu')): ?>
                                        <li><a href="index.php?r=contas_bancarias" class="<?= ($route ?? '') == 'contas_bancarias' ? 'active' : '' ?>"><i class="bi bi-credit-card-2-front"></i> Contas Bancárias</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('tipos_recebimentos.menu')): ?>
                                        <li><a href="index.php?r=tipos_recebimentos" class="<?= ($route ?? '') == 'tipos_recebimentos' ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Tipos de Recebimentos</a></li>
                                    <?php endif; ?>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>

                    <!-- Relatórios -->
                    <?php if (temPermissao('relatorios.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['relatorio_situacao_pedidos', 'extrato_conta']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-bar-chart"></i> Relatórios</summary>
                                <ul>
                                    <?php if (temPermissao('relatorios_situacao_pedidos.menu')): ?>
                                        <li><a href="index.php?r=relatorio_situacao_pedidos" class="<?= ($route ?? '') == 'relatorio_situacao_pedidos' ? 'active' : '' ?>"><i class="bi bi-clipboard-data"></i> Situação dos Pedidos</a></li>
                                    <?php endif; ?>

                                    <?php if (temPermissao('relatorios_extrato_conta.menu')): ?>
                                        <li><a href="index.php?r=extrato_conta" class="<?= ($route ?? '') == 'extrato_conta' ? 'active' : '' ?>"><i class="bi bi-receipt"></i> Extrato Conta</a></li>
                                    <?php endif; ?>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>

                    <!-- Configurações -->
                    <?php if (temPermissao('configuracoes.menu')): ?>
                        <li>
                            <details <?= in_array($route ?? '', ['configuracoes', 'usuarios', 'grupos']) ? 'open' : '' ?>>
                                <summary><i class="bi bi-gear"></i> Configurações</summary>
                                <ul>
                                    <?php if (temPermissao('config_geral.menu')): ?>
                                        <li><a href="index.php?r=configuracoes" class="<?= ($route ?? '') == 'configuracoes' ? 'active' : '' ?>"><i class="bi bi-gear"></i> Config. Sistema</a></li>
                                    <?php endif; ?>

                                    <li>
                                        <details <?= in_array($route ?? '', ['usuarios', 'grupos']) ? 'open' : '' ?>>
                                            <summary><i class="bi bi-person-gear"></i> Usuários</summary>
                                            <ul>
                                                <?php if (temPermissao('usuarios.menu')): ?>
                                                    <li><a href="index.php?r=usuarios" class="<?= ($route ?? '') == 'usuarios' ? 'active' : '' ?>"><i class="bi bi-person-gear"></i> Gerenciar Usuários</a></li>
                                                <?php endif; ?>

                                                <?php if (temPermissao('grupos.menu')): ?>
                                                    <li><a href="index.php?r=grupos" class="<?= ($route ?? '') == 'grupos' ? 'active' : '' ?>"><i class="bi bi-shield-lock"></i> Grupos e Permissões</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </details>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    <?php endif; ?>
                </ul>
            </aside>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const optionsDate = {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            };
            const optionsTime = {
                hour: '2-digit',
                minute: '2-digit'
            };

            const dateStr = now.toLocaleDateString('pt-BR', optionsDate);
            const timeStr = now.toLocaleTimeString('pt-BR', optionsTime);

            const dateEl = document.getElementById('clock-date');
            const timeEl = document.getElementById('clock-time');

            if (dateEl) dateEl.innerText = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
            if (timeEl) timeEl.innerText = timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>

    <script>
        // Theme Toggle Logic
        function toggleTheme() {
            const html = document.querySelector('html');
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'bmlab' : 'dark';

            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            if (!icon) return;

            if (theme === 'dark') {
                icon.classList.remove('bi-moon-fill');
                icon.classList.add('bi-sun-fill');
            } else {
                icon.classList.remove('bi-sun-fill');
                icon.classList.add('bi-moon-fill');
            }
        }

        // Initialize Theme Icon
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'bmlab';
            updateThemeIcon(savedTheme);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
