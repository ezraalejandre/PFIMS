"""Source-weight sensitivity selected only on historical training validation."""
import sys,hashlib
from pathlib import Path
script=Path(__file__).with_name('compare_merged_dataset.py')
prefix=script.read_text().split("for kind,options in {'svr':",1)[0]
exec(compile(prefix,str(script),'exec'))
SOURCE_INFLUENCE=1.0
def project_weights(rows,balance=False):
    counts=Counter(str(r['project_id'])for r in rows)
    w=np.array([(1. if str(r['project_id'])in {str(i)for i in trainids} else SOURCE_INFLUENCE)/counts[str(r['project_id'])]for r in rows])
    if balance:
        y=labels(rows);w=w/np.where(y,2*w[y].sum(),2*w[~y].sum())
    else:w=w/w.mean()
    return w
def fingerprint():
    def clean(rows):return [{k:v for k,v in r.items()if k!='pipeline_inputs'}for r in rows]
    return hashlib.sha256(json.dumps({'fit':clean(fit),'validation':clean(generated_val),'test':clean(TEST),'features':features},sort_keys=True,separators=(',',':')).encode()).hexdigest()
def signature(row):return row['view'],json.dumps(row['parameters'],sort_keys=True)
refit='--refit' in sys.argv
current=json.loads((ROOT/'results.json').read_text())
baseline=current if refit else json.loads((ROOT/'baseline-results.json').read_text())
if refit and current.get('input_fingerprint')!=fingerprint():
    raise RuntimeError('Source data changed; run the full six-model comparison before repeating the selected fit.')
report=current if refit else baseline
cost_trials=[] if refit else json.loads((ROOT/'baseline-cost-search.json').read_text())
class_trials=[] if refit else json.loads((ROOT/'baseline-classification-search.json').read_text())
for kind in ['svr','linear','ridge']:
    existing=report['cost'][kind];ranked=[]
    options=[(existing.get('source_influence',1.0),existing)]if refit else [(weight,min([t for t in cost_trials if t['kind']==kind and t['view']==view],key=lambda t:t['chronological_validation']['mae']))for weight in [1.,.1,.02]for view in ['pipeline16','corrected16','corrected_plus_pace']]
    for weight,trial in options:
        SOURCE_INFLUENCE=weight;CURRENT_VIEW=trial['view'];params=trial['parameters']
        if refit:cv=trial['chronological_validation']
        else:
            vals=[];pred=[]
            for fr,vr in folds:
                b=fit_reg(fr,kind,params);vals+=vr;pred+=reg_predict(b,vr).tolist()
            cv=reg_metrics(vals,np.array(pred))
        bundle=fit_reg(fit,kind,params);scenario=reg_metrics(generated_val,reg_predict(bundle,generated_val))
        row={**trial,'source_influence':weight,'chronological_validation':cv,'generated_validation':scenario}
        ranked.append((cv['mae'],scenario['mae'],row,bundle))
    _,_,selected,bundle=min(ranked,key=lambda x:(x[0],x[1]))
    SOURCE_INFLUENCE=selected.get('source_influence',1.0);CURRENT_VIEW=selected['view']
    if bundle is None:bundle=fit_reg(fit,kind,selected['parameters'])
    report['cost'][kind]={**selected,'training':evaluate_reg(bundle,fit),'tests':{name:evaluate_reg(bundle,rows)for name,rows in [('database_combined',TEST),('historical_only',historical_test),('imported_generated_only',imported_test)]}}
    joblib.dump((bundle,CURRENT_VIEW),ROOT/(kind+'-candidate.joblib'))
    if kind=='svr':
        save('svr-parity-reference.json',{'predictions':reg_predict(bundle,TEST).tolist()})
        model,scale,mean,std=bundle
        vectors=[' '.join([format(coef,'.17g')]+[f'{j+1}:{v:.17g}'for j,v in enumerate(vector)])for coef,vector in zip(model.dual_coef_[0],model.support_vectors_)]
        libsvm=f'svm_type epsilon_svr\nkernel_type rbf\ngamma {model._gamma:.17g}\nnr_class 2\ntotal_sv {len(vectors)}\nrho {-model.intercept_[0]:.17g}\nSV\n'+'\n'.join(vectors)+'\n'
        (ROOT/'svr-candidate.libsvm').write_text(libsvm)
        fn=features+(['spending_pace_ratio']if CURRENT_VIEW=='corrected_plus_pace'else[])
        transformer={'feature_names':fn,'input_preprocessing':'duration_months_from_planned_days_divided_by_30','selected_feature_indexes':list(range(len(fn))),'ranges':[{'name':n,'min':float(lo),'max':float(hi)}for n,lo,hi in zip(fn,scale.data_min_,scale.data_max_)],'target_scaling':'remaining_cost_fraction_of_budget','target_normalization':{'mean':mean,'scale':std}}
        save('svr-transformer.json',transformer)
    print('Weighted cost',kind,'influence',SOURCE_INFLUENCE,'CV MAE',selected['chronological_validation']['mae'],flush=True)
for kind in ['logistic','decision_tree','random_forest']:
    existing=report['classification'][kind];ranked=[]
    options=[(existing.get('source_influence',1.0),existing)]if refit else [(weight,max([t for t in class_trials if t['kind']==kind and t['view']==view],key=lambda t:cls_key(t['chronological_validation'])))for weight in [1.,.1,.02]for view in ['pipeline16','corrected16','pace']]
    for weight,trial in options:
        SOURCE_INFLUENCE=weight;view=trial['view'];params=trial['parameters']
        model=fit_cls(kind,params,fit,view)
        if refit:threshold=trial['threshold'];cv=trial['chronological_validation'];scenario=trial['generated_validation']
        else:
            vals=[];scores=[]
            for fr,vr in folds:
                m=fit_cls(kind,params,fr,view);vals+=vr;scores+=cls_predict(m,vr,view).tolist()
            _,threshold,cv,scenario=choose_threshold(vals,np.array(scores),generated_val,cls_predict(model,generated_val,view))
        row={**trial,'source_influence':weight,'threshold':threshold,'chronological_validation':cv,'generated_validation':scenario};ranked.append((cls_key(cv),row,model))
    _,selected,model=max(ranked,key=lambda x:x[0]);SOURCE_INFLUENCE=selected.get('source_influence',1.0);view=selected['view']
    if model is None:model=fit_cls(kind,selected['parameters'],fit,view)
    report['classification'][kind]={**selected,'training':cls_scopes(fit,cls_predict(model,fit,view),selected['threshold']),'tests':{name:cls_scopes(rows,cls_predict(model,rows,view),selected['threshold'])for name,rows in [('database_combined',TEST),('historical_only',historical_test),('imported_generated_only',imported_test)]}}
    joblib.dump((model,view,selected['threshold']),ROOT/(kind+'-candidate.joblib'))
    if kind=='random_forest':
        trees=[]
        for estimator in model.estimators_:
            t=estimator.tree_;nodes=[]
            for i in range(t.node_count):
                values=t.value[i][0];probability=float(values[1]/values.sum())
                nodes.append([int(t.feature[i]),float(t.threshold[i]),int(t.children_left[i]),int(t.children_right[i]),probability])
            trees.append(nodes)
        save('random-forest-portable.json',{'algorithm':'random_forest_classifier','view':view,'ranges':[{'min':float(min(r[f]for r in fit)),'max':float(max(r[f]for r in fit))}for f in features],'trees':trees})
        save('random-forest-parity.json',{'features':[[float(r[f])for f in features]for r in TEST+generated_val],'scores':cls_predict(model,TEST+generated_val,view).tolist(),'test_rows':len(TEST)})
    if kind=='logistic':save('logistic-candidate.json',{'algorithm':'project_balanced_logistic_regression','ranges':[{'min':float(lo),'max':float(lo+span)}for lo,span in zip(model.lo,model.span)],'coefficients':model.coef.tolist()})
    print('Weighted classifier',kind,'influence',SOURCE_INFLUENCE,'CV',selected['chronological_validation'],flush=True)
report['validation_selected_winners']={'cost':min(report['cost'],key=lambda k:report['cost'][k]['chronological_validation']['mae']),'classification':max(report['classification'],key=lambda k:cls_key(report['classification'][k]['chronological_validation']))}
report['input_fingerprint']=fingerprint();report['repeated_selected_fit']=refit
report['training_project_ids']=sorted({str(r['project_id'])for r in fit})
report['holdout_project_ids']=sorted({str(r['project_id'])for r in TEST})
save('results.json',report)
print('Winners',report['validation_selected_winners'],flush=True)
