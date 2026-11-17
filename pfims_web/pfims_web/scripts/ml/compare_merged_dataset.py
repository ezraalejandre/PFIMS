import os
for key in ['OMP_NUM_THREADS','OPENBLAS_NUM_THREADS','MKL_NUM_THREADS']:os.environ[key]='1'
import json,math,hashlib,itertools
from pathlib import Path
import datetime,platform,sklearn
from collections import Counter
import numpy as np
from sklearn.svm import SVR
from sklearn.linear_model import LinearRegression,Ridge
from sklearn.tree import DecisionTreeClassifier
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import MinMaxScaler
from sklearn.metrics import mean_absolute_error,mean_absolute_percentage_error,r2_score
import joblib
APP=Path(__file__).resolve().parents[2];ROOT=APP/'storage/app/ml-revalidation-20261117';SEED=20261117
source=json.loads((ROOT/'source.json').read_text())
manifest=json.loads((APP/'storage/app/ml-datasets/current/manifest.json').read_text())
previous={'training_project_ids':manifest['database_training_project_ids'],'test_project_ids':manifest['database_test_project_ids']}
merged=json.loads((ROOT/'training-data.json').read_text(encoding='utf-8'))
def save(name,value):
 (ROOT/name).write_text(json.dumps(value,indent=2,allow_nan=False),encoding='utf-8')


def arrays(rows):
 budget=np.array([r['budget']for r in rows]);spent=np.array([r['fin_total_expense']for r in rows]);remain=np.array([r['actual_cost']for r in rows])
 return budget,spent,remain

def project_weights(rows,balance=False):
 counts=Counter(str(r['project_id'])for r in rows)
 w=np.array([1/counts[str(r['project_id'])]for r in rows],float)
 if balance:
  y=labels(rows)
  assert len(np.unique(y))==2
  w=w/np.where(y,2*w[y].sum(),2*w[~y].sum())
 else:w=w/w.mean()
 return w

def latest_indices(rows):
 result={}
 for i,r in enumerate(rows):
  if r['project_id']not in result or r['captured_at']>rows[result[r['project_id']]]['captured_at']:result[r['project_id']]=i
 return list(result.values())

def reg_metrics(rows,pred):
 b,s,y=arrays(rows);actual=s+y;median=float(np.median(actual[latest_indices(rows)]));mae=float(mean_absolute_error(actual,pred))
 return dict(projects=len({r['project_id']for r in rows}),observations=len(rows),mae=round(mae,2),mape=round(float(mean_absolute_percentage_error(actual,pred))*100,2),r_squared=round(float(r2_score(actual,pred)),4),median_final_cost=round(median,2),mae_percent_of_median=round(mae/median*100,3),below_50000=mae<50000,below_one_percent=mae<.01*median,mape_below_five=float(mean_absolute_percentage_error(actual,pred))<.05)

def reg_scopes(rows,pred):
 ix=latest_indices(rows)
 result={'all_checkpoints':reg_metrics(rows,pred),'latest_checkpoint':reg_metrics([rows[i]for i in ix],pred[ix]),'stages':{}}
 for stage,lo,hi in [('early',0,.33),('middle',.33,.66),('late',.66,10)]:
  ix=[i for i,r in enumerate(rows)if lo<r['elapsed_time_fraction']<=hi]
  if ix:result['stages'][stage]=reg_metrics([rows[i]for i in ix],pred[ix])
 return result

def fit_reg(rows,kind,params):
 x=matrix(rows);b,s,y=arrays(rows);ratio=y/b;mean=float(ratio.mean());std=float(ratio.std());std=std if std>0 else 1.
 scale=MinMaxScaler().fit(x)
 if kind=='svr':model=SVR(cache_size=128,tol=.001,**params)
 elif kind=='ridge':model=Ridge(**params)
 else:model=LinearRegression()
 model.fit(scale.transform(x),(ratio-mean)/std,sample_weight=project_weights(rows))
 return model,scale,mean,std

def reg_predict(fitted,rows):
 model,scale,mean,std=fitted;b,s,y=arrays(rows)
 ratio=model.predict(scale.transform(matrix(rows)))*std+mean
 return s+np.maximum(0,ratio*b)


class ExistingLogistic:
 def fit(self,rows,view):
  x=matrix(rows,view);self.lo=x.min(axis=0);self.span=x.max(axis=0)-self.lo
  x=np.divide(x-self.lo,self.span,out=np.zeros_like(x),where=self.span>0)
  v=np.column_stack([np.ones(len(x)),x]);w=project_weights(rows,True);y=labels(rows).astype(float);coef=np.zeros(v.shape[1])
  for _ in range(1000):
   z=np.clip(v@coef,-700,700);p=1/(1+np.exp(-z));gradient=v.T@((p-y)*w);penalty=.01*coef;penalty[0]=0;coef-=.2*(gradient+penalty)
  self.coef=coef;return self
 def predict(self,rows,view):
  x=matrix(rows,view);x=np.divide(x-self.lo,self.span,out=np.zeros_like(x),where=self.span>0);z=np.clip(np.column_stack([np.ones(len(x)),x])@self.coef,-700,700)
  return 1/(1+np.exp(-z))

def labels(rows):
 b,s,y=arrays(rows);return np.round(s+y,2)>np.round(b,2)

def cls_metrics(rows,score,threshold):
 y=labels(rows);known=np.array([r['fin_total_expense']>r['budget']for r in rows]);p=(score>=threshold)|known
 tp=int(np.sum(y&p));fp=int(np.sum(~y&p));tn=int(np.sum(~y&~p));fn=int(np.sum(y&~p));ratio=lambda a,b:round(100*a/b,2)if b else None
 recall=ratio(tp,tp+fn);specificity=ratio(tn,tn+fp)
 return dict(projects=len({r['project_id']for r in rows}),observations=len(rows),accuracy=ratio(tp+tn,len(rows)),precision=ratio(tp,tp+fp),recall=recall,f1=ratio(2*tp,2*tp+fp+fn),balanced_accuracy=round((100*tp/(tp+fn)+100*tn/(tn+fp))/2,2)if tp+fn and tn+fp else None,counts=dict(tp=tp,fp=fp,tn=tn,fn=fn))

def cls_scopes(rows,score,threshold):
 result={'all_checkpoints':cls_metrics(rows,score,threshold),'stages':{}}
 ix=latest_indices(rows);result['latest_checkpoint']=cls_metrics([rows[i]for i in ix],score[ix],threshold)
 ix=[i for i,r in enumerate(rows)if r['fin_total_expense']<=r['budget']]
 result['before_budget_exceeded']=cls_metrics([rows[i]for i in ix],score[ix],threshold)if ix else None
 for stage,lo,hi in [('early',0,.33),('middle',.33,.66),('late',.66,10)]:
  ix=[i for i,r in enumerate(rows)if lo<r['elapsed_time_fraction']<=hi]
  if ix:result['stages'][stage]=cls_metrics([rows[i]for i in ix],score[ix],threshold)
 return result

def threshold_key(metrics):
 values=[metrics.get(k)or 0 for k in ['accuracy','precision','recall','f1','balanced_accuracy']]
 return (int(min(values)>=90),min(values),metrics['f1']or 0,metrics['precision']or 0,metrics['recall']or 0)

def select_threshold(rows,score):
 known=np.array([r['fin_total_expense']>r['budget']for r in rows]);values=np.unique(np.where(known,1.,score))
 thresholds=np.unique(np.r_[1e-9,.5,1-1e-9,(values[:-1]+values[1:])/2])
 trials=[(cls_metrics(rows,score,float(t)),float(t))for t in thresholds if 0<t<1]
 return max(trials,key=lambda v:(*threshold_key(v[0]),-abs(v[1]-.5)))

def fit_cls(kind,params,rows,view):
 if kind=='logistic':return ExistingLogistic().fit(rows,view)
 model=DecisionTreeClassifier(random_state=SEED,**params)if kind=='decision_tree'else RandomForestClassifier(n_estimators=150,n_jobs=1,random_state=SEED,**params)
 model.fit(matrix(rows,view),labels(rows),sample_weight=project_weights(rows,True)*len(rows));return model

def cls_predict(model,rows,view):
 return model.predict(rows,view)if isinstance(model,ExistingLogistic)else model.predict_proba(matrix(rows,view))[:,1]


features=source['features'];trainids=set(previous['training_project_ids']);oldtestids=set(previous['test_project_ids'])
H=source['records'];TRAIN=[r for r in H if r['project_id']in trainids];TEST=[r for r in H if r['project_id']not in trainids]
historical_test=[r for r in TEST if r['project_id']in oldtestids];imported_test=[r for r in TEST if r['project_id']not in oldtestids]
assert len({r['project_id']for r in TRAIN})==67
assert len({r['project_id']for r in historical_test})==17
assert len({r['project_id']for r in imported_test})==300
validation=set(merged['metadata']['generated_validation_project_ids'])
original=[r for r in merged['training_validation_observations']if str(r['project_id']).startswith('ingested-')]
added=[r for r in merged['training_validation_observations']if not str(r['project_id']).startswith('ingested-')]
generated_fit=[r for r in added if r['project_id']not in validation];generated_val=[r for r in added if r['project_id']in validation]
assert all(int(r['donor_project_id'])in trainids for r in original)
fit=TRAIN+original+generated_fit
assert not {str(r['project_id'])for r in fit}&{str(r['project_id'])for r in TEST+generated_val}
def key(r):return tuple(round(float(r[f]),8)for f in features)
assert not set(map(key,fit))&set(map(key,TEST))
DATES={r['project_id']:r['completed_at']for r in TRAIN};order=sorted(trainids,key=lambda i:(DATES[i],i));folds=[]
for ids in np.array_split(order[math.ceil(len(order)*.4):],5):
    v=set(map(int,ids));cutoff=min(DATES[i]for i in v);f={i for i in trainids if DATES[i]<cutoff}
    fr=[r for r in TRAIN if r['project_id']in f]+[r for r in original if r['donor_project_id']in f and r['completed_at']<cutoff]+[r for r in generated_fit if r['completed_at']<cutoff]
    vr=[r for r in TRAIN if r['project_id']in v]
    assert max(r['completed_at']for r in fr)<min(r['completed_at']for r in vr)
    folds.append((fr,vr))
CURRENT_VIEW='corrected16'
def matrix(rows,view=None):
    view=view or CURRENT_VIEW
    if view=='pace':return np.array([[(r['fin_total_expense']/r['budget'])/max(r['elapsed_time_fraction'],.01)]for r in rows])
    x=np.array([[float(r[f])for f in features]for r in rows])
    # Use the same day-based duration in file and database observations. The
    # database's integer month difference otherwise turns a 180-day plan into 5.
    x[:,features.index('duration_months')]=[max(1.,float(r.get('planned_duration_days',r['elapsed_days']+r['remaining_planned_days']))/30.)for r in rows]
    if view=='pipeline16':x[:,features.index('elapsed_days')]=0;x[:,features.index('remaining_planned_days')]=0
    if view=='corrected_plus_pace':x=np.column_stack([x,[(r['fin_total_expense']/r['budget'])/max(r['elapsed_time_fraction'],.01)for r in rows]])
    return x
def evaluate_reg(bundle,rows):return reg_scopes(rows,reg_predict(bundle,rows))
def cls_key(m):return threshold_key(m)
def choose_threshold(real,rs,gen,gs):
    ts=np.unique(np.r_[np.arange(.05,.951,.025),.5])
    trials=[]
    for t in ts:
        a=cls_metrics(real,rs,t);b=cls_metrics(gen,gs,t)
        trials.append((cls_key(a)+(min(b[k]or 0 for k in ['accuracy','precision','recall','f1','balanced_accuracy']),-abs(t-.5)),float(t),a,b))
    return max(trials,key=lambda x:x[0])
report={'scope':'Local development revalidation; imported generated tests are not independent company evidence. Previously inspected historical holdout. No deployment.',
 'counts':{'historical_train_projects':67,'original_ingested_projects':1000,'added_fit_projects':800,'fit_projects':1867,'fit_checkpoints':len(fit),'generated_validation_projects':200,'generated_validation_checkpoints':len(generated_val),'chronological_validation_projects':40,'chronological_validation_checkpoints':120,'database_test_projects':317,'database_test_checkpoints':len(TEST),'historical_test_projects':17,'imported_generated_test_projects':300},
 'folds':[{'fit_projects':len({r['project_id']for r in f}),'validation_projects':len({r['project_id']for r in v}),'cutoff':min(r['completed_at']for r in v)}for f,v in folds],
 'cost':{},'classification':{},'readiness':source['readiness']}
cost_trials=[];class_trials=[];cost_bundles={};class_bundles={}
for kind,options in {'svr':[dict(C=c,gamma=g,epsilon=e)for c,g,e in itertools.product([10,100],[.01,.1,1],[.01,.1])],'linear':[{}],'ridge':[dict(alpha=a)for a in [.01,1,100]]}.items():
    ranked=[]
    for view in ['pipeline16','corrected16','corrected_plus_pace']:
        CURRENT_VIEW=view
        for params in options:
            vals=[];pred=[]
            for fr,vr in folds:
                b=fit_reg(fr,kind,params);vals+=vr;pred+=reg_predict(b,vr).tolist()
            cv=reg_metrics(vals,np.array(pred));bundle=fit_reg(fit,kind,params);scenario=reg_metrics(generated_val,reg_predict(bundle,generated_val))
            trial={'kind':kind,'view':view,'parameters':params,'chronological_validation':cv,'generated_validation':scenario}
            cost_trials.append(trial);ranked.append((cv['mae'],scenario['mae'],trial,bundle))
        print('Finished cost validation',kind,view,flush=True)
    _,_,best,bundle=min(ranked,key=lambda x:(x[0],x[1]));CURRENT_VIEW=best['view'];cost_bundles[kind]=(bundle,CURRENT_VIEW)
    result={**best,'training':evaluate_reg(bundle,fit),'tests':{name:evaluate_reg(bundle,rows)for name,rows in [('database_combined',TEST),('historical_only',historical_test),('imported_generated_only',imported_test)]}}
    report['cost'][kind]=result;joblib.dump((bundle,CURRENT_VIEW),ROOT/(kind+'-candidate.joblib'));save('results.json',report);save('cost-search.json',cost_trials)
    print('Selected cost',kind,best['view'],best['parameters'],result['tests']['historical_only']['all_checkpoints'],flush=True)
for kind,options in {'logistic':[{}],'decision_tree':[dict(max_depth=d,min_samples_leaf=l)for d,l in itertools.product([2,4,6],[5,15])],'random_forest':[dict(max_depth=d,min_samples_leaf=l,max_features='sqrt')for d,l in itertools.product([4,8],[5,15])]}.items():
    ranked=[]
    for view in ['pipeline16','corrected16','pace']:
        for params in options:
            vals=[];scores=[]
            for fr,vr in folds:
                model=fit_cls(kind,params,fr,view);vals+=vr;scores+=cls_predict(model,vr,view).tolist()
            model=fit_cls(kind,params,fit,view);gs=cls_predict(model,generated_val,view)
            rank,t,cv,scenario=choose_threshold(vals,np.array(scores),generated_val,gs)
            trial={'kind':kind,'view':view,'parameters':params,'threshold':t,'chronological_validation':cv,'generated_validation':scenario};class_trials.append(trial);ranked.append((rank,trial,model))
        print('Finished classifier validation',kind,view,flush=True)
    _,best,model=max(ranked,key=lambda x:x[0]);view=best['view'];class_bundles[kind]=(model,view,best['threshold'])
    result={**best,'training':cls_scopes(fit,cls_predict(model,fit,view),best['threshold']),'tests':{name:cls_scopes(rows,cls_predict(model,rows,view),best['threshold'])for name,rows in [('database_combined',TEST),('historical_only',historical_test),('imported_generated_only',imported_test)]}}
    report['classification'][kind]=result;joblib.dump((model,view,best['threshold']),ROOT/(kind+'-candidate.joblib'));save('results.json',report);save('classification-search.json',class_trials)
    print('Selected classifier',kind,view,best['threshold'],result['tests']['historical_only']['all_checkpoints'],flush=True)
costwinner=min(report['cost'],key=lambda k:report['cost'][k]['chronological_validation']['mae'])
classwinner=max(report['classification'],key=lambda k:cls_key(report['classification'][k]['chronological_validation']))
report['validation_selected_winners']={'cost':costwinner,'classification':classwinner}
save('results.json',report)
print('Winners',report['validation_selected_winners'],flush=True)
