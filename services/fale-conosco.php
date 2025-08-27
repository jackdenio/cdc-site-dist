<?php
// Incluir configurações e funções de email
require_once 'config.php';
require_once 'email-config.php';

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }

    // Validar reCAPTCHA
    $recaptchaToken = $input['recaptchaToken'] ?? '';
    
    // Log para debug
    error_log("reCAPTCHA token recebido: " . ($recaptchaToken ? 'presente' : 'vazio'));
    error_log("Dados recebidos: " . json_encode($input));
    
    // TEMPORÁRIO: Aceitar tokens vazios para debug
    if (empty($recaptchaToken)) {
        logSubmission('fale_conosco', $input, 'recaptcha_empty_accepted_temp');
        // Aceitar temporariamente para debug
    } elseif ($recaptchaToken === 'test_token_for_debug') {
        logSubmission('fale_conosco', $input, 'debug_token_accepted');
    } elseif (!validateRecaptcha($recaptchaToken)) {
        logSubmission('fale_conosco', $input, 'recaptcha_failed');
        throw new Exception('Verificação de segurança falhou. Tente novamente.');
    }

    // Validar campos obrigatórios
    $nome = sanitizeInput($input['nome'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $telefone = sanitizeInput($input['telefone'] ?? '');
    $mensagem = sanitizeInput($input['mensagem'] ?? '');

    if (empty($nome) || empty($email) || empty($telefone) || empty($mensagem)) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
    }

    // Validar email
    if (!validateEmail($email)) {
        throw new Exception('E-mail inválido');
    }

    // Validar telefone
    if (!validatePhone($telefone)) {
        throw new Exception('Telefone inválido');
    }

    // Preparar dados para log
    $logData = [
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone,
        'mensagem' => $mensagem,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    // Preparar mensagem do email usando o template bonito
    $subject = 'Nova mensagem do site - Fale Conosco';
    $messageData = [
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone,
        'mensagem' => $mensagem
    ];
    $message = getFaleConoscoTemplate($messageData);

    // Enviar email para o colégio
    $emailSent = sendEmail($subject, $message, [], 'fale_conosco');
    
    if (!$emailSent) {
        logSubmission('fale_conosco', $logData, 'email_failed');
        throw new Exception('Erro ao enviar mensagem. Tente novamente.');
    }

    // Enviar email de confirmação para o usuário
    $confirmationSent = sendConfirmationEmail($email, $nome, 'fale_conosco');
    
    if (!$confirmationSent) {
        // Log do erro mas não falha o envio principal
        logSubmission('fale_conosco', ['user_email' => $email, 'user_name' => $nome], 'confirmation_failed');
    }

    // Log de sucesso
    logSubmission('fale_conosco', $logData, 'success');

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Mensagem enviada com sucesso! Entraremos em contato em breve.'
    ]);

} catch (Exception $e) {
    // Log de erro
    $errorData = [
        'error' => $e->getMessage(),
        'input' => $input ?? [],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    logSubmission('fale_conosco', $errorData, 'error');

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 