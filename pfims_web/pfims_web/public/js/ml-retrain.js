(() => {
    const button = document.getElementById('manualRetrainButton');
    const status = document.getElementById('retrainStatus');
    const root = document.getElementById('predictiveAnalyticsRoot');
    if (!button || !status || !root) return;
    let running = false;
    button.addEventListener('click', async () => {
        if (running) return;
        running = true;
        button.disabled = true;
        button.textContent = 'Retraining…';
        status.textContent = 'Training and evaluating with the existing pipeline. This may take several minutes. Keep this page open.';
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(`${root.dataset.apiBase}/retrain`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const data = await response.json();
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
