const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('app/app.js', 'utf8');
function extract(start, end) {
    const from = source.indexOf(start);
    const to = source.indexOf(end, from);
    assert(from >= 0 && to > from);
    return source.slice(from, to);
}
function element() {
    const classes = new Set(['hidden']);
    return {
        textContent: '', innerHTML: '', className: '', disabled: false,
        classList: { add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value) },
        scrollIntoView() { this.scrolled = true; }
    };
}
const elements = {};
['choose-plan-spinner', 'choose-plan-grid', 'choose-plan-error', 'choose-plan-pending', 'choose-plan-history', 'choose-plan-history-list'].forEach(id => { elements[id] = element(); });
const button = element();
button.getAttribute = () => '2';
elements['choose-plan-grid'].querySelectorAll = selector => selector === '.btn-request-plan' ? [button] : [];
let response = { success: false, error: 'Asaas indisponível' };
const context = {
    document: { getElementById: id => elements[id] },
    fetch: async url => {
        if (url.endsWith('plans-public.php')) return { json: async () => ({ success: true, data: { items: [{ id: 2, slug: 'basic', priceMonthly: 20 }] } }) };
        if (url.endsWith('plan-requests.php')) return { ok: true, json: async () => ({ success: true, data: { items: [{ planName: '<Teste>', status: 'pending', createdAt: '' }] } }) };
        return { json: async () => response };
    },
    API_BASE: '/api/',
    dashboardMetricEscape: value => String(value),
    escapeHtml: value => String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;'),
    loadSubscriptionStatus() {},
    console
};
vm.runInNewContext(extract('function loadChoosePlanData(', '\nfunction renderCreditsAdminTab('), context);
vm.runInNewContext(extract('async function loadManualPlanRequests()', '\nasync function loadCrmAddonPlanBox()'), context);

(async () => {
    context.loadChoosePlanData('1');
    await new Promise(resolve => setImmediate(resolve));
    await button.onclick();
    assert.strictEqual(elements['choose-plan-error'].textContent, 'Asaas indisponível');
    assert.strictEqual(elements['choose-plan-error'].classList.contains('hidden'), false);
    assert.strictEqual(elements['choose-plan-error'].scrolled, true);
    response = { success: true, data: { mode: 'asaas', payment_url: 'https://asaas.example/pay' }, message: 'Assinatura criada' };
    await button.onclick();
    assert(elements['choose-plan-pending'].innerHTML.includes('https://asaas.example/pay'));
    assert.strictEqual(elements['choose-plan-pending'].scrolled, true);
    assert.strictEqual(elements['choose-plan-error'].classList.contains('hidden'), true);
    await context.loadManualPlanRequests();
    assert(elements['choose-plan-history-list'].innerHTML.includes('&lt;Teste&gt;'));
    assert(elements['choose-plan-history-list'].innerHTML.includes('Aguardando aprovação manual'));
    assert.strictEqual(elements['choose-plan-history'].classList.contains('hidden'), false);
    console.log('Plan subscription UI regression: OK');
})().catch(error => { console.error(error); process.exitCode = 1; });
