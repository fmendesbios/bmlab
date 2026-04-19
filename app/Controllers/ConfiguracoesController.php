<?php

namespace App\Controllers;

use App\Core\Controller;

class ConfiguracoesController extends Controller {

    public function index() {
        $conn = $this->db;
        $this->requireAuth();

        // Ensure table exists (Legacy behavior)
        $conn->query("CREATE TABLE IF NOT EXISTS configuracoes_sistema (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            chave VARCHAR(50) NOT NULL UNIQUE,
            arquivo_path VARCHAR(255) DEFAULT NULL,
            arquivo_nome VARCHAR(255) DEFAULT NULL,
            mime_type VARCHAR(100) DEFAULT NULL,
            tamanho_bytes INT(11) DEFAULT NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $configAtual = null;
        $resCfg = $conn->query("SELECT * FROM configuracoes_sistema WHERE chave = 'papel_timbrado_pdf' LIMIT 1");
        if ($resCfg && $resCfg->num_rows > 0) { $configAtual = $resCfg->fetch_assoc(); }

        $assinAtual = null;
        $resAss = $conn->query("SELECT * FROM configuracoes_sistema WHERE chave = 'assinatura_carimbo' LIMIT 1");
        if ($resAss && $resAss->num_rows > 0) { $assinAtual = $resAss->fetch_assoc(); }

        $this->view('configuracoes/index', [
            'configAtual' => $configAtual,
            'assinAtual' => $assinAtual
        ]);
    }

    public function store() {
        $conn = $this->db;
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?r=configuracoes');
            exit;
        }

        $acao = $_POST['acao'] ?? '';
        $rootPath = dirname(__DIR__, 2); // Go up from app/Controllers to root

        if ($acao === 'upload_papel_timbrado') {
            if (!isset($_FILES['papel_timbrado']) || !is_uploaded_file($_FILES['papel_timbrado']['tmp_name'])) {
                $_SESSION['flash_error'] = 'Selecione um arquivo PDF.';
            } else {
                $file = $_FILES['papel_timbrado'];
                $mime = $file['type'] ?? '';
                $name = $file['name'] ?? '';
                $size = (int) ($file['size'] ?? 0);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($ext !== 'pdf' || (strpos($mime, 'pdf') === false && $mime !== 'application/octet-stream')) {
                    $_SESSION['flash_error'] = 'Arquivo inválido. Envie um PDF.';
                } else {
                    $dir = $rootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'papel_timbrado';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    
                    $targetRel = 'uploads/papel_timbrado/papel_timbrado.pdf';
                    $targetAbs = $dir . DIRECTORY_SEPARATOR . 'papel_timbrado.pdf';
                    
                    if (file_exists($targetAbs)) { @unlink($targetAbs); }
                    
                    if (move_uploaded_file($file['tmp_name'], $targetAbs)) {
                        $resChk = $conn->query("SELECT id FROM configuracoes_sistema WHERE chave = 'papel_timbrado_pdf' LIMIT 1");
                        if ($resChk && $resChk->num_rows > 0) {
                            $row = $resChk->fetch_assoc();
                            $stmt = $conn->prepare('UPDATE configuracoes_sistema SET arquivo_path = ?, arquivo_nome = ?, mime_type = ?, tamanho_bytes = ? WHERE id = ?');
                            $stmt->bind_param('sssii', $targetRel, $name, $mime, $size, $row['id']);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            $stmt = $conn->prepare('INSERT INTO configuracoes_sistema (chave, arquivo_path, arquivo_nome, mime_type, tamanho_bytes) VALUES (?,?,?,?,?)');
                            $ch = 'papel_timbrado_pdf';
                            $stmt->bind_param('ssssi', $ch, $targetRel, $name, $mime, $size);
                            $stmt->execute();
                            $stmt->close();
                        }
                        $_SESSION['flash_success'] = 'Papel timbrado atualizado com sucesso.';
                    } else {
                        $_SESSION['flash_error'] = 'Falha ao enviar o arquivo.';
                    }
                }
            }
        } elseif ($acao === 'upload_assinatura') {
            if (!isset($_FILES['assinatura_arquivo']) || !is_uploaded_file($_FILES['assinatura_arquivo']['tmp_name'])) {
                $_SESSION['flash_error'] = 'Selecione um arquivo.';
            } else {
                $file = $_FILES['assinatura_arquivo'];
                $mime = $file['type'] ?? '';
                $name = $file['name'] ?? '';
                $size = (int)($file['size'] ?? 0);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $ok = in_array($ext, ['png','jpg','jpeg','pdf']);

                if (!$ok) {
                    $_SESSION['flash_error'] = 'Arquivo inválido. Envie PNG, JPG ou PDF.';
                } else {
                    $dir = $rootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'assinatura';
                    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                    
                    $targetRel = 'uploads/assinatura/assinatura.' . $ext;
                    $targetAbs = $dir . DIRECTORY_SEPARATOR . 'assinatura.' . $ext;
                    
                    // Clear old files
                    foreach (['png','jpg','jpeg','pdf'] as $e) { 
                        $p = $dir . DIRECTORY_SEPARATOR . 'assinatura.' . $e; 
                        if (file_exists($p)) { @unlink($p); } 
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $targetAbs)) {
                        $resChk = $conn->query("SELECT id FROM configuracoes_sistema WHERE chave = 'assinatura_carimbo' LIMIT 1");
                        if ($resChk && $resChk->num_rows > 0) {
                            $row = $resChk->fetch_assoc();
                            $stmt = $conn->prepare('UPDATE configuracoes_sistema SET arquivo_path = ?, arquivo_nome = ?, mime_type = ?, tamanho_bytes = ? WHERE id = ?');
                            $stmt->bind_param('sssii', $targetRel, $name, $mime, $size, $row['id']);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            $stmt = $conn->prepare('INSERT INTO configuracoes_sistema (chave, arquivo_path, arquivo_nome, mime_type, tamanho_bytes) VALUES (?,?,?,?,?)');
                            $ch = 'assinatura_carimbo';
                            $stmt->bind_param('ssssi', $ch, $targetRel, $name, $mime, $size);
                            $stmt->execute();
                            $stmt->close();
                        }
                        $_SESSION['flash_success'] = 'Assinatura atualizada com sucesso.';
                    } else {
                        $_SESSION['flash_error'] = 'Falha ao enviar o arquivo.';
                    }
                }
            }
        }

        header('Location: index.php?r=configuracoes');
        exit;
    }
}
