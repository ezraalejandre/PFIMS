"""Refit the active gradient boosting configuration on the refreshed local cohort."""
import hashlib,json,math
from pathlib import Path
import joblib,numpy as np
from sklearn.base import clone
from sklearn.metrics import mean_absolute_error,mean_absolute_percentage_error,r2_score
from improve_models import matrix,classification
root=Path('storage/app/ml-improvement');raw=(root/'source.json').read_bytes();source=json.loads(raw)
old=joblib.load(root/'candidate.joblib');report=json.loads((root/'report.json').read_text())
assert old['mode']=='original' and old['target']=='budget_fraction'
rows=sorted(source['records'],key=lambda r:(r['completed_at'],r['project_id'],r['captured_at']))
ids=list(dict.fromkeys(r['project_id'] for r in rows));assert len(ids)>=20
assert not set(ids)&set(source.get('reserved_project_ids',[]))
test_ids=ids[-math.ceil(len(ids)*.2):];train=[r for r in rows if r['project_id'] not in test_ids];test=[r for r in rows if r['project_id'] in test_ids]
# Rebuild the same training-only chronological fold policy for the current source.
train_ids=ids[:-len(test_ids)]
dates={r['project_id']:r['completed_at'] for r in rows}
folds=[]
for validation_ids in np.array_split(train_ids[max(8,math.ceil(len(train_ids)*.4)):],5):
 validation_ids=[int(i) for i in validation_ids]
 if not validation_ids: continue
 first=min(dates[i] for i in validation_ids)
 fit_ids=[i for i in train_ids if dates[i]<first]
 if len(fit_ids)<8: continue
 folds.append(dict(training_project_ids=fit_ids,validation_project_ids=validation_ids,
                   latest_training_completion=max(dates[i] for i in fit_ids),earliest_validation_completion=first))
assert len(folds)>=2, 'Insufficient chronological validation folds'
report['folds']=folds
x=matrix(train,source['features'],'original');y=np.array([r['actual_cost']/r['budget'] for r in train])
old['regressor']=clone(old['regressor']).fit(x,y)
# Refit the stored classifier configuration without selecting parameters on test outcomes.
labels=np.array([r['fin_total_expense']+r['actual_cost']>r['budget'] for r in train])
assert len(set(labels))==2
classifier=clone(old['classifier']);cx=matrix(train,source['features'],'normalized')
if classifier.__class__.__name__=='GradientBoostingClassifier':
 classifier.fit(cx,labels,sample_weight=np.array([len(labels)/(2*np.sum(labels==v)) for v in labels]))
else: classifier.fit(cx,labels)
old['classifier']=classifier

def evaluate(records):
 b=np.array([r['budget'] for r in records]);s=np.array([r['fin_total_expense'] for r in records]);a=s+np.array([r['actual_cost'] for r in records])
 pred=s+np.maximum(0,old['regressor'].predict(matrix(records,source['features'],'original'))*b)
 median=float(np.median([r['actual_cost']+r['fin_total_expense'] for r in {r['project_id']:r for r in records}.values()]));mae=float(mean_absolute_error(a,pred))
 scores=np.where(s>b,1,classifier.predict_proba(matrix(records,source['features'],'normalized'))[:,1])
 return dict(projects=len(set(r['project_id'] for r in records)),observations=len(records),mae=round(mae,2),mape=round(mean_absolute_percentage_error(a,pred)*100,2),r_squared=round(r2_score(a,pred),4),median_final_cost=round(median,2),mae_percent_of_median=round(mae/median*100,3),below_50000=bool(mae<50000),below_one_percent=bool(mae<median*.01),classifier=classification(a>b,scores>=old['threshold']))
latest=list({r['project_id']:r for r in sorted(test,key=lambda r:r['captured_at'])}.values())
report.update(generated_at=source['generated_at'],source_sha256=hashlib.sha256(raw).hexdigest(),training_project_ids=ids[:-len(test_ids)],development_test_project_ids=test_ids,evaluation_all_checkpoints=evaluate(test),evaluation_latest_checkpoint=evaluate(latest),evaluation_by_stage={})
report['retraining_method']='Refit previously selected configuration; no tuning on holdout outcomes.'
report.pop('synthetic_sensitivity_only',None)
joblib.dump(old,root/'candidate.joblib');(root/'report.json').write_text(json.dumps(report,indent=2,allow_nan=False))
print(json.dumps(report['evaluation_latest_checkpoint']))
