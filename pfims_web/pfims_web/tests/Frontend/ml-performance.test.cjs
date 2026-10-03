const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
function page(){const nodes=new Map();const get=id=>{if(!nodes.has(id))nodes.set(id,{textContent:''});return nodes.get(id)};const ctx={document:{getElementById:get},window:{}};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/ml-performance.js'),'utf8'),ctx);return {get,update:ctx.window.pfimsPerformance.update}}
test('uses active any-overrun results rather than candidate reports',()=>{const p=page();p.update({mean_absolute_percentage_error:5.97,overrun_detection:{any_overrun:{precision:80,recall:90,f1_score:85,classification_accuracy:88}},candidate_evaluations:{reports:{presentation_progress:{evaluation:{precision:1}}}}});assert.equal(p.get('metricPrecision').textContent,'80.00%');assert.equal(p.get('metricOverrunAccuracy').textContent,'88.00%');assert.equal(p.get('metricMAPE').textContent,'5.97%');assert.match(p.get('performanceScope').textContent,/active forecasting model/)});
test('does not reuse legacy above-5-percent accuracy for any overrun, clears stale scores',()=>{const p=page();p.update({precision:null,recall:null,f1_score:null,overrun_classification_accuracy:100});assert.equal(p.get('metricRecall').textContent,'Unavailable');assert.equal(p.get('metricOverrunAccuracy').textContent,'Unavailable');p.update({});assert.equal(p.get('metricOverrunAccuracy').textContent,'Unavailable')});
