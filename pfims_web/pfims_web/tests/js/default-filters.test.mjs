import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/pfims-system-ui.js', import.meta.url), 'utf8');
const start = script.indexOf('function installDefaultFilters()');
const end = script.indexOf('function installAdminAuditNavigation()', start);
const installSource = script.slice(start, end);

function element(tagName = 'SPAN') {
    const listeners = new Map();
    return {
        tagName,
        className: '',
        dataset: {},
        children: [],
        hidden: false,
        textContent: '',
        classList: { add() {}, toggle() {} },
        setAttribute() {},
        appendChild(child) { this.children.push(child); },
        insertAdjacentElement(position, child) { this.children.push(child); },
        prepend(child) { this.children.unshift(child); },
        addEventListener(name, listener) {
            listeners.set(name, [...(listeners.get(name) || []), listener]);
        },
        dispatchEvent(event) {
            (listeners.get(event.type) || []).forEach(listener => listener(event));
        },
    };
}

function setup(defaults, initialOptions = ['all', 'Delayed'], module = 'projects') {
    const component = element('DIV');
    const clear = element('BUTTON');
    component.closest = () => component;
    component.querySelector = selector => selector.includes(', .btn-clear-search') ? clear : null;
    const search = element('INPUT');
    search.id = module === 'dashboard' ? 'search' : 'projectDateFrom';
    search.type = module === 'dashboard' ? 'search' : 'date';
    search.value = '';
    search.closest = () => component;
    const status = element('SELECT');
    status.id = module === 'dashboard' ? 'stockStatus' : 'projectStatusFilter';
    status.value = 'all';
    status.options = initialOptions.map(value => ({ value }));
    status.closest = () => component;
    const controls = { [search.id]: search, [status.id]: status };
    const snapshots = [];
    for (const control of Object.values(controls)) {
        control.addEventListener('change', () => snapshots.push([search.value, status.value]));
    }
    let observeChanges;
    class Observer {
        constructor(callback) { observeChanges = callback; }
        observe() {}
    }
    const body = element('BODY');
    body.dataset = {};
    body.classList.contains = () => false;
    const document = {
        body,
        getElementById: key => controls[key] || null,
        querySelector: () => null,
        createElement: tag => element(tag.toUpperCase()),
    };
    const context = {
        document,
        window: { setTimeout: callback => callback() },
        fetch: async () => ({ ok: true, json: async () => ({ [module]: defaults }) }),
        currentDefaultFilterModule: () => module,
        MutationObserver: Observer,
        Event: class { constructor(type) { this.type = type; this.isTrusted = false; } },
        CSS: { escape: value => value },
    };
    vm.runInNewContext(installSource + '\ninstallDefaultFilters();', context);
    return {
        search,
        status,
        clear,
        component,
        snapshots,
        ready: () => new Promise(resolve => setImmediate(resolve)),
        notifyMutation: () => observeChanges(),
    };
}

test('all saved values are set before filter handlers run; clear and reset remain usable', async () => {
    const ui = setup({ projectDateFrom: '2026-09-01', projectStatusFilter: 'Delayed' });
    await ui.ready();
    assert.equal(ui.search.value, '2026-09-01');
    assert.equal(ui.status.value, 'Delayed');
    assert.deepEqual(ui.snapshots, [['2026-09-01', 'Delayed'], ['2026-09-01', 'Delayed']]);

    ui.clear.dispatchEvent({ type: 'click', isTrusted: true });
    ui.search.value = '';
    ui.status.value = 'all';
    ui.notifyMutation();
    assert.equal(ui.search.value, '');
    assert.equal(ui.status.value, 'all');

    ui.snapshots.length = 0;
    const reset = ui.component.children.find(child => child.className === 'pfims-reset-defaults');
    reset.dispatchEvent({ type: 'click', isTrusted: true });
    assert.deepEqual(ui.snapshots, [['2026-09-01', 'Delayed'], ['2026-09-01', 'Delayed']]);
});

test('a saved dropdown value applies after its options load and after they are rebuilt', async () => {
    const ui = setup({ projectDateFrom: '2026-09-01', projectStatusFilter: 'Delayed' }, ['all']);
    await ui.ready();
    assert.equal(ui.status.value, 'all');
    ui.status.options.push({ value: 'Delayed' });
    ui.notifyMutation();
    assert.equal(ui.status.value, 'Delayed');
    assert.deepEqual(ui.snapshots.at(-1), ['2026-09-01', 'Delayed']);

    ui.status.options = [{ value: 'all' }, { value: 'Delayed' }];
    ui.status.value = 'all';
    ui.notifyMutation();
    assert.equal(ui.status.value, 'Delayed');
});

test('a legacy saved search value is ignored while other defaults still apply', async () => {
    const ui = setup({ projectSearch: 'cement', projectStatusFilter: 'Delayed' });
    await ui.ready();
    assert.equal(ui.search.value, '');
    assert.equal(ui.status.value, 'Delayed');
    assert.deepEqual(ui.snapshots, [['', 'Delayed']]);
});

test('a previously saved dashboard stock code applies to its current labeled option', async () => {
    const ui = setup({ stockStatus: 'out_of_stock' }, ['', 'Out of stock'], 'dashboard');
    await ui.ready();
    assert.equal(ui.status.value, 'Out of stock');
    assert.deepEqual(ui.snapshots, [['', 'Out of stock']]);
});
