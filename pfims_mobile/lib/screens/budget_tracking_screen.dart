import 'package:flutter/material.dart';
import '../widgets/app_bottom_nav_bar.dart';
import '../widgets/app_header.dart';
import '../services/finance_service.dart';
import '../services/inventory_service.dart';
import 'package:flutter/services.dart';

// ---------------------------------------------------------------------
// COLOR TOKENS — same palette as before; theme itself untouched.
// ---------------------------------------------------------------------
const Color kDarkPill = Color(0xFF14161F);
const Color kAmberStrong = Color(0xFFF0B94A);
const Color kUnderGreen = Color(0xFF1F9254);
const Color kOverRed = Color(0xFFE5483B);

const Color _laborBg = Color(0xFFE3EEFC);
const Color _laborText = Color(0xFF2F6FE4);
const Color _materialBg = Color(0xFFFCEAD9);
const Color _materialText = Color(0xFFD97B3F);
const Color _equipmentBg = Color(0xFFEFE3FC);
const Color _equipmentText = Color(0xFF7C4FE0);
const Color _otherBg = Color(0xFFE9EAEC);
const Color _otherText = Color(0xFF5B6270);

const List<String> _monthAbbr = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

/// Long form for card display, mirroring project_tracking_screen's _formatDate.
String _formatDate(DateTime date) {
  return '${_monthAbbr[date.month - 1]} ${date.day}, ${date.year}';
}

/// mm/dd/yyyy, used inside date-picker fields (matches the modal date fields
/// in project_tracking_screen, which also show numeric m/d/yyyy).
String _fmtDateField(DateTime d) => '${d.month}/${d.day}/${d.year}';

/// Minimal peso formatter (no `intl` dependency).
String _peso(num value) {
  final isNegative = value < 0;
  final whole = value.abs().round().toString();
  final buffer = StringBuffer();
  for (int i = 0; i < whole.length; i++) {
    if (i != 0 && (whole.length - i) % 3 == 0) buffer.write(',');
    buffer.write(whole[i]);
  }
  return "${isNegative ? '-' : ''}\u20b1$buffer";
}

/// Trims trailing ".0" off whole-number quantities (e.g. "50 kg" not "50.0 kg").
String _formatQty(num q) => q % 1 == 0 ? q.toInt().toString() : q.toString();

Color _categoryBg(String category) {
  switch (category.trim().toLowerCase()) {
    case 'labor':
      return _laborBg;
    case 'material':
      return _materialBg;
    case 'equipment':
      return _equipmentBg;
    default:
      return _otherBg;
  }
}

Color _categoryText(String category) {
  switch (category.trim().toLowerCase()) {
    case 'labor':
      return _laborText;
    case 'material':
      return _materialText;
    case 'equipment':
      return _equipmentText;
    default:
      return _otherText;
  }
}

/// Restricts a TextField to non-negative decimal amounts (e.g. "1234.56").
/// The minus sign isn't in the allowed pattern at all, so a negative
/// number can't be typed or pasted in — not just flagged after the fact.
/// Also caps the fractional part at 2 digits.
class _NonNegativeAmountFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final text = newValue.text;
    if (text.isEmpty) return newValue;

    final regex = RegExp(r'^\d*\.?\d{0,2}$');
    if (!regex.hasMatch(text)) {
      return oldValue;
    }
    return newValue;
  }
}

// ---------------------------------------------------------------------
// SAMPLE REFERENCE DATA — replace with real project/category sources
// once wired to the backend (e.g. ProjectsService.getProjects()).
// ---------------------------------------------------------------------
const List<String> kProjects = [
  'Downtown Tower',
  'Riverside Villas',
  'Grand Mall Renovation',
  'Seaside Resort Expansion',
];

const List<String> kExpenseCategories = ['Labor', 'Material', 'Equipment', 'Other'];

/// ---------------------------------------------------------------------
/// Model for a single logged expense line item.
/// ---------------------------------------------------------------------
class _ExpenseEntry {
  final int id;
  final int projectId;
  final String project;
  final String description;
  final int categoryId;
  final String category;
  final double amount;
  final DateTime date;
  final String remarks;
  final int? linkedStockInTransactionId;

  const _ExpenseEntry({
    required this.id,
    required this.projectId,
    required this.project,
    required this.description,
    required this.categoryId,
    required this.category,
    required this.amount,
    required this.date,
    required this.remarks,
    this.linkedStockInTransactionId,
  });

  /// Builds a display entry from the joined row returned by the
  /// expenses endpoints (project_name / category_name / actual_amount).
  /// project_id / project_name will be absent/empty for expenses logged
  /// without a project, which is now the normal case.
  ///
  /// If this expense was logged from a stock-in transaction, the
  /// transaction id is embedded (invisibly, to the user) in `remarks` as
  /// `[txn#<id>]`. It's parsed out here and stripped from the remarks
  /// text shown anywhere in the UI.
  factory _ExpenseEntry.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    double parseAmount(dynamic v) {
      if (v == null) return 0.0;
      return double.tryParse(v.toString()) ?? 0.0;
    }

    DateTime parseDate(dynamic v) {
      return DateTime.tryParse(v?.toString() ?? '') ?? DateTime.now();
    }

    final rawRemarks = (json['remarks'] ?? '').toString();
    final txnMatch = RegExp(r'\[txn#(\d+)\]').firstMatch(rawRemarks);
    final cleanedRemarks =
        rawRemarks.replaceAll(RegExp(r'\s*\[txn#\d+\]'), '').trim();

    return _ExpenseEntry(
      id: parseInt(json['expense_id']),
      projectId: parseInt(json['project_id']),
      project: (json['project_name'] ?? '').toString(),
      description: (json['expense_description'] ?? '').toString(),
      categoryId: parseInt(json['expense_category_id']),
      category: (json['category_name'] ?? '').toString(),
      amount: parseAmount(json['actual_amount']),
      date: parseDate(json['expense_date']),
      remarks: cleanedRemarks,
      linkedStockInTransactionId:
          txnMatch != null ? int.tryParse(txnMatch.group(1)!) : null,
    );
  }

  Color get tagBg => _categoryBg(category);
  Color get tagText => _categoryText(category);
  String get dateLabel => _formatDate(date);
}

/// A single dropdown choice fetched from the backend (id kept around for
/// when expense create/update actually hits the API; only `.name` is used
/// for display and for the in-memory _ExpenseEntry today).
class _DropdownOption {
  final int id;
  final String name;
  const _DropdownOption({required this.id, required this.name});
}

/// ---------------------------------------------------------------------
/// A single row from budgets_tbl, joined with its project's name.
/// ---------------------------------------------------------------------
class _BudgetEntry {
  final int id;
  final int projectId;
  final String projectName;
  final double budgetAmount;
  final double actualAmount;

  const _BudgetEntry({
    required this.id,
    required this.projectId,
    required this.projectName,
    required this.budgetAmount,
    required this.actualAmount,
  });

  factory _BudgetEntry.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic v) => v is int ? v : int.tryParse(v?.toString() ?? '') ?? 0;
    double parseAmount(dynamic v) {
      if (v == null) return 0.0;
      return v is num ? v.toDouble() : double.tryParse(v.toString()) ?? 0.0;
    }

    return _BudgetEntry(
      id: parseInt(json['budget_id']),
      projectId: parseInt(json['project_id']),
      projectName: (json['project_name'] ?? '').toString(),
      budgetAmount: parseAmount(json['budget_amount']),
      actualAmount: parseAmount(json['actual_amount']),
    );
  }

  double get variance => budgetAmount - actualAmount;
  double get percentUsed =>
      budgetAmount <= 0 ? 0 : (actualAmount / budgetAmount).clamp(0, 1.5);
}

/// ---------------------------------------------------------------------
/// A single stock-IN inventory transaction, shown in the expense feed so
/// the user can log the matching expense for it (category defaults to
/// Material). Stock-IN transactions never carry a project_id server-side
/// (only OUT transactions do).
/// ---------------------------------------------------------------------
class _StockInTxn {
  final int transactionId;
  final int itemId;
  final String itemName;
  final String unitName;
  final double quantity;
  final DateTime date;

  const _StockInTxn({
    required this.transactionId,
    required this.itemId,
    required this.itemName,
    required this.unitName,
    required this.quantity,
    required this.date,
  });

  factory _StockInTxn.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic v) => v is int ? v : int.tryParse(v?.toString() ?? '') ?? 0;
    double parseAmount(dynamic v) {
      if (v == null) return 0.0;
      return v is num ? v.toDouble() : double.tryParse(v.toString()) ?? 0.0;
    }
    DateTime parseDate(dynamic v) => DateTime.tryParse(v?.toString() ?? '') ?? DateTime.now();

    return _StockInTxn(
      transactionId: parseInt(json['inventory_transaction_id']),
      itemId: parseInt(json['item_id']),
      itemName: (json['item_name'] ?? '').toString(),
      unitName: (json['unit_name'] ?? '').toString(),
      quantity: parseAmount(json['quantity']),
      date: parseDate(json['transaction_date']),
    );
  }

  String get dateLabel => _formatDate(date);
}

/// Lets the expense feed hold two kinds of cards (real expenses and
/// stock-ins awaiting an expense) sorted together by date, newest first.
abstract class _FeedItem {
  DateTime get sortDate;
}

class _ExpenseFeedItem extends _FeedItem {
  final _ExpenseEntry entry;
  _ExpenseFeedItem(this.entry);
  @override
  DateTime get sortDate => entry.date;
}

class _StockInFeedItem extends _FeedItem {
  final _StockInTxn txn;
  _StockInFeedItem(this.txn);
  @override
  DateTime get sortDate => txn.date;
}

/// ---------------------------------------------------------------------

class BudgetTrackingScreen extends StatefulWidget {
  final String email;

  const BudgetTrackingScreen({super.key, this.email = ''});

  @override
  State<BudgetTrackingScreen> createState() => _BudgetTrackingScreenState();
}

class _BudgetTrackingScreenState extends State<BudgetTrackingScreen> {
  final TextEditingController _searchController = TextEditingController();

  String _selectedPeriod = "Monthly";
  static const List<String> _periods = ["Daily", "Weekly", "Monthly", "Yearly"];

  // null == "All Projects"
  String? _selectedProjectFilter;

  // 0 = Expenses tab, 1 = Budgets tab
  int _selectedTab = 0;

  late Future<({List<_ExpenseEntry> expenses, List<_StockInTxn> stockIns})> _feedFuture;
  late Future<List<_BudgetEntry>> _budgetsFuture;
  List<_BudgetEntry> _budgetEntries = [];

  // Legacy local-only budget map (kept for _AddBudgetModal's existing
  // return type); the Budgets tab itself reads from _budgetsFuture, which
  // is the real budgets_tbl data.
  final Map<String, double> _budgets = {};

  @override
  void initState() {
    super.initState();
    _feedFuture = _loadFeed();
    _budgetsFuture = _loadBudgets();
    _refreshBudgets();
    _searchController.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<_ExpenseEntry>> _loadExpenses() async {
    final raw = await FinanceService.getExpenses();
    return raw.map((e) => _ExpenseEntry.fromJson(e)).toList();
  }

  // Stock-IN transactions (stock replenishment) are pulled from Inventory
  // so their matching expense can be logged straight from this screen.
  // Stock-OUT rows are excluded here — they're inventory leaving for a
  // project, not money being spent.
  Future<List<_StockInTxn>> _loadStockIns() async {
    final raw = await InventoryService.fetchTransactions();
    return raw
        .where((e) => (e['transaction_type']?.toString().toUpperCase() ?? '') == 'IN')
        .map((e) => _StockInTxn.fromJson(e))
        .toList();
  }

  Future<({List<_ExpenseEntry> expenses, List<_StockInTxn> stockIns})> _loadFeed() async {
    final results = await Future.wait([_loadExpenses(), _loadStockIns()]);
    final expenses = results[0] as List<_ExpenseEntry>;
    final allStockIns = results[1] as List<_StockInTxn>;

    // Hide any stock-in transaction that already has a matching expense
    // logged against it (linked via the [txn#<id>] marker in remarks).
    // Derived fresh from server data every load, so it survives refreshes
    // and restarts rather than relying on local-only state.
    final loggedTxnIds = expenses
        .map((e) => e.linkedStockInTransactionId)
        .whereType<int>()
        .toSet();
    final stockIns = allStockIns
        .where((s) => !loggedTxnIds.contains(s.transactionId))
        .toList();

    return (expenses: expenses, stockIns: stockIns);
  }

  Future<void> _refreshExpenses() async {
    final future = _loadFeed();
    setState(() {
      _feedFuture = future;
    });
    await future;
  }

  Future<List<_BudgetEntry>> _loadBudgets() async {
    final raw = await FinanceService.getBudgets();
    return raw.map((e) => _BudgetEntry.fromJson(e)).toList();
  }

  Future<void> _refreshBudgets() async {
  final budgets = await _loadBudgets();

  setState(() {
    _budgetEntries = budgets;
    _budgetsFuture = Future.value(budgets);
  });
}
  List<_ExpenseEntry> _byProject(List<_ExpenseEntry> all) {
    if (_selectedProjectFilter == null) return all;
    return all.where((e) => e.project == _selectedProjectFilter).toList();
  }

  List<_ExpenseEntry> _filter(List<_ExpenseEntry> entries) {
    final query = _searchController.text.trim().toLowerCase();
    if (query.isEmpty) return entries;
    return entries
        .where((e) =>
            e.description.toLowerCase().contains(query) ||
            e.remarks.toLowerCase().contains(query) ||
            e.project.toLowerCase().contains(query))
        .toList();
  }

  // Stock-ins have no project attached, so they only ever show up under
  // "All Projects" — filtering by a specific project hides them, since we
  // can't say for certain which project they belong to.
  List<_StockInTxn> _filterStockIns(List<_StockInTxn> all) {
    final query = _searchController.text.trim().toLowerCase();
    if (query.isEmpty) return all;
    return all.where((s) => s.itemName.toLowerCase().contains(query)).toList();
  }

  // double get _totalBudget {
  //   if (_selectedProjectFilter == null) {
  //     return _budgets.values.fold(0.0, (a, b) => a + b);
  //   }
  //   return _budgets[_selectedProjectFilter] ?? 0.0;
  // }
  

  double _totalActualFor(List<_ExpenseEntry> byProject) =>
      byProject.fold(0.0, (a, e) => a + e.amount);

  Future<void> _openAddExpense() async {
    final result = await showDialog<_ExpenseEntry>(
      context: context,
      barrierDismissible: false,
      builder: (context) => const _AddExpenseModal(),
    );
    // Dialog is fully popped (with its result) before we touch state —
    // no dialog stacking.
    if (result != null && mounted) {
      await _refreshExpenses();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Expense added successfully.')),
        );
      }
    }
  }

  // Opens the same Add Expense modal, but pre-filled from a stock-IN
  // transaction: description = item name, category = Material, date =
  // transaction date, and a remark noting the quantity received. The
  // transaction id itself is threaded through so the submitted expense
  // gets tagged and this stock-in disappears from the feed afterward.
  Future<void> _openExpenseFromStockIn(_StockInTxn txn) async {
    final result = await showDialog<_ExpenseEntry>(
      context: context,
      barrierDismissible: false,
      builder: (context) => _AddExpenseModal(
        initialDescription: txn.itemName,
        initialCategoryName: 'Material',
        initialDate: txn.date,
        initialRemarks: 'Stock-in: ${_formatQty(txn.quantity)} ${txn.unitName}',
        stockInTransactionId: txn.transactionId,
      ),
    );
    if (result != null && mounted) {
      await _refreshExpenses();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Expense added successfully.')),
        );
      }
    }
  }

  Future<void> _openAddBudget() async {
    final result = await showDialog<MapEntry<String, double>>(
      context: context,
      barrierDismissible: false,
      builder: (context) => const _AddBudgetModal(),
    );
    if (result != null && mounted) {
      setState(() {
        _budgets[result.key] = result.value;
      });
      await _refreshBudgets();
    }
  }

  Future<void> _openExpenseDetails(_ExpenseEntry expense) async {
    final result = await showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (context) => _ExpenseDetailsModal(expense: expense),
    );

    if (!mounted) return;

    if (result == 'edit') {
      final updated = await showDialog<bool>(
        context: context,
        barrierDismissible: false,
        builder: (context) => _EditExpenseModal(expense: expense),
      );
      if (updated == true && mounted) {
        await _refreshExpenses();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Expense updated successfully.')),
          );
        }
      }
    } else if (result == 'deleted') {
      await _refreshExpenses();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Expense deleted.')),
        );
      }
    }
  }

  Widget _tabButton(String label, int index) {
    final selected = _selectedTab == index;
    return GestureDetector(
      onTap: () => setState(() => _selectedTab = index),
      child: Container(
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: selected ? kDarkPill : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : Colors.grey[600],
          ),
        ),
      ),
    );
  }

  Widget _buildBudgetsSection() {
    return FutureBuilder<List<_BudgetEntry>>(
      future: _budgetsFuture,
      builder: (context, snapshot) {
        final isLoading = snapshot.connectionState == ConnectionState.waiting;
        final hasError = snapshot.hasError;
        final allBudgets = snapshot.data ?? const <_BudgetEntry>[];

        final budgets = _selectedProjectFilter == null
            ? allBudgets
            : allBudgets.where((b) => b.projectName == _selectedProjectFilter).toList();

        if (isLoading) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 48),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        if (hasError) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 32),
            child: Column(
              children: [
                Text(
                  snapshot.error.toString().replaceFirst('Exception: ', ''),
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey[600]),
                ),
                const SizedBox(height: 12),
                OutlinedButton(
                  onPressed: _refreshBudgets,
                  child: const Text("Retry"),
                ),
              ],
            ),
          );
        }
        if (budgets.isEmpty) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 32),
            child: Center(
              child: Text(
                "No budgets yet. Tap \"Add Budget\" to add one.",
                style: TextStyle(color: Colors.grey[600]),
              ),
            ),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              "${budgets.length} BUDGETS",
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: Colors.grey[600],
                letterSpacing: .2,
              ),
            ),
            const SizedBox(height: 10),
            ...budgets.map((b) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _BudgetCard(entry: b),
                )),
          ],
        );
      },
    );
  }

@override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF2F3F5),
      appBar: AppHeader(email: widget.email),
      body: RefreshIndicator(
        onRefresh: () async {
          await Future.wait([_refreshExpenses(), _refreshBudgets()]);
        },
        child: FutureBuilder<({List<_ExpenseEntry> expenses, List<_StockInTxn> stockIns})>(
          future: _feedFuture,
          builder: (context, snapshot) {
            final isLoading = snapshot.connectionState == ConnectionState.waiting;
            final hasError = snapshot.hasError;
            final allExpenses = snapshot.data?.expenses ?? const <_ExpenseEntry>[];
            final allStockIns = snapshot.data?.stockIns ?? const <_StockInTxn>[];

            final byProject = _byProject(allExpenses);
            final filteredExpenses = _filter(byProject);
            // Only show stock-ins when no specific project is selected —
            // there's no project to match them against otherwise.
            final filteredStockIns = _selectedProjectFilter == null
                ? _filterStockIns(allStockIns)
                : const <_StockInTxn>[];

            final netVariance = 0.0;

            final feedItems = <_FeedItem>[
              ...filteredExpenses.map((e) => _ExpenseFeedItem(e)),
              ...filteredStockIns.map((s) => _StockInFeedItem(s)),
            ]..sort((a, b) => b.sortDate.compareTo(a.sortDate));

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                // ---- Title + Add Expense / Add Budget ---- (unchanged)
               Column(
  crossAxisAlignment: CrossAxisAlignment.start,
  children: [
    const Text(
      "BUDGET & FINANCE",
      style: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.bold,
        letterSpacing: .2,
        color: Colors.black87,
      ),
    ),
    const SizedBox(height: 4),
    Text(
      "Track spending and budgets across all projects",
      style: TextStyle(fontSize: 13, color: Colors.grey[600]),
    ),
    const SizedBox(height: 16),
    Row(
      children: [
        Expanded(
          child: ElevatedButton.icon(
            onPressed: _openAddExpense,
            style: ElevatedButton.styleFrom(
              backgroundColor: kDarkPill,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            icon: const Icon(Icons.add, size: 18),
            label: const Text(
              "Add Expense",
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: OutlinedButton.icon(
            onPressed: _openAddBudget,
            style: OutlinedButton.styleFrom(
              foregroundColor: kDarkPill,
              side: BorderSide(color: Colors.grey.shade300, width: 1.2),
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            icon: const Icon(Icons.add, size: 18),
            label: const Text(
              "Add Budget",
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
            ),
          ),
        ),
      ],
    ),
    const SizedBox(height: 20),
    Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(child: _tabButton("Expenses", 0)),
          Expanded(child: _tabButton("Budgets", 1)),
        ],
      ),
    ),
  ],
),
const SizedBox(height: 16),

                // ---- Stat tiles ----

              const SizedBox(height: 16),

// Calculate everything from fresh state
Builder(
  builder: (context) {
    // Budgets
    final allBudgets = _budgetEntries; // already refreshed in _refreshBudgets()

    final displayedBudgets = _selectedProjectFilter == null
        ? allBudgets
        : allBudgets
            .where((b) => b.projectName == _selectedProjectFilter)
            .toList();

    final totalBudget = displayedBudgets.fold<double>(
      0.0,
      (sum, b) => sum + b.budgetAmount,
    );

    // Expenses (use already filtered list from outer scope)
    final totalActual = _totalActualFor(byProject);

    final netVariance = totalBudget - totalActual;

    return Row(
      children: [
        Expanded(
          child: _StatTile(
            label: "TOTAL BUDGET",
            value: _peso(totalBudget),
            footer: _selectedProjectFilter ?? "Across all projects",
            footerColor: Colors.grey[600]!,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _StatTile(
            label: "NET VARIANCE",
            value: "${netVariance < 0 ? '-' : ''}${_peso(netVariance.abs())}",
            footer: netVariance < 0 ? "Over budget" : "Under budget",
            footerColor: netVariance < 0 ? kOverRed : kUnderGreen,
            valueColor: netVariance < 0 ? kOverRed : kUnderGreen,
          ),
        ),
      ],
    );
  },
),
const SizedBox(height: 16),
                if (_selectedTab == 0) ...[
                  // ---- Period filter tabs ---- (unchanged)
                  Row(
                    children: _periods.map((p) {
                      final selected = p == _selectedPeriod;
                      return Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: GestureDetector(
                            onTap: () => setState(() => _selectedPeriod = p),
                            child: Container(
                              alignment: Alignment.center,
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              decoration: BoxDecoration(
                                color: selected ? kDarkPill : Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                  color: selected ? kDarkPill : Colors.grey[300]!,
                                ),
                              ),
                              child: Text(
                                p,
                                style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: selected ? Colors.white : Colors.grey[600],
                                ),
                              ),
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),

                  // ---- Search + project filter ---- (unchanged)
                  Row(
                    children: [
                      Expanded(
                        child: Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: Colors.grey[200]!),
                          ),
                          child: TextField(
                            controller: _searchController,
                            style: const TextStyle(fontSize: 14),
                            decoration: InputDecoration(
                              hintText: "Search expenses or remarks...",
                              hintStyle:
                                  TextStyle(color: Colors.grey[400], fontSize: 13.5),
                              prefixIcon:
                                  Icon(Icons.search, color: Colors.grey[400], size: 20),
                              border: InputBorder.none,
                              contentPadding: const EdgeInsets.symmetric(vertical: 14),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Container(
                        height: 48,
                        constraints: const BoxConstraints(minWidth: 150),
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: Colors.grey[200]!),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String?>(
                            value: _selectedProjectFilter,
                            isDense: true,
                            icon: Icon(Icons.tune, color: Colors.grey[700], size: 18),
                            hint: const Text("All Projects",
                                style: TextStyle(fontSize: 13, color: Colors.black87)),
                            items: [
                              const DropdownMenuItem<String?>(
                                value: null,
                                child: Text("All Projects", style: TextStyle(fontSize: 13)),
                              ),
                              ...kProjects.map(
                                (p) => DropdownMenuItem<String?>(
                                  value: p,
                                  child: Text(p, style: const TextStyle(fontSize: 13)),
                                ),
                              ),
                            ],
                            onChanged: (v) => setState(() => _selectedProjectFilter = v),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),

                  Text(
                    isLoading
                        ? "LOADING EXPENSES..."
                        : filteredStockIns.isEmpty
                            ? "${filteredExpenses.length} EXPENSES"
                            : "${filteredExpenses.length} EXPENSES · ${filteredStockIns.length} STOCK-IN TO LOG",
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey[600],
                      letterSpacing: .2,
                    ),
                  ),
                  const SizedBox(height: 10),

                  if (isLoading)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 48),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (hasError)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 32),
                      child: Column(
                        children: [
                          Text(
                            snapshot.error.toString().replaceFirst('Exception: ', ''),
                            textAlign: TextAlign.center,
                            style: TextStyle(color: Colors.grey[600]),
                          ),
                          const SizedBox(height: 12),
                          OutlinedButton(
                            onPressed: _refreshExpenses,
                            child: const Text("Retry"),
                          ),
                        ],
                      ),
                    )
                  else if (feedItems.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 32),
                      child: Center(
                        child: Text(
                          allExpenses.isEmpty && allStockIns.isEmpty
                              ? "No expenses yet. Tap \"Add Expense\" to add one."
                              : "Nothing matches your search.",
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                      ),
                    )
                  else
                    ...feedItems.map((item) {
                      if (item is _ExpenseFeedItem) {
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _ExpenseCard(
                            entry: item.entry,
                            onTap: () => _openExpenseDetails(item.entry),
                          ),
                        );
                      }
                      final stockIn = (item as _StockInFeedItem).txn;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _StockInExpenseCard(
                          txn: stockIn,
                          onTap: () => _openExpenseFromStockIn(stockIn),
                        ),
                      );
                    }),
                ] else ...[
                  // ---- Budgets tab content ----
                  _buildBudgetsSection(),
                ],
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: AppBottomNavBar(currentIndex: 2, email: widget.email),
    );
  }
}

class _StatTile extends StatelessWidget {
  final String label;
  final String value;
  final String footer;
  final Color footerColor;
  final Color? valueColor;

  const _StatTile({
    required this.label,
    required this.value,
    required this.footer,
    required this.footerColor,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey[200]!),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 9.5,
              fontWeight: FontWeight.w600,
              letterSpacing: .2,
              color: Colors.grey[500],
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: valueColor ?? Colors.black87,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            footer,
            style: TextStyle(
                fontSize: 10.5, fontWeight: FontWeight.w600, color: footerColor),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

/// Expense card — same shape as project_tracking_screen's _ProjectCard:
/// title/subtitle + tag chip up top, meta line + colored dot/label at the
/// bottom. Category takes the chip's role; amount takes the status dot's role.
/// The project subtitle only renders when an expense actually has one
/// (older records) — newly logged expenses no longer carry a project.
class _ExpenseCard extends StatelessWidget {
  final _ExpenseEntry entry;
  final VoidCallback onTap;

  const _ExpenseCard({required this.entry, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.grey[200]!),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        entry.description,
                        style: const TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.bold,
                          color: Colors.black87,
                        ),
                      ),
                      if (entry.project.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          entry.project,
                          style: TextStyle(fontSize: 12.5, color: Colors.grey[500]),
                        ),
                      ],
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: entry.tagBg,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    entry.category,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: entry.tagText,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        entry.dateLabel,
                        style: TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        entry.remarks.isEmpty ? "No remarks" : entry.remarks,
                        style: TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        margin: const EdgeInsets.only(right: 5),
                        decoration: BoxDecoration(
                          color: entry.tagText,
                          shape: BoxShape.circle,
                        ),
                      ),
                      Text(
                        _peso(entry.amount),
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: entry.tagText,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// A stock-IN transaction shown in the expense feed so the user can log
/// its matching expense. Category defaults to Material once opened.
/// Visually distinguished from a real expense card with a dashed-feel
/// amber border and a "NEEDS EXPENSE" chip instead of an amount.
class _StockInExpenseCard extends StatelessWidget {
  final _StockInTxn txn;
  final VoidCallback onTap;

  const _StockInExpenseCard({required this.txn, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _materialText.withValues(alpha: .35), width: 1.2),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        txn.itemName,
                        style: const TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.bold,
                          color: Colors.black87,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        "Stock in \u00b7 +${_formatQty(txn.quantity)} ${txn.unitName}",
                        style: TextStyle(fontSize: 12.5, color: Colors.grey[500]),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: _materialBg,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    "NEEDS EXPENSE",
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: _materialText,
                      letterSpacing: .2,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  txn.dateLabel,
                  style: TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                ),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: const [
                    Icon(Icons.add_circle_outline, size: 15, color: _materialText),
                    SizedBox(width: 4),
                    Text(
                      "Add Expense",
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: _materialText,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// A single row from budgets_tbl: project name, budget vs. actual,
/// variance, and a simple usage bar. Read-only for now — editing an
/// existing budget can be wired up the same way _EditExpenseModal was.
class _BudgetCard extends StatelessWidget {
  final _BudgetEntry entry;

  const _BudgetCard({required this.entry});

  @override
  Widget build(BuildContext context) {
    final over = entry.variance < 0;
    final barColor = over ? kOverRed : kUnderGreen;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey[200]!),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  entry.projectName.isEmpty ? "Unknown Project" : entry.projectName,
                  style: const TextStyle(
                    fontSize: 15.5,
                    fontWeight: FontWeight.bold,
                    color: Colors.black87,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: over
                      ? kOverRed.withValues(alpha: .12)
                      : kUnderGreen.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  over ? "Over budget" : "Under budget",
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: barColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _budgetStat("BUDGET", _peso(entry.budgetAmount)),
              _budgetStat("ACTUAL", _peso(entry.actualAmount)),
              _budgetStat(
                "VARIANCE",
                "${over ? '-' : ''}${_peso(entry.variance.abs())}",
                color: barColor,
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: entry.percentUsed > 1 ? 1 : entry.percentUsed,
              minHeight: 6,
              backgroundColor: Colors.grey[200],
              valueColor: AlwaysStoppedAnimation(barColor),
            ),
          ),
        ],
      ),
    );
  }

  Widget _budgetStat(String label, String value, {Color? color}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: TextStyle(
                fontSize: 10, color: Colors.grey[500], fontWeight: FontWeight.w600)),
        const SizedBox(height: 2),
        Text(value,
            style: TextStyle(
                fontSize: 13.5, fontWeight: FontWeight.w700, color: color ?? Colors.black87)),
      ],
    );
  }
}

/// ---------------------------------------------------------------------
/// Add Expense modal — same chrome as project_tracking_screen's modals:
/// transparent Dialog, white rounded-24 container, bold title + close icon,
/// bordered fields, footer with OutlinedButton Cancel + filled submit button.
///
/// No project selection anymore — expenses are logged independently of
/// any specific project. Optionally opened pre-filled (from a stock-IN
/// transaction): description, category (matched by name), date, and
/// remarks can be seeded via the `initial*` params. When opened from a
/// stock-in row, `stockInTransactionId` is threaded through and silently
/// embedded into the submitted remarks so that transaction can be
/// recognized as "already logged" afterward.
/// ---------------------------------------------------------------------
class _AddExpenseModal extends StatefulWidget {
  final String? initialDescription;
  final String? initialCategoryName;
  final DateTime? initialDate;
  final String? initialRemarks;
  final int? stockInTransactionId;

  const _AddExpenseModal({
    this.initialDescription,
    this.initialCategoryName,
    this.initialDate,
    this.initialRemarks,
    this.stockInTransactionId,
  });

  @override
  State<_AddExpenseModal> createState() => _AddExpenseModalState();
}

class _AddExpenseModalState extends State<_AddExpenseModal> {
  final TextEditingController _descController = TextEditingController();
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _remarksController = TextEditingController();

  _DropdownOption? _category;
  DateTime? _date;

  late Future<List<_DropdownOption>> _categoriesFuture;

  @override
  void initState() {
    super.initState();
    if (widget.initialDescription != null) {
      _descController.text = widget.initialDescription!;
    }
    if (widget.initialRemarks != null) {
      _remarksController.text = widget.initialRemarks!;
    }
    _date = widget.initialDate;
    _categoriesFuture = _loadCategories();
  }

  Future<List<_DropdownOption>> _loadCategories() async {
    final raw = await FinanceService.getExpenseCategories();

    final categories = raw
        .map((e) => _DropdownOption(
              id: e['expense_category_id'] is int
                  ? e['expense_category_id'] as int
                  : int.tryParse(e['expense_category_id']?.toString() ?? '') ?? 0,
              name: (e['category_name'] ?? '').toString(),
            ))
        .toList();

    // Auto-select the category matching initialCategoryName (e.g. "Material"
    // when opened from a stock-in transaction), if it exists among the
    // fetched categories.
    if (mounted && widget.initialCategoryName != null && _category == null) {
      final match = categories.where(
        (c) => c.name.trim().toLowerCase() == widget.initialCategoryName!.trim().toLowerCase(),
      );
      if (match.isNotEmpty) {
        _category = match.first;
      }
    }

    return categories;
  }

  void _retryLoadCategories() {
    setState(() {
      _categoriesFuture = _loadCategories();
    });
  }

  @override
  void dispose() {
    _descController.dispose();
    _amountController.dispose();
    _remarksController.dispose();
    super.dispose();
  }

  bool get _isValid {
    final amount = double.tryParse(_amountController.text.trim());
    return _category != null &&
        _descController.text.trim().isNotEmpty &&
        amount != null &&
        amount > 0 &&
        _date != null;
  }

  Future<void> _pickDate() async {
    final today = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? today,
      firstDate: DateTime(2015),
      lastDate: DateTime(today.year + 2, 12, 31),
    );
    if (picked != null) {
      setState(() => _date = picked);
    }
  }

  bool _isSaving = false;

  Future<void> _submit() async {
    if (!_isValid || _isSaving) return;

    setState(() => _isSaving = true);

    try {
      // Tag the remarks with the source transaction id, invisibly — the
      // visible remarks field itself stays exactly what the user typed.
      final remarksText = _remarksController.text.trim();
      final finalRemarks = widget.stockInTransactionId != null
          ? '$remarksText${remarksText.isEmpty ? '' : ' '}[txn#${widget.stockInTransactionId}]'
          : remarksText;

      final result = await FinanceService.createExpense(
        expenseCategoryId: _category!.id,
        description: _descController.text.trim(),
        amount: double.parse(_amountController.text.trim()),
        date: _date!,
        remarks: finalRemarks,
      );

      if (!mounted) return;
      Navigator.of(context).pop(_ExpenseEntry.fromJson(result));
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      child: Container(
        constraints: const BoxConstraints(maxWidth: 460, maxHeight: 680),
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 24),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Add Expense",
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                ),
                InkWell(
                  onTap: () => Navigator.of(context).pop(),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),
            if (widget.initialDescription != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: _materialBg,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    "Logging expense for stock-in: ${widget.initialDescription}",
                    style: const TextStyle(
                      fontSize: 12.5,
                      color: _materialText,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 20),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _inputLabel("Expense Description *"),
                    const SizedBox(height: 8),
                    _input(controller: _descController, hint: "e.g. Salary"),
                    const SizedBox(height: 16),
                    _inputLabel("Category *"),
                    const SizedBox(height: 8),
                    _categoryField(),
                    const SizedBox(height: 16),
                    _inputLabel("Amount *"),
                    const SizedBox(height: 8),
                    _input(
                      controller: _amountController,
                      hint: "0.00",
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      inputFormatters: [_NonNegativeAmountFormatter()],
                    ),
                    const SizedBox(height: 16),
                    _inputLabel("Date *"),
                    const SizedBox(height: 8),
                    _dateField(date: _date, onTap: _pickDate),
                    const SizedBox(height: 16),
                    _inputLabel("Remarks"),
                    const SizedBox(height: 8),
                    _input(
                        controller: _remarksController,
                        hint: "Additional notes..."),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: OutlinedButton.styleFrom(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text("Cancel",
                      style: TextStyle(fontSize: 16, color: Colors.black54)),
                ),
                ElevatedButton(
                  onPressed: (_isValid && !_isSaving) ? _submit : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kAmberStrong,
                    foregroundColor: Colors.black87,
                    disabledBackgroundColor: Colors.grey[300],
                    padding:
                        const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.black87),
                          ),
                        )
                      : const Text("Add Expense", style: TextStyle(fontSize: 16)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ---------------- async dropdown field ----------------

  Widget _categoryField() {
    return FutureBuilder<List<_DropdownOption>>(
      future: _categoriesFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _loadingField("Loading categories...");
        }
        if (snapshot.hasError) {
          return _errorField("Couldn't load categories");
        }
        final categories = snapshot.data!;
        return _optionDropdown(
          value: _category,
          hint: categories.isEmpty
              ? "No categories available"
              : "Select Category...",
          items: categories,
          onChanged: (v) => setState(() => _category = v),
        );
      },
    );
  }

  Widget _loadingField(String label) {
    return Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(color: Colors.grey[500])),
        ],
      ),
    );
  }

  Widget _errorField(String message) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        border: Border.all(color: kOverRed.withValues(alpha: .4)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(message, style: const TextStyle(color: kOverRed, fontSize: 13)),
          ),
          TextButton(
            onPressed: _retryLoadCategories,
            child: const Text("Retry"),
          ),
        ],
      ),
    );
  }

  Widget _optionDropdown({
    required _DropdownOption? value,
    required String hint,
    required List<_DropdownOption> items,
    required ValueChanged<_DropdownOption?> onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(10),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<_DropdownOption>(
          value: value,
          isExpanded: true,
          hint: Text(hint, style: TextStyle(color: Colors.grey[400])),
          items: items
              .map((e) => DropdownMenuItem(value: e, child: Text(e.name)))
              .toList(),
          onChanged: items.isEmpty ? null : onChanged,
        ),
      ),
    );
  }

  // ---------------- shared small widgets (mirrors project_tracking_screen) ----------------

  Widget _inputLabel(String text) {
    return Text(text,
        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500));
  }

  Widget _input({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      onChanged: (_) => setState(() {}),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400]),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
      ),
    );
  }

  Widget _dateField({required DateTime? date, required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey[300]!),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              date == null ? "mm/dd/yy" : _fmtDateField(date),
              style:
                  TextStyle(color: date == null ? Colors.grey[400] : Colors.black87),
            ),
            const Icon(Icons.calendar_month, size: 20),
          ],
        ),
      ),
    );
  }
}

/// ---------------------------------------------------------------------
/// Expense details modal — shown when a card is tapped. Pops with:
///   'edit'    -> the caller should open _EditExpenseModal
///   'deleted' -> the caller should refresh the list
///   null      -> nothing changed (closed / cancelled)
/// ---------------------------------------------------------------------
class _ExpenseDetailsModal extends StatefulWidget {
  final _ExpenseEntry expense;

  const _ExpenseDetailsModal({required this.expense});

  @override
  State<_ExpenseDetailsModal> createState() => _ExpenseDetailsModalState();
}

/// ---------------------------------------------------------------------
/// Edit expense modal — pre-filled from the tapped card's data. No
/// project selection anymore — expenses aren't tied to a project. Same
/// dropdown-loading pattern as _AddExpenseModal.
/// ---------------------------------------------------------------------
class _EditExpenseModal extends StatefulWidget {
  final _ExpenseEntry expense;

  const _EditExpenseModal({required this.expense});

  @override
  State<_EditExpenseModal> createState() => _EditExpenseModalState();
}

class _EditExpenseModalState extends State<_EditExpenseModal> {
  late final TextEditingController _descController;
  late final TextEditingController _amountController;
  late final TextEditingController _remarksController;

  _DropdownOption? _category;
  DateTime? _date;

  late Future<List<_DropdownOption>> _categoriesFuture;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    final e = widget.expense;
    _descController = TextEditingController(text: e.description);
    _amountController = TextEditingController(text: e.amount.toStringAsFixed(2));
    _remarksController = TextEditingController(text: e.remarks);
    _date = e.date;
    _categoriesFuture = _loadCategories();
  }

  Future<List<_DropdownOption>> _loadCategories() async {
    final raw = await FinanceService.getExpenseCategories();

    final categories = raw
        .map((e) => _DropdownOption(
              id: e['expense_category_id'] is int
                  ? e['expense_category_id'] as int
                  : int.tryParse(e['expense_category_id']?.toString() ?? '') ?? 0,
              name: (e['category_name'] ?? '').toString(),
            ))
        .toList();

    // Pre-select the current category once the list arrives.
    if (mounted) {
      _category =
          categories.where((c) => c.id == widget.expense.categoryId).firstOrNull;
    }

    return categories;
  }

  void _retryLoadCategories() {
    setState(() {
      _categoriesFuture = _loadCategories();
    });
  }

  @override
  void dispose() {
    _descController.dispose();
    _amountController.dispose();
    _remarksController.dispose();
    super.dispose();
  }

  bool get _isValid {
    final amount = double.tryParse(_amountController.text.trim());
    return _category != null &&
        _descController.text.trim().isNotEmpty &&
        amount != null &&
        amount > 0 &&
        _date != null;
  }

  Future<void> _pickDate() async {
    final today = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? today,
      firstDate: DateTime(2015),
      lastDate: DateTime(today.year + 2, 12, 31),
    );
    if (picked != null) {
      setState(() => _date = picked);
    }
  }

  Future<void> _save() async {
    if (!_isValid || _isSaving) return;

    setState(() => _isSaving = true);

    try {
      await FinanceService.updateExpense(
        expenseId: widget.expense.id,
        expenseCategoryId: _category!.id,
        description: _descController.text.trim(),
        amount: double.parse(_amountController.text.trim()),
        date: _date!,
        remarks: _remarksController.text.trim(),
      );

      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      child: Container(
        constraints: const BoxConstraints(maxWidth: 460, maxHeight: 680),
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 24),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Edit Expense",
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                ),
                InkWell(
                  onTap: () => Navigator.of(context).pop(),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),
            const SizedBox(height: 20),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _inputLabel("Expense Description *"),
                    const SizedBox(height: 8),
                    _input(controller: _descController, hint: "e.g. Salary"),
                    const SizedBox(height: 16),
                    _inputLabel("Category *"),
                    const SizedBox(height: 8),
                    _categoryField(),
                    const SizedBox(height: 16),
                    _inputLabel("Amount *"),
                    const SizedBox(height: 8),
                    _input(
                      controller: _amountController,
                      hint: "0.00",
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      inputFormatters: [_NonNegativeAmountFormatter()],
                    ),
                    const SizedBox(height: 16),
                    _inputLabel("Date *"),
                    const SizedBox(height: 8),
                    _dateField(date: _date, onTap: _pickDate),
                    const SizedBox(height: 16),
                    _inputLabel("Remarks"),
                    const SizedBox(height: 8),
                    _input(
                        controller: _remarksController,
                        hint: "Additional notes..."),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: OutlinedButton.styleFrom(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text("Cancel",
                      style: TextStyle(fontSize: 16, color: Colors.black54)),
                ),
                ElevatedButton(
                  onPressed: (_isValid && !_isSaving) ? _save : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kDarkPill,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.grey[300],
                    padding:
                        const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Text("Save changes", style: TextStyle(fontSize: 16)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ---------------- async dropdown field ----------------

  Widget _categoryField() {
    return FutureBuilder<List<_DropdownOption>>(
      future: _categoriesFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _loadingField("Loading categories...");
        }
        if (snapshot.hasError) {
          return _errorField("Couldn't load categories");
        }
        final categories = snapshot.data!;
        return _optionDropdown(
          value: _category,
          hint: categories.isEmpty
              ? "No categories available"
              : "Select Category...",
          items: categories,
          onChanged: (v) => setState(() => _category = v),
        );
      },
    );
  }

  Widget _loadingField(String label) {
    return Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(color: Colors.grey[500])),
        ],
      ),
    );
  }

  Widget _errorField(String message) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        border: Border.all(color: kOverRed.withValues(alpha: .4)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(message, style: const TextStyle(color: kOverRed, fontSize: 13)),
          ),
          TextButton(
            onPressed: _retryLoadCategories,
            child: const Text("Retry"),
          ),
        ],
      ),
    );
  }

  Widget _optionDropdown({
    required _DropdownOption? value,
    required String hint,
    required List<_DropdownOption> items,
    required ValueChanged<_DropdownOption?> onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(10),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<_DropdownOption>(
          value: value,
          isExpanded: true,
          hint: Text(hint, style: TextStyle(color: Colors.grey[400])),
          items: items
              .map((e) => DropdownMenuItem(value: e, child: Text(e.name)))
              .toList(),
          onChanged: items.isEmpty ? null : onChanged,
        ),
      ),
    );
  }

  // ---------------- shared small widgets ----------------

  Widget _inputLabel(String text) {
    return Text(text,
        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500));
  }

  Widget _input({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      onChanged: (_) => setState(() {}),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400]),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
      ),
    );
  }

  Widget _dateField({required DateTime? date, required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey[300]!),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              date == null ? "mm/dd/yy" : _fmtDateField(date),
              style:
                  TextStyle(color: date == null ? Colors.grey[400] : Colors.black87),
            ),
            const Icon(Icons.calendar_month, size: 20),
          ],
        ),
      ),
    );
  }
}

class _ExpenseDetailsModalState extends State<_ExpenseDetailsModal> {
  bool _isDeleting = false;

  Future<void> _confirmDelete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("Delete expense?"),
        content: Text(
          "This will permanently delete \"${widget.expense.description}\". This action cannot be undone.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text("Cancel"),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: kOverRed),
            child: const Text("Delete"),
          ),
        ],
      ),
    );

    if (confirmed != true) return;
    if (!mounted) return;

    setState(() => _isDeleting = true);

    try {
      await FinanceService.deleteExpense(widget.expense.id);
      if (!mounted) return;
      Navigator.of(context).pop('deleted');
    } catch (e) {
      if (!mounted) return;
      setState(() => _isDeleting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final expense = widget.expense;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HEADER
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        expense.description,
                        style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                      ),
                      if (expense.project.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          expense.project,
                          style: TextStyle(fontSize: 15, color: Colors.grey[600]),
                        ),
                      ],
                      const SizedBox(height: 8),
                      Container(
                        padding:
                            const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: expense.tagBg,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          expense.category,
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: expense.tagText,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                InkWell(
                  onTap: () => Navigator.pop(context),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),

            const SizedBox(height: 22),

            // AMOUNT
            Text(
              "AMOUNT",
              style: TextStyle(fontSize: 13, color: Colors.grey[600]),
            ),
            const SizedBox(height: 6),
            Text(
              _peso(expense.amount),
              style: TextStyle(
                fontSize: 26,
                fontWeight: FontWeight.bold,
                color: expense.tagText,
              ),
            ),

            const SizedBox(height: 22),

            // DETAILS
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                border: Border.all(color: Colors.grey[200]!),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _detailRow("Date", expense.dateLabel),
                  _detailRow(
                      "Remarks", expense.remarks.isEmpty ? "No remarks" : expense.remarks),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // ACTIONS
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _isDeleting ? null : _confirmDelete,
                    icon: _isDeleting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation(kOverRed),
                            ),
                          )
                        : const Icon(Icons.delete_outline, size: 18, color: kOverRed),
                    label: Text(
                      _isDeleting ? "Deleting..." : "Delete",
                      style: const TextStyle(color: kOverRed),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: kOverRed),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _isDeleting ? null : () => Navigator.pop(context, 'edit'),
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: const Text("Edit"),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: kDarkPill,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 14)),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

/// ---------------------------------------------------------------------
/// Add Budget modal — same chrome as _AddExpenseModal, simpler form.
/// Budgets are still per-project, so this modal is unchanged.
/// ---------------------------------------------------------------------
class _AddBudgetModal extends StatefulWidget {
  const _AddBudgetModal();

  @override
  State<_AddBudgetModal> createState() => _AddBudgetModalState();
}

class _AddBudgetModalState extends State<_AddBudgetModal> {
  final TextEditingController _amountController = TextEditingController();
  _DropdownOption? _project;
  bool _isSaving = false;

  late Future<List<_DropdownOption>> _projectsFuture;

  @override
  void initState() {
    super.initState();
    _projectsFuture = _loadProjects();
  }

  Future<List<_DropdownOption>> _loadProjects() async {
    final raw = await FinanceService.getProjects();
    return raw
        .map((e) => _DropdownOption(
              id: e['project_id'] is int
                  ? e['project_id'] as int
                  : int.tryParse(e['project_id']?.toString() ?? '') ?? 0,
              name: (e['project_name'] ?? '').toString(),
            ))
        .toList();
  }

  void _retryLoadProjects() {
    setState(() {
      _projectsFuture = _loadProjects();
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  bool get _isValid {
    final amount = double.tryParse(_amountController.text.trim());
    return _project != null && amount != null && amount > 0;
  }

  Future<void> _submit() async {
    if (!_isValid || _isSaving) return;

    setState(() => _isSaving = true);

    try {
      final result = await FinanceService.createBudget(
        projectId: _project!.id,
        amount: double.parse(_amountController.text.trim()),
      );

      if (!mounted) return;
      final entry = MapEntry(
        (result['project_name'] ?? _project!.name).toString(),
        double.tryParse(result['budget_amount']?.toString() ?? '') ??
            double.parse(_amountController.text.trim()),
      );
      Navigator.of(context).pop(entry); // pop with result, no stacked dialogs
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),
      child: Container(
        constraints: const BoxConstraints(maxWidth: 460),
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Add Budget",
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                ),
                InkWell(
                  onTap: () => Navigator.of(context).pop(),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),
            const SizedBox(height: 20),
            const Text("Project *",
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
            const SizedBox(height: 8),
            _projectField(),
            const SizedBox(height: 16),
            const Text("Budget Amount *",
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
            const SizedBox(height: 8),
            TextField(
              controller: _amountController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [_NonNegativeAmountFormatter()],
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: "0.00",
                hintStyle: TextStyle(color: Colors.grey[400]),
                filled: true,
                fillColor: Colors.white,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: Colors.grey[300]!),
                ),
              ),
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: OutlinedButton.styleFrom(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text("Cancel",
                      style: TextStyle(fontSize: 16, color: Colors.black54)),
                ),
                ElevatedButton(
                  onPressed: (_isValid && !_isSaving) ? _submit : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kAmberStrong,
                    foregroundColor: Colors.black87,
                    disabledBackgroundColor: Colors.grey[300],
                    padding:
                        const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.black87),
                          ),
                        )
                      : const Text("Add Budget", style: TextStyle(fontSize: 16)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _projectField() {
    return FutureBuilder<List<_DropdownOption>>(
      future: _projectsFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return _loadingField("Loading projects...");
        }
        if (snapshot.hasError) {
          return _errorField("Couldn't load projects");
        }
        final projects = snapshot.data!;
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.grey[300]!),
            borderRadius: BorderRadius.circular(10),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<_DropdownOption>(
              value: _project,
              isExpanded: true,
              hint: Text(
                projects.isEmpty ? "No projects available" : "Select Project...",
                style: TextStyle(color: Colors.grey[400]),
              ),
              items: projects
                  .map((p) => DropdownMenuItem(value: p, child: Text(p.name)))
                  .toList(),
              onChanged: projects.isEmpty
                  ? null
                  : (v) => setState(() => _project = v),
            ),
          ),
        );
      },
    );
  }

  Widget _loadingField(String label) {
    return Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(color: Colors.grey[500])),
        ],
      ),
    );
  }

  Widget _errorField(String message) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        border: Border.all(color: kOverRed.withValues(alpha: .4)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(message, style: const TextStyle(color: kOverRed, fontSize: 13)),
          ),
          TextButton(
            onPressed: _retryLoadProjects,
            child: const Text("Retry"),
          ),
        ],
      ),
    );
  }
}