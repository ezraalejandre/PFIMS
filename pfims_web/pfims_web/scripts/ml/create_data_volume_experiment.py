"""Illustrative data sufficiency experiment. Never writes the operational database."""
import json, random, sqlite3, datetime, hashlib
from pathlib import Path

app=Path(__file__).resolve().parents[2]
out=Path(__file__).resolve().parents[2]/'database/data/ml-additions/data-volume-v2'
out.mkdir(exist_ok=True)
schema=json.loads((app/'storage/app/client-history-source.json').read_text())
train=[]; test=[]
db=sqlite3.connect(out/'heldout-experiment.sqlite')
if db.execute("SELECT count(*) FROM sqlite_master WHERE type='table'").fetchone()[0]:
    raise RuntimeError('Experiment database already exists; preserve it and choose a new directory.')
for name in ['project_tbl','budgets_tbl','project_budget_history','fin_expense_tbl','inventory_item_tbl','inventory_transaction_tbl','inventory_cost_allocation_tbl','fin_expense_category_tbl','unit_tbl','inventory_category_tbl','supplier_tbl']:
    columns=schema[name]['columns']
    db.execute('CREATE TABLE '+name+' ('+','.join('"'+c['name']+'" '+('REAL' if any(t in c['type'] for t in ['int','decimal','double','float']) else 'TEXT') for c in columns)+')')
def insert(table,row):
    fields=[c['name'] for c in schema[table]['columns']]
    db.execute('INSERT INTO '+table+' ('+','.join('"'+f+'"' for f in fields)+') VALUES ('+','.join('?' for _ in fields)+')',[row.get(f) for f in fields])

for table in ['fin_expense_category_tbl','unit_tbl','inventory_category_tbl','supplier_tbl']:
    for row in schema[table]['rows']:
        # Lookup IDs/names only; private contact fields are not needed in the experiment.
        insert(table,{k:v for k,v in row.items() if k not in ['address','contact_number']})

def generate(count,seed,heldout=False):
    rng=random.Random(seed);rows=[];manifest=[]
    for i in range(count):
        over=i<50 if heldout else i%2==0
        small=(i%2==0) if heldout else (i//2)%2==0
        ratio=rng.uniform(1.005,1.05) if over and small else rng.uniform(1.06,1.25) if over else rng.uniform(.80,.995)
        budget=round(rng.uniform(600000,5000000),2);final=round(budget*ratio,2)
        pid=(800000 if heldout else 700000)+i
        year=2026 if heldout else 2025 if i>=800 else 2024
        start=datetime.date(year,4 if not heldout and i>=800 else 1,1);end=start+datetime.timedelta(days=180)
        events=[];remaining=final
        # Uniform work packages with modest independent timing/cost-distribution noise.
        amounts=[]
        for j in range(3):
            value=round(final*.25*rng.uniform(.97,1.03),2);amounts.append(value);remaining-=value
        amounts.append(round(remaining,2))
        for j,amount in enumerate(amounts):
            day=start+datetime.timedelta(days=30+45*j)
            labor=round(amount*.4,2);material=round(amount-labor,2)
            events.append((day,labor,material))
            if heldout:
                receipt=pid*10+j*2;withdrawal=receipt+1;quantity=material/100
                insert('fin_expense_tbl',dict(fin_expense_id=pid*10+j,project_id=pid,fin_category_id=2,amount=labor,expense_date=str(day),expense_description='Illustrative labor package',project_cost_component='labor',created_at=str(day)+' 10:00:00'))
                insert('fin_expense_tbl',dict(fin_expense_id=pid*10+j+5,project_id=pid,fin_category_id=1,amount=material,expense_date=str(day),expense_description='Illustrative material receipt',project_cost_component='material',inventory_transaction_id=receipt,entry_kind='inventory_purchase',created_at=str(day)+' 09:00:00'))
                for tid,typ in [(receipt,'IN'),(withdrawal,'OUT')]:insert('inventory_transaction_tbl',dict(inventory_transaction_id=tid,item_id=pid,project_id=pid,transaction_type=typ,quantity=quantity,transaction_date=str(day),movement_reason='purchase' if typ=='IN' else None,recorded_at=str(day)+' 09:30:00'))
                insert('inventory_cost_allocation_tbl',dict(allocation_id=pid*10+j,in_transaction_id=receipt,out_transaction_id=withdrawal,project_id=pid,quantity=quantity,valuation_status='valued',unit_cost=100,allocated_amount=material,allocated_at=str(day)+' 09:30:00'))
        if heldout:
            insert('project_tbl',dict(project_id=pid,project_name=f'Illustrative held-out project {i+1}',client_name='Experiment',project_manager='Experiment',start_date=str(start),estimated_end_date=str(end),actual_end_date=str(end),worker_count=12,phase='Completion',completion_percentage=100,status='Completed',data_source='company_inspired_sample'))
            insert('budgets_tbl',dict(budget_id=pid,project_id=pid,budget_amount=budget,actual_amount=final))
            approval=str(start-datetime.timedelta(days=10))+' 09:00:00'
            insert('project_budget_history',dict(history_id=pid,project_id=pid,budget_id=pid,budget_amount=budget,event_type='initial_recorded',effective_at=approval,recorded_at=approval,reason='Illustrative approval before work started'))
            insert('inventory_item_tbl',dict(item_id=pid,item_name='Illustrative material',inventory_category_id=1,supplier_id=1,unit_id=1,unit_price=100,current_stock=0,reorder_level=0))
        for elapsed in [45,90,135]:
            at=start+datetime.timedelta(days=elapsed);past=[e for e in events if e[0]<=at]
            spent=round(sum(e[1]+e[2] for e in past),2);recent=[e for e in past if (at-e[0]).days<30]
            recent7=[e for e in past if (at-e[0]).days<7]
            material=sum(e[2] for e in past);labor=sum(e[1] for e in past)
            burn=sum(e[1]+e[2] for e in recent)/30;burn7=sum(e[1]+e[2] for e in recent7)/7
            rows.append(dict(project_id=pid,snapshot_id=f'experiment-{pid}-{elapsed}',captured_at=str(at),completed_at=str(end),budget=budget,duration_months=6,worker_count=12,
                elapsed_days=elapsed,remaining_planned_days=180-elapsed,elapsed_time_fraction=elapsed/180,fin_total_expense=spent,
                budget_used_fraction=spent/budget,material_cost_share=material/spent,labor_cost_share=labor/spent,equipment_cost_share=0,
                cost_burn_rate_30d=burn,burn_rate_acceleration=burn7/burn if burn else 0,inventory_cost_burn_rate_30d=sum(e[2] for e in recent)/30,
                expense_frequency_7d=len(recent7),stock_out_frequency_7d=len(recent7),actual_cost=round(final-spent,2),
                data_source='company_inspired_sample',budget_basis='illustrative_dated_approval',record_origin='Illustrative experiment'))
        manifest.append(dict(project_id=pid,overrun=over,small_overrun=over and small,budget=budget,final_cost=final))
    return rows,manifest
train,tm=generate(1000,2026111501)
test,vm=generate(150,2026111502,True)
db.commit()
assert not set(r['project_id'] for r in train)&set(r['project_id'] for r in test)
assert sum(r['overrun'] for r in tm)==500 and sum(r['overrun'] for r in vm)==50
report=dict(training_records=train,validation_project_ids=[700000+i for i in range(800,1000)],test_records=test,
    training_manifest=tm,test_manifest=vm,training_seed=2026111501,test_seed=2026111502,
    scope='Simulated project-independent draws from the same construction-cost assumptions. Not independent verified company evidence.',
    database_scope='Separate SQLite experiment database; no operational database changes.',
    assumptions=['Four staged work packages; costs available at each checkpoint.', 'Approved budget recorded before project starts.',
                 'Receipt prices and withdrawals fully reconciled.', 'Cost trajectory is simpler and more regular than real construction history.',
                 '50 overrun and 100 within-budget held-out projects; prevalence is designed, not representative company prevalence.'])
(out/'experiment-data.json').write_text(json.dumps(report,indent=2))
db.close()
print(json.dumps({'directory':str(out),'training_projects':1000,'training_overruns':500,'test_projects':150,'test_overruns':50,'operational_database_changes':0}))
