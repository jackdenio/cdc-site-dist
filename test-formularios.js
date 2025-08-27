/**
 * 🧪 Script de Teste Automatizado de Formulários
 * Colégio Dona Clara
 * 
 * Este script testa automaticamente os formulários Fale Conosco e Trabalhe Conosco
 * Executar no console do navegador ou como script Node.js
 */

class FormTester {
  constructor() {
    this.results = [];
    this.baseUrl = window.location.origin;
  }

  // Teste do formulário Fale Conosco
  async testFaleConosco() {
    console.log('🧪 Testando formulário Fale Conosco...');
    
    const testData = {
      nome: 'Teste Automatizado',
      email: 'teste@donaclara.com.br',
      telefone: '(31) 99999-9999',
      mensagem: 'Este é um teste automatizado do sistema de contato.',
      recaptchaToken: 'test_token_for_debug'
    };

    const results = await this.testForm('fale-conosco', testData);
    this.results.push({
      form: 'Fale Conosco',
      timestamp: new Date().toISOString(),
      results
    });

    return results;
  }

  // Teste do formulário Trabalhe Conosco
  async testTrabalheConosco() {
    console.log('🧪 Testando formulário Trabalhe Conosco...');
    
    const testData = {
      nome: 'Candidato Teste',
      email: 'candidato@teste.com',
      telefone: '(31) 88888-8888',
      endereco: 'Rua Teste, 123 - Belo Horizonte/MG',
      professor: 'Educação Infantil',
      facilitador: 'Esportes',
      estagio: 'Pedagogia',
      setor: 'Biblioteca',
      mensagem: 'Teste automatizado do sistema de candidatura.',
      recaptchaToken: 'test_token_for_debug'
    };

    const results = await this.testForm('trabalhe-conosco', testData);
    this.results.push({
      form: 'Trabalhe Conosco',
      timestamp: new Date().toISOString(),
      results
    });

    return results;
  }

  // Função principal de teste
  async testForm(formType, testData) {
    const results = {
      formType,
      tests: []
    };

    // Teste 1: Validação de campos obrigatórios (envio vazio)
    try {
      console.log(`  📝 Teste 1: Validação de campos obrigatórios`);
      const response = await fetch(`${this.baseUrl}/services/${formType}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      });
      
      const responseText = await response.text();
      const isValid = response.status === 400 && responseText.includes('obrigatórios');
      
      results.tests.push({
        name: 'Validação de campos obrigatórios',
        status: isValid ? 'PASS' : 'FAIL',
        expected: 'Status 400 com mensagem de campos obrigatórios',
        actual: `Status ${response.status}: ${responseText.substring(0, 100)}...`,
        details: responseText
      });
    } catch (error) {
      results.tests.push({
        name: 'Validação de campos obrigatórios',
        status: 'ERROR',
        details: error.message
      });
    }

    // Teste 2: Validação de email inválido
    try {
      console.log(`  📝 Teste 2: Validação de email inválido`);
      const invalidEmailData = { ...testData, email: 'email-invalido' };
      const response = await fetch(`${this.baseUrl}/services/${formType}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(invalidEmailData)
      });
      
      const responseText = await response.text();
      const isValid = response.status === 400 && responseText.includes('E-mail inválido');
      
      results.tests.push({
        name: 'Validação de email inválido',
        status: isValid ? 'PASS' : 'FAIL',
        expected: 'Status 400 com mensagem de email inválido',
        actual: `Status ${response.status}: ${responseText.substring(0, 100)}...`,
        details: responseText
      });
    } catch (error) {
      results.tests.push({
        name: 'Validação de email inválido',
        status: 'ERROR',
        details: error.message
      });
    }

    // Teste 3: Validação de telefone inválido
    try {
      console.log(`  📝 Teste 3: Validação de telefone inválido`);
      const invalidPhoneData = { ...testData, telefone: '123' };
      const response = await fetch(`${this.baseUrl}/services/${formType}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(invalidPhoneData)
      });
      
      const responseText = await response.text();
      const isValid = response.status === 400 && responseText.includes('Telefone inválido');
      
      results.tests.push({
        name: 'Validação de telefone inválido',
        status: isValid ? 'PASS' : 'FAIL',
        expected: 'Status 400 com mensagem de telefone inválido',
        actual: `Status ${response.status}: ${responseText.substring(0, 100)}...`,
        details: responseText
      });
    } catch (error) {
      results.tests.push({
        name: 'Validação de telefone inválido',
        status: 'ERROR',
        details: error.message
      });
    }

    // Teste 4: Envio com dados válidos
    try {
      console.log(`  📝 Teste 4: Envio com dados válidos`);
      const response = await fetch(`${this.baseUrl}/services/${formType}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(testData)
      });
      
      const responseText = await response.text();
      let responseData;
      try {
        responseData = JSON.parse(responseText);
      } catch (e) {
        responseData = { success: false, error: 'Resposta não é JSON válido' };
      }
      
      const isValid = response.ok && responseData.success;
      
      results.tests.push({
        name: 'Envio com dados válidos',
        status: isValid ? 'PASS' : 'FAIL',
        expected: 'Status 200 com success: true',
        actual: `Status ${response.status}: ${JSON.stringify(responseData)}`,
        details: responseText
      });
    } catch (error) {
      results.tests.push({
        name: 'Envio com dados válidos',
        status: 'ERROR',
        details: error.message
      });
    }

    return results;
  }

  // Executar todos os testes
  async runAllTests() {
    console.log('🚀 Iniciando testes automatizados de formulários...');
    console.log('📍 URL base:', this.baseUrl);
    console.log('⏰ Timestamp:', new Date().toISOString());
    console.log('');

    await this.testFaleConosco();
    await this.testTrabalheConosco();

    this.generateReport();
  }

  // Gerar relatório
  generateReport() {
    console.log('');
    console.log('📊 RELATÓRIO DE TESTES');
    console.log('='.repeat(50));

    let totalTests = 0;
    let passedTests = 0;
    let failedTests = 0;
    let errorTests = 0;

    this.results.forEach(formResult => {
      console.log(`\n📋 ${formResult.form}`);
      console.log('-'.repeat(30));
      
      formResult.results.tests.forEach(test => {
        totalTests++;
        
        switch (test.status) {
          case 'PASS':
            passedTests++;
            console.log(`✅ ${test.name}`);
            break;
          case 'FAIL':
            failedTests++;
            console.log(`❌ ${test.name}`);
            console.log(`   Esperado: ${test.expected}`);
            console.log(`   Atual: ${test.actual}`);
            break;
          case 'ERROR':
            errorTests++;
            console.log(`💥 ${test.name}`);
            console.log(`   Erro: ${test.details}`);
            break;
        }
      });
    });

    console.log('\n📈 RESUMO');
    console.log('='.repeat(30));
    console.log(`Total de testes: ${totalTests}`);
    console.log(`✅ Passou: ${passedTests}`);
    console.log(`❌ Falhou: ${failedTests}`);
    console.log(`💥 Erro: ${errorTests}`);
    console.log(`📊 Taxa de sucesso: ${((passedTests / totalTests) * 100).toFixed(1)}%`);

    // Salvar relatório no localStorage
    const report = {
      timestamp: new Date().toISOString(),
      summary: {
        total: totalTests,
        passed: passedTests,
        failed: failedTests,
        errors: errorTests,
        successRate: ((passedTests / totalTests) * 100).toFixed(1)
      },
      results: this.results
    };

    localStorage.setItem('formTestReport', JSON.stringify(report));
    console.log('\n💾 Relatório salvo no localStorage como "formTestReport"');

    return report;
  }

  // Enviar relatório por email (opcional)
  async sendReportByEmail(email) {
    const report = this.generateReport();
    
    const emailData = {
      to: email,
      subject: `[TESTE] Relatório de Formulários - ${new Date().toLocaleDateString()}`,
      body: `
        Relatório de Testes Automatizados
        
        Data: ${new Date().toLocaleString()}
        URL: ${this.baseUrl}
        
        Resumo:
        - Total de testes: ${report.summary.total}
        - Passou: ${report.summary.passed}
        - Falhou: ${report.summary.failed}
        - Erro: ${report.summary.errors}
        - Taxa de sucesso: ${report.summary.successRate}%
        
        Detalhes completos em anexo.
      `
    };

    // Aqui você pode implementar o envio do email
    console.log('📧 Relatório preparado para envio:', emailData);
  }
}

// Função para executar testes no navegador
function executarTestesFormularios() {
  const tester = new FormTester();
  return tester.runAllTests();
}

// Função para executar testes específicos
function testarFaleConosco() {
  const tester = new FormTester();
  return tester.testFaleConosco();
}

function testarTrabalheConosco() {
  const tester = new FormTester();
  return tester.testTrabalheConosco();
}

// Função para enviar relatório por email
function enviarRelatorioEmail(email) {
  const tester = new FormTester();
  return tester.sendReportByEmail(email);
}

// Auto-executar se estiver no navegador
if (typeof window !== 'undefined') {
  console.log('🧪 Script de teste de formulários carregado!');
  console.log('Comandos disponíveis:');
  console.log('- executarTestesFormularios() - Executar todos os testes');
  console.log('- testarFaleConosco() - Testar apenas Fale Conosco');
  console.log('- testarTrabalheConosco() - Testar apenas Trabalhe Conosco');
  console.log('- enviarRelatorioEmail("email@exemplo.com") - Enviar relatório por email');
}

// Exportar para Node.js
if (typeof module !== 'undefined' && module.exports) {
  module.exports = FormTester;
}
