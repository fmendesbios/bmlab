<?php

namespace App\Controllers;

use App\Core\Controller;

class ResultadosTextoController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        $this->exigirPermissao('resultados.menu');

        $db = $this->db;
        $mensagem = '';
        $erro = '';

        // Check for edit mode
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $registroAtual = null;

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM resultados_texto WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                $registroAtual = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            }
        }

        // List
        $lista = [];
        $res = $db->query("SELECT id, codigo_texto, texto FROM resultados_texto ORDER BY id DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $lista[] = $row;
            }
        }

        $this->view('cadastros/resultados_texto/index', [
            'lista' => $lista,
            'registroAtual' => $registroAtual,
            'mensagem' => $mensagem,
            'erro' => $erro
        ]);
    }

    public function store()
    {
        $this->requireAuth();
        $this->exigirPermissao('resultados.menu'); // Using same permission for now

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=resultados_texto');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        $codigo = trim($_POST['codigo_texto'] ?? '');
        $db = $this->db;

        if ($texto === '') {
            // Error handling via redirect with param? Or session?
            // For simplicity/consistency with other migrations:
            header('Location: index.php?r=resultados_texto' . ($id ? "&id=$id" : '') . '&error=' . urlencode('Informe o conteúdo do Resultado Texto.'));
            exit;
        }

        if ($id > 0) {
            // Edição: Mantém o código existente (ignora o POST se for readonly, ou usa o POST se quisermos permitir edição manual em algum caso,
            // mas a regra é ser automático. Se o usuário tentar forçar, vamos respeitar o que está no banco ou o que veio?)
            // Melhor: Se veio vazio e é edição, mantemos o que tem. Mas como o campo é readonly e preenchido, deve vir o valor correto.
            // Para garantir integridade, se o código estiver vazio, não atualizamos ele.
            
            $sql = "UPDATE resultados_texto SET texto = ?";
            $params = [$texto];
            $types = "s";
            
            if ($codigo !== '') {
                $sql .= ", codigo_texto = ?";
                $params[] = $codigo;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";

            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
                header('Location: index.php?r=resultados_texto&success=Registro atualizado com sucesso.');
            } else {
                header('Location: index.php?r=resultados_texto&id=$id&error=Erro ao atualizar registro.');
            }
        } else {
            // Inserção: Gera código automático se não vier
            if ($codigo === '' || $codigo === 'Auto') {
                // Busca o maior código numérico
                $resMax = $db->query("SELECT MAX(CAST(codigo_texto AS UNSIGNED)) as max_cod FROM resultados_texto WHERE codigo_texto REGEXP '^[0-9]+$'");
                $maxCod = 0;
                if ($resMax && $row = $resMax->fetch_assoc()) {
                    $maxCod = (int)$row['max_cod'];
                }
                $prox = $maxCod + 1;
                $codigo = str_pad((string)$prox, 4, '0', STR_PAD_LEFT);
            }

            $stmt = $db->prepare("INSERT INTO resultados_texto (texto, codigo_texto) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ss', $texto, $codigo);
                $stmt->execute();
                $stmt->close();
                header('Location: index.php?r=resultados_texto&success=Registro incluído com sucesso.');
            } else {
                header('Location: index.php?r=resultados_texto&error=Erro ao incluir registro.');
            }
        }
        exit;
    }

    public function delete()
    {
        $this->requireAuth();
        $this->exigirPermissao('resultados.menu');

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->db->query("DELETE FROM resultados_texto WHERE id = $id");
        }
        
        header('Location: index.php?r=resultados_texto&success=Registro excluído com sucesso.');
        exit;
    }

    public function get_all_json()
    {
        $this->requireAuth();
        
        $lista = [];
        $res = $this->db->query("SELECT id, codigo_texto as codigo, texto FROM resultados_texto ORDER BY texto ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $lista[] = $row;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($lista);
        exit;
    }
}
