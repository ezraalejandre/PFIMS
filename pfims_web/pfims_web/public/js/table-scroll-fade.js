// Set up the table scroll fade page actions.
(function () {
    var SCROLL_SELECTOR = '.table-wrapper, .table-wrap, .table-container, .table-responsive, .budget-table-wrapper, .items-table-wrapper, .forecast-table-wrapper, .report-table-wrapper, .analytics-table-wrapper';
    var resizeObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(function (entries) { entries.forEach(function (entry) { updateEdges(entry.target); }); })
        : null;

    // Update edges.
    function updateEdges(el) {
        var maxScroll = el.scrollWidth - el.clientWidth;
        var scrollable = maxScroll > 1;
        var atStart = el.scrollLeft <= 1;
        var atEnd = el.scrollLeft >= maxScroll - 1;

        el.classList.toggle('is-at-start', !scrollable || atStart);
        el.classList.toggle('is-at-end', !scrollable || atEnd);
    }

    // Get the bind.
    function bind(el) {
        updateEdges(el);
        // Return early if the pfims scroll fade equals ready.
        if (el.dataset.pfimsScrollFade === 'ready') return;
        el.dataset.pfimsScrollFade = 'ready';
        // Handle the scroll event.
        el.addEventListener('scroll', function () { updateEdges(el); }, { passive: true });
        // Run this step if the resize observer is set.
        if (resizeObserver) resizeObserver.observe(el);
        new MutationObserver(function () { updateEdges(el); }).observe(el, {
            childList: true, subtree: true, attributes: true,
            attributeFilter: ['class', 'style', 'hidden', 'colspan']
        });
    }

    // Set up this page.
    function init() {
        document.querySelectorAll(SCROLL_SELECTOR).forEach(bind);
    }

    // Handle the resize event.
    window.addEventListener('resize', function () {
        document.querySelectorAll(SCROLL_SELECTOR).forEach(updateEdges);
    });

    new MutationObserver(init).observe(document.documentElement, { childList: true, subtree: true });

    // Run this step if the ready state equals loading.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Call this manually after any JS that loads table rows dynamically
    // (e.g. after fetchProjects(), fetchExpenses(), etc.) so newly-added
    // content is measured correctly.
    window.refreshTableScrollFade = init;
})();
