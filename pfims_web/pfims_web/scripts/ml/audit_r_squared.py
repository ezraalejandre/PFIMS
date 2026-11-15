"""Audit a trusted local saved estimator without refitting or selecting on test results."""
import hashlib
import json
from pathlib import Path
import joblib
import numpy as np
from sklearn.base import clone
from sklearn.metrics import mean_absolute_error, r2_score
from improve_models import matrix

app = Path(__file__).resolve().parents[2]
directory = app / 'storage/app/ml-improvement'
raw = (directory / 'source.json').read_bytes()
source = json.loads(raw)
report = json.loads((directory / 'report.json').read_text())
assert hashlib.sha256(raw).hexdigest() == report['source_sha256'], 'Stale source'
candidate = joblib.load(directory / 'candidate.joblib')
assert candidate['target'] == 'budget_fraction' and candidate['mode'] == 'original'
features = candidate['features']
assert features == source['features']
assert not set(features) & {'actual_cost', 'reconciled_final_cost', 'completed_at', 'project_id'}
train_ids = set(report['training_project_ids'])
test_ids = set(report['development_test_project_ids'])
assert not train_ids & test_ids, 'Project overlap'
assert not (train_ids | test_ids) & set(source.get('reserved_project_ids', []))
train = [r for r in source['records'] if r['project_id'] in train_ids]
test = [r for r in source['records'] if r['project_id'] in test_ids]
assert max(r['completed_at'] for r in train) <= min(r['completed_at'] for r in test)
assert all(r['captured_at'] < r['completed_at'] for r in train + test)

def predictions(model, rows):
    b = np.array([r['budget'] for r in rows])
    s = np.array([r['fin_total_expense'] for r in rows])
    return s + np.maximum(0, model.predict(matrix(rows, features, 'original')) * b)

def metrics(rows, predicted):
    actual = np.array([r['actual_cost'] + r['fin_total_expense'] for r in rows])
    spent = np.array([r['fin_total_expense'] for r in rows])
    budget = np.array([r['budget'] for r in rows])
    remaining = actual - spent
    score = lambda a, p: round(float(r2_score(a, p)), 4) if len(a) > 1 and np.var(a) > 0 else None
    return dict(projects=len({r['project_id'] for r in rows}), observations=len(rows),
                r_squared=score(actual, predicted), remaining_cost_r_squared=score(remaining, predicted-spent),
                mae=round(float(mean_absolute_error(actual, predicted)), 2),
                budget_baseline_r_squared=score(actual, np.maximum(spent, budget)),
                budget_baseline_mae=round(float(mean_absolute_error(actual, np.maximum(spent, budget))), 2))

model = candidate['regressor']
p = predictions(model, test)
assert abs(metrics(test, p)['r_squared'] - report['evaluation_all_checkpoints']['r_squared']) < .00011
folds = []
oof_rows, oof_predictions = [], []
for fold in report['folds']:
    fit_ids, val_ids = set(fold['training_project_ids']), set(fold['validation_project_ids'])
    assert not fit_ids & val_ids and not (fit_ids | val_ids) & test_ids
    fit = [r for r in train if r['project_id'] in fit_ids]
    val = [r for r in train if r['project_id'] in val_ids]
    assert max(r['completed_at'] for r in fit) < min(r['completed_at'] for r in val)
    fitted = clone(model).fit(matrix(fit, features, 'original'), [r['actual_cost']/r['budget'] for r in fit])
    predicted = predictions(fitted, val)
    folds.append(metrics(val, predicted))
    oof_rows.extend(val)
    oof_predictions.extend(predicted)

# Resample whole test projects, preserving correlation between their checkpoints.
rng = np.random.default_rng(15112026)
ids = sorted(test_ids)
indices = {pid: [i for i,r in enumerate(test) if r['project_id'] == pid] for pid in ids}
boot = []
for _ in range(2000):
    chosen = [i for pid in rng.choice(ids, len(ids), replace=True) for i in indices[pid]]
    a = [test[i]['actual_cost'] + test[i]['fin_total_expense'] for i in chosen]
    if np.var(a) > 0:
        boot.append(float(r2_score(a, p[chosen])))
signatures = lambda rows: {tuple(r[f] for f in features) for r in rows}
by_stage = {}
for name, fraction in [('early', .25), ('middle', .5), ('late', .75)]:
    ix = [i for i,r in enumerate(test) if abs(r['elapsed_time_fraction']-fraction) < .02]
    by_stage[name] = metrics([test[i] for i in ix], p[ix])
payload = dict(schema_version=1, model_sha256=hashlib.sha256((app/'storage/app/ml_model.phpml').read_bytes()).hexdigest(),
    source_sha256=hashlib.sha256(raw).hexdigest(),
    scope='Reused historical development test; no fresh independent validation. No score targeting or model refit.',
    training=metrics(train, predictions(model, train)), test=metrics(test, p), by_stage=by_stage,
    chronological_validation=metrics(oof_rows, np.array(oof_predictions)), validation_folds=folds,
    project_bootstrap_r_squared_95_percent_interval=[round(float(v),4) for v in np.quantile(boot,[.025,.975])],
    exact_train_test_feature_duplicates=len(signatures(train) & signatures(test)),
    budget_fallback_observations=sum(r.get('budget_basis') == 'latest_recorded_budget_no_effective_history' for r in source['records']),
    checks=dict(project_partitions_disjoint=True, reserved_projects_excluded=True, outcomes_excluded_from_features=True,
                checkpoints_before_completion=True, chronological_validation=True, saved_scores_reproduced=True),
    limitations=['Configuration was previously selected on these validation folds; validation is not a fresh independent estimate.',
        'Final-cost R² includes spending already known at the checkpoint; remaining-cost R² is also reported.',
        'Record dates do not establish when information was entered; budget fallback and later valuation can leak hindsight.',
        'A high R² alone neither proves nor disproves overfitting; no score is forced to 0.96.'])
(app/'storage/app/ml-r-squared-audit.json').write_text(json.dumps(payload,indent=2,allow_nan=False))
print(json.dumps(payload,indent=2))
