<?php
/**
 * Configuração Avançada de Email
 * Colégio Dona Clara
 */

// Incluir autoloader do Composer para PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

// Funções necessárias (sem incluir config.php para evitar headers)
if (!function_exists('logSubmission')) {
    function logSubmission($type, $data, $status) {
        $logEntry = date('Y-m-d H:i:s') . " | Type: $type | Status: $status | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | Data: " . json_encode($data) . "\n";
        
        $logFile = __DIR__ . '/logs/form_submissions.log';
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Funções de validação necessárias
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 11;
    }
}

// ========================================
// CONFIGURAÇÕES DE EMAIL
// ========================================

// Emails que receberão os formulários
define('EMAIL_FALE_CONOSCO', 'contato@donaclara.com.br');
define('EMAIL_TRABALHE_CONOSCO', 'rh@donaclara.com.br');

// Email que aparecerá como remetente
define('EMAIL_FROM', 'site_no-reply@donaclara.com.br');

// Nome que aparecerá como remetente
define('EMAIL_NAME', 'Colégio Dona Clara');

// ========================================
// OPÇÕES DE ENVIO DE EMAIL
// ========================================

// Opção 1: Função mail() do PHP (padrão)
define('USE_MAIL_FUNCTION', false);

// Opção 2: SMTP (recomendado para melhor entrega)
define('USE_SMTP', true);

// Configurações SMTP (se USE_SMTP = true)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'site_no-reply@donaclara.com.br'); // Seu email Gmail
define('SMTP_PASSWORD', 'hhnoenohshfzmivj'); // Sua senha de app Gmail
define('SMTP_SECURE', 'tls'); // tls ou ssl

// ========================================
// FUNÇÕES DE ENVIO DE EMAIL
// ========================================

/**
 * Função principal para enviar email
 */
function sendEmail($subject, $message, $attachments = [], $type = 'fale_conosco') {
    // Definir email de destino baseado no tipo
    $emailTo = ($type === 'trabalhe_conosco') ? EMAIL_TRABALHE_CONOSCO : EMAIL_FALE_CONOSCO;
    
    if (USE_SMTP) {
        return sendEmailSMTP($subject, $message, $attachments, $emailTo);
    } else {
        return sendEmailMail($subject, $message, $attachments, $emailTo);
    }
}

/**
 * Função para enviar email de confirmação para o usuário
 */
function sendConfirmationEmail($userEmail, $userName, $type = 'fale_conosco') {
    try {
        $subject = ($type === 'trabalhe_conosco') 
            ? 'Candidatura Recebida - Colégio Dona Clara'
            : 'Mensagem Recebida - Colégio Dona Clara';
        
        $message = ($type === 'trabalhe_conosco') 
            ? getConfirmationTemplateTrabalheConosco($userName)
            : getConfirmationTemplateFaleConosco($userName);
        
        error_log("sendConfirmationEmail: Enviando para $userEmail com assunto: $subject");
        
        if (USE_SMTP) {
            $result = sendEmailSMTP($subject, $message, [], $userEmail);
            error_log("sendConfirmationEmail SMTP resultado: " . ($result ? 'SUCESSO' : 'FALHA'));
            return $result;
        } else {
            $result = sendEmailMail($subject, $message, [], $userEmail);
            error_log("sendConfirmationEmail MAIL resultado: " . ($result ? 'SUCESSO' : 'FALHA'));
            return $result;
        }
    } catch (Exception $e) {
        error_log("sendConfirmationEmail ERRO: " . $e->getMessage());
        return false;
    }
}

/**
 * Envio usando função mail() do PHP
 */
function sendEmailMail($subject, $message, $attachments = [], $emailTo = null) {
    // Usar email padrão se não especificado
    if (!$emailTo) {
        $emailTo = EMAIL_FALE_CONOSCO;
    }
    
    $headers = [
        'From: ' . EMAIL_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To: ' . EMAIL_FROM,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];

    $emailSent = mail($emailTo, $subject, $message, implode("\r\n", $headers));
    
    if (!$emailSent) {
        $errorInfo = error_get_last();
        logSubmission('email_error', [
            'subject' => $subject, 
            'method' => 'mail()', 
            'to' => $emailTo,
            'error_info' => $errorInfo,
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'php_version' => phpversion()
        ], 'failed');
        return false;
    }
    
    logSubmission('email_success', ['subject' => $subject, 'method' => 'mail()', 'to' => $emailTo], 'sent');
    return true;
}

/**
 * Envio usando SMTP (requer PHPMailer)
 */
function sendEmailSMTP($subject, $message, $attachments = [], $emailTo = null) {
    // Usar email padrão se não especificado
    if (!$emailTo) {
        $emailTo = EMAIL_FALE_CONOSCO;
    }
    
    // Verificar se PHPMailer está disponível
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        logSubmission('email_error', ['subject' => $subject, 'error' => 'PHPMailer não encontrado'], 'failed');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Remetente e destinatário
        $mail->setFrom(EMAIL_FROM, EMAIL_NAME);
        $mail->addAddress($emailTo);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_NAME);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        // Anexos
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }

        $mail->send();
        logSubmission('email_success', ['subject' => $subject, 'method' => 'SMTP', 'to' => $emailTo], 'sent');
        return true;
        
    } catch (Exception $e) {
        logSubmission('email_error', ['subject' => $subject, 'error' => $e->getMessage(), 'method' => 'SMTP', 'to' => $emailTo], 'failed');
        return false;
    }
}

/**
 * Função para testar configuração de email
 */
function testEmailConfiguration() {
    $testSubject = 'Teste de Email - Colégio Dona Clara';
    $testMessage = '
    <html>
    <head>
        <title>Teste de Email</title>
    </head>
    <body>
        <h2>Teste de Configuração de Email</h2>
        <p>Este é um email de teste para verificar se a configuração está funcionando.</p>
        <p><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</p>
        <p><strong>Servidor:</strong> ' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '</p>
        <p><strong>Método:</strong> ' . (USE_SMTP ? 'SMTP' : 'mail()') . '</p>
    </body>
    </html>
    ';

    $result = sendEmail($testSubject, $testMessage);
    
    if ($result) {
        return ['success' => true, 'message' => 'Email de teste enviado com sucesso!'];
    } else {
        return ['success' => false, 'message' => 'Erro ao enviar email de teste. Verifique os logs.'];
    }
}

/**
 * Função para obter status da configuração de email
 */
function getEmailStatus() {
    $status = [
        'email_fale_conosco' => EMAIL_FALE_CONOSCO,
        'email_trabalhe_conosco' => EMAIL_TRABALHE_CONOSCO,
        'email_from' => EMAIL_FROM,
        'email_name' => EMAIL_NAME,
        'use_smtp' => USE_SMTP,
        'use_mail_function' => USE_MAIL_FUNCTION,
        'php_mail_available' => function_exists('mail'),
        'smtp_configured' => USE_SMTP ? (defined('SMTP_HOST') && defined('SMTP_USERNAME')) : false
    ];

    if (USE_SMTP) {
        $status['smtp_host'] = SMTP_HOST;
        $status['smtp_port'] = SMTP_PORT;
        $status['smtp_secure'] = SMTP_SECURE;
    }

    return $status;
}

// ========================================
// TEMPLATES DE EMAIL
// ========================================

/**
 * Template para email do formulário Fale Conosco
 */
function getFaleConoscoTemplate($data) {
    return '
    <html>
    <head>
        <title>Nova mensagem - Fale Conosco</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #284089 0%, #1a2b5a 100%); color: white; padding: 40px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 300; opacity: 0.9; }
            .content { padding: 40px 20px; background: #f8f9fa; }
            .message-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
            .section-title { font-size: 18px; font-weight: bold; color: #284089; margin-bottom: 20px; border-bottom: 3px solid #d6b33a; padding-bottom: 10px; }
            .field { margin-bottom: 20px; }
            .label { font-weight: bold; color: #284089; margin-bottom: 8px; display: block; font-size: 14px; }
            .value { padding: 15px; background: #f8f9fa; border-left: 4px solid #d6b33a; border-radius: 6px; font-size: 16px; }
            .message-content { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .message-content .label { color: #856404; }
            .message-content .value { background: white; border-left: 4px solid #ffc107; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
            .info-item { background: #e8f4fd; padding: 15px; border-radius: 8px; }
            .info-item .label { font-size: 12px; color: #284089; margin-bottom: 5px; }
            .info-item .value { background: white; padding: 10px; border-radius: 4px; font-size: 14px; }
            .footer { text-align: center; padding: 30px 20px; color: #666; font-size: 12px; background: #f5f5f5; }
            .logo { max-width: 180px; height: auto; margin-bottom: 15px; }
            .footer-logo { max-width: 120px; height: auto; margin-bottom: 15px; }
            .icon { display: inline-block; margin-right: 8px; font-size: 16px; }
            .highlight { background: linear-gradient(135deg, #d6b33a 0%, #ffd966 100%); color: #333; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://donaclara.com.br/site2/uploads/logo-dona-clara-transparent.webp" alt="Colégio Dona Clara" class="logo">
                <h1>Nova Mensagem Recebida</h1>
                <p>Formulário Fale Conosco</p>
            </div>
            <div class="content">
                <div class="message-card">
                    <div class="section-title">📋 Informações do Contato</div>
                    
                    <div class="field">
                        <div class="label">👤 Nome:</div>
                        <div class="value">' . htmlspecialchars($data['nome']) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="label">📧 E-mail:</div>
                        <div class="value">' . htmlspecialchars($data['email']) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="label">📞 Telefone:</div>
                        <div class="value">' . htmlspecialchars($data['telefone']) . '</div>
                    </div>
                    
                    <div class="message-content">
                        <div class="label">💬 Mensagem:</div>
                        <div class="value">' . nl2br(htmlspecialchars($data['mensagem'])) . '</div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">📅 Data/Hora:</div>
                            <div class="value">' . date('d/m/Y H:i:s') . '</div>
                        </div>
                        <div class="info-item">
                            <div class="label">🌐 IP:</div>
                            <div class="value">' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '</div>
                        </div>
                    </div>
                </div>
                
                <div class="highlight">
                    ⚡ Nova mensagem aguardando resposta
                </div>
            </div>
            <div class="footer">
                <img src="https://donaclara.com.br/site2/uploads/logo-dona-clara-transparent.webp" alt="Colégio Dona Clara" class="footer-logo">
                <p>Este email foi enviado automaticamente pelo sistema de formulários do Colégio Dona Clara.</p>
                <p>© 2025 Colégio Dona Clara. Todos os direitos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

/**
 * Template para email do formulário Trabalhe Conosco
 */
function getTrabalheConoscoTemplate($data) {
    return '
    <html>
    <head>
        <title>Nova candidatura - Trabalhe Conosco</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #284089; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #284089; }
            .value { padding: 10px; background: white; border-left: 4px solid #d6b33a; }
            .section { margin: 20px 0; padding: 15px; background: white; border-radius: 5px; }
            .section-title { font-size: 18px; font-weight: bold; color: #284089; margin-bottom: 10px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Nova Candidatura - Trabalhe Conosco</h1>
                <p>Colégio Dona Clara</p>
            </div>
            <div class="content">
                <div class="section">
                    <div class="section-title">Informações Pessoais</div>
                    <div class="field">
                        <div class="label">Nome:</div>
                        <div class="value">' . htmlspecialchars($data['nome']) . '</div>
                    </div>
                    <div class="field">
                        <div class="label">E-mail:</div>
                        <div class="value">' . htmlspecialchars($data['email']) . '</div>
                    </div>
                    <div class="field">
                        <div class="label">Telefone:</div>
                        <div class="value">' . htmlspecialchars($data['telefone']) . '</div>
                    </div>
                    <div class="field">
                        <div class="label">Endereço:</div>
                        <div class="value">' . htmlspecialchars($data['endereco'] ?: 'Não informado') . '</div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Áreas de Interesse</div>
                    <div class="field">
                        <div class="label">Professor:</div>
                        <div class="value">' . htmlspecialchars($data['professor'] ?: 'Não informado') . '</div>
                    </div>
                    <div class="field">
                        <div class="label">Facilitador:</div>
                        <div class="value">' . htmlspecialchars($data['facilitador'] ?: 'Não informado') . '</div>
                    </div>
                    <div class="field">
                        <div class="label">Estágio:</div>
                        <div class="value">' . htmlspecialchars($data['estagio'] ?: 'Não informado') . '</div>
                    </div>
                    <div class="field">
                        <div class="label">Setor Administrativo:</div>
                        <div class="value">' . htmlspecialchars($data['setor'] ?: 'Não informado') . '</div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Mensagem/Observações</div>
                    <div class="value">' . nl2br(htmlspecialchars($data['mensagem'] ?: 'Não informado')) . '</div>
                </div>
                
                <div class="field">
                    <div class="label">Currículo Anexado:</div>
                    <div class="value">' . htmlspecialchars($data['arquivo'] ?: 'Não anexado') . '</div>
                </div>
                
                <div class="field">
                    <div class="label">Data/Hora:</div>
                    <div class="value">' . date('d/m/Y H:i:s') . '</div>
                </div>
                
                <div class="field">
                    <div class="label">IP:</div>
                    <div class="value">' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '</div>
                </div>
            </div>
            <div class="footer">
                <p>Este email foi enviado automaticamente pelo sistema de formulários do Colégio Dona Clara.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

/**
 * Template para email de confirmação - Fale Conosco
 */
function getConfirmationTemplateFaleConosco($userName) {
    return '
    <html>
    <head>
        <title>Mensagem Recebida - Colégio Dona Clara</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: #284089; color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; }
            .content { padding: 40px 20px; background: #f9f9f9; }
            .message { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .greeting { font-size: 18px; color: #284089; margin-bottom: 20px; }
            .text { margin-bottom: 20px; line-height: 1.8; }
            .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #d6b33a; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; background: #f5f5f5; }
            .contact-info { background: #e8f4fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .contact-info h3 { margin: 0 0 10px 0; color: #284089; }
            .contact-info p { margin: 5px 0; }
            .logo { max-width: 200px; height: auto; margin-bottom: 15px; }
            .footer-logo { max-width: 150px; height: auto; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://donaclara.com.br/site2/uploads/logo-dona-clara-transparent.webp" alt="Colégio Dona Clara" class="logo">
            </div>
            <div class="content">
                <div class="message">
                    <div class="greeting">Olá, ' . htmlspecialchars($userName) . '!</div>
                    
                    <div class="text">
                        Recebemos sua mensagem através do formulário "Fale Conosco" em nosso site.
                    </div>
                    
                    <div class="highlight">
                        <strong>Obrigado por entrar em contato conosco!</strong><br>
                        Nossa equipe analisará sua mensagem e retornaremos em breve.
                    </div>
                    
                    <div class="text">
                        Estamos comprometidos em responder todas as mensagens no menor tempo possível.
                    </div>
                    
                    <div class="contact-info">
                        <h3>📞 Precisa de atendimento imediato?</h3>
                        <p><strong>Telefone:</strong> (31) 3497-6919</p>
                        <p><strong>Horário:</strong> Segunda a Sexta, 07h às 18h</p>
                    </div>
                    
                    <div class="text">
                        Atenciosamente,<br>
                        <strong>Equipe Colégio Dona Clara</strong>
                    </div>
                </div>
            </div>
            <div class="footer">
                <img src="https://donaclara.com.br/site2/uploads/logo-dona-clara-transparent.webp" alt="Colégio Dona Clara" class="footer-logo">
                <p>Este é um email automático. Por favor, não responda a esta mensagem.</p>
                <p>© 2025 Colégio Dona Clara. Todos os direitos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

/**
 * Template para email de confirmação - Trabalhe Conosco
 */
function getConfirmationTemplateTrabalheConosco($userName) {
    return '
    <html>
    <head>
        <title>Candidatura Recebida - Colégio Dona Clara</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .header { background: linear-gradient(135deg, #284089 0%, #1a2b5a 100%); color: white; padding: 40px 20px; text-align: center; }
            .content { padding: 40px 20px; background: #f8f9fa; }
            .message { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .greeting { font-size: 18px; color: #284089; margin-bottom: 20px; font-weight: bold; }
            .text { margin-bottom: 20px; line-height: 1.8; font-size: 16px; color: #284089; }
            .highlight { background: linear-gradient(135deg, #d6b33a 0%, #ffd966 100%); color: #333; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; font-weight: bold; }
            .footer { text-align: center; padding: 30px 20px; color: #666; font-size: 12px; background: #f5f5f5; }
            .logo { max-width: 180px; height: auto; margin-bottom: 15px; }
            .footer-logo { max-width: 120px; height: auto; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://donaclara.com.br/site2/uploads/logo-dona-clara-transparent.webp" alt="Colégio Dona Clara" class="logo">
            </div>
            <div class="content">
                <div class="message">
                    <div class="greeting">Prezado/a ' . htmlspecialchars($userName) . '</div>
                    
                    <div class="text">
                        Confirmamos o recebimento do seu currículo e agradecemos pelo interesse em fazer parte da nossa equipe no Colégio Dona Clara. O seu currículo foi devidamente encaminhado ao departamento de Recursos Humanos para análise.
                    </div>
                    
                    <div class="text">
                        Assim que surgirem oportunidades alinhadas ao seu perfil, entraremos em contato para dar continuidade ao processo.
                    </div>
                    

                    
                    <div class="text">
                        Atenciosamente,<br>
                        <strong>Recursos Humanos do Colégio Dona Clara</strong>
                    </div>
                </div>
            </div>
            <div class="footer">
                <img src="https://donaclara.com.br/site2/uploads/logo-dona-clara-transparent.webp" alt="Colégio Dona Clara" class="footer-logo">
                <p>Este é um email automático. Por favor, não responda a esta mensagem.</p>
                <p>© 2025 Colégio Dona Clara. Todos os direitos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

?> 