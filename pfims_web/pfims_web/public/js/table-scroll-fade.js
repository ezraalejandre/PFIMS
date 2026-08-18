(function () {
    var SCROLL_SELECTOR = '.table-wrapper, .table-container, .budget-table-wrapper, .items-table-wrapper, .forecast-table-wrapper, .report-table-wrapper';

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
        el.addEventListener('scroll', function () { updateEdges(el); }, { passive: true });
    }

    function init() {
        document.querySelectorAll(SCROLL_SELECTOR).forEach(bind);
    }

    window.addEventListener('resize', function () {
        document.querySelectorAll(SCROLL_SELECTOR).forEach(updateEdges);
    });

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