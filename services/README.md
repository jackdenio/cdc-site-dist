# Sistema de Formulários - Colégio Dona Clara

## 📋 Visão Geral

Este sistema PHP processa os formulários do site do Colégio Dona Clara, incluindo validação, reCAPTCHA v3, upload de arquivos e envio de emails.

## 🏗️ Estrutura

```
services/
├── config.php              # Configurações e funções utilitárias
├── fale-conosco.php        # Endpoint para formulário Fale Conosco
├── trabalhe-conosco.php    # Endpoint para formulário Trabalhe Conosco
├── uploads/                # Diretório para arquivos enviados
├── logs/                   # Logs do sistema
└── README.md              # Esta documentação
```

## ⚙️ Configuração

### 1. Configurar reCAPTCHA v3

1. Acesse [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
2. Crie um novo site
3. Escolha reCAPTCHA v3
4. Adicione seu domínio
5. Copie as chaves para `config.php`:

```php
define('RECAPTCHA_SITE_KEY', '6LeqR30rAAAAAMBln8N9adOEcj3mO9-t9Plv8vAP');
define('RECAPTCHA_SECRET_KEY', '6LeqR30rAAAAAHvEoclOIAsYQrkh1YYuJsOuPq5f');
```

### 2. Configurar Email

Edite `config.php` com suas configurações de email:

```php
define('EMAIL_TO', 'contato@donaclara.com.br');
define('EMAIL_FROM', 'noreply@donaclara.com.br');
define('EMAIL_NAME', 'Colégio Dona Clara');
```

### 3. Configurar Permissões

```bash
chmod 755 services/
chmod 644 services/*.php
chmod 755 services/uploads/
chmod 755 services/logs/
```

## 🔧 Endpoints

### POST /services/fale-conosco.php

**Campos obrigatórios:**
- `nome` (string)
- `email` (string, formato válido)
- `telefone` (string, 10-11 dígitos)
- `mensagem` (string)
- `recaptchaToken` (string)

**Resposta de sucesso:**
```json
{
  "success": true,
  "message": "Mensagem enviada com sucesso! Entraremos em contato em breve."
}
```

**Resposta de erro:**
```json
{
  "success": false,
  "error": "Descrição do erro"
}
```

### POST /services/trabalhe-conosco.php

**Campos obrigatórios:**
- `nome` (string)
- `email` (string, formato válido)
- `telefone` (string, 10-11 dígitos)
- `recaptchaToken` (string)

**Campos opcionais:**
- `endereco` (string)
- `professor` (string)
- `facilitador` (string)
- `estagio` (string)
- `setor` (string)
- `mensagem` (string)
- `documento` (file, PDF, máximo 5MB)

**Resposta de sucesso:**
```json
{
  "success": true,
  "message": "Candidatura enviada com sucesso! Analisaremos seu perfil e entraremos em contato em breve."
}
```

## 🛡️ Segurança

### Validações Implementadas

1. **reCAPTCHA v3**: Score mínimo de 0.5
2. **Sanitização**: Todos os inputs são sanitizados
3. **Validação de Email**: Formato válido obrigatório
4. **Validação de Telefone**: 10-11 dígitos numéricos
5. **Upload de Arquivo**: Apenas PDF, máximo 5MB
6. **Headers de Segurança**: CSP, XSS Protection, etc.

### Headers de Segurança

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy`: Configurado para permitir reCAPTCHA

## 📊 Logs

O sistema gera logs detalhados em `logs/form_submissions.log`:

```
2024-01-15 14:30:25 | Type: fale_conosco | Status: success | IP: 192.168.1.1 | Data: {"nome":"João Silva","email":"joao@email.com",...}
2024-01-15 14:31:10 | Type: trabalhe_conosco | Status: recaptcha_failed | IP: 192.168.1.2 | Data: {...}
```

## 📧 Emails

### Formato dos Emails

Os emails são enviados em HTML com tabela formatada contendo:
- Todos os dados do formulário
- Data/hora do envio
- IP do usuário
- Informações do arquivo anexado (se aplicável)

### Configuração de Email

O sistema usa a função `mail()` do PHP. Para melhor entrega, configure:

1. **SPF Record**: Adicione ao DNS
2. **DKIM**: Configure autenticação
3. **DMARC**: Configure política de email

## 🔄 Integração com Frontend

### Exemplo de Uso - Fale Conosco

```javascript
const handleSubmit = async (formData) => {
  try {
    // Obter token do reCAPTCHA
    const recaptchaToken = await grecaptcha.execute('sua_chave_site', {action: 'fale_conosco'});
    
    const response = await fetch('/services/fale-conosco.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        ...formData,
        recaptchaToken
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Sucesso
      console.log(result.message);
    } else {
      // Erro
      console.error(result.error);
    }
  } catch (error) {
    console.error('Erro:', error);
  }
};
```

### Exemplo de Uso - Trabalhe Conosco

```javascript
const handleSubmit = async (formData) => {
  try {
    // Obter token do reCAPTCHA
    const recaptchaToken = await grecaptcha.execute('sua_chave_site', {action: 'trabalhe_conosco'});
    
    // Criar FormData para upload de arquivo
    const form = new FormData();
    form.append('recaptchaToken', recaptchaToken);
    form.append('nome', formData.nome);
    form.append('email', formData.email);
    // ... outros campos
    if (formData.documento) {
      form.append('documento', formData.documento);
    }
    
    const response = await fetch('/services/trabalhe-conosco.php', {
      method: 'POST',
      body: form
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Sucesso
      console.log(result.message);
    } else {
      // Erro
      console.error(result.error);
    }
  } catch (error) {
    console.error('Erro:', error);
  }
};
```

## 🚀 Deploy

### Requisitos do Servidor

- PHP 7.4 ou superior
- Extensão `fileinfo` habilitada
- Função `mail()` configurada
- Permissões de escrita nos diretórios `uploads/` e `logs/`

### Configuração do Apache

Adicione ao `.htaccess`:

```apache
# Permitir upload de arquivos grandes
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300

# Proteger arquivos sensíveis
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

# Proteger logs
<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
```

### Configuração do Nginx

```nginx
location /services/ {
    # Permitir upload de arquivos grandes
    client_max_body_size 10M;
    
    # Proteger arquivos sensíveis
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}

# Negar acesso a arquivos sensíveis
location ~ /services/(config\.php|.*\.log)$ {
    deny all;
}
```

## 🔍 Monitoramento

### Logs Importantes

1. **Sucesso**: `Status: success`
2. **Falha reCAPTCHA**: `Status: recaptcha_failed`
3. **Falha email**: `Status: email_failed`
4. **Erro geral**: `Status: error`

### Métricas Recomendadas

- Taxa de sucesso dos envios
- Taxa de falha do reCAPTCHA
- Volume de spam detectado
- Tempo de resposta dos endpoints

## 🛠️ Manutenção

### Limpeza de Logs

```bash
# Manter apenas últimos 30 dias
find services/logs/ -name "*.log" -mtime +30 -delete
```

### Limpeza de Uploads

```bash
# Manter apenas últimos 90 dias
find services/uploads/ -name "*.pdf" -mtime +90 -delete
```

### Backup

```bash
# Backup diário
tar -czf backup-$(date +%Y%m%d).tar.gz services/
```

## 📞 Suporte

Para dúvidas ou problemas:

1. Verifique os logs em `services/logs/`
2. Teste os endpoints individualmente
3. Verifique configurações de email
4. Confirme chaves do reCAPTCHA

---

**Versão**: 1.0.0  
**Última atualização**: Janeiro 2024  
**Desenvolvido para**: Colégio Dona Clara 