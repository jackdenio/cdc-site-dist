<?php
/**
 * Configurações do Sistema de Formulários
 * Colégio Dona Clara
 */

// ========================================
// OTIMIZAÇÕES PHP PARA TTFB
// ========================================

// Configurações de Performance PHP
ini_set('max_execution_time', 30);
ini_set('memory_limit', '256M');
ini_set('max_input_time', 60);
ini_set('post_max_size', '10M');
ini_set('upload_max_filesize', '10M');

// Otimizações de Output Buffering
if (function_exists('ob_start')) {
    ob_start();
}

// Configurações de Cache
// ini_set('opcache.enable', 1);
// ini_set('opcache.memory_consumption', 128);
// ini_set('opcache.interned_strings_buffer', 8);
// ini_set('opcache.max_accelerated_files', 4000);
// ini_set('opcache.revalidate_freq', 60);
// ini_set('opcache.fast_shutdown', 1);

// Headers de Performance
header('X-Powered-By: PHP/8.1');
header('Server: Apache/2.4');
header('Accept-Encoding: gzip, deflate, br');

// Configurações de Segurança
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Configurações de Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de Email - Movidas para email-config.php
// Use require_once 'email-config.php' para acessar as configurações de email

// Configurações reCAPTCHA v3
define('RECAPTCHA_SITE_KEY', '6LeqR30rAAAAAMBln8N9adOEcj3mO9-t9Plv8vAP');
define('RECAPTCHA_SECRET_KEY', '6LeqR30rAAAAAHvEoclOIAsYQrkh1YYuJsOuPq5f');
define('RECAPTCHA_THRESHOLD', 0.5); // Score mínimo para considerar válido

// Configurações de Upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['pdf']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Configurações de Log
define('LOG_FILE', __DIR__ . '/logs/form_submissions.log');

// Headers de Segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; frame-src https://www.google.com/recaptcha/;');

// Função para validar reCAPTCHA
function validateRecaptcha($token) {
    if (empty($token)) {
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);

    return $response['success'] && $response['score'] >= RECAPTCHA_THRESHOLD;
}

// Função para sanitizar dados
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Função para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para validar telefone
function validatePhone($phone) {
    // Remove caracteres não numéricos
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Verifica se tem entre 10 e 11 dígitos
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

// Função para log
function logSubmission($type, $data, $status) {
    $logEntry = date('Y-m-d H:i:s') . " | Type: $type | Status: $status | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | Data: " . json_encode($data) . "\n";
    
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0755, true);
    }
    
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

// Função para enviar email - Movida para email-config.php
// Use require_once 'email-config.php' para acessar as funções de email

// Função para processar upload de arquivo
function processFileUpload($file) {
    if (!isset($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo foi enviado'];
    }
    
    // Verificar se é um upload real ou simulado (para testes)
    if (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Arquivo inválido'];
    }

    // Verificar tamanho
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 5MB.'];
    }

    // Verificar tipo
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, UPLOAD_ALLOWED_TYPES)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Apenas PDF.'];
    }

    // Criar diretório se não existir
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Gerar nome único
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = UPLOAD_DIR . $fileName;

    // Mover arquivo
    if (is_uploaded_file($file['tmp_name'])) {
        // Upload real via HTTP
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => true, 'file' => $fileName, 'path' => $filePath];
        } else {
            return ['success' => false, 'error' => 'Erro ao salvar arquivo'];
        }
    } else {
        // Upload simulado (para testes)
        if (copy($file['tmp_name'], $filePath)) {
            return ['success' => true, 'file' => $fileName, 'path' => $filePath];
        } else {
            return ['success' => false, 'error' => 'Erro ao salvar arquivo'];
        }
    }
}
?> 