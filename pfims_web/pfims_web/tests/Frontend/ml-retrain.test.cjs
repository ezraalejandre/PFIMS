const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function page(fetch){
    let click; const updates=[];
    const button={disabled:false,textContent:'Retrain model',addEventListener:(event,handler)=>{click=handler}};
    const status={textContent:''}; const root={dataset:{apiBase:'/api/ml'},setAttribute(){},removeAttribute(){}};
    const nodes={manualRetrainButton:button,retrainStatus:status,predictiveAnalyticsRoot:root,lastUpdated:{textContent:''}};
    vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../../public/js/ml-retrain.js'),'utf8'),{document:{getElementById:id=>nodes[id],querySelector:()=>({content:'token'})},window:{pfimsPerformance:{update:metrics=>updates.push(metrics)}},fetch});
    return {button,status,updates,click:()=>click()};
}
test('runs one POST while busy, shows candidate results and retains the active model label',async()=>{
    let resolve, calls=0;
    const p=page((url,options)=>{calls++;assert.equal(url,'/api/ml/retrain');assert.equal(options.method,'POST');assert.equal(options.headers['X-CSRF-TOKEN'],'token');return new Promise(r=>{resolve=r})});
    const pending=p.click();assert.equal(p.button.disabled,true);await p.click();assert.equal(calls,1);
    resolve({ok:true,json:async()=>({success:true,candidate_activated:false,metrics:{mean_absolute_error:101581,latest_optimization:{evaluation:{mean_absolute_error:140000}}}})});
    await pending;assert.equal(p.updates[0].latest_optimization.evaluation.mean_absolute_error,140000);assert.match(p.status.textContent,/active model was retained/);assert.equal(p.button.disabled,false);
});
test('reports an activated retrain accurately',async()=>{const p=page(async()=>({ok:true,json:async()=>({success:true,candidate_activated:true,metrics:{mean_absolute_error:50000}})}));await p.click();assert.match(p.status.textContent,/active model and its evaluation have been updated/);assert.equal(p.updates[0].mean_absolute_error,50000)});
test('failed or expired requests leave displayed metrics intact and allow recovery',async()=>{const p=page(async()=>({ok:false,json:async()=>({success:false,message:'Session expired'})}));await p.click();assert.equal(p.updates.length,0);assert.match(p.status.textContent,/Session expired/);assert.match(p.status.textContent,/latest saved results/);assert.equal(p.button.disabled,false)});
