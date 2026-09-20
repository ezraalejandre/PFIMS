"""Reproduce demonstration records; never imports or modifies the database."""
import calendar
import datetime as dt
import json
import random
from pathlib import Path

ROOT = Path(__file__).resolve().parent
RNG = random.Random(20190907)
AS_OF = dt.date(2026, 9, 20)
catalog = json.loads((ROOT / 'catalog.json').read_text(encoding='utf-8'))
tables = {name: [] for name in [
    'expense_category_tbl', 'inventory_category_tbl', 'supplier_tbl', 'unit_tbl',
    'project_tbl', 'company_asset_tbl', 'company_bank_account_tbl', 'fin_expense_category_tbl',
    'inventory_item_tbl', 'budgets_tbl', 'inventory_transaction_tbl', 'expense_tbl', 'fin_expense_tbl',
    'fin_construction_bond_tbl', 'fin_receivable_payable_tbl', 'fin_equipment_expense_tbl',
    'fin_equipment_rental_income_tbl', 'fin_cash_position_tbl', 'fin_project_contract_tbl']}

def add(table, key, **row):
    row[key] = len(tables[table]) + 1
    tables[table].append(row)
    return row[key]

def month_add(day, months):
    n = day.year * 12 + day.month - 1 + months
    y, m = divmod(n, 12)
    return dt.date(y, m + 1, min(day.day, calendar.monthrange(y, m + 1)[1]))

def expense(project, category, component, amount, day, description, transaction=None, remarks=None):
    add('fin_expense_tbl', 'fin_expense_id', project_id=project, fin_category_id=category,
        project_cost_component=component, amount=round(amount, 2), expense_date=str(day),
        expense_description=description, inventory_transaction_id=transaction,
        remarks=remarks or 'Reviewed demonstration transaction; supporting reference retained in the project file.')

categories = [('CONSTRUCTION_SUPPLY','Construction supplies','material'),
              ('SALARIES_WAGES','Site salaries and wages','labor'),
              ('EQUIPMENT_RENTAL','Equipment rental and fuel','equipment'),
              ('PERMITS_TAXES','Permits, taxes and licenses','other'),
              ('DELIVERY','Delivery and hauling','other'),
              ('UTILITIES','Site utilities','other'),
              ('OFFICE_RENT','Office rent','other'),
              ('ADMIN_SALARIES','Office salaries','labor'),
              ('SSS_PHILHEALTH','Employer contributions','other')]
for i, (code, name, _) in enumerate(categories):
    add('fin_expense_category_tbl','fin_category_id',category_code=code,category_name=name,
        classification='direct' if i < 6 else 'admin',is_active=1)
for name in ['Materials','Labor','Equipment','Other']:
    add('expense_category_tbl','expense_category_id',category_name=name)
suppliers = [
    ('Lemery Aggregates and Hardware', 'Unit 4, National Road, Barangay Bagong Pook, Lemery, Batangas', '+63 917 842 1635'),
    ('Taal Electrical Supply', '118 Rizal Street, Barangay Poblacion 3, Taal, Batangas', '+63 905 318 7742'),
    ('Lipa Paint and Finishing Supply', '45 JP Laurel Highway, Barangay Maraouy, Lipa City, Batangas', '+63 998 461 2058'),
    ('Malvar Boards and Fittings', '26 Governor Drive, Barangay San Andres, Malvar, Batangas', '+63 917 624 8901'),
    ('Batangas Plumbing Supply', '77 Diversion Road, Barangay Alangilan, Batangas City', '+63 906 732 4519'),
    ('Rosario Tools and Hardware', '203 Padre Garcia Road, Barangay Quilib, Rosario, Batangas', '+63 975 285 6407'),
]
for name, address, phone in suppliers:
    add('supplier_tbl','supplier_id',supplier_name=name,address=address,contact_number=phone)

items = [x for x in catalog['items'] if x['unit'] != 'unspecified' and 'Quartz' not in x['name']]
unit_ids, category_ids = {}, {}
for index, item in enumerate(items):
    unit = item['unit'].lower().rstrip('.')
    unit = 'pc' if unit in ['pcs','pc'] else unit
    category = item['category'].replace('Inventory 2025','Ceiling and Modular Fittings')
    if unit not in unit_ids: unit_ids[unit] = add('unit_tbl','unit_id',unit_name=unit)
    if category not in category_ids:
        category_ids[category] = add('inventory_category_tbl','inventory_category_id',inventory_category_name=category)
    supplier = {'Structural':1,'Electrical':2,'Paints and Chemicals':3,'Accessories':3,
                'Modular Accessories':4,'Ceiling and Modular Fittings':4,'Sanitary Fittings':5}.get(category,6)
    add('inventory_item_tbl','item_id',item_name=item['name'],unit_id=unit_ids[unit],
        inventory_category_id=category_ids[category],supplier_id=supplier,current_stock=0,
        reorder_level=5 if unit in ['cu.m','roll','big box'] else 15)

locations = ['Lemery','Taal','Lipa','Malvar','Mataas na Kahoy','Rosario','Padre Garcia','Taysan',
             'San Jose','Bauan','San Luis','Batangas City']
project_names = ['Riverside bungalow extension','Cypress family residence','Market-front retail fit-out',
                 'Hillside two-storey shell','Harborview kitchen renovation','Barangay health-center annex',
                 'Farmhouse service-area upgrade','Small warehouse office conversion']
clients = ['Maribel Santos','Ramon and Celia Villanueva','Northpoint Merchandising','Joel Mercado',
           'Alicia de la Cruz','San Isidro Community Association','Teresa and Nestor Garcia','Luzon Craftworks']
managers = ['Engr. Carlo Mendoza','Engr. Nina Bautista','Ar. Paolo Reyes','Engr. Mae Villareal',
            'Engr. Vincent Flores','Ar. Lianne Castillo']
project_suffixes = ['Phase A','East Wing','South Lot','Main Compound']
scopes = [('Bungalow construction',900000,1500000),('House extension',450000,900000),
          ('Residential renovation',400000,850000),('Two-storey residential shell',1500000,2400000),
          ('Retail unit fit-out',550000,1100000),('Kitchen and service-area extension',350000,650000)]
completed_starts = []
cursor = dt.date(2019, 1, 14)
for _ in range(72):
    completed_starts.append(cursor)
    rainy_season_pause = 14 if cursor.month in [6, 7, 8, 9] and RNG.random() < .45 else 0
    cursor += dt.timedelta(days=RNG.randint(19, 43) + rainy_season_pause)
# 72 completed projects distributed over 2019–2026; 8 active and 2 planned.
for i in range(82):
    completed = i < 72
    pending = i >= 80
    start = completed_starts[i] if completed else dt.date(2026, [5,6,7,6,8,7,5,8,10,11][i-72], RNG.randint(3, 24))
    months = RNG.choice([3,4,4,5,5,6])
    end = month_add(start,months)
    if completed:
        actual_end = end + dt.timedelta(days=RNG.choice([-6,0,3,8,15]))
        completion, status, phase = 100,'Completed','Complete'
    elif pending:
        actual_end, completion, status, phase = None,0,'Pending','Planning'
    else:
        actual_end = None
        completion = min(94,max(12,round((AS_OF-start).days/(end-start).days*100)-RNG.randint(0,18)))
        status = 'Delayed' if end < AS_OF else ('At Risk' if i % 3 == 0 else 'On Track')
        phase = 'Finishing' if completion > 70 else ('Structure' if completion > 30 else 'Foundation')
    scope, low, high = scopes[i % len(scopes)]
    budget = round(RNG.uniform(low,high)/5000)*5000
    project = add('project_tbl','project_id',project_name=f'{project_names[i%len(project_names)]}, {locations[i%12]} - {project_suffixes[i//24]}',
        client_name=clients[(i * 5 + i // 9)%len(clients)],project_manager=managers[(i * 5 + i // 7)%len(managers)],start_date=str(start),
        estimated_end_date=str(end),actual_end_date=str(actual_end) if actual_end else None,
        worker_count=RNG.randint(4,9) if budget<1000000 else RNG.randint(8,14),
        completion_percentage=completion,status=status,phase=phase,data_source='company_inspired_sample')
    # Varied outcomes are chosen once, never tuned to evaluation scores.
    final_ratio = RNG.uniform(.74,.98) if i%5 else RNG.uniform(1.02,1.16)
    planned_spend = round(budget*final_ratio*(1 if completed else completion/100),2)
    last_day = actual_end if completed else AS_OF
    if not pending:
        weights=[RNG.uniform(.43,.53),RNG.uniform(.28,.37),RNG.uniform(.025,.07),.025,.025,.01]
        denom=sum(weights)
        # Monthly stage payments; final closeout occurs on completion date.
        stages=(months + RNG.randint(1, 4)) if completed else max(2,(AS_OF-start).days//24+1)
        allocated=0
        for component_index, weight in enumerate(weights):
            target=round(planned_spend*weight/denom,2) if component_index<5 else round(planned_spend-allocated,2)
            allocated+=target
            spent=0
            stage_weights = [RNG.uniform(.45, 1.65) for _ in range(stages)]
            if stages > 3 and RNG.random() < .55:
                stage_weights[RNG.randrange(1, stages - 1)] *= RNG.uniform(.12, .38)
            stage_weight_total = sum(stage_weights)
            for stage in range(stages):
                base_offset=round((last_day-start).days*(stage+1)/stages)
                day=min(last_day,start+dt.timedelta(days=max(1,base_offset+RNG.randint(-7,8))))
                amount=round(target*stage_weights[stage]/stage_weight_total,2) if stage<stages-1 else round(target-spent,2)
                spent+=amount
                descriptions = {
                    'material': ['cement and aggregate replenishment', 'electrical rough-in materials', 'finishing materials and consumables'],
                    'labor': ['site crew payroll and allowances', 'specialist trade labor', 'overtime and site attendance'],
                    'equipment': ['loader hire and fuel', 'concrete mixer and delivery equipment', 'equipment standby and service'],
                    'other': ['permit and inspection fees', 'haulage and site delivery', 'temporary site utilities'],
                }
                description = descriptions[categories[component_index][2]][(stage + i) % 3]
                expense(project,component_index+1,categories[component_index][2],amount,day,
                        description, remarks=f'Project cost allocation for {project_names[i%len(project_names)].lower()} stage {stage + 1}.')
    actual=sum(round(e['amount']*100) for e in tables['fin_expense_tbl'] if e['project_id']==project)/100
    add('budgets_tbl','budget_id',project_id=project,budget_amount=budget,actual_amount=actual)
    received=budget if completed else round(budget*(0 if pending else max(.2,completion/100-.05)),2)
    add('fin_project_contract_tbl','contract_id',project_id=project,original_contract_price=budget,
        additional_works_contract=0,original_payment_received=received,additional_works_payment=0,
        remarks='Demonstration contract schedule; progress billing retained for reporting practice.')
    if not completed and not pending:
        add('fin_receivable_payable_tbl','rp_id',entry_type='accounts_receivable',project_id=project,
            counterparty_name=clients[i%len(clients)],entry_date=str(AS_OF),amount_30d=round(budget-received,2),
            status='outstanding',remarks=f'Uncollected sample contract balance; aging as of {AS_OF}.')
        add('fin_construction_bond_tbl','bond_id',project_id=project,bond_date=str(start),amount=10000 if budget<1000000 else 15000,
            bond_provider='Client retention account',status='active',remarks='Refundable security held against completion obligations; excluded from expense.')

# Warehouse receipts, warehouse transfers, and project issues. Each applicable
# project-linked stock-in has one linked finance expense. A warehouse OUT and
# matching project OUT make the transfer/consumption lifecycle explicit without
# creating a duplicate cost.
for i,item in enumerate(tables['inventory_item_tbl']):
    quantity = RNG.randint(20,80)
    remaining = 0 if i%13==0 else (RNG.randint(2,10) if i%7==0 else RNG.randint(18,35))
    incoming=quantity+remaining
    project=1 + (i * 7) % 72
    project_start = dt.date.fromisoformat(tables['project_tbl'][project - 1]['start_date'])
    receipt_day = project_start + dt.timedelta(days=7 + (i * 11) % 45)
    add('inventory_transaction_tbl','inventory_transaction_id',item_id=item['item_id'],project_id=None,
        transaction_type='IN',quantity=incoming,bar_code=2600000+i,transaction_date=str(receipt_day))
    add('inventory_transaction_tbl','inventory_transaction_id',item_id=item['item_id'],project_id=None,
        transaction_type='OUT',quantity=quantity,bar_code=2650000+i,transaction_date=str(receipt_day + dt.timedelta(days=1)))
    tx_in=add('inventory_transaction_tbl','inventory_transaction_id',item_id=item['item_id'],project_id=project,
        transaction_type='IN',quantity=quantity,bar_code=2800000+i,transaction_date=str(receipt_day + dt.timedelta(days=2)))
    tx=add('inventory_transaction_tbl','inventory_transaction_id',item_id=item['item_id'],project_id=project,
        transaction_type='OUT',quantity=quantity,bar_code=2700000+i,transaction_date=str(receipt_day + dt.timedelta(days=3)))
    # Move an existing material posting onto the linked stock-in row so the
    # budget total remains unchanged while the inventory-to-finance link is real.
    parent=max((e for e in tables['fin_expense_tbl'] if e['project_id']==project and e['fin_category_id']==1 and e['inventory_transaction_id'] is None),key=lambda e:e['amount'])
    unit_cost=items[i]['unit_cost']
    amount=round(quantity*unit_cost,2)
    if amount>=parent['amount']:
        quantity=max(1,int(parent['amount']*.25/unit_cost))
        tables['inventory_transaction_tbl'][-3]['quantity']=quantity
        tables['inventory_transaction_tbl'][-2]['quantity']=quantity
        tables['inventory_transaction_tbl'][-1]['quantity']=quantity
        remaining=incoming-quantity
        amount=round(quantity*unit_cost,2)
    parent['amount']=round(parent['amount']-amount,2)
    expense(project,1,'material',amount,receipt_day + dt.timedelta(days=2),f"Procured {quantity} {next(k for k,v in unit_ids.items() if v==item['unit_id'])} of {item['item_name']}",tx_in,
            remarks=f'Linked stock-in valuation for {item["item_name"]}; receiving reference {2800000+i}.')
    item['current_stock']=remaining

for i,anchor in enumerate(catalog['equipment_anchors']):
    asset=add('company_asset_tbl','asset_id',asset_name=anchor['name'],asset_type='heavy_equipment',
              asset_code=f'EVC-EQ-{i+1:02d}',acquisition_cost=anchor['asset_cost'],status='active')
    for month_index in range(33):
        period = month_add(dt.date(2024,1,1),month_index)
        if period > AS_OF.replace(day=1) or (period.month in [6,7,8] and RNG.random() < .16): continue
        for kind,base in [('gas_diesel',6500),('payroll_operator',9000),('repair',2500),('delivery',1500)]:
            seasonal = 1.18 if period.month in [3,4,5] else (.82 if period.month in [7,8,9] else 1)
            add('fin_equipment_expense_tbl','equip_expense_id',asset_id=asset,project_id=None,expense_type=kind,
                amount=round((base+RNG.uniform(-900,2100))*seasonal,2),expense_date=str(period.replace(day=RNG.randint(3,24))),remarks=RNG.choice(['Operator log and fuel issue reconciled.','External hire operating cost reviewed.','Workshop and dispatch record matched.']))
        add('fin_equipment_rental_income_tbl','rental_income_id',asset_id=asset,project_id=None,
            period_month=str(period),amount=round(RNG.uniform(28500,51200),2),remarks=RNG.choice(['External equipment hire receipt.','Monthly equipment deployment billing.','Rental collection net of standby days.']))
for name,kind,base in [('Office petty cash','cash_on_hand',25000),('Site revolving fund','cash_on_hand_field',45000),('Company treasury','treasury',680000)]:
    account=add('company_bank_account_tbl','account_id',account_name=name,account_type=kind)
    balance=base*RNG.uniform(.82,1.16)
    for month_index in range(93):
        period=month_add(dt.date(2019,1,1),month_index)
        if period > AS_OF.replace(day=1): break
        balance=max(base*.35,balance+RNG.gauss(0,base*.075)+(base*.05 if period.month in [11,12] else 0))
        add('fin_cash_position_tbl','cash_position_id',account_id=account,period_month=str(period),balance_amount=round(balance,2))
for month_index in range(93):
    period=month_add(dt.date(2019,1,1),month_index)
    if period > AS_OF.replace(day=1): break
    office_costs=[
        (7,RNG.uniform(12800,19200),RNG.choice(['main office lease and association dues','office rent with common-area charge'])),
        (8,RNG.uniform(24500,36500)*(1.04**(period.year-2019)),RNG.choice(['administrative payroll and allowances','office staff payroll and overtime'])),
        (9,RNG.uniform(3100,5900),RNG.choice(['employer statutory contributions','SSS, PhilHealth and Pag-IBIG remittance'])),
    ]
    if period.month in [4,7,10]: office_costs.append((7,RNG.uniform(4500,18000),RNG.choice(['printer maintenance and office supplies','software renewal and communications','minor office repair materials'])))
    for category,amount,description in office_costs:
        expense(None,category,categories[category-1][2],amount,period.replace(day=RNG.randint(3,23)),description,remarks=RNG.choice(['Administrative voucher reviewed.','Paid through office disbursement batch.','Recurring obligation matched to billing statement.']))

document={'metadata':{'version':1,'seed':20190907,'as_of':str(AS_OF),'data_source':'company_inspired_sample',
    'completed_projects':72,'note':'Synthetic demonstration history calibrated to company workbooks; not actual company transactions.'},'tables':tables}
(ROOT/'dataset.json').write_text(json.dumps(document,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
print(json.dumps({table:len(rows) for table,rows in tables.items()},indent=2))
