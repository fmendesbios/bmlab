<?php

namespace App\Controllers;

use App\Core\Controller;
use Exception;
use mysqli_sql_exception;

class PacientesController extends Controller {

    public function index() {
        $conn = $this->db;

        if (!temPermissao('pacientes.acesso')) {
            $_SESSION['flash_error'] = 'Acesso negado.';
            header('Location: index.php?r=home');
            exit;
        }

        $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        // Base query conditions
        $where = "WHERE 1=1";
        if ($busca) {
            $term = $conn->real_escape_string($busca);
            $where .= " AND (p.nome LIKE '%$term%' OR p.cpf LIKE '%$term%' OR p.codigo_paciente LIKE '%$term%')";
        }

        // Count Total
        $totalRecords = 0;
        $sqlCount = "SELECT COUNT(*) as total FROM pacientes p $where";
        $resCount = $conn->query($sqlCount);
        if ($resCount) {
            $row = $resCount->fetch_assoc();
            $totalRecords = (int)$row['total'];
        }
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "SELECT p.*, m.nome as municipio_nome, m.uf as municipio_uf 
                FROM pacientes p 
                LEFT JOIN municipios m ON p.municipio_id = m.id 
                $where";
        
        $sql .= " ORDER BY p.nome ASC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $pacientes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $pacientes[] = $row;
            }
        }

        // Fetch Municipios for the modal
        $municipios = [];
        $resM = $conn->query("SELECT id, nome, uf FROM municipios ORDER BY nome ASC");
        if ($resM) {
            while ($row = $resM->fetch_assoc()) {
                $municipios[] = $row;
            }
        }

        $this->view('pacientes/index', [
            'pacientes' => $pacientes,
            'municipios' => $municipios,
            'busca' => $busca,
            'route' => 'pacientes',
            'page' => $page,
            'limit' => $limit,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages
        ]);
    }

    public function create() {
        $this->form();
    }

    public function edit() {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: index.php?r=pacientes');
            exit;
        }
        $this->form($id);
    }

    private function form($id = 0) {
        $conn = $this->db;
        $paciente = [];
        
        if ($id > 0) {
            if (!temPermissao('pacientes.acesso')) {
                $_SESSION['flash_error'] = 'Acesso negado.';
                header('Location: index.php?r=pacientes');
                exit;
            }
            $stmt = $conn->prepare("SELECT * FROM pacientes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $paciente = $res->fetch_assoc();
            $stmt->close();
            
            if (!$paciente) {
                $_SESSION['flash_error'] = 'Paciente não encontrado.';
                header('Location: index.php?r=pacientes');
                exit;
            }
        } else {
             if (!temPermissao('pacientes.acesso')) {
                $_SESSION['flash_error'] = 'Acesso negado.';
                header('Location: index.php?r=pacientes');
                exit;
            }
            // Defaults
            $paciente = [
                'id' => 0,
                'ativo' => 1,
                'nome' => '',
                'nascimento' => '',
                'sexo' => '',
                'rg' => '',
                'orgao_rg' => '',
                'cpf' => '',
                'codigo_paciente' => '',
                'nf_responsavel_nome' => '',
                'nf_responsavel_nascimento' => '',
                'nf_responsavel_cpf' => '',
                'cep' => '',
                'endereco' => '',
                'numero' => '',
                'complemento' => '',
                'bairro' => '',
                'municipio_id' => '',
                'celular' => '',
                'email' => '',
                'observacoes' => '',
                'gestante_programa' => 0 
            ];
            
            // Generate next code for display
            $sql = "SELECT MAX(id) as max_id FROM pacientes";
            $res = $conn->query($sql);
            $nextId = 1;
            if ($res && $row = $res->fetch_assoc()) {
                $nextId = ((int)$row['max_id']) + 1;
            }
            $paciente['codigo_paciente'] = '01' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
        }

        // Fetch Municipios
        $municipios = [];
        $resM = $conn->query("SELECT id, nome, uf FROM municipios ORDER BY nome ASC");
        if ($resM) {
            while ($row = $resM->fetch_assoc()) {
                $municipios[] = $row;
            }
        }

        $this->view('pacientes/form', [
            'paciente' => $paciente,
            'municipios' => $municipios
        ]);
    }

    private function validarCPF($s) {
        $v = preg_replace('/\D/','', (string)$s);
        if (strlen($v) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $v)) return false;
        $sum = 0;
        for ($i=0; $i<9; $i++) { $sum += (int)$v[$i] * (10 - $i); }
        $d1 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);
        if ((int)$v[9] !== $d1) return false;
        $sum = 0;
        for ($i=0; $i<10; $i++) { $sum += (int)$v[$i] * (11 - $i); }
        $d2 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);
        return (int)$v[10] === $d2;
    }

    public function next_code() {
        if (!temPermissao('pacientes.acesso')) {
            header('Content-Type: application/json');
            echo json_encode(['code' => '']);
            exit;
        }

        $conn = $this->db;
        $sql = "SELECT MAX(id) as max_id FROM pacientes";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            $nextId = ((int)$row['max_id']) + 1;
        }
        
        // Format: 01 + 6 digits (e.g. 01000123)
        $code = '01' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
        
        header('Content-Type: application/json');
        echo json_encode(['code' => $code]);
        exit;
    }

    public function busca_municipio() {
        $conn = $this->db;
        $cep = isset($_GET['cep']) ? preg_replace('/[^0-9]/', '', $_GET['cep']) : '';

        if (strlen($cep) < 8) {
            echo json_encode(['error' => 'CEP inválido']);
            exit;
        }

        // Tenta buscar exato primeiro
        $cepFmt = substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        $sql = "SELECT id, nome, uf FROM municipios WHERE cep = ? OR REPLACE(cep, '-', '') = ? LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $cepFmt, $cep);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && $row = $res->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(null);
        }
        $stmt->close();
        exit;
    }

    public function store() {
        $conn = $this->db;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=pacientes');
            exit;
        }

        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        
        // Fields
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $nome = trim($_POST['nome'] ?? '');
        $nascimento = $_POST['nascimento'] ?? null;
        if (empty($nascimento)) $nascimento = null;
        
        $sexo = $_POST['sexo'] ?? '';
        $rg = trim($_POST['rg'] ?? '');
        $orgao_rg = trim($_POST['orgao_rg'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $codigo_paciente = trim($_POST['codigo_paciente'] ?? '');
        
        $nf_outro_nome_ativo = isset($_POST['nf_outro_nome_ativo']);

        if ($nf_outro_nome_ativo) {
            $nf_responsavel_nome = trim($_POST['nf_responsavel_nome'] ?? '');
            $nf_responsavel_nascimento = $_POST['nf_responsavel_nascimento'] ?? null;
            if (empty($nf_responsavel_nascimento)) $nf_responsavel_nascimento = null;
            $nf_responsavel_cpf = trim($_POST['nf_responsavel_cpf'] ?? '');
        } else {
            $nf_responsavel_nome = '';
            $nf_responsavel_nascimento = null;
            $nf_responsavel_cpf = '';
        }
        
        $cep = trim($_POST['cep'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $municipio_id = isset($_POST['municipio_id']) ? (int)$_POST['municipio_id'] : null;
        if ($municipio_id <= 0) $municipio_id = null;
        
        $celular = trim($_POST['celular'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        // Gestante Logic
        $gestante_programa = isset($_POST['gestante_programa']) ? 1 : 0;
        $gestante_dpp = isset($_POST['gestante_dpp']) && $_POST['gestante_dpp'] !== '' ? $_POST['gestante_dpp'] : null;

        $observacoes = preg_replace('/\[GESTANTE(?:\s+DPP:\d{4}-\d{2}-\d{2})?\]\s*/i', '', $observacoes);
        if ($gestante_programa) {
            $observacoes = '[GESTANTE' . ($gestante_dpp ? ' DPP:' . $gestante_dpp : '') . '] ' . $observacoes;
        }

        // Validation Logic
        if (empty($nome)) {
            echo json_encode(['success' => false, 'message' => 'O nome do paciente é obrigatório.']);
            exit;
        }

        if (empty($sexo)) {
            echo json_encode(['success' => false, 'message' => 'Informe o sexo do paciente.']);
            exit;
        }

        if (!$nascimento) {
            echo json_encode(['success' => false, 'message' => 'Informe o nascimento do paciente.']);
            exit;
        }

        $isMenor = false;
        try {
            $dtN = new \DateTime($nascimento);
            $hoje = new \DateTime('today');
            $anos = (int)$hoje->diff($dtN)->y;
            $isMenor = ($anos < 18);
        } catch (Exception $e) {}

        if (!$isMenor) {
            // Adulto: CPF obrigatório
            if ($cpf === '') {
                echo json_encode(['success' => false, 'message' => 'Informe o CPF do paciente (obrigatório para maiores de 18 anos).']);
                exit;
            } elseif (!$this->validarCPF($cpf)) {
                echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
                exit;
            }
        } else {
            // Menor: CPF opcional
            if ($cpf !== '' && !$this->validarCPF($cpf)) {
                echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
                exit;
            }
            
            // Validação do Responsável para Menores
            if ($nf_responsavel_nome === '') {
                echo json_encode(['success' => false, 'message' => 'Informe o nome do responsável (obrigatório para menores).']);
                exit;
            }
            if (!$nf_responsavel_nascimento) {
                echo json_encode(['success' => false, 'message' => 'Informe o nascimento do responsável.']);
                exit;
            }
            if ($nf_responsavel_cpf === '') {
                echo json_encode(['success' => false, 'message' => 'Informe o CPF do responsável.']);
                exit;
            }
            if (!$this->validarCPF($nf_responsavel_cpf)) {
                echo json_encode(['success' => false, 'message' => 'CPF do responsável inválido.']);
                exit;
            }
        }

        // Other validations
        if ($cep === '') {
            echo json_encode(['success' => false, 'message' => 'Informe o CEP.']);
            exit;
        }
        if ($endereco === '') {
            echo json_encode(['success' => false, 'message' => 'Informe o endereço.']);
            exit;
        }
        if ($numero === '') {
            echo json_encode(['success' => false, 'message' => 'Informe o número.']);
            exit;
        }
        if ($bairro === '') {
            echo json_encode(['success' => false, 'message' => 'Informe o bairro.']);
            exit;
        }
        if (is_null($municipio_id)) {
            echo json_encode(['success' => false, 'message' => 'Selecione o município.']);
            exit;
        }
        
        $celularDigits = preg_replace('/\D/', '', $celular);
        if ($celularDigits === '') {
             echo json_encode(['success' => false, 'message' => 'Informe o celular.']);
             exit;
        }

        // Check Duplicate CPF
        if (!empty($cpf)) {
            $checkSql = "SELECT id FROM pacientes WHERE cpf = ? AND id != ?";
            $stmtCheck = $conn->prepare($checkSql);
            $stmtCheck->bind_param("si", $cpf, $id);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            if ($stmtCheck->num_rows > 0) {
                $stmtCheck->close();
                echo json_encode(['success' => false, 'message' => 'Este CPF já está cadastrado para outro paciente.']);
                exit;
            }
            $stmtCheck->close();
        }

        if ($id > 0) {
            // Edição
            if (!temPermissao('pacientes.acesso')) { 
                echo json_encode(['success' => false, 'message' => 'Sem permissão para editar pacientes.']);
                exit;
            }

            // Generate code if empty or check existing
            if ($codigo_paciente === '') {
                $stmtC = $conn->prepare("SELECT codigo_paciente FROM pacientes WHERE id = ?");
                $stmtC->bind_param("i", $id);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                $rowC = $resC->fetch_assoc();
                $stmtC->close();

                if (empty($rowC['codigo_paciente'])) {
                    $codigo_paciente = '01' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
                } else {
                    $codigo_paciente = $rowC['codigo_paciente'];
                }
            }

            $sql = "UPDATE pacientes SET 
                    ativo=?, nome=?, nascimento=?, sexo=?, rg=?, orgao_rg=?, cpf=?, codigo_paciente=?, 
                    nf_responsavel_nome=?, nf_responsavel_nascimento=?, nf_responsavel_cpf=?, 
                    cep=?, endereco=?, numero=?, complemento=?, bairro=?, municipio_id=?, 
                    celular=?, email=?, observacoes=? 
                    WHERE id=?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssssssssssissssi", 
                $ativo, $nome, $nascimento, $sexo, $rg, $orgao_rg, $cpf, $codigo_paciente,
                $nf_responsavel_nome, $nf_responsavel_nascimento, $nf_responsavel_cpf,
                $cep, $endereco, $numero, $complemento, $bairro, $municipio_id,
                $celular, $email, $observacoes, $id
            );
            
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Paciente atualizado com sucesso!';
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $conn->error]);
            }
            $stmt->close();

        } else {
            // Criação
            if (!temPermissao('pacientes.acesso')) {
                echo json_encode(['success' => false, 'message' => 'Sem permissão para criar pacientes.']);
                exit;
            }

            $sql = "INSERT INTO pacientes (
                    ativo, nome, nascimento, sexo, rg, orgao_rg, cpf, codigo_paciente, 
                    nf_responsavel_nome, nf_responsavel_nascimento, nf_responsavel_cpf, 
                    cep, endereco, numero, complemento, bairro, municipio_id, 
                    celular, email, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssssssssssissss", 
                $ativo, $nome, $nascimento, $sexo, $rg, $orgao_rg, $cpf, $codigo_paciente,
                $nf_responsavel_nome, $nf_responsavel_nascimento, $nf_responsavel_cpf,
                $cep, $endereco, $numero, $complemento, $bairro, $municipio_id,
                $celular, $email, $observacoes
            );
            
            if ($stmt->execute()) {
                $novoId = $conn->insert_id;
                
                if ($codigo_paciente === '') {
                    $codigoAuto = '01' . str_pad((string)$novoId, 6, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE pacientes SET codigo_paciente = '$codigoAuto' WHERE id = $novoId");
                }

                $_SESSION['flash_success'] = 'Paciente criado com sucesso!';
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao criar: ' . $conn->error]);
            }
            $stmt->close();
        }

        exit;
    }

    public function delete() {
        $conn = $this->db;

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: index.php?r=pacientes');
            exit;
        }

        if (!temPermissao('pacientes.acesso')) { 
             $_SESSION['flash_error'] = 'Sem permissão para excluir pacientes.';
             header('Location: index.php?r=pacientes');
             exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM pacientes WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Paciente excluído com sucesso!';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir.';
            }
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['flash_error'] = 'Não é possível excluir: existem registros vinculados a este paciente.';
            } else {
                $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Erro ao excluir: ' . $e->getMessage();
        }

        header('Location: index.php?r=pacientes');
        exit;
    }

    public function checkCpf() {
        $conn = $this->db;
        header('Content-Type: application/json');
        
        $cpf = isset($_GET['cpf']) ? trim($_GET['cpf']) : '';
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($cpf === '') {
            echo json_encode(['exists' => false]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM pacientes WHERE cpf = ? AND id != ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('si', $cpf, $id);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            echo json_encode(['exists' => $exists]);
        } else {
            echo json_encode(['exists' => false, 'error' => $conn->error]);
        }
        exit;
    }

    public function nextCode() {
        $conn = $this->db;
        header('Content-Type: application/json');

        // Get the next auto_increment id
        $result = $conn->query("SELECT AUTO_INCREMENT 
                                FROM information_schema.TABLES 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'pacientes'");
        
        if ($result && $row = $result->fetch_assoc()) {
            $nextId = $row['AUTO_INCREMENT'];
            $code = '01' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
            echo json_encode(['code' => $code]);
        } else {
            // Fallback if permission denied or error
            echo json_encode(['code' => '']);
        }
        exit;
    }
}
