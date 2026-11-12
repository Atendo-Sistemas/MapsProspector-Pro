const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('app/app.js', 'utf8');
const start = source.indexOf('function loadRequestsTab()');
const end = source.indexOf('\nfunction renderSaasConfigTab(', start);
assert(start >= 0 && end > start);
function section() { return { innerHTML: '', querySelectorAll: () => [] }; }
const credits = section();
const plans = section();
const context = {
    document: { getElementById: id => id === 'requests-credits-section' ? credits : plans },
    fetch: async url => url.endsWith('credit-requests.php')
        ? { json: async () => ({ success: false, error: 'Erro de créditos' }) }
        : { json: async () => ({ success: true, data: { items: [
            { id: '1', status: 'pending', tenantName: 'Empresa', planName: 'Básico' },
            { id: '2', status: 'approved', tenantName: '<Empresa>', planName: 'Avançado' },
            { id: '3', status: 'rejected', tenantName: 'Outra', planName: 'Plano' }
        ] } }) },
    API_BASE: '/api/',
    console: { error() {} }
};
vm.runInNewContext(source.slice(start, end), context);
context.loadRequestsTab();
setImmediate(() => {
    try {
        assert(credits.innerHTML.includes('não foi possível carregar'));
        assert(plans.innerHTML.includes('Aprovar plano'));
        assert(plans.innerHTML.includes('Histórico de solicitações manuais (2)'));
        assert(plans.innerHTML.includes('&lt;Empresa&gt;'));
        assert(plans.innerHTML.includes('Aprovada'));
        assert(plans.innerHTML.includes('Recusada'));
        assert.strictEqual((plans.innerHTML.match(/class="request-approve/g) || []).length, 1);
        console.log('Plan request history UI: OK');
    } catch (error) {
        console.error(error);
        process.exitCode = 1;
    }
});
