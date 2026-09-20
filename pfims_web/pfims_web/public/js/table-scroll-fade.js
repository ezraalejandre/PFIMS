(function () {
    var SCROLL_SELECTOR = '.table-wrapper, .table-wrap, .table-container, .table-responsive, .budget-table-wrapper, .items-table-wrapper, .forecast-table-wrapper, .report-table-wrapper, .analytics-table-wrapper';
    var resizeObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(function (entries) { entries.forEach(function (entry) { updateEdges(entry.target); }); })
        : null;

    function updateEdges(el) {
        var maxScroll = el.scrollWidth - el.clientWidth;
        var scrollable = maxScroll > 1;
        var atStart = el.scrollLeft <= 1;
        var atEnd = el.scrollLeft >= maxScroll - 1;

        el.classList.toggle('is-at-start', !scrollable || atStart);
        el.classList.toggle('is-at-end', !scrollable || atEnd);
    }

    function bind(el) {
        updateEdges(el);
        if (el.dataset.pfimsScrollFade === 'ready') return;
        el.dataset.pfimsScrollFade = 'ready';
        el.addEventListener('scroll', function () { updateEdges(el); }, { passive: true });
        if (resizeObserver) resizeObserver.observe(el);
        new MutationObserver(function () { updateEdges(el); }).observe(el, {
            childList: true, subtree: true, attributes: true,
            attributeFilter: ['class', 'style', 'hidden', 'colspan']
        });
    }

    function init() {
        document.querySelectorAll(SCROLL_SELECTOR).forEach(bind);
    }

    window.addEventListener('resize', function () {
        document.querySelectorAll(SCROLL_SELECTOR).forEach(updateEdges);
    });

    new MutationObserver(init).observe(document.documentElement, { childList: true, subtree: true });

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
