(function () {
    'use strict';
    var settings = document.querySelector('script[data-session-epoch]');
    if (!settings || !settings.dataset.sessionEpoch || window.PFIMS_PROTECTED_NAVIGATION) return;
    window.PFIMS_PROTECTED_NAVIGATION = true;
    var internalNavigation = false;
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var epoch = settings.dataset.sessionEpoch;
    var pageNonce = '';
    var nativeFetch = window.fetch.bind(window);
    var navigationType = performance.getEntriesByType('navigation')[0]?.type || 'navigate';
    var allowedEntry = settings.dataset.navigationGranted === '1' || navigationType === 'reload';
    var resumeReady = allowedEntry ? nativeFetch('/session/resume',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({mode:navigationType==='reload'?'reload':'navigate'})}).then(async function(response) {
        if (!response.ok) throw new Error('Session ended');
        pageNonce = (await response.json()).page_nonce;
        document.getElementById('pfims-session-privacy')?.remove();
        document.documentElement.style.visibility = 'visible';
        return true;
    }).catch(function() { closeSession(false); window.location.replace('/'); return false; }) : Promise.resolve(false);
    if (!allowedEntry) { closeSession(false); window.location.replace('/'); }
    window.fetch = function(input,options) {
        var url = typeof input === 'string' ? input : input.url;
        if (new URL(url,window.location.href).pathname.startsWith('/session/')) return nativeFetch(input,options);
        return resumeReady.then(function(allowed) { if (!allowed) throw new Error('Session ended'); return nativeFetch(input,options); });
    };

    function closeSession(closing) {
        var data = new FormData(); data.append('_token',csrf); data.append('epoch',epoch); data.append('closing',closing?'1':'0'); data.append('page_nonce',pageNonce);
        navigator.sendBeacon('/session/close',data);
    }
    window.pfimsNavigate = async function(target) {
        if (internalNavigation) return;
        var destination = new URL(target,window.location.href);
        if (destination.origin !== window.location.origin) return;
        try {
            internalNavigation = true;
            var response = await fetch('/session/navigate',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({target:destination.pathname+destination.search})});
            if (!response.ok) throw new Error('Your session has ended or the requested page is unavailable.');
            window.location.assign(destination.href);
        } catch(error) {
            internalNavigation = false;
            window.showPfimsAlert?.(error.message,'error');
            window.PFIMS_PAGE_LOADER?.hide();
        }
    };
    window.pfimsSubmitForm = function(form) { internalNavigation=true; form.submit(); };
    document.addEventListener('click',function(event) {
        var link=event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || link.target==='_blank' || link.hasAttribute('download')) return;
        if (link.matches('.nav-parent-toggle')) return;
        var raw=link.getAttribute('href');
        if (!raw || raw.startsWith('#')) return;
        var target=new URL(link.href,window.location.href);
        if (target.origin!==window.location.origin || target.pathname.startsWith('/api/') || target.pathname.startsWith('/storage/')) return;
        event.preventDefault(); event.stopImmediatePropagation(); window.pfimsNavigate(target.href);
    },true);
    document.addEventListener('submit',function(event) {
        if (event.defaultPrevented) return;
        var form=event.target;
        if ((form.getAttribute('method')||'GET').toUpperCase()==='GET') {
            event.preventDefault();
            var target=new URL(form.action,window.location.origin);
            target.search=new URLSearchParams(new FormData(form)).toString();
            window.pfimsNavigate(target.href);
        } else internalNavigation=true;
    });
    window.addEventListener('pagehide',function() {
        document.documentElement.style.visibility='hidden';
        if (!internalNavigation) closeSession(true);
    });
    window.addEventListener('pageshow',function(event) {
        if (event.persisted) { closeSession(false); window.location.replace('/'); }
    });
})();
