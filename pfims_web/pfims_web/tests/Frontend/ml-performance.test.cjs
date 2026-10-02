const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function page(fetch = () => { throw new Error('Unexpected evaluation request'); }) {
    const elements = new Map();
    const get = id => {
        if (!elements.has(id)) elements.set(id, {value: '', textContent: '', disabled: false, listeners: {}, addEventListener(event, callback) {this.listeners[event] = callback;}});
        return elements.get(id);
    };
    get('performanceSource').value = 'active';
    get('performanceWeighting').value = 'all';
    get('performanceThreshold').value = 'material_overrun';
    get('predictiveAnalyticsRoot').dataset = {apiBase: '/api/ml'};
    const context = {document: {getElementById: get, querySelector: () => ({content: 'csrf'})}, window: {}, fetch};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/ml-performance.js'), 'utf8'), context);
    return {get, update: context.window.pfimsPerformance.update, change(id, value) {get(id).value = value; get(id).listeners.change();}};
}
const detection = {precision: 58.82, recall: 66.67, f1_score: 62.5, classification_accuracy: 60, actual_overruns: 15, evaluated_observations: 30, classification_counts: {tp: 10, fn: 5, fp: 7, tn: 8}};
const report = {status: 'evaluated', evaluation: {mean_absolute_percentage_error: 6.99, evaluation_observations: 30, evaluation_projects: 10, overrun_detection: {material_overrun: detection, any_overrun: {...detection, precision: 72.73}}, latest_observation_per_project: {evaluation_observations: 10, evaluation_projects: 10, overrun_detection: {material_overrun: {...detection, recall: 80, evaluated_observations: 10}}}}, budget_baseline_comparison: {model_outperforms_budget_baseline: false, baseline_evaluation: {mean_absolute_percentage_error: 7.71}}, model_comparison: {production_model_is_best_option: false}};
const active = {prediction_strategy: 'planning_only_baseline', mean_absolute_percentage_error: 5.97, test_samples: 9, precision: null, recall: null, f1_score: null, candidate_evaluations: {reports: {presentation_progress: report}}};

test('active and rejected candidate scores retain their own scopes and denominators', () => {
    const p = page(); p.update(active);
    assert.equal(p.get('metricMAPE').textContent, '5.97%');
    assert.equal(p.get('metricRecall').textContent, 'Unavailable');
    assert.equal(p.get('refreshPerformance').disabled, true);
    p.change('performanceSource', 'presentation_progress');
    assert.equal(p.get('metricMAPE').textContent, '6.99%');
    assert.equal(p.get('metricRecall').textContent, '66.67%');
    assert.equal(p.get('metricOverrunAccuracy').textContent, '60.00%');
    assert.equal(p.get('samplesCount').textContent, '10 test projects · 30 observations');
    assert.match(p.get('performanceStatus').textContent, /does not meet/);
    p.change('performanceWeighting', 'latest');
    assert.equal(p.get('metricRecall').textContent, '80.00%');
    assert.equal(p.get('samplesCount').textContent, '10 test projects · 10 observations');
    p.change('performanceSource', 'active');
    assert.equal(p.get('metricMAPE').textContent, '5.97%');
});
test('changing the overrun definition does not substitute unsupported legacy scores', () => {
    const p = page(); p.update({...active, precision: 0, recall: 0, f1_score: 0});
    assert.equal(p.get('metricRecall').textContent, '0.00%');
    p.change('performanceThreshold', 'any_overrun');
    assert.equal(p.get('metricRecall').textContent, 'Unavailable');
    assert.equal(p.get('metricOverrunAccuracy').textContent, 'Unavailable');
    p.change('performanceSource', 'presentation_progress');
    assert.equal(p.get('metricPrecision').textContent, '72.73%');
});
test('missing reports clear previous results and explain insufficient evidence', () => {
    const p = page(); p.update(active); p.change('performanceSource', 'planning');
    assert.equal(p.get('metricMAPE').textContent, 'Unavailable');
    assert.match(p.get('performanceStatus').textContent, /No saved evaluation/);
    p.update({candidate_evaluations: {reports: {planning: {status: 'insufficient_projects'}}}});
    assert.match(p.get('performanceStatus').textContent, /Not enough/);
});
test('refresh uses evaluation endpoint and preserves active scores', async () => {
    let calls = 0;
    const p = page(async (url, options) => {
        calls++; assert.equal(url, '/api/ml/evaluate'); assert.equal(options.method, 'POST');
        assert.equal(JSON.parse(options.body).cohort, 'presentation_progress');
        return {ok: true, json: async () => ({success: true, report})};
    });
    p.update(active); p.change('performanceSource', 'presentation_progress');
    await p.get('refreshPerformance').listeners.click();
    assert.equal(calls, 1); assert.equal(p.get('refreshPerformance').disabled, false);
    p.change('performanceSource', 'active'); assert.equal(p.get('metricMAPE').textContent, '5.97%');
});
test('failed refresh retains saved scores and re-enables controls', async () => {
    const p = page(async () => ({ok: false, json: async () => ({})}));
    p.update(active); p.change('performanceSource', 'presentation_progress');
    await p.get('refreshPerformance').listeners.click();
    assert.equal(p.get('metricMAPE').textContent, '6.99%');
    assert.match(p.get('performanceStatus').textContent, /could not be refreshed/);
    assert.equal(p.get('performanceSource').disabled, false);
    assert.equal(p.get('refreshPerformance').disabled, false);
});
