"""Local-only development experiments. Final development test never selects settings."""
import argparse
import hashlib
import json
import math
from pathlib import Path
import joblib
import numpy as np
from sklearn.base import clone
from sklearn.ensemble import GradientBoostingRegressor, ExtraTreesRegressor, RandomForestRegressor
from sklearn.ensemble import RandomForestClassifier, ExtraTreesClassifier, GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression, Ridge
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler
from sklearn.svm import SVR
from sklearn.metrics import mean_absolute_error, mean_absolute_percentage_error, r2_score
from sklearn.metrics import confusion_matrix, precision_score, recall_score, f1_score, balanced_accuracy_score


def classification(actual, predicted):
    tn, fp, fn, tp = confusion_matrix(actual, predicted, labels=[False, True]).ravel()
    return dict(accuracy=round(float(np.mean(actual == predicted)) * 100, 2),
                precision=round(precision_score(actual, predicted, zero_division=0) * 100, 2),
                recall=round(recall_score(actual, predicted, zero_division=0) * 100, 2),
                f1=round(f1_score(actual, predicted, zero_division=0) * 100, 2),
                balanced_accuracy=round(balanced_accuracy_score(actual, predicted) * 100, 2),
                counts={k: int(v) for k, v in dict(tp=tp, fp=fp, tn=tn, fn=fn).items()})


def matrix(rows, features, mode):
    if mode == 'original':
        return np.array([[r[f] for f in features] for r in rows], dtype=float)
    values = []
    for r in rows:
        b = r['budget']
        spent = r['fin_total_expense'] / b
        burn = r['cost_burn_rate_30d'] / b
        remaining = r['remaining_planned_days']
        values.append([math.log1p(b), r['duration_months'], r['worker_count'], r['elapsed_time_fraction'],
                       spent, 1 - spent, r['material_cost_share'], r['labor_cost_share'], r['equipment_cost_share'],
                       burn, r['burn_rate_acceleration'], r['inventory_cost_burn_rate_30d'] / b,
                       burn * remaining, spent + burn * remaining,
                       r['expense_frequency_7d'], r['stock_out_frequency_7d'], remaining])
    return np.array(values, dtype=float)


def run(source, output):
    raw = source.read_bytes()
    data = json.loads(raw)
    rows = sorted(data['records'], key=lambda r: (r['completed_at'], r['project_id'], r['captured_at']))
    ids = list(dict.fromkeys(r['project_id'] for r in rows))
    if len(ids) < 20:
        raise ValueError('At least 20 distinct completed development projects are required')
    reserved = set(data.get('reserved_project_ids', []))
    assert not reserved.intersection(ids), 'Reserved independent project leaked into development'
    test_ids = ids[-math.ceil(len(ids) * .2):]
    train_ids = ids[:-len(test_ids)]
    train = [r for r in rows if r['project_id'] in train_ids]
    test = [r for r in rows if r['project_id'] in test_ids]
    dates = {r['project_id']: r['completed_at'] for r in rows}
    warmup = max(8, math.ceil(len(train_ids) * .4))
    folds = []
    for val_ids in np.array_split(train_ids[warmup:], 5):
        val_ids = [int(i) for i in val_ids]
        if not val_ids:
            continue
        first = min(dates[i] for i in val_ids)
        fit_ids = [i for i in train_ids if dates[i] < first]
        fit = [r for r in train if r['project_id'] in fit_ids]
        val = [r for r in train if r['project_id'] in val_ids]
        if len(fit_ids) >= 8:
            folds.append((fit, val))
    if len(folds) < 2:
        raise ValueError('At least two strictly chronological project validation folds are required')

    def arrays(records):
        budget = np.array([r['budget'] for r in records])
        spent = np.array([r['fin_total_expense'] for r in records])
        remaining = np.array([r['actual_cost'] for r in records])
        return budget, spent, remaining

    def estimate(model, records, mode, target):
        b, s, _ = arrays(records)
        pred = model.predict(matrix(records, data['features'], mode))
        return s + np.maximum(0, pred * b if target == 'budget_fraction' else pred)

    candidates = []
    for mode in ['original', 'normalized']:
        for depth in [1, 2, 3]:
            for loss in ['absolute_error', 'huber', 'squared_error']:
                for target in ['budget_fraction', 'pesos']:
                    candidates.append((f'gradient_boosting_{loss}_d{depth}_{mode}_{target}',
                                       GradientBoostingRegressor(loss=loss, n_estimators=150, max_depth=depth,
                                                                 learning_rate=.04, min_samples_leaf=5, random_state=1511), mode, target))
        for leaf in [2, 5, 10]:
            candidates.append((f'extra_trees_leaf{leaf}_{mode}', ExtraTreesRegressor(n_estimators=150,
                               min_samples_leaf=leaf, random_state=1511, n_jobs=1), mode, 'budget_fraction'))
            candidates.append((f'random_forest_leaf{leaf}_{mode}', RandomForestRegressor(n_estimators=150,
                               min_samples_leaf=leaf, random_state=1511, n_jobs=1), mode, 'budget_fraction'))
        candidates.append((f'ridge_{mode}', make_pipeline(StandardScaler(), Ridge(alpha=1)), mode, 'budget_fraction'))
        candidates.append((f'svr_{mode}', make_pipeline(StandardScaler(), SVR(C=3, gamma='scale', epsilon=.03)), mode, 'budget_fraction'))

    results = []
    for name, spec, mode, target in candidates:
        actual, predicted = [], []
        for fit, val in folds:
            b, _, y = arrays(fit)
            model = clone(spec).fit(matrix(fit, data['features'], mode), y / b if target == 'budget_fraction' else y)
            _, s, y_val = arrays(val)
            actual.extend(s + y_val)
            predicted.extend(estimate(model, val, mode, target))
        results.append(dict(name=name, mode=mode, target=target, validation_mae=float(mean_absolute_error(actual, predicted))))
    results.sort(key=lambda r: (r['validation_mae'], r['name']))
    best = results[0]
    _, spec, mode, target = next(c for c in candidates if c[0] == best['name'])
    b, _, y = arrays(train)
    regressor = clone(spec).fit(matrix(train, data['features'], mode), y / b if target == 'budget_fraction' else y)

    # Threshold selection uses all validation checkpoints, not only easy late checkpoints.
    classifiers = [('balanced_logistic', make_pipeline(StandardScaler(), LogisticRegression(class_weight='balanced', C=.3, max_iter=2000, random_state=1511)))]
    for depth in [1, 2, 3]:
        classifiers.append((f'balanced_gradient_boosting_d{depth}', GradientBoostingClassifier(n_estimators=150,
                            max_depth=depth, learning_rate=.04, min_samples_leaf=4, random_state=1511)))
    for leaf in [2, 5, 10]:
        classifiers += [(f'balanced_forest_leaf{leaf}', RandomForestClassifier(n_estimators=200, min_samples_leaf=leaf,
                         class_weight='balanced', max_depth=5, random_state=1511, n_jobs=1)),
                        (f'balanced_extra_trees_leaf{leaf}', ExtraTreesClassifier(n_estimators=200, min_samples_leaf=leaf,
                         class_weight='balanced', max_depth=5, random_state=1511, n_jobs=1))]
    cls_trials = []
    for name, spec_cls in classifiers:
        scores, actual = [], []
        for fit, val in folds:
            fb, fs, fy = arrays(fit)
            labels = fs + fy > fb
            if len(set(labels)) < 2:
                continue
            weights = np.array([len(labels) / (2 * np.sum(labels == label)) for label in labels])
            model = clone(spec_cls)
            if isinstance(model, GradientBoostingClassifier):
                model.fit(matrix(fit, data['features'], 'normalized'), labels, sample_weight=weights)
            else:
                model.fit(matrix(fit, data['features'], 'normalized'), labels)
            vb, vs, vy = arrays(val)
            scores.extend(np.where(vs > vb, 1, model.predict_proba(matrix(val, data['features'], 'normalized'))[:, 1]))
            actual.extend(vs + vy > vb)
        if not actual or len(set(actual)) < 2:
            continue
        for threshold in np.arange(.1, .901, .05):
            metrics = classification(np.array(actual), np.array(scores) >= threshold)
            cls_trials.append(dict(name=name, threshold=round(float(threshold), 2), validation=metrics))

    def cls_rank(t):
        m = t['validation']
        feasible = m['precision'] >= 75 and m['recall'] >= 80
        floor = m['precision'] >= 75
        return (feasible, floor, m['recall'] if floor else m['f1'], m['f1'], m['balanced_accuracy'])
    cls_trials.sort(key=cls_rank, reverse=True)
    if not cls_trials:
        raise ValueError('No temporal validation fold contains usable overrun and within-budget outcomes; collect more finalized projects.')
    chosen = cls_trials[0]
    spec_cls = next(m for n, m in classifiers if n == chosen['name'])
    b, s, y = arrays(train)
    classifier = clone(spec_cls)
    labels = s + y > b
    if isinstance(classifier, GradientBoostingClassifier):
        classifier.fit(matrix(train, data['features'], 'normalized'), labels,
                       sample_weight=np.array([len(labels) / (2 * np.sum(labels == label)) for label in labels]))
    else:
        classifier.fit(matrix(train, data['features'], 'normalized'), labels)

    def evaluate(records):
        b, s, y = arrays(records)
        actual = s + y
        prediction = estimate(regressor, records, mode, target)
        scores = np.where(s > b, 1, classifier.predict_proba(matrix(records, data['features'], 'normalized'))[:, 1])
        median = float(np.median([r['actual_cost'] + r['fin_total_expense'] for r in {r['project_id']: r for r in records}.values()]))
        mae = float(mean_absolute_error(actual, prediction))
        return dict(projects=len(set(r['project_id'] for r in records)), observations=len(records),
                    mae=round(mae, 2), mape=round(mean_absolute_percentage_error(actual, prediction) * 100, 2),
                    r_squared=round(r2_score(actual, prediction), 4), median_final_cost=round(median, 2),
                    mae_percent_of_median=round(mae / median * 100, 3), below_50000=bool(mae < 50000), below_one_percent=bool(mae < median * .01),
                    classifier=classification(actual > b, scores >= chosen['threshold']))
    latest = list({r['project_id']: r for r in sorted(test, key=lambda r: r['captured_at'])}.values())
    report = dict(schema_version=1, generated_at=data['generated_at'], source_sha256=hashlib.sha256(raw).hexdigest(),
                  scope='Local development comparison; reused development test, not fresh independent validation.',
                  active_model_changed=False, training_project_ids=train_ids, development_test_project_ids=test_ids,
                  reserved_independent_project_ids=list(reserved), folds=[dict(training_project_ids=sorted(set(r['project_id'] for r in f)),
                     validation_project_ids=sorted(set(r['project_id'] for r in v)), latest_training_completion=max(r['completed_at'] for r in f),
                     earliest_validation_completion=min(r['completed_at'] for r in v)) for f, v in folds],
                  selection_rule='Lowest training-only temporal validation peso MAE across all checkpoints; test never selects settings.',
                  selected_regressor=best, regression_trials=results, selected_classifier=chosen, classifier_trials=cls_trials,
                  evaluation_all_checkpoints=evaluate(test), evaluation_latest_checkpoint=evaluate(latest),
                  evaluation_by_stage={stage:evaluate([r for r in test if (r['elapsed_time_fraction'] <= .33 if stage == 'early' else .33 < r['elapsed_time_fraction'] <= .66 if stage == 'middle' else r['elapsed_time_fraction'] > .66)]) for stage in ['early', 'middle', 'late']},
                  data_quality=data['quality_summary'], historical_budget_fallback_observations=data['data_audit']['budget_fallback_observations'],
                  active_baseline_all_checkpoints=data.get('active_baseline_all_checkpoints'),
                  active_baseline_latest_checkpoint=data.get('active_baseline_latest_checkpoint'),
                  remaining_cost_review_count=data['remaining_cost_review_count'],
                  limitations=['Inspected development test cannot prove fresh performance.', 'Latest-budget fallback may differ from historical approval.',
                               'No invented historical remaining-work inputs; new reviews require future finalized outcomes.'])
    stress_path = source.parent.parent / 'ml-stress-scenarios.json'
    if stress_path.exists():
        report['synthetic_sensitivity_only'] = evaluate(json.loads(stress_path.read_text(encoding='utf-8')))
    output.mkdir(parents=True, exist_ok=True)
    joblib.dump(dict(regressor=regressor, classifier=classifier, mode=mode, target=target, threshold=chosen['threshold'], features=data['features']), output/'candidate.joblib')
    (output/'report.json').write_text(json.dumps(report, indent=2, allow_nan=False), encoding='utf-8')
    print(json.dumps({k:report[k] for k in ['selected_regressor','selected_classifier','evaluation_all_checkpoints','evaluation_latest_checkpoint']}, indent=2))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.source, args.output)
