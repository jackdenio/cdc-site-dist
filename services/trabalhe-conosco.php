<?php
// Incluir autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

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
    // Obter dados do POST (FormData)
    $recaptchaToken = $_POST['recaptchaToken'] ?? '';
    $nome = sanitizeInput($_POST['nome'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $telefone = sanitizeInput($_POST['telefone'] ?? '');
    $endereco = sanitizeInput($_POST['endereco'] ?? '');
    $professor = sanitizeInput($_POST['professor'] ?? '');
    $facilitador = sanitizeInput($_POST['facilitador'] ?? '');
    $estagio = sanitizeInput($_POST['estagio'] ?? '');
    $setor = sanitizeInput($_POST['setor'] ?? '');
    $mensagem = sanitizeInput($_POST['mensagem'] ?? '');

    // Validar reCAPTCHA
    if ($recaptchaToken === 'test_token_for_debug') {
        logSubmission('trabalhe_conosco', $_POST, 'debug_token_accepted');
    } elseif (empty($recaptchaToken)) {
        logSubmission('trabalhe_conosco', $_POST, 'recaptcha_empty');
        throw new Exception('Token de segurança não fornecido');
    } elseif (!validateRecaptcha($recaptchaToken)) {
        logSubmission('trabalhe_conosco', $_POST, 'recaptcha_failed');
        throw new Exception('Verificação de segurança falhou. Tente novamente.');
    }

    if (empty($nome) || empty($email) || empty($telefone)) {
        throw new Exception('Nome, e-mail e telefone são obrigatórios');
    }

    // Validar email
    if (!validateEmail($email)) {
        throw new Exception('E-mail inválido');
    }

    // Validar telefone
    if (!validatePhone($telefone)) {
        throw new Exception('Telefone inválido');
    }

    // Processar upload de arquivo se existir
    $uploadedFile = null;
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = processFileUpload($_FILES['documento']);
        if (!$uploadResult['success']) {
            throw new Exception($uploadResult['error']);
        }
        $uploadedFile = $uploadResult['file'];
    }

    // Preparar dados para log
    $logData = [
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone,
        'endereco' => $endereco,
        'professor' => ($professor === 'Selecione uma opção') ? '' : $professor,
        'facilitador' => ($facilitador === 'Selecione uma opção') ? '' : $facilitador,
        'estagio' => ($estagio === 'Selecione uma opção') ? '' : $estagio,
        'setor' => ($setor === 'Selecione uma opção') ? '' : $setor,
        'mensagem' => $mensagem,
        'arquivo' => $uploadedFile,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    // Preparar mensagem do email
    $subject = 'Nova candidatura - Trabalhe Conosco';
    $message = "
    <html>
    <head>
        <title>Nova candidatura recebida</title>
    </head>
    <body>
        <h2>Nova candidatura recebida através do formulário Trabalhe Conosco</h2>
        <table style='border-collapse: collapse; width: 100%;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Nome:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>$nome</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>E-mail:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>$email</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Telefone:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>$telefone</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Endereço:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . ($endereco ?: 'Não informado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Interesse em Professor:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . (($professor && $professor !== 'Selecione uma opção') ? $professor : 'Não informado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Interesse em Facilitador:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . (($facilitador && $facilitador !== 'Selecione uma opção') ? $facilitador : 'Não informado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Interesse em Estágio:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . (($estagio && $estagio !== 'Selecione uma opção') ? $estagio : 'Não informado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Interesse em Setor Administrativo:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . (($setor && $setor !== 'Selecione uma opção') ? $setor : 'Não informado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Mensagem/Observações:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . ($mensagem ? nl2br($mensagem) : 'Não informado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Currículo Anexado:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . ($uploadedFile ?: 'Não anexado') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>Data/Hora:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . date('d/m/Y H:i:s') . "</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>IP:</td>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "</td>
            </tr>
        </table>
    </body>
    </html>
    ";

    // Preparar anexo se existir
    $attachments = [];
    if ($uploadedFile) {
        $attachments[] = UPLOAD_DIR . $uploadedFile;
    }

    // Enviar email para o RH
    $emailSent = sendEmail($subject, $message, $attachments, 'trabalhe_conosco');
    
    if (!$emailSent) {
        logSubmission('trabalhe_conosco', $logData, 'email_failed');
        throw new Exception('Erro ao enviar candidatura. Tente novamente.');
    }

    // Enviar email de confirmação para o candidato
    $confirmationSent = sendConfirmationEmail($email, $nome, 'trabalhe_conosco');
    
    if (!$confirmationSent) {
        // Log do erro mas não falha o envio principal
        logSubmission('trabalhe_conosco', ['user_email' => $email, 'user_name' => $nome], 'confirmation_failed');
    }

    // Log de sucesso
    logSubmission('trabalhe_conosco', $logData, 'success');

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Candidatura enviada com sucesso! Analisaremos seu perfil e entraremos em contato em breve.'
    ]);

} catch (Exception $e) {
    // Log de erro
    $errorData = [
        'error' => $e->getMessage(),
        'input' => $_POST ?? [],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    logSubmission('trabalhe_conosco', $errorData, 'error');

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 