// Set up the protected navigation page actions.
(function () {
    'use strict';
    var settings = document.querySelector('script[data-session-epoch]');
    // Return early if the settings is missing or false or the session epoch is missing or false or the pfims protected navigation is set.
    if (!settings || !settings.dataset.sessionEpoch || window.PFIMS_PROTECTED_NAVIGATION) return;
    window.PFIMS_PROTECTED_NAVIGATION = true;
    var internalNavigation = false;
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var epoch = settings.dataset.sessionEpoch;
    var pageNonce = '';
    var nativeFetch = window.fetch.bind(window);
    var navigationType = performance.getEntriesByType('navigation')[0]?.type || 'navigate';
    var allowedEntry = settings.dataset.navigationGranted === '1' || navigationType === 'reload';
    // Use the result when this request finishes.
    var resumeReady = allowedEntry ? nativeFetch('/session/resume',{method:'POST',signal:AbortSignal.timeout(10000),headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({mode:navigationType==='reload'?'reload':'navigate'})}).then(async function(response) {
        // Run this step if the response ok is missing or false.
        if (!response.ok) {
            // Try this action and handle any errors below.
            try { window.pfimsRememberSessionExpiry?.(await response.clone().json()); } catch (error) {}
            throw new Error('Session ended');
        }
        pageNonce = (await response.json()).page_nonce;
        document.getElementById('pfims-session-privacy')?.remove();
        document.documentElement.style.visibility = 'visible';
        return true;
    }).catch(function() { closeSession(false); window.location.replace('/'); return false; }) : Promise.resolve(false);
    // Run this step if the allowed entry is missing or false.
    if (!allowedEntry) { closeSession(false); window.location.replace('/'); }
    window.fetch = function(input,options) {
        var url = typeof input === 'string' ? input : input.url;
        // Return early if the starts with check matches.
        if (new URL(url,window.location.href).pathname.startsWith('/session/')) return nativeFetch(input,options);
        // Use the result when this request finishes.
        return resumeReady.then(function(allowed) { if (!allowed) throw new Error('Session ended'); return nativeFetch(input,options); });
    };

    // Close session.
    function closeSession(closing) {
        var data = new FormData(); data.append('_token',csrf); data.append('epoch',epoch); data.append('closing',closing?'1':'0'); data.append('page_nonce',pageNonce);
        navigator.sendBeacon('/session/close',data);
    }
    window.pfimsNavigate = async function(target) {
        // Return early if the internal navigation is set.
        if (internalNavigation) return;
        var destination = new URL(target,window.location.href);
        // Return early if the origin does not equal the origin.
        if (destination.origin !== window.location.origin) return;
        // Try this action and handle any errors below.
        try {
            internalNavigation = true;
            var response = await fetch('/session/navigate',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({target:destination.pathname+destination.search})});
            // Run this step if the response ok is missing or false.
            if (!response.ok) throw new Error('Your session has ended or the requested page is unavailable.');
            window.location.assign(destination.href);
        } catch(error) {
            internalNavigation = false;
            window.showPfimsAlert?.(error.message,'error');
            window.PFIMS_PAGE_LOADER?.hide();
        }
    };
    window.pfimsSubmitForm = function(form) { internalNavigation=true; form.submit(); };
    // Handle the click event.
    document.addEventListener('click',function(event) {
        var link=event.target.closest('a[href]');
        // Return early if the value checks match.
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || link.target==='_blank' || link.hasAttribute('download')) return;
        // Return early if the matches check matches.
        if (link.matches('.nav-parent-toggle')) return;
        var raw=link.getAttribute('href');
        // Return early if the raw is missing or false or the starts with check matches.
        if (!raw || raw.startsWith('#')) return;
        var target=new URL(link.href,window.location.href);
        // Return early if the origin does not equal the origin or the starts with check matches or the starts with check matches.
        if (target.origin!==window.location.origin || target.pathname.startsWith('/api/') || target.pathname.startsWith('/storage/')) return;
        event.preventDefault(); event.stopImmediatePropagation(); window.pfimsNavigate(target.href);
    },true);
    // Handle the submit event.
    document.addEventListener('submit',function(event) {
        // Return early if the default prevented is set.
        if (event.defaultPrevented) return;
        var form=event.target;
        // Run this step if the to upper case equals GET.
        if ((form.getAttribute('method')||'GET').toUpperCase()==='GET') {
            event.preventDefault();
            var target=new URL(form.getAttribute('action') || window.location.href,window.location.href);
            target.search=new URLSearchParams(new FormData(form)).toString();
            window.pfimsNavigate(target.href);
        } else internalNavigation=true;
    });
    // Handle the pagehide event.
    window.addEventListener('pagehide',function() {
        document.documentElement.style.visibility='hidden';
        // Run this step if the internal navigation is missing or false.
        if (!internalNavigation) closeSession(true);
    });
    // Handle the pageshow event.
    window.addEventListener('pageshow',function(event) {
        // Run this step if the saved is set.
        if (event.persisted) { closeSession(false); window.location.replace('/'); }
    });
})();
