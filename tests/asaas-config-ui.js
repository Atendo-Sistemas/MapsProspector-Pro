const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('app/app.js', 'utf8');
const start = source.indexOf('function setupPaymentsPanel()');
const end = source.indexOf('\nfunction renderVersionsTab(', start);
assert(start >= 0 && end > start);

function element() {
    const classes = new Set(['hidden']);
    return {
        value: '', textContent: '', innerHTML: '', checked: false, disabled: false,
        classList: {
            add: value => classes.add(value),
            remove: value => classes.delete(value),
            contains: value => classes.has(value),
            toggle: (value, force) => force ? classes.add(value) : classes.delete(value)
        }
    };
}
const ids = ['status', 'message', 'config-form', 'orders', 'orders-list', 'subscriptions',
    'subscriptions-list', 'webhook-box', 'toggle', 'refresh', 'save', 'remove', 'environment',
    'active', 'webhook-url', 'webhook-token-display', 'apikey', 'webhook-token',
    'generate-token', 'generated-token-box', 'generated-token', 'copy-token', 'test-connection'];
const elements = Object.fromEntries(ids.map(id => ['asaas-' + id, element()]));
let config = { api_key_configured: true, webhook_token_configured: true, environment: 'production', active: true, webhook_url: 'https://example.test/webhook' };
let configFails = false;
let calls = [];
const tick = () => new Promise(resolve => setImmediate(resolve));
const context = {
    document: { getElementById: id => elements[id] },
    window: { confirm: () => true },
    confirm: () => true,
    navigator: {},
    API_BASE: '/api/',
    dashboardMetricEscape: String,
    fetch: async (url, options = {}) => {
        calls.push({ url, options });
        if (url.includes('getConfig')) return { json: async () => configFails ? { success: false, error: 'Erro ao carregar' } : { success: true, data: config } };
        if (url.includes('orders')) return { json: async () => ({ success: true, data: { orders: [] } }) };
        if (url.endsWith('settings.php')) return { ok: true, json: async () => ({ success: true, data: { webhookToken: 'new-token' } }) };
        if (url.includes('saveConfig')) return { json: async () => ({ success: true }) };
        throw new Error('Unexpected request: ' + url);
    }
};
vm.runInNewContext(source.slice(start, end), context);

(async () => {
    configFails = true;
    context.setupPaymentsPanel();
    await tick();
    assert.strictEqual(elements['asaas-status'].textContent, 'Erro ao carregar');
    elements['asaas-webhook-token'].value = 'manual';
    elements['asaas-save'].onclick();
    assert(!calls.some(call => call.url.includes('saveConfig')));

    configFails = false;
    elements['asaas-refresh'].onclick();
    await tick();
    elements['asaas-webhook-token'].value = 'old-pending-value';
    await elements['asaas-generate-token'].onclick();
    await tick();
    assert.strictEqual(elements['asaas-webhook-token'].value, '');
    assert.strictEqual(elements['asaas-generated-token'].value, 'new-token');
    elements['asaas-copy-token'].onclick();
    assert(elements['asaas-message'].textContent.includes('Selecione e copie'));
    elements['asaas-save'].onclick();
    await tick();
    const payload = JSON.parse(calls.find(call => call.url.includes('saveConfig')).options.body);
    assert.strictEqual(payload.api_key, '');
    assert.strictEqual(payload.webhook_token, '');
    assert.strictEqual(payload.environment, 'production');
    assert.strictEqual(payload.active, true);

    config = { api_key_configured: false, webhook_token_configured: false, environment: 'sandbox', active: false, webhook_url: 'https://example.test/webhook' };
    elements['asaas-refresh'].onclick();
    await tick();
    calls = [];
    elements['asaas-save'].onclick();
    assert(!calls.some(call => call.url.includes('saveConfig')));
    assert(elements['asaas-message'].textContent.includes('token'));
    console.log('Asaas config UI regression: OK');
})().catch(error => { console.error(error); process.exitCode = 1; });
