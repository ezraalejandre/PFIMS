"""Generate a fresh held-out simulation only after a candidate has been frozen."""
import ast, datetime, json, random, secrets, sqlite3
from pathlib import Path

app=Path(__file__).resolve().parents[2]
out=Path(__file__).resolve().parents[2]/'database/data/ml-additions/spending-pace'
assert (out/'frozen-candidate.json').exists(), 'Freeze selection before generating the final test'
assert not (out/'fresh-test.json').exists(), 'Preserve the locked test; use a new experiment version for another run'
source=sqlite3.connect(app/'database/data/ml-additions/data-volume-v2/heldout-experiment.sqlite')
db=sqlite3.connect(out/'fresh-test.sqlite')
for name,sql in source.execute("SELECT name,sql FROM sqlite_master WHERE type='table'"):
    db.execute(sql)
    if name in ['fin_expense_category_tbl','unit_tbl','inventory_category_tbl','supplier_tbl']:
        rows=source.execute('SELECT * FROM '+name).fetchall()
        db.executemany('INSERT INTO '+name+' VALUES ('+','.join('?' for _ in rows[0])+')',rows)
def insert(table,row):
    fields=[r[1] for r in db.execute('PRAGMA table_info('+table+')')]
    db.execute('INSERT INTO '+table+' VALUES ('+','.join('?' for _ in fields)+')',[row.get(f) for f in fields])
tree=ast.parse((app/'scripts/ml/create_data_volume_experiment.py').read_text())
function=next(node for node in tree.body if isinstance(node,ast.FunctionDef) and node.name=='generate')
class NewIds(ast.NodeTransformer):
    def visit_Constant(self,node):
        return ast.copy_location(ast.Constant(1800000),node) if node.value==800000 else node
function=NewIds().visit(function)
module=ast.fix_missing_locations(ast.Module(body=[function],type_ignores=[]))
environment=dict(random=random,datetime=datetime,insert=insert)
exec(compile(module,'unchanged_simulator_with_fresh_identifiers','exec'),environment)
seed=secrets.randbits(63)
records,manifest=environment['generate'](150,seed,True)
db.commit()
for pid,actual in db.execute('SELECT project_id,actual_amount FROM budgets_tbl'):
    direct=db.execute('SELECT SUM(amount) FROM fin_expense_tbl WHERE project_id=? AND inventory_transaction_id IS NULL',(pid,)).fetchone()[0]
    material=db.execute('SELECT SUM(allocated_amount) FROM inventory_cost_allocation_tbl WHERE project_id=?',(pid,)).fetchone()[0]
    assert abs(direct+material-actual)<.01
assert sum(r['overrun'] for r in manifest)==50
payload=dict(generated_at=datetime.datetime.now(datetime.timezone.utc).isoformat(),seed=seed,records=records,manifest=manifest,
    scope='Fresh simulated draws from the same assumptions; no verified company outcomes added.')
(out/'fresh-test.json').write_text(json.dumps(payload,indent=2))
print('Fresh test saved: 150 projects, 50 overruns, 100 controls; all final ledgers reconcile.')
