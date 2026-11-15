import json, sqlite3
from pathlib import Path
directory=Path(__file__).resolve().parents[2]/'database/data/ml-additions/data-volume-v2'
data=json.loads((directory/'experiment-data.json').read_text())
results=json.loads((directory/'results.json').read_text())
c=sqlite3.connect(directory/'heldout-experiment.sqlite')
assert len(set(r['project_id'] for r in data['training_records']))==1000
assert len(set(r['project_id'] for r in data['test_records']))==150
assert not set(r['project_id'] for r in data['training_records'])&set(r['project_id'] for r in data['test_records'])
assert sum(r['overrun'] for r in data['training_manifest'])==500
assert sum(r['small_overrun'] for r in data['training_manifest'])==250
assert sum(r['overrun'] for r in data['test_manifest'])==50
assert sum(r['small_overrun'] for r in data['test_manifest'])==25
for pid,actual in c.execute('SELECT project_id,actual_amount FROM budgets_tbl'):
    direct=c.execute('SELECT SUM(amount) FROM fin_expense_tbl WHERE project_id=? AND inventory_transaction_id IS NULL',(pid,)).fetchone()[0]
    material=c.execute('SELECT SUM(allocated_amount) FROM inventory_cost_allocation_tbl WHERE project_id=?',(pid,)).fetchone()[0]
    assert abs(direct+material-actual)<.01
for r in data['test_records']:
    direct=c.execute('SELECT COALESCE(SUM(amount),0) FROM fin_expense_tbl WHERE project_id=? AND inventory_transaction_id IS NULL AND expense_date<=?',(r['project_id'],r['captured_at'])).fetchone()[0]
    material=c.execute('SELECT COALESCE(SUM(a.allocated_amount),0) FROM inventory_cost_allocation_tbl a JOIN inventory_transaction_tbl t ON t.inventory_transaction_id=a.out_transaction_id WHERE a.project_id=? AND t.transaction_date<=?',(r['project_id'],r['captured_at'])).fetchone()[0]
    assert abs(direct+material-r['fin_total_expense'])<.01
assert c.execute('SELECT COUNT(*) FROM project_budget_history h JOIN project_tbl p ON p.project_id=h.project_id WHERE h.effective_at>=p.start_date').fetchone()[0]==0
assert c.execute('SELECT COUNT(*) FROM inventory_cost_allocation_tbl WHERE ABS(quantity*unit_cost-allocated_amount)>.011').fetchone()[0]==0
assert c.execute('SELECT COUNT(*) FROM fin_expense_tbl e LEFT JOIN fin_expense_category_tbl f ON e.fin_category_id=f.fin_category_id WHERE f.fin_category_id IS NULL').fetchone()[0]==0
assert results['operational_records_added']==0 and not results['active_model_changed']
print('Verified separate partitions, 500/50 overruns, 250/25 small overruns, all 150 final-cost ledgers, all 450 spending checkpoints, approvals, receipt prices and category links.')
