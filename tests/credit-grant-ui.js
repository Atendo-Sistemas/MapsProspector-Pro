const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('app/app.js', 'utf8');
const start = source.indexOf('function setupCreditGrantForm()');
const end = source.indexOf('\nfunction loadRequestsTab()', start);
assert(start >= 0 && end > start);
const elements = {};
for (const id of ['credit-grant-form', 'credit-grant-tenant', 'credit-grant-amount', 'credit-grant-submit', 'credit-grant-message']) {
    elements[id] = { value: '', disabled: false, textContent: '', innerHTML: '', appendChild() {} };
}
const storage = new Map();
const requests = [];
let fail = true;
const context = {
    document: { getElementById: id => elements[id], createElement: () => ({}) },
    AppState: { user: { id: 8 } },
    API_BASE: '/api/',
    sessionStorage: {
        getItem: key => storage.get(key) || null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: key => storage.delete(key)
    },
    window: { crypto: { getRandomValues: bytes => bytes.fill(42) } },
    confirm: () => true,
    fetch: async (url, options) => {
        if (url.endsWith('tenants.php')) return { json: async () => ({ success: true, data: { items: [{ id: '2', name: 'Empresa' }] } }) };
        requests.push(JSON.parse(options.body));
        if (fail) { fail = false; throw new Error('connection lost'); }
        return { ok: true, json: async () => ({ success: true, message: 'Créditos adicionados.' }) };
    },
    console: { error() {} },
    Uint8Array
};
vm.runInNewContext(source.slice(start, end), context);
(async () => {
    context.setupCreditGrantForm();
    await new Promise(resolve => setImmediate(resolve));
    elements['credit-grant-tenant'].value = '2';
    elements['credit-grant-amount'].value = '500';
    await elements['credit-grant-form'].onsubmit({ preventDefault() {} });
    assert.strictEqual(storage.size, 1);
    assert.strictEqual(elements['credit-grant-tenant'].disabled, true);
    await elements['credit-grant-form'].onsubmit({ preventDefault() {} });
    assert.strictEqual(requests.length, 2);
    assert.deepStrictEqual(requests[0], requests[1]);
    assert.strictEqual(storage.size, 0);
    assert.strictEqual(elements['credit-grant-tenant'].disabled, false);
    assert.strictEqual(elements['credit-grant-message'].textContent, 'Créditos adicionados.');
    console.log('Credit grant UI regression: OK');
})().catch(error => { console.error(error); process.exitCode = 1; });
