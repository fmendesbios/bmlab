<?php
require_once 'config.php';
require_once 'auth_permissoes.php';
require_once 'app/Core/Controller.php';
require_once 'app/Core/Router.php';

verificaLogin();

$router = new Router();

// Auth
$router->add('GET', 'login', ['AuthController', 'login']);
$router->add('POST', 'auth/authenticate', ['AuthController', 'authenticate']);
$router->add('GET', 'logout', ['AuthController', 'logout']);

// Home
$router->add('GET', 'home', ['HomeController', 'index']);

// Municipios (MVC Migration)
$router->add('GET', 'municipios', ['MunicipiosController', 'index']);
$router->add('POST', 'municipios/salvar', ['MunicipiosController', 'store']);
$router->add('GET', 'municipios/excluir', ['MunicipiosController', 'delete']);

// Postos (MVC Migration)
$router->add('GET', 'postos', ['PostosController', 'index']);
$router->add('POST', 'postos/salvar', ['PostosController', 'store']);
$router->add('GET', 'postos/excluir', ['PostosController', 'delete']);
$router->add('GET', 'postos/busca_municipio', ['PostosController', 'busca_municipio']);
$router->add('GET', 'postos/next_code', ['PostosController', 'next_code']);

// Convenios (MVC Migration)
$router->add('GET', 'convenios', ['ConveniosController', 'index']);
$router->add('POST', 'convenios/salvar', ['ConveniosController', 'store']);
$router->add('GET', 'convenios/excluir', ['ConveniosController', 'delete']);
$router->add('GET', 'convenios/next_code', ['ConveniosController', 'next_code']);

// Parceiros (MVC Migration)
$router->add('GET', 'parceiros', ['ParceirosController', 'index']);
$router->add('POST', 'parceiros/salvar', ['ParceirosController', 'store']);
$router->add('GET', 'parceiros/excluir', ['ParceirosController', 'delete']);
$router->add('GET', 'parceiros/busca_municipio', ['ParceirosController', 'busca_municipio']);
$router->add('GET', 'parceiros/next_code', ['ParceirosController', 'next_code']);

// Campanhas/Programas
$router->add('GET', 'campanhas_programas', ['CampanhasProgramasController', 'index']);
$router->add('POST', 'campanhas_programas/store', ['CampanhasProgramasController', 'store']);
$router->add('GET', 'campanhas_programas/delete', ['CampanhasProgramasController', 'delete']);
$router->add('GET', 'campanhas_programas/next_code', ['CampanhasProgramasController', 'next_code']);

// Pacientes em Campanhas (Associação)
$router->add('GET', 'pacientes_campanhas', ['PacientesCampanhasController', 'index']);
$router->add('POST', 'pacientes_campanhas/store', ['PacientesCampanhasController', 'store']);
$router->add('GET', 'pacientes_campanhas/delete', ['PacientesCampanhasController', 'delete']);
$router->add('GET', 'pacientes_campanhas/search_pacientes', ['PacientesCampanhasController', 'search_pacientes']);

// Laboratorios (MVC Migration)
$router->add('GET', 'laboratorios', ['LaboratoriosController', 'index']);
$router->add('POST', 'laboratorios/salvar', ['LaboratoriosController', 'store']);
$router->add('GET', 'laboratorios/excluir', ['LaboratoriosController', 'delete']);
$router->add('GET', 'laboratorios/next_code', ['LaboratoriosController', 'next_code']);

// Medicos (MVC Migration)
$router->add('GET', 'medicos', ['MedicosController', 'index']);
$router->add('POST', 'medicos/salvar', ['MedicosController', 'store']);
$router->add('GET', 'medicos/excluir', ['MedicosController', 'delete']);
$router->add('GET', 'medicos/next_code', ['MedicosController', 'next_code']);

// Pacientes (MVC Migration)
$router->add('GET', 'pacientes', ['PacientesController', 'index']);
$router->add('GET', 'pacientes/create', ['PacientesController', 'create']);
$router->add('GET', 'pacientes/edit', ['PacientesController', 'edit']);
$router->add('POST', 'pacientes/store', ['PacientesController', 'store']);
$router->add('POST', 'pacientes/salvar', ['PacientesController', 'store']);
$router->add('GET', 'pacientes/excluir', ['PacientesController', 'delete']);
$router->add('GET', 'pacientes/check_cpf', ['PacientesController', 'checkCpf']);
$router->add('GET', 'pacientes/next_code', ['PacientesController', 'next_code']);
$router->add('GET', 'pacientes/busca_municipio', ['PacientesController', 'busca_municipio']);

// Fornecedores (MVC Migration)
$router->add('GET', 'fornecedores', ['FornecedoresController', 'index']);
$router->add('POST', 'fornecedores/salvar', ['FornecedoresController', 'store']);
$router->add('GET', 'fornecedores/excluir', ['FornecedoresController', 'delete']);
$router->add('GET', 'fornecedores/next_code', ['FornecedoresController', 'next_code']);
$router->add('GET', 'fornecedores/busca_municipio', ['FornecedoresController', 'busca_municipio']);

// Setores (MVC Migration)
$router->add('GET', 'setores', ['SetoresController', 'index']);
$router->add('POST', 'setores/salvar', ['SetoresController', 'store']);
$router->add('GET', 'setores/excluir', ['SetoresController', 'delete']);
$router->add('GET', 'setores/next_code', ['SetoresController', 'next_code']);

// Materiais Biologicos (MVC Migration)
$router->add('GET', 'materiais_biologicos', ['MateriaisBiologicosController', 'index']);
$router->add('POST', 'materiais_biologicos/salvar', ['MateriaisBiologicosController', 'store']);
$router->add('GET', 'materiais_biologicos/excluir', ['MateriaisBiologicosController', 'delete']);
$router->add('GET', 'materiais_biologicos/next_code', ['MateriaisBiologicosController', 'next_code']);

// Recipientes Coleta (MVC Migration)
$router->add('GET', 'recipientes_coleta', ['RecipientesColetaController', 'index']);
$router->add('POST', 'recipientes_coleta/salvar', ['RecipientesColetaController', 'store']);
$router->add('GET', 'recipientes_coleta/excluir', ['RecipientesColetaController', 'delete']);
$router->add('GET', 'recipientes_coleta/next_code', ['RecipientesColetaController', 'next_code']);

// Conservacao Reagentes (MVC Migration)
$router->add('GET', 'conservacao_reagentes', ['ConservacaoReagentesController', 'index']);
$router->add('POST', 'conservacao_reagentes/salvar', ['ConservacaoReagentesController', 'store']);
$router->add('GET', 'conservacao_reagentes/excluir', ['ConservacaoReagentesController', 'delete']);
$router->add('GET', 'conservacao_reagentes/next_code', ['ConservacaoReagentesController', 'next_code']);

// Exames (MVC Migration)
$router->add('GET', 'exames', ['ExamesController', 'index']);
$router->add('GET', 'exames/edit', ['ExamesController', 'edit']);
$router->add('POST', 'exames/store', ['ExamesController', 'store']);
$router->add('GET', 'exames/delete', ['ExamesController', 'delete']);
$router->add('GET', 'exames/delete_apoio', ['ExamesController', 'delete_apoio']);

// NFSe (MVC Migration)
$router->add('GET', 'nfse/notas_expedidas', ['NfseController', 'notas_expedidas']);
$router->add('GET', 'nfse/painel', ['NfseController', 'painel']);
$router->add('POST', 'nfse/salvar_config', ['NfseController', 'salvar_config']);
$router->add('POST', 'nfse/importar', ['NfseController', 'importar']);
$router->add('GET', 'nfse/emitir', ['NfseController', 'emitir']); // Show form
$router->add('POST', 'nfse/emitir', ['NfseController', 'emitir']); // Process emission/consultation
$router->add('POST', 'nfse/testar_sefin', ['NfseController', 'testar_sefin']);
$router->add('GET', 'nfse/downloadXml', ['NfseController', 'downloadXml']);
$router->add('GET', 'nfse/visualizarDanfse', ['NfseController', 'visualizarDanfse']);
$router->add('GET', 'nfse/cancelar', ['NfseController', 'cancelar']);
$router->add('GET', 'nfse/substituir', ['NfseController', 'substituir']);

$router->add('GET', 'exames/delete_preco', ['ExamesController', 'delete_preco']);
$router->add('POST', 'exames/layout_fetch', ['ExamesController', 'layout_fetch']);
$router->add('POST', 'exames/quick_store', ['ExamesController', 'quick_store']);
$router->add('GET', 'exames/check_mnemonico', ['ExamesController', 'check_mnemonico']);
$router->add('GET', 'exames/get_all_variaveis_opcoes', ['ExamesController', 'get_all_variaveis_opcoes']);
$router->add('POST', 'exames/save_layout', ['ExamesController', 'save_layout']);
$router->add('GET', 'exames/get_textos_padrao', ['ExamesController', 'get_textos_padrao']);
$router->add('POST', 'exames/save_texto_padrao', ['ExamesController', 'save_texto_padrao']);
$router->add('POST', 'exames/delete_texto_padrao', ['ExamesController', 'delete_texto_padrao']);

// Resultados (MVC Migration)
$router->add('GET', 'resultados', ['ResultadosController', 'index']);
$router->add('GET', 'resultados/entry', ['ResultadosController', 'entry']);
$router->add('POST', 'resultados/store', ['ResultadosController', 'store']);
$router->add('POST', 'resultados/save_entry', ['ResultadosController', 'save_entry']);
$router->add('GET', 'resultados/print_laudo', ['ResultadosController', 'print_laudo']);
$router->add('POST', 'resultados/upload_pdf_resultado', ['ResultadosController', 'upload_pdf_resultado']);
$router->add('POST', 'resultados/ajax_vars', ['ResultadosController', 'ajax_vars']);

// Resultados Texto (MVC Migration)
$router->add('GET', 'resultados_texto', ['ResultadosTextoController', 'index']);
$router->add('POST', 'resultados_texto/store', ['ResultadosTextoController', 'store']);
$router->add('GET', 'resultados_texto/delete', ['ResultadosTextoController', 'delete']);
$router->add('GET', 'resultados_texto/get_all_json', ['ResultadosTextoController', 'get_all_json']);

// Pedidos (MVC Migration)
$router->add('GET', 'pedidos', ['PedidosController', 'index']);
$router->add('GET', 'pedidos/create', ['PedidosController', 'create']);
$router->add('GET', 'pedidos/edit', ['PedidosController', 'edit']);
$router->add('POST', 'pedidos/store', ['PedidosController', 'store']);
$router->add('GET', 'pedidos/delete', ['PedidosController', 'delete']);
$router->add('GET', 'pedidos/search_exames', ['PedidosController', 'search_exames']);
$router->add('GET', 'pedidos/search_pacientes', ['PedidosController', 'search_pacientes']);
$router->add('POST', 'pedidos/receber', ['PedidosController', 'receber']);
$router->add('GET', 'pedidos/etiquetas', ['PedidosController', 'etiquetas']);
$router->add('POST', 'pedidos/desbloquear', ['PedidosController', 'desbloquear']);
$router->add('GET', 'pedidos/ver_modal', ['PedidosController', 'ver_modal']);

// Triagem (MVC Migration)
$router->add('GET', 'triagem', ['TriagemController', 'index']);
$router->add('GET', 'triagem/show', ['TriagemController', 'show']);
$router->add('POST', 'triagem/store', ['TriagemController', 'store']);

// Contas Receber (MVC Migration)
$router->add('GET', 'contas_receber', ['ContasReceberController', 'index']);
$router->add('GET', 'contas_receber/create', ['ContasReceberController', 'create']);
$router->add('POST', 'contas_receber/store', ['ContasReceberController', 'store']);
$router->add('POST', 'contas_receber/receive', ['ContasReceberController', 'receive']);
$router->add('POST', 'contas_receber/transfer', ['ContasReceberController', 'transfer']);
$router->add('POST', 'contas_receber/delete', ['ContasReceberController', 'delete']);

// Contas Pagar (MVC Migration)
$router->add('GET', 'contas_pagar', ['ContasPagarController', 'index']);
$router->add('GET', 'contas_pagar/create', ['ContasPagarController', 'create']);
$router->add('POST', 'contas_pagar/store', ['ContasPagarController', 'store']);
$router->add('POST', 'contas_pagar/pay', ['ContasPagarController', 'pay']);
$router->add('POST', 'contas_pagar/reverse', ['ContasPagarController', 'reverse']);

// Contas Bancarias (MVC Migration)
$router->add('GET', 'contas_bancarias', ['ContasBancariasController', 'index']);
$router->add('GET', 'contas_bancarias/create', ['ContasBancariasController', 'create']);
$router->add('GET', 'contas_bancarias/edit', ['ContasBancariasController', 'edit']);
$router->add('POST', 'contas_bancarias/store', ['ContasBancariasController', 'store']);
$router->add('GET', 'contas_bancarias/delete', ['ContasBancariasController', 'delete']);

// Transferencias Bancarias (MVC Migration)
$router->add('GET', 'transferencias', ['TransferenciasBancariasController', 'index']);
$router->add('GET', 'transferencias_bancarias', ['TransferenciasBancariasController', 'index']);
$router->add('GET', 'transferencias/create', ['TransferenciasBancariasController', 'create']);
$router->add('POST', 'transferencias/store', ['TransferenciasBancariasController', 'store']);
$router->add('POST', 'transferencias/reverse', ['TransferenciasBancariasController', 'reverse']);
$router->add('GET', 'transferencias/get_balance', ['TransferenciasBancariasController', 'get_balance']);

// Fechamento de Caixa (MVC Migration)
$router->add('GET', 'fechamento_caixa', ['FechamentoCaixaController', 'index']);
$router->add('POST', 'fechamento_caixa/store', ['FechamentoCaixaController', 'store']);

// Usuarios (MVC Migration)
$router->add('GET', 'usuarios', ['UsuariosController', 'index']);
$router->add('POST', 'usuarios/store', ['UsuariosController', 'store']);
$router->add('GET', 'usuarios/excluir', ['UsuariosController', 'delete']);
$router->add('GET', 'alterar_senha', ['UsuariosController', 'alterar_senha']);
$router->add('POST', 'alterar_senha', ['UsuariosController', 'alterar_senha']);

// Configuracoes (MVC Migration)
$router->add('GET', 'configuracoes', ['ConfiguracoesController', 'index']);
$router->add('POST', 'configuracoes/store', ['ConfiguracoesController', 'store']);

// Grupos (MVC Migration)
$router->add('GET', 'grupos', ['GruposController', 'index']);
$router->add('POST', 'grupos/store', ['GruposController', 'store']);
$router->add('GET', 'grupos/delete', ['GruposController', 'delete']);
$router->add('POST', 'grupos/save_permissions', ['GruposController', 'save_permissions']);

// Tipos Recebimentos (MVC Migration)
$router->add('GET', 'tipos_recebimentos', ['TiposRecebimentosController', 'index']);
$router->add('POST', 'tipos_recebimentos/store', ['TiposRecebimentosController', 'store']);
$router->add('GET', 'tipos_recebimentos/delete', ['TiposRecebimentosController', 'delete']);
$router->add('GET', 'tipos_recebimentos/classificacoes', ['TiposRecebimentosController', 'classificacoes']);
$router->add('POST', 'tipos_recebimentos/store_classificacao', ['TiposRecebimentosController', 'store_classificacao']);
$router->add('GET', 'tipos_recebimentos/delete_classificacao', ['TiposRecebimentosController', 'delete_classificacao']);

// Agendamento (MVC Migration)
$router->add('GET', 'agendamento', ['AgendamentoController', 'index']);
$router->add('POST', 'agendamento/store', ['AgendamentoController', 'store']);
$router->add('POST', 'agendamento/update', ['AgendamentoController', 'update']);
$router->add('POST', 'agendamento/delete', ['AgendamentoController', 'delete']);

// Orcamentos (MVC Migration)
$router->add('GET', 'orcamentos', ['OrcamentosController', 'index']);
$router->add('POST', 'orcamentos/store', ['OrcamentosController', 'store']);
$router->add('GET', 'orcamentos/delete', ['OrcamentosController', 'delete']);
$router->add('POST', 'orcamentos/desbloquear', ['OrcamentosController', 'desbloquear']);
$router->add('GET', 'orcamentos/exportar_pedido', ['OrcamentosController', 'exportar_pedido']);
$router->add('GET', 'orcamentos/crm_history', ['OrcamentosController', 'crm_history']);
$router->add('POST', 'orcamentos/crm_save', ['OrcamentosController', 'crm_save']);

// Relatorios (MVC Migration)
$router->add('GET', 'extrato_conta', ['RelatoriosController', 'extrato_conta']);
$router->add('GET', 'relatorio_situacao_pedidos', ['RelatoriosController', 'situacao_pedidos']);
$router->add('GET', 'relatorios/pedidos_situacao_pdf', ['RelatoriosController', 'pedidos_situacao_pdf']);

// WhatsApp Logs (MVC Migration)
$router->add('GET', 'disparos_whatsapp_logs', ['WhatsAppController', 'logs']);

// CRM Motivos (MVC Migration)
$router->add('GET', 'crm_motivos', ['CrmMotivosController', 'index']);
$router->add('POST', 'crm_motivos/store', ['CrmMotivosController', 'store']);
$router->add('GET', 'crm_motivos/delete', ['CrmMotivosController', 'delete']);

// Try to dispatch via MVC Router
if ($router->dispatch()) {
    exit;
}

// Default redirect to home if no route match (or 404 behavior)
header("Location: index.php?r=home");
exit;
