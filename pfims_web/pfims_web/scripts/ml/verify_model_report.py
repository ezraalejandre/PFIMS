import json
import hashlib
from pathlib import Path
import joblib
import numpy as np
from improve_models import matrix

root = Path(__file__).resolve().parents[2]
directory = root / 'storage/app/ml-improvement'
report = json.loads((directory / 'report.json').read_text())
source = json.loads((directory / 'source.json').read_text())
assert hashlib.sha256((directory / 'source.json').read_bytes()).hexdigest() == report['source_sha256']
candidate = joblib.load(directory / 'candidate.joblib')
assert not set(report['training_project_ids']) & set(report['development_test_project_ids'])
assert not set(report['reserved_independent_project_ids']) & set(report['training_project_ids'] + report['development_test_project_ids'])
for fold in report['folds']:
    assert not set(fold['training_project_ids']) & set(fold['validation_project_ids'])
    assert not set(fold['training_project_ids'] + fold['validation_project_ids']) & set(report['development_test_project_ids'])
    assert fold['latest_training_completion'] < fold['earliest_validation_completion']
assert report['selected_regressor']['name'] == min(report['regression_trials'], key=lambda t:t['validation_mae'])['name']
test = [r for r in source['records'] if r['project_id'] in report['development_test_project_ids']]
b = np.array([r['budget'] for r in test])
s = np.array([r['fin_total_expense'] for r in test])
y = np.array([r['actual_cost'] for r in test]) + s
estimate = candidate['regressor'].predict(matrix(test, source['features'], candidate['mode']))
if candidate['target'] == 'budget_fraction': estimate *= b
prediction = s + np.maximum(0, estimate)
assert abs(np.mean(np.abs(y - prediction)) - report['evaluation_all_checkpoints']['mae']) < .01
assert np.all(prediction >= s)
assert not report['active_model_changed']
print('Passed project separation, chronological folds, training-only selection, saved-model reproducibility, and spending-floor checks.')
