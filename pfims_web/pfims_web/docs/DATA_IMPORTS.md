# PFIMS data import formats

PFIMS accepts UTF-8 CSV files and standard `.xlsx` workbooks up to 5 MB. Only the first XLSX worksheet is read. A file may contain at most 2,000 non-empty data rows and 50 columns.

Imports are atomic: PFIMS validates the headers, every row, configured lookups, duplicates, and inventory stock before inserting anything. If one row fails, no rows from that upload are inserted. The response identifies the spreadsheet row, field, and error.

## Finance expenses

Required headers:

- `category_code`
- `expense_description`
- `amount`
- `expense_date`

Optional headers:

- `project_name` — blank means an office/admin expense
- `remarks`

Dates use `YYYY-MM-DD`. `category_code` must be an active Finance Expense Category from Settings > Configurations. A supplied project name must match exactly one existing PFIMS project. A duplicate is the same project/office scope, finance category, date, amount, and normalized description.

Download: `/api/imports/templates/finance-expenses`

## Inventory items

Required headers:

- `item_name`
- `category`
- `supplier`
- `unit`
- `current_stock`
- `reorder_level`

Optional header:

- `opening_balance_date` — defaults to the upload date

Category, supplier, and unit names must already exist in Settings > Configurations. A positive current stock creates an `IN` opening-balance transaction in the normal inventory transaction table, so imported stock remains auditable.

Download: `/api/imports/templates/inventory-items`

## Inventory transactions

Required headers:

- `item_name`
- `transaction_type` — `IN` or `OUT`
- `quantity`
- `transaction_date`

Optional headers:

- `project_name`
- `bar_code` — a non-negative integer

Transactions are processed in file order for stock validation. An `OUT` row that would make current stock negative rejects the entire file. A duplicate is the same item, project, direction, quantity, barcode, and date.

Download: `/api/imports/templates/inventory-transactions`

All import and template endpoints require a signed-in user. Finance imports are limited to administrators and accounting users; inventory imports are limited to administrators and operations users.
