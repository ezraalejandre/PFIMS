// Set up the ml retrain page actions.
(() => {
    const button = document.getElementById('manualRetrainButton');
    const status = document.getElementById('retrainStatus');
    const root = document.getElementById('predictiveAnalyticsRoot');
    // Return early if the button is missing or false or the status is missing or false or the root is missing or false.
    if (!button || !status || !root) return;
    let running = false;
    // Handle the click event.
    button.addEventListener('click', async () => {
        // Return early if the running is set.
        if (running) return;
        running = true;
        button.disabled = true;
        button.textContent = 'Retraining…';
        status.textContent = 'Training and evaluating with the existing pipeline. This may take several minutes. Keep this page open.';
        root.setAttribute('aria-busy', 'true');
        // Try this action and handle any errors below.
        try {
            const response = await fetch(`${root.dataset.apiBase}/retrain`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const data = await response.json();
            // Run this step if the response ok is missing or false or the success is missing or false.
            if (!response.ok || !data.success) throw new Error(data.message || 'The training request could not be completed.');
            window.pfimsPerformance.update(data.metrics);
            status.textContent = data.candidate_activated
                ? 'Retraining completed. The active model and its evaluation have been updated.'
                : 'Retraining completed. New scores are shown in “Latest training result”. The active model was retained under the existing activation rules.';
            document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
        } catch (error) {
            status.textContent = `Could not confirm retraining: ${error.message} Refresh the page to check the latest saved results before trying again.`;
        } finally {
            running = false;
            button.disabled = false;
            button.textContent = 'Retrain model';
            root.removeAttribute('aria-busy');
        }
    });
})();
