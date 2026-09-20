# EVC construction demonstration dataset

This is non-sensitive, synthetic demonstration data inspired by the supplied EVC workbooks, not the company's actual project history. Project names, clients, schedules, suppliers, cash balances and transaction allocations are generated to resemble a mature Batangas-based construction operation. Supplier contact details are fictional but complete. Do not present model scores on this dataset as real-world company accuracy.

## Sources and assumptions

- `SAMPLE-REPORT.xlsx`, `PROFIT!D8:M27`: sample contracts around PHP 1,000,000. The generated contracts range roughly from PHP 350,000 to PHP 2,400,000 depending on scope. Expense outcomes include profitable and over-budget projects and are not adjusted to obtain better ML metrics.
- `SAMPLE-REPORT.xlsx`, `EXPOVRALL!A5:O6`: construction supplies, wages, permits, transport, utilities, delivery and office overhead categories.
- `SAMPLE-REPORT.xlsx`, `B. HOE KOMATSU!A4` and `B. HOE SUMITOMO!A4`: equipment acquisition-cost anchors of PHP 860,000 and PHP 1,075,000.
- `EVC-MAIN-BRANCH-DECEMBER-2024.xlsx` and `INVENTORY-2.xlsx`: product names, brands and unit-price anchors. Exact item references are in `catalog.json`. Prices are historical reference prices, not current supplier quotations.
- Localities are Batangas locations, consistent with the supplied equipment-site examples. Generated project names describe sample construction scope and municipality; they do not claim actual company contracts at those sites.
- History begins in January 2019. Most projects last 3–6 months, with occasional small completion delays. There are 72 completed projects, 8 active projects and 2 planned projects as of September 20, 2026.
- The generated finance ledger contains more than 2,000 varied transactions across all nine finance categories, with seasonal gaps, project-stage spending, and a complete 2019–2026 date span.
- The source contains no confirmed supplier master. The six supplier names, phone numbers, and addresses are fictional demonstration records.
- Ambiguous welding-rod and quartz-unit rows are excluded from the generated inventory. Units are normalized so `PC` and `PCS.` are one unit. Source descriptions are retained where package size was not given.

## Reproduce and install

`catalog.json` is the reviewed reference catalog. `generate.py` uses a fixed random seed and writes the full, versioned `dataset.json`. The Python standard library is sufficient.

```sh
python database/data/evc-sample/generate.py
php artisan migrate --path=database/migrations/2026_09_07_000001_add_data_source_to_project_tbl.php
php artisan pfims:replace-company-sample-data
php artisan pfims:replace-company-sample-data --apply
php artisan ml:retrain
```

The command is restricted to the local application environment. The first invocation validates without changing data. `--apply` backs up every scoped table, related notifications, and existing ML model artifacts before replacing records in one transaction. Any insertion or reconciliation failure rolls back the database transaction. Backups are under the configured local storage disk's `sample-backups` directory, and the command prints the exact path.

Users, authentication, reports and unrelated notifications are preserved. Old uploaded proof files are retained for recovery, while new sample records have no fabricated proof attachments. Legacy `expense_tbl` is explicitly emptied; all new project expenses are stored in `fin_expense_tbl` to avoid counting the same cost twice. Inventory records use explicit warehouse receipt, warehouse transfer, project stock-in, and project issue rows; each project-linked stock-in is linked to exactly one finance expense row through `inventory_transaction_id`.

## Reconciliation

Each project's `budgets_tbl.actual_amount` equals its finance expense total. Inventory transfer and issue postings preserve the material-stage valuation without adding duplicate cost. Warehouse current stock equals receipts less transfers and issues and never becomes negative. Equipment costs and rental income represent external hire activity, separate from project expenses. Bonds are refundable security and excluded from expense totals. Cash positions are illustrative dated balance snapshots, not a generated general ledger.

The as-of date is fixed for reproducibility. Future use should intentionally advance the dataset and dates rather than silently relabel old records. A larger synthetic dataset does not establish real prediction performance. The ML service reports `sample_trained_model` and explains this limitation in prediction reliability notes.

### Prediction model behavior

Until genuine progress history is sufficient, the estimator uses only fields represented consistently in completed and future projects: the recorded budget and planned duration. Current cumulative expenses are not given to that planning model as if they were completed-project totals. A final-cost estimate is also prevented from falling below recorded spending.

PFIMS now records append-only project-cost snapshots when project, budget, or finance data changes. When a project is genuinely completed, its earlier snapshots receive the authoritative final finance-ledger cost. The progress model activates only after at least ten operational completed projects have genuine snapshots in each early, middle, and late stage. All snapshots from one project remain in one chronological evaluation partition, and the newest 20 percent of projects form the fixed holdout.

The production estimate is presented as supported only when it uses operational company data, is within the training range, beats the recorded-budget baseline by the configured margin, and the served estimator is the best evaluated candidate. Sample, synthetic, fallback, constrained, and out-of-range results remain visible for review but are labeled as insufficient evidence. No generated values are changed to optimize evaluation scores, and generated snapshots are never invented for past dates.

## Recovery

Keep the printed backup directory private; it contains the previous database rows. Its manifest records original table counts and SHA-256 hashes. Restore rows in the importer's parent-before-child table order inside a transaction after deleting replacement rows in reverse order. Restore the backed-up scoped notifications and model files as part of recovery. Unrelated tables and proof files were not deleted. Do not use `migrate:fresh` to restore this dataset because it would remove unrelated system data.
