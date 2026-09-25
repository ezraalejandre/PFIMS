import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/theme.js', import.meta.url), 'utf8');
const start = script.indexOf('function updateCharts(isDark)');
const end = script.indexOf('window.toggleDarkMode', start);
const source = script.slice(start, end);

test('theme refresh changes raw chart options without assigning resolved Chart.js proxies', () => {
    let updates = 0;
    const rawOptions = { plugins: {}, scales: { y: {} } };
    const chart = {
        config: { options: rawOptions },
        options: new Proxy({}, {
            get() { throw new Error('Resolved Chart.js options must not be read'); },
            set() { throw new Error('Resolved Chart.js options must not be assigned'); },
        }),
        update() { updates += 1; },
    };
    const context = {
        window: { Chart: { defaults: {}, instances: { 1: chart } } },
        Chart: { defaults: {}, instances: { 1: chart } },
        console,
    };
    vm.runInNewContext(source + '\nupdateCharts(false);', context);
    assert.equal(updates, 1);
    assert.equal(rawOptions.plugins.legend.labels.color, '#475569');
    assert.equal(rawOptions.scales.y.grid.color, 'rgba(71, 85, 105, 0.14)');
});
