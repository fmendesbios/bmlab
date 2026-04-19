<?php

namespace App\Controllers;

use App\Core\Controller;

class AgendamentoController extends Controller {

    public function index() {
        $conn = $this->db;
        $this->requireAuth();

        // Fetch Patients
        $pacientes = [];
        $res = $conn->query("SELECT id, nome, celular AS whatsapp FROM pacientes ORDER BY nome");
        if ($res) {
            while ($row = $res->fetch_assoc()) $pacientes[] = $row;
        }

        // Fetch Appointments
        $agendamentos = [];
        $res = $conn->query("
            SELECT a.*, p.nome AS nome_paciente, p.celular AS whatsapp
            FROM agendamentos a
            LEFT JOIN pacientes p ON p.id = a.paciente_id
        ");
        if ($res) {
            while ($row = $res->fetch_assoc()) $agendamentos[] = $row;
        }

        // Prepare Events for FullCalendar
        $eventos_js = array_map(function ($ag) {
            $start = $ag['data_hora'];
            $end = !empty($ag['data_fim']) && $ag['data_fim'] !== '0000-00-00 00:00:00' 
                ? $ag['data_fim'] 
                : date('Y-m-d H:i:s', strtotime($start . ' +30 minutes'));

            return [
                'id' => $ag['id'],
                'title' => $ag['nome_paciente'] ?? $ag['nome_avulso'],
                'start' => $start,
                'end' => $end,
                'allDay' => false,
                'extendedProps' => [
                    'whatsapp' => $ag['whatsapp'] ?? $ag['whatsapp_avulso'] ?? ''
                ]
            ];
        }, $agendamentos);

        $this->view('agendamento/index', [
            'pacientes' => $pacientes,
            'eventos_js' => $eventos_js
        ]);
    }

    public function store() {
        $conn = $this->db;
        $this->requireAuth();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        
        $paciente_id = !empty($input['paciente_id']) ? (int)$input['paciente_id'] : null;
        $nome = trim($input['nome'] ?? '');
        $whatsapp = trim($input['whatsapp'] ?? '');
        $data_hora = trim($input['data_hora'] ?? '');

        if (!$data_hora || (!$paciente_id && !$nome)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO agendamentos (paciente_id, nome_avulso, whatsapp_avulso, data_hora) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $paciente_id, $nome, $whatsapp, $data_hora);
        
        if ($stmt->execute()) {
            echo json_encode(['sucesso' => true, 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['sucesso' => false, 'erro' => $conn->error]);
        }
        exit;
    }

    public function update() {
        $conn = $this->db;
        $this->requireAuth();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $data_hora = trim($input['data_hora'] ?? '');
        $data_fim = trim($input['data_fim'] ?? '');

        if ($id <= 0 || !$data_hora) {
            echo json_encode(['sucesso' => false]);
            exit;
        }

        if ($data_fim) {
            $stmt = $conn->prepare("UPDATE agendamentos SET data_hora = ?, data_fim = ? WHERE id = ?");
            $stmt->bind_param('ssi', $data_hora, $data_fim, $id);
        } else {
            $stmt = $conn->prepare("UPDATE agendamentos SET data_hora = ? WHERE id = ?");
            $stmt->bind_param('si', $data_hora, $id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['sucesso' => true]);
        } else {
            echo json_encode(['sucesso' => false]);
        }
        exit;
    }

    public function delete() {
        $conn = $this->db;
        $this->requireAuth();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['sucesso' => false]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['sucesso' => true]);
        } else {
            echo json_encode(['sucesso' => false]);
        }
        exit;
    }

    public function logs() {
        $conn = $this->db;
        $this->requireAuth();

        $registros_por_pagina = 15;
        $pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $offset = ($pagina_atual - 1) * $registros_por_pagina;

        // Total de registros para calcular o total de páginas
        $resTotal = $conn->query("SELECT COUNT(*) FROM logs_envio");
        $total_registros = $resTotal ? $resTotal->fetch_row()[0] : 0;
        $total_paginas = ceil($total_registros / $registros_por_pagina);

        // Busca logs paginados
        $logs = [];
        $sql = "SELECT * FROM logs_envio ORDER BY criado_em DESC LIMIT $offset, $registros_por_pagina";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $logs[] = $row;
            }
        }

        $this->view('agendamento/logs', [
            'logs' => $logs,
            'total_paginas' => $total_paginas,
            'pagina_atual' => $pagina_atual
        ]);
    }
}


