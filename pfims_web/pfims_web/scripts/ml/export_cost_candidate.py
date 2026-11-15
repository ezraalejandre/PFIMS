"""Export the trusted local sklearn candidate for PHP serving and parity checks."""
import json, joblib
from pathlib import Path
import numpy as np
root=Path('storage/app/ml-improvement')
c=joblib.load(root/'candidate.joblib'); m=c['regressor']
assert c['mode']=='original' and c['target']=='budget_fraction'
assert m.__class__.__name__=='GradientBoostingRegressor'
trees=[]
for estimator in m.estimators_[:,0]:
 t=estimator.tree_
 trees.append(dict(left=t.children_left.tolist(),right=t.children_right.tolist(),feature=t.feature.tolist(),threshold=t.threshold.tolist(),value=t.value[:,0,0].tolist()))
s=json.loads((root/'source.json').read_text())
fixtures=[dict(features=[r[f] for f in c['features']],expected=float(m.predict(np.array([[r[f] for f in c['features']]]))[0])) for r in s['records']]
(root/'portable-cost.json').write_text(json.dumps(dict(features=c['features'],initial=float(m.init_.constant_[0,0]),rate=m.learning_rate,trees=trees,fixtures=fixtures)))
