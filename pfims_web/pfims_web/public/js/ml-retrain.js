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

        status.textContent = 'Retraining the model and evaluating completed projects. Keep this page open.';

        const previousResults = document.getElementById('retrainResults');

        if (previousResults) previousResults.hidden = true;

        root.setAttribute('aria-busy', 'true');

        // Try this action and handle any errors below.

        try {

            const response = await fetch(`${root.dataset.apiBase}/retrain`, {

                method: 'POST',

                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },

            });

            const data = await response.json().catch(() => { throw new Error('The server returned an incomplete response.'); });

            // Run this step if the response ok is missing or false or the success is missing or false.

            if (!response.ok || !data.success) throw new Error(data.message || 'The training request could not be completed.');

            window.pfimsPerformance.update(data.metrics);

            status.textContent = data.training_performed === false ? data.message : 'retraining done!';

            const host = document.getElementById('retrainResults');

            if (host) {

                host.replaceChildren();

                host.hidden = false;

                const metrics = data.metrics || {};

                // Match the active SVR evaluation shown in the performance cards.
                const scores = metrics.development_evaluation?.metrics || metrics;

                const number = (value, digits = 2) => value != null && Number.isFinite(Number(value)) ? Number(value).toLocaleString('en-PH', {minimumFractionDigits: digits, maximumFractionDigits: digits}) : 'Unavailable';

                const heading = document.createElement('p');
                heading.textContent = data.detector_activated ? 'Random forest updated; SVR retained:' : data.candidate_activated ? 'Active model updated:' : metrics.development_evaluation ? 'Comparison updated; active model retained:' : 'Active model retained:';
                host.appendChild(heading);
                const facts = [

                    `Average peso error (MAE): ₱${number(scores.mean_absolute_error)}`,

                    `Average percentage error (MAPE): ${number(scores.mean_absolute_percentage_error)}%`,

                    `R²: ${number(scores.r_squared, 4)}`,



                    `Completed: ${new Date().toLocaleString('en-PH')}`];

                const list = document.createElement('ul'); list.className = 'performance-facts';

                facts.forEach(text => { const item = document.createElement('li'); item.textContent = text; list.appendChild(item); });

                host.appendChild(list);

            }

            const updated = document.getElementById('lastUpdated');

            if (updated) updated.textContent = new Date().toLocaleString();

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
