(function () {
    'use strict';
    if (window.PFIMS_MODAL_POLICY) return;
    window.PFIMS_MODAL_POLICY = true;
    var backdrops = '.modal-overlay, .modal-backdrop, .pfims-import-overlay, .modal, dialog';
    ['pointerdown', 'mousedown', 'mouseup', 'click', 'dblclick', 'touchstart', 'touchend'].forEach(function(type) {
        document.addEventListener(type, function(event) {
            var target = event.target;
            if (!(target instanceof Element) || !target.matches(backdrops)) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    });
})();
