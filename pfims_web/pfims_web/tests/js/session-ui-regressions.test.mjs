import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import vm from 'node:vm';
const ui = readFileSync(new URL('../../public/js/pfims-system-ui.js',import.meta.url),'utf8');
const theme = readFileSync(new URL('../../public/js/theme.js',import.meta.url),'utf8');
function functionSource(name,next) {return ui.slice(ui.indexOf('    function '+name),ui.indexOf('    function '+next,ui.indexOf('    function '+name)));}
function control(text,label='',filter=false) {
    const attrs={'aria-label':label};
    return {textContent:text,title:'',dataset:{},attrs,classList:{add(){}},
        matches:()=>false,hasAttribute:()=>false,
        closest:selector=>filter && selector.includes('.pfims-filter-panel') ? {} : null,
        getAttribute:key=>attrs[key]||'',setAttribute:(key,value)=>attrs[key]=value,
        replaceChildren(){this.replaced=true;}};
}
test('notification and dialog X buttons stay plain while filter clear buttons expand',()=>{
    const close=control('×','Close alert');
    const plain=control('×');
    const clear=control('Clear filters','Clear filters',true);
    const document={querySelectorAll:()=>[close,plain,clear],createElement:()=>({setAttribute(){}})};
    vm.runInNewContext(functionSource('installExpandableActionButtons()','normalizePageSize(')+'\ninstallExpandableActionButtons();',{document,actionIcon:()=>'<svg/>'});
    assert.equal(close.replaced,undefined);assert.equal(close.attrs['aria-label'],'Close alert');
    assert.equal(plain.replaced,undefined);
    assert.equal(clear.replaced,true);assert.equal(clear.attrs.title,'Clear filters');
});
test('a stalled initial request aborts and releases its loading count without cancelling later requests',async()=>{
    const controllers=[];const signals=[];let settled=0;
    const window={location:{pathname:'/audit-logs'},dispatchEvent(){settled++;},
        fetch:(_input,options)=>new Promise((resolve,reject)=>{
            signals.push(options?.signal);
            options?.signal?.addEventListener('abort',()=>reject(new Error('timed out')));
        })};
    const document={currentScript:null,documentElement:{classList:{add(){}}}};
    const prefix=theme.slice(theme.indexOf('    const themeScript'),theme.indexOf('    // Authenticated pages include'));
    vm.runInNewContext(prefix,{window,document,AbortSignal:{timeout(ms){assert.equal(ms,15000);const c=new AbortController();controllers.push(c);return c.signal;},any:AbortSignal.any},CustomEvent:class{}});
    const request=window.fetch('/api/default-filters');
    assert.equal(window.PFIMS_PAGE_PRELOADER.pending,1);
    controllers[0].abort();await assert.rejects(request,/timed out/);
    assert.equal(window.PFIMS_PAGE_PRELOADER.pending,0);assert.equal(settled,1);
    window.PFIMS_PAGE_PRELOADER.active=false;
    window.fetch('/api/projects');assert.equal(signals[1],undefined);
});
test('audit bootstrap does not submit or wait for optional defaults before showing server-rendered records',()=>{
    const blade=readFileSync(new URL('../../resources/views/audit-logs.blade.php',import.meta.url),'utf8');
    const source=blade.slice(blade.indexOf('        (function () {'),blade.indexOf('    </script>',blade.indexOf('        (function () {')));
    let submitted=0;const events=[];
    const form={querySelectorAll:()=>[],querySelector:()=>({addEventListener(){}}),requestSubmit(){submitted++;}};
    const document={getElementById:id=>id==='auditLogFilters'?form:{addEventListener(){}},body:{dataset:{latestAuditId:'1'}}};
    vm.runInNewContext(source.replace(/\{\{.*?\}\}/g,'/audit-logs/latest'),{document,window:{setInterval(){},addEventListener:name=>events.push(name)}});
    assert.equal(submitted,0);assert.equal(events.includes('load'),false);
});
test('landing notice uses actual seconds and avoids authenticated-page initializers',()=>{
    const start=ui.indexOf('    function initializeSystemUi()');
    const landing=ui.slice(start,ui.indexOf("        document.querySelectorAll('header",start))+'\n}\ninitializeSystemUi();';
    const notices=[];
    vm.runInNewContext(landing,{document:{body:{classList:{contains:()=>true},dataset:{inactivityTimeoutSeconds:'90'}}},window:{showPfimsAlert:(...args)=>notices.push(args)}});
    assert.match(notices[0][0],/90 seconds of inactivity/);assert.equal(notices[0][1],'warning');
});

test('GET forms read the action attribute even when a field named action shadows the property',()=>{
    const protectedUi=readFileSync(new URL('../../public/js/protected-navigation.js',import.meta.url),'utf8');
    const start=protectedUi.indexOf("    document.addEventListener('submit'");
    const end=protectedUi.indexOf("    window.addEventListener('pagehide'",start);
    let onSubmit;let target;
    const form={action:'CREATE',getAttribute:name=>name==='method'?'GET':'/audit-logs'};
    vm.runInNewContext(protectedUi.slice(start,end),{
        document:{addEventListener:(name,handler)=>onSubmit=handler},
        window:{location:{href:'https://pfims.test/audit-logs'},pfimsNavigate:href=>target=href},
        URL,URLSearchParams,FormData:class {constructor(){return [['action','CREATE']];}}
    });
    onSubmit({defaultPrevented:false,target:form,preventDefault(){}});
    assert.equal(target,'https://pfims.test/audit-logs?action=CREATE');
});
