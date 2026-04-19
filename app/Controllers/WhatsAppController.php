<?php
class WhatsAppController extends Controller {
    public function __construct() {
        parent::__construct();
        if (!temPermissao('admin')) { // Assuming admin only or adjust permissions
             // For now allowing access if logged in, as per original file which only checked verificaLogin()
        }
    }

    public function logs() {
        $registros_por_pagina = 15;
        $pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $offset = ($pagina_atual - 1) * $registros_por_pagina;

        // Total de registros
        $stmt = $this->db->query("SELECT COUNT(*) FROM logs_envio");
        $total_registros = $stmt->fetch_row()[0];
        $total_paginas = ceil($total_registros / $registros_por_pagina);

        // Busca logs
        $logs = $this->db->query("
            SELECT * FROM logs_envio 
            ORDER BY criado_em DESC 
            LIMIT $offset, $registros_por_pagina
        ")->fetch_all(MYSQLI_ASSOC);

        $this->view('whatsapp/logs', [
            'logs' => $logs,
            'total_paginas' => $total_paginas,
            'pagina_atual' => $pagina_atual
        ]);
    }
}
