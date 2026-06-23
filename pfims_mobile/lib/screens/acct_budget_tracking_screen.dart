import 'package:flutter/material.dart';
import '../widgets/acct_bottom_nav_bar.dart';
import '../widgets/app_header.dart';

const Color kDarkPill = Color(0xFF14161F);
const Color kAmberStrong = Color(0xFFF0B94A);
const Color kAmberLight = Color(0xFFFBEFD6);
const Color kUnderGreen = Color(0xFF1F9254);
const Color kUnderGreenBg = Color(0xFFE1F6E8);
const Color kOverRed = Color(0xFFE5483B);
const Color kOverRedBg = Color(0xFFFCE6E3);
const Color kLaborBg = Color(0xFFE3EEFC);
const Color kLaborText = Color(0xFF2F6FE4);
const Color kMaterialBg = Color(0xFFFCEAD9);
const Color kMaterialText = Color(0xFFD97B3F);
const Color kNavyText = Color(0xFF1F2A44);

/// Minimal peso formatter so we don't need to pull in `intl` just for
/// comma-separated currency. Swap for NumberFormat if you add intl later.
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

/// ---------------------------------------------------------------------
/// SAMPLE DATA — hard-coded for the UI build-out. Replace with real
/// budget records once the backend is connected.
/// ---------------------------------------------------------------------
class _ExpenseItem {
  final String name;
  final String tag; // "Labor" / "Material"
  final String meta; // "150 hrs @ ₱10  05/13/24"
  final double budget;
  final double actual;

  const _ExpenseItem({
    required this.name,
    required this.tag,
    required this.meta,
    required this.budget,
    required this.actual,
  });

  bool get isOver => actual > budget;
  double get diff => (budget - actual).abs();
  double get ratio =>
      budget == 0 ? 0 : (actual / budget).clamp(0, 1.0).toDouble();
}

const List<_ExpenseItem> _expenses = [
  _ExpenseItem(
    name: "Worker Salaries",
    tag: "Labor",
    meta: "150 hrs @ ₱10   05/13/24",
    budget: 1700,
    actual: 1450,
  ),
  _ExpenseItem(
    name: "Machine Operation",
    tag: "Labor",
    meta: "200 hrs @ ₱15   05/13/24",
    budget: 3000,
    actual: 2900,
  ),
  _ExpenseItem(
    name: "Raw Materials (Steel)",
    tag: "Material",
    meta: "₱500 x 5   05/13/24",
    budget: 2500,
    actual: 2400,
  ),
  _ExpenseItem(
    name: "Raw Materials (Packaging)",
    tag: "Material",
    meta: "₱300 x 2   05/13/24",
    budget: 600,
    actual: 650,
  ),
  _ExpenseItem(
    name: "Office Staff Wages",
    tag: "Labor",
    meta: "180 hrs @ ₱8   05/13/24",
    budget: 1440,
    actual: 1400,
  ),
];

const double _totalBudget = 9240;
const double _totalActual = 8800;
const double _currentBudget = 67000000;

const String _companyName = "mlem company";
const String _companyAddress = "eheheheheh";
const String _companyPhone = "09123456789";

/// ---------------------------------------------------------------------

class AcctBudgetTrackingScreen extends StatefulWidget {
  const AcctBudgetTrackingScreen({super.key});

  @override
  State<AcctBudgetTrackingScreen> createState() =>
      _AcctBudgetTrackingScreenState();
}

class _AcctBudgetTrackingScreenState extends State<AcctBudgetTrackingScreen> {
  String _selectedPeriod = "Monthly";
  bool _categoriesExpanded = true;

  static const List<String> _periods = ["Daily", "Weekly", "Monthly", "Yearly"];

  @override
  Widget build(BuildContext context) {
    final netVariance = _totalBudget - _totalActual;

    return Scaffold(
      backgroundColor: const Color(0xFFF2F3F5),
      appBar: const AppHeader(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          // ---- Title + Add Expense ----
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "BUDGET & FINANCE",
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      letterSpacing: .2,
                      color: Colors.black87,
                    ),
                  ),
                  SizedBox(height: 2),
                  Text(
                    "construction operation overview",
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: () {
                  // TODO: hook up to "add expense" flow.
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: kDarkPill,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 10),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(24),
                  ),
                ),
                icon: const Icon(Icons.add, size: 16),
                label: const Text(
                  "Add Expense",
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // ---- Current budget banner + totals ----
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: .04),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                Container(
                  width: double.infinity,
                  color: kAmberStrong,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        "CURRENT BUDGET",
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          letterSpacing: .3,
                          color: kNavyText,
                        ),
                      ),
                      Text(
                        _peso(_currentBudget),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: kNavyText,
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 16, vertical: 14),
                  child: Row(
                    children: [
                      Expanded(
                        child: _BudgetStat(
                          label: "TOTAL BUDGET",
                          value: _peso(_totalBudget),
                        ),
                      ),
                      Expanded(
                        child: _BudgetStat(
                          label: "TOTAL ACTUAL",
                          value: _peso(_totalActual),
                        ),
                      ),
                      Expanded(
                        child: _BudgetStat(
                          label: "NET VARIANCE",
                          value:
                              "${netVariance < 0 ? '-' : ''}${_peso(netVariance.abs())}",
                          valueColor:
                              netVariance < 0 ? kOverRed : kUnderGreen,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // ---- Company details ----
          Container(
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
                const Text(
                  "Company Details",
                  style: TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.bold,
                      color: Colors.black87),
                ),
                const SizedBox(height: 10),
                const _DetailRow(label: "Company Name", value: _companyName),
                const SizedBox(height: 8),
                const _DetailRow(label: "Address", value: _companyAddress),
                const SizedBox(height: 8),
                const _DetailRow(label: "Phone", value: _companyPhone),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // ---- Period filter tabs ----
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
          const SizedBox(height: 18),

          // ---- Expense breakdown header ----
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                "EXPENSE BREAKDOWN",
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  letterSpacing: .2,
                  color: Colors.black87,
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.grey[300]!),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      _selectedPeriod,
                      style: const TextStyle(fontSize: 12, color: Colors.black87),
                    ),
                    const SizedBox(width: 4),
                    Icon(Icons.keyboard_arrow_down,
                        size: 16, color: Colors.grey[600]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // ---- Categories card ----
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: .04),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                GestureDetector(
                  onTap: () =>
                      setState(() => _categoriesExpanded = !_categoriesExpanded),
                  child: Container(
                    width: double.infinity,
                    color: kAmberLight,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 14),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          "Categories",
                          style: TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.bold,
                            color: Colors.black87,
                          ),
                        ),
                        Row(
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(
                                  "Budget / Actual",
                                  style: TextStyle(
                                      fontSize: 10, color: Colors.grey[600]),
                                ),
                                Text(
                                  "${_peso(_totalBudget)} / ${_peso(_totalActual)}",
                                  style: const TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.black87,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(width: 8),
                            Icon(
                              _categoriesExpanded
                                  ? Icons.keyboard_arrow_up
                                  : Icons.keyboard_arrow_down,
                              color: Colors.grey[700],
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                if (_categoriesExpanded)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      children: [
                        for (final e in _expenses)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            child: _ExpenseRow(item: e),
                          ),
                        const Divider(height: 1),
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                "Total",
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.black87,
                                ),
                              ),
                              Row(
                                children: [
                                  Text("Budget ${_peso(_totalBudget)}    ",
                                      style: TextStyle(
                                          fontSize: 12.5,
                                          color: Colors.grey[600])),
                                  Text(
                                    "Actual ${_peso(_totalActual)}",
                                    style: const TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w600,
                                      color: Colors.black87,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: const AcctBottomNavBar(currentIndex: 1),
    );
  }
}

class _BudgetStat extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;
  const _BudgetStat({required this.label, required this.value, this.valueColor});

  @override
  Widget build(BuildContext context) {
    return Column(
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
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.bold,
            color: valueColor ?? Colors.black87,
          ),
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  const _DetailRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(fontSize: 12.5, color: Colors.grey[600])),
        Text(
          value,
          style: const TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
            color: kNavyText,
          ),
        ),
      ],
    );
  }
}

class _ExpenseRow extends StatelessWidget {
  final _ExpenseItem item;
  const _ExpenseRow({required this.item});

  @override
  Widget build(BuildContext context) {
    final tagBg = item.tag == "Labor" ? kLaborBg : kMaterialBg;
    final tagText = item.tag == "Labor" ? kLaborText : kMaterialText;
    final statusBg = item.isOver ? kOverRedBg : kUnderGreenBg;
    final statusText = item.isOver ? kOverRed : kUnderGreen;
    final statusLabel = item.isOver
        ? "+${_peso(item.diff)} over"
        : "${_peso(item.diff)} under";

    return Column(
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
                    item.name,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: Colors.black87,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: tagBg,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          item.tag,
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w600,
                            color: tagText,
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          item.meta,
                          style:
                              TextStyle(fontSize: 11, color: Colors.grey[500]),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: statusBg,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                statusLabel,
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w600,
                  color: statusText,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text("Budget: ${_peso(item.budget)}",
                style: TextStyle(fontSize: 11.5, color: Colors.grey[500])),
            Text("Actual: ${_peso(item.actual)}",
                style: TextStyle(fontSize: 11.5, color: Colors.grey[500])),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: item.isOver ? 1.0 : item.ratio,
            minHeight: 7,
            backgroundColor: Colors.grey[200],
            valueColor: AlwaysStoppedAnimation<Color>(
              item.isOver ? kOverRed : kUnderGreen,
            ),
          ),
        ),
      ],
    );
  }
}