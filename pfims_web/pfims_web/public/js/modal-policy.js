// Set up the modal policy page actions.
(function () {
    'use strict';
    // Return early if the pfims modal policy is set.
    if (window.PFIMS_MODAL_POLICY) return;
    window.PFIMS_MODAL_POLICY = true;
    var backdrops = '.modal-overlay, .modal-backdrop, .pfims-import-overlay, .modal, dialog';
    // Process each type.
    ['pointerdown', 'mousedown', 'mouseup', 'click', 'dblclick', 'touchstart', 'touchend'].forEach(function(type) {
        // Run the add event listener action.
        document.addEventListener(type, function(event) {
            var target = event.target;
            // Return early if the value is missing or false or the matches is missing or false.
            if (!(target instanceof Element) || !target.matches(backdrops)) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    });
})();
