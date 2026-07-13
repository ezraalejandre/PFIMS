import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../widgets/app_bottom_nav_bar.dart';
import '../widgets/app_header.dart';
import '../services/inventory_service.dart';

const Color kDarkPill = Color(0xFF14161F);
const int kLowStockThreshold = 20;

enum StockStatus { stockIn, stockOut }

class InventoryItem {
  final int? itemId;
  final int? transactionId;
  final String name;
  final String category;
  final String unit;
  final int stock; // running current_stock of the item at load time
  final double quantity; // amount moved by THIS specific transaction
  final StockStatus status;
  final DateTime date;
  final String? projectName;

  const InventoryItem({
    this.itemId,
    this.transactionId,
    required this.name,
    required this.category,
    required this.unit,
    required this.stock,
    required this.quantity,
    required this.status,
    required this.date,
    this.projectName,
  });

  factory InventoryItem.fromJson(Map<String, dynamic> json) {
    final typeStr = (json['transaction_type'] as String?)?.toUpperCase();
    final dateStr = json['transaction_date'] as String?;
    final rawStock = json['current_stock'];
    final rawQty = json['quantity'];

    return InventoryItem(
      itemId: json['item_id'] is int
          ? json['item_id'] as int
          : int.tryParse('${json['item_id']}'),
      transactionId: json['inventory_transaction_id'] is int
          ? json['inventory_transaction_id'] as int
          : int.tryParse('${json['inventory_transaction_id']}'),
      name: json['item_name'] as String? ?? '',
      category: json['inventory_category_name'] as String? ?? '-',
      unit: json['unit_name'] as String? ?? '-',
      stock: rawStock is int
          ? rawStock
          : (double.tryParse('$rawStock')?.round() ?? 0),
      quantity: rawQty is num
          ? rawQty.toDouble()
          : (double.tryParse('$rawQty') ?? 0),
      status: typeStr == 'OUT' ? StockStatus.stockOut : StockStatus.stockIn,
      date: dateStr != null
          ? (DateTime.tryParse(dateStr) ?? DateTime.now())
          : DateTime.now(),
      projectName: json['project_name'] as String?,
    );
  }
}

class ItemRecord {
  final int itemId;
  final String name;
  final int? categoryId;
  final String categoryName;
  final int? unitId;
  final String unitName;
  final int? supplierId;
  final String supplierName;
  final double currentStock;

  const ItemRecord({
    required this.itemId,
    required this.name,
    required this.categoryId,
    required this.categoryName,
    required this.unitId,
    required this.unitName,
    required this.supplierId,
    required this.supplierName,
    required this.currentStock,
  });
}

class Supplier {
  final int? supplierId;
  final String name;
  final int itemCount;
  final String phone;
  final String address;
  final bool isActive;

  const Supplier({
    this.supplierId,
    required this.name,
    required this.itemCount,
    required this.phone,
    required this.address,
    required this.isActive,
  });

  

  // Build a Supplier from the JSON returned by GET /suppliers.
  // NOTE: supplier_tbl has no is_active column, so every supplier loaded
  // from the API is treated as active. Add the column + wire it through
  // api.php and InventoryService if you want real active/inactive tracking.
  factory Supplier.fromJson(Map<String, dynamic> json) {
    final rawCount = json['item_count'];

    return Supplier(
      supplierId: json['supplier_id'] is int
          ? json['supplier_id'] as int
          : int.tryParse('${json['supplier_id']}'),
      name: json['supplier_name'] as String? ?? '',
      itemCount: rawCount is int
          ? rawCount
          : (int.tryParse('$rawCount') ?? 0),
      phone: json['contact_number'] as String? ?? '-',
      address: json['address'] as String? ?? '-',
      isActive: true,
    );
  }
}

class InventoryTrackingScreen extends StatefulWidget {
  final String email;

  const InventoryTrackingScreen({super.key, this.email = ''});

  @override
  State<InventoryTrackingScreen> createState() => _InventoryTrackingScreenState();
}

class _InventoryTrackingScreenState extends State<InventoryTrackingScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  int _currentTab = 0;

  List<InventoryItem> _items = [];
  bool _loadingItems = true;
  String? _itemsError;

  List<ItemRecord> _itemRecords = [];
  bool _loadingItemRecords = true;
  String? _itemRecordsError;
  

  List<Supplier> _suppliers = [];
  bool _loadingSuppliers = true;
  String? _suppliersError;

  @override
  void initState() {
    super.initState();

    _loadInventoryItems();
    _loadItemRecords();
    _loadSuppliers();

    _tabController = TabController(
      length: 3,
      vsync: this,
    );

    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        setState(() {
          _currentTab = _tabController.index;
        });
      }
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  // Loads every transaction row (one entry per stock movement) instead of
  // "latest transaction per item", so the Inventory tab shows full history.
  Future<void> _loadInventoryItems() async {
    setState(() {
      _loadingItems = true;
      _itemsError = null;
    });
    try {
      final data = await InventoryService.fetchTransactions();
      if (mounted) {
        final parsed = data.map((json) => InventoryItem.fromJson(json)).toList();
        // Newest transaction first. Sort by date, then by transactionId as
        // a tiebreaker for same-day entries (higher id = created later),
        // so the list stays correctly ordered even if the API's own
        // ordering changes.
        parsed.sort((a, b) {
          final dateCompare = b.date.compareTo(a.date);
          if (dateCompare != 0) return dateCompare;
          return (b.transactionId ?? 0).compareTo(a.transactionId ?? 0);
        });
        setState(() {
          _items = parsed;
          _loadingItems = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _itemsError = "Failed to load inventory. Pull to refresh.";
          _loadingItems = false;
        });
      }
    }
  }

  Future<void> _loadSuppliers() async {
    setState(() {
      _loadingSuppliers = true;
      _suppliersError = null;
    });
    try {
      final data = await InventoryService.fetchSuppliers();
      if (mounted) {
        setState(() {
          _suppliers = data.map((json) => Supplier.fromJson(json)).toList();
          _loadingSuppliers = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _suppliersError = "Failed to load suppliers. Pull to refresh.";
          _loadingSuppliers = false;
        });
      }
    }
  }
  Future<void> _loadItemRecords() async {
  setState(() {
    _loadingItemRecords = true;
    _itemRecordsError = null;
  });
  try {
    final results = await Future.wait([
      InventoryService.fetchItems(),
      InventoryService.fetchCategories(),
      InventoryService.fetchUnits(),
      InventoryService.fetchSuppliers(),
    ]);
    if (!mounted) return;

    final rawItems = results[0];
    final categoryNames = {
      for (final c in results[1]) c['inventory_category_id']: c['inventory_category_name'] as String? ?? '-'
    };
    final unitNames = {
      for (final u in results[2]) u['unit_id']: u['unit_name'] as String? ?? '-'
    };
    final supplierNames = {
      for (final s in results[3]) s['supplier_id']: s['supplier_name'] as String? ?? '-'
    };

    final parsed = rawItems.map((json) {
      final categoryId = json['inventory_category_id'] is int
          ? json['inventory_category_id'] as int
          : int.tryParse('${json['inventory_category_id']}');
      final unitId = json['unit_id'] is int
          ? json['unit_id'] as int
          : int.tryParse('${json['unit_id']}');
      final supplierId = json['supplier_id'] is int
          ? json['supplier_id'] as int
          : int.tryParse('${json['supplier_id']}');
      final rawStock = json['current_stock'];

      return ItemRecord(
        itemId: json['item_id'] is int ? json['item_id'] as int : int.tryParse('${json['item_id']}') ?? 0,
        name: json['item_name'] as String? ?? '',
        categoryId: categoryId,
        categoryName: categoryNames[categoryId] ?? '-',
        unitId: unitId,
        unitName: unitNames[unitId] ?? '-',
        supplierId: supplierId,
        supplierName: supplierNames[supplierId] ?? '-',
        currentStock: rawStock is num ? rawStock.toDouble() : (double.tryParse('$rawStock') ?? 0),
      );
    }).toList();

    parsed.sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));

    setState(() {
      _itemRecords = parsed;
      _loadingItemRecords = false;
    });
  } catch (e) {
    if (mounted) {
      setState(() {
        _itemRecordsError = "Failed to load items. Pull to refresh.";
        _loadingItemRecords = false;
      });
    }
  }
}

// After any create/edit/delete on the Items tab, refresh both this list
// AND the transaction-derived Inventory tab, since that tab also reads
// item.stock and would otherwise show stale values.
void _refreshAfterItemChange() {
  _loadItemRecords();
  _loadInventoryItems();
}

  // ---- KPI cards (driven by real data, not placeholders) ----

  // Total distinct items tracked, sourced from the Items tab data (the
  // item master list) rather than the transaction list, so it can never
  // double count an item with multiple transactions.
  int get _totalItemsCount => _itemRecords.length;

  // Items at or below the low-stock threshold, computed from the same
  // item master data the Items tab itself renders from, so this KPI and
  // the "Low Stock" pills on that tab always agree.
  int get _lowStockItemsCount =>
      _itemRecords.where((i) => i.currentStock < kLowStockThreshold).length;

  // Active suppliers on file.
  int get _totalSuppliersCount => _suppliers.length;

  // Stock movements recorded in the current calendar month, split by
  // direction so the card can show an IN/OUT breakdown at a glance.
  int get _movementsInThisMonth {
    final now = DateTime.now();
    return _items
        .where((t) =>
            t.status == StockStatus.stockIn &&
            t.date.year == now.year &&
            t.date.month == now.month)
        .length;
  }

  int get _movementsOutThisMonth {
    final now = DateTime.now();
    return _items
        .where((t) =>
            t.status == StockStatus.stockOut &&
            t.date.year == now.year &&
            t.date.month == now.month)
        .length;
  }

  void _goToTab(int index) {
    setState(() => _currentTab = index);
    _tabController.animateTo(index);
  }

  String _formatDate(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppHeader(email: widget.email),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'INVENTORY',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                                color: AppColors.dark,
                              ),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'construction operation overview',
                              style: TextStyle(fontSize: 12, color: Colors.grey),
                            ),
                          ],
                        ),
                      ),
                      // NEW: when on the Inventory tab, show "Add Item" beside
                      // "Add Transaction". When on the Suppliers tab, show only
                      // "Add Supplier" like before.
                      Wrap(
                        spacing: 8,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          if (_currentTab == 1) // Items tab
                            OutlinedButton.icon(
                              onPressed: () {
                                showDialog(
                                  context: context,
                                  barrierDismissible: false,
                                  builder: (_) => _AddItemModal(onSaved: _refreshAfterItemChange),
                                );
                              },
                              style: OutlinedButton.styleFrom(
                                foregroundColor: kDarkPill,
                                side: const BorderSide(color: kDarkPill),
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                              ),
                              icon: const Icon(Icons.inventory_2_outlined, size: 16),
                              label: const Text('Add Item', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                            )
                          else
                            ElevatedButton.icon(
                              onPressed: () {
                                if (_currentTab == 0) {
                                  showDialog(
                                    context: context,
                                    barrierDismissible: false,
                                    builder: (_) => _AddTransactionModal(onSaved: _loadInventoryItems),
                                  );
                                } else {
                                  // _currentTab == 2 (Suppliers)
                                  showDialog(
                                    context: context,
                                    barrierDismissible: false,
                                    builder: (_) => _AddSupplierModal(onSaved: _loadSuppliers),
                                  );
                                }
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: kDarkPill,
                                foregroundColor: Colors.white,
                                elevation: 0,
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                              ),
                              icon: const Icon(Icons.add, size: 16),
                              label: Text(
                                _currentTab == 0 ? 'Add Transaction' : 'Add Supplier',
                                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _StatsGrid(
                    totalItems: _totalItemsCount,
                    lowStock: _lowStockItemsCount,
                    totalSuppliers: _totalSuppliersCount,
                    movementsIn: _movementsInThisMonth,
                    movementsOut: _movementsOutThisMonth,
                    onSelectTab: _goToTab,
                  ),
                  const SizedBox(height: 14),
                  TabBar(
                    controller: _tabController,
                    labelColor: AppColors.orange,
                    unselectedLabelColor: Colors.black54,
                    indicatorColor: AppColors.orange,
                    indicatorSize: TabBarIndicatorSize.label,
                    labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                    unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                    tabs: const [
                      Tab(text: 'Inventory'),
                      Tab(text: 'Items'),      // NEW
                      Tab(text: 'Suppliers'),
                    ],
                  ),
                ],
              ),
            ),
           Expanded(
              child: TabBarView(
                controller: _tabController,
                children: [
                  // --- Inventory (transactions) — unchanged ---
                  _loadingItems
                      ? const Center(child: CircularProgressIndicator())
                      : _itemsError != null
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(_itemsError!),
                                  const SizedBox(height: 8),
                                  ElevatedButton(onPressed: _loadInventoryItems, child: const Text("Retry")),
                                ],
                              ),
                            )
                          : _InventoryList(
                              items: _items,
                              formatDate: _formatDate,
                              onTap: (item) {
                                showDialog(
                                  context: context,
                                  barrierDismissible: false,
                                  builder: (_) => _InventoryDetailsModal(item: item, onChanged: _loadInventoryItems),
                                );
                              },
                            ),

                  // --- NEW: Items (item master data) ---
                  _loadingItemRecords
                      ? const Center(child: CircularProgressIndicator())
                      : _itemRecordsError != null
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(_itemRecordsError!),
                                  const SizedBox(height: 8),
                                  ElevatedButton(onPressed: _loadItemRecords, child: const Text("Retry")),
                                ],
                              ),
                            )
                          : _ItemsList(
                              items: _itemRecords,
                              onTap: (item) {
                                showDialog(
                                  context: context,
                                  barrierDismissible: false,
                                  builder: (_) => _ItemDetailsModal(item: item, onChanged: _refreshAfterItemChange),
                                );
                              },
                            ),

                  // --- Suppliers — unchanged ---
                  _loadingSuppliers
                      ? const Center(child: CircularProgressIndicator())
                      : _suppliersError != null
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(_suppliersError!),
                                  const SizedBox(height: 8),
                                  ElevatedButton(onPressed: _loadSuppliers, child: const Text("Retry")),
                                ],
                              ),
                            )
                          : _SuppliersList(suppliers: _suppliers, onChanged: _loadSuppliers),
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: AppBottomNavBar(currentIndex: 3, email: widget.email),
    );
  }
}

// KPI cards laid out as a 2x2 grid (rather than a single cramped row) so
// four metrics fit comfortably on a phone-width screen. Each card is
// tappable and jumps to the tab that explains that number.
class _StatsGrid extends StatelessWidget {
  final int totalItems;
  final int lowStock;
  final int totalSuppliers;
  final int movementsIn;
  final int movementsOut;
  final ValueChanged<int> onSelectTab;

  const _StatsGrid({
    required this.totalItems,
    required this.lowStock,
    required this.totalSuppliers,
    required this.movementsIn,
    required this.movementsOut,
    required this.onSelectTab,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _StatCard(
                label: 'TOTAL ITEMS',
                value: '$totalItems',
                caption: 'Tracked in inventory',
                icon: Icons.inventory_2_outlined,
                onTap: () => onSelectTab(1),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _StatCard(
                label: 'LOW STOCK',
                value: '$lowStock',
                caption: lowStock == 0 ? 'All items healthy' : 'Below $kLowStockThreshold units',
                valueColor: lowStock == 0 ? Colors.green.shade700 : const Color(0xFFD23B5C),
                icon: Icons.warning_amber_rounded,
                onTap: () => onSelectTab(1),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: _StatCard(
                label: 'SUPPLIERS',
                value: '$totalSuppliers',
                caption: 'Active partners',
                icon: Icons.local_shipping_outlined,
                onTap: () => onSelectTab(2),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _StatCard(
                label: 'THIS MONTH',
                value: '$movementsIn in / $movementsOut out',
                caption: 'Stock movements',
                icon: Icons.swap_vert_rounded,
                onTap: () => onSelectTab(0),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final String caption;
  final Color? valueColor;
  final IconData? icon;
  final VoidCallback? onTap;

  const _StatCard({
    required this.label,
    required this.value,
    required this.caption,
    this.valueColor,
    this.icon,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final card = Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [
          BoxShadow(color: Color(0x0F000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.black45, letterSpacing: .3),
                ),
              ),
              if (icon != null) Icon(icon, size: 14, color: Colors.black26),
            ],
          ),
          const SizedBox(height: 6),
          // FittedBox keeps longer values (e.g. "12 in / 5 out") from
          // overflowing the card instead of hard-coding a smaller font.
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: valueColor ?? AppColors.dark),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            caption,
            style: const TextStyle(fontSize: 10, color: Colors.black38),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );

    if (onTap == null) return card;

    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: card,
    );
  }
}

class _InventoryList extends StatelessWidget {
  final List<InventoryItem> items;
  final String Function(DateTime) formatDate;
  final Function(InventoryItem) onTap;

  const _InventoryList({
    required this.items,
    required this.formatDate,
    required this.onTap,
  });

  String _formatQty(double q) => q % 1 == 0 ? q.toInt().toString() : q.toString();

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Center(child: Text('No inventory items yet.'));
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      itemCount: items.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final item = items[index];
        final isIn = item.status == StockStatus.stockIn;
        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => onTap(item),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [
                BoxShadow(color: Color(0x0F000000), blurRadius: 6, offset: Offset(0, 2)),
              ],
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.name, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.dark)),
                      const SizedBox(height: 3),
                      Text('${item.category} · ${item.unit}', style: const TextStyle(fontSize: 12.5, color: Colors.black54)),
                      // OUT transactions are tied to a project — show which
                      // one this stock left for.
                      if (!isIn && (item.projectName ?? '').isNotEmpty && item.projectName != '-')
                        Padding(
                          padding: const EdgeInsets.only(top: 3),
                          child: Text('For: ${item.projectName}', style: const TextStyle(fontSize: 12, color: Colors.black45)),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${isIn ? '+' : '-'}${_formatQty(item.quantity)} ${item.unit}',
                          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.dark),
                        ),
                        const SizedBox(width: 8),
                        _StatusPill(
                          text: isIn ? 'IN' : 'OUT',
                          background: isIn ? const Color(0xFFDCF2DE) : const Color(0xFFFBDCE0),
                          textColor: isIn ? const Color(0xFF2E8B3D) : const Color(0xFFD23B5C),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(formatDate(item.date), style: const TextStyle(fontSize: 11, color: Colors.black38)),
                    const SizedBox(height: 2),
                    Text('Stock now: ${item.stock}', style: const TextStyle(fontSize: 10.5, color: Colors.black38)),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _SuppliersList extends StatelessWidget {
  final List<Supplier> suppliers;
  final VoidCallback onChanged;

  const _SuppliersList({required this.suppliers, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    if (suppliers.isEmpty) {
      return const Center(child: Text('No suppliers added yet.'));
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      itemCount: suppliers.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final supplier = suppliers[index];

        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () {
            showDialog(
              context: context,
              barrierDismissible: false,
              builder: (_) => _SupplierDetailsModal(
                supplier: supplier,
                onChanged: onChanged,
              ),
            );
          },
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x0F000000),
                  blurRadius: 6,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        supplier.name,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.dark,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${supplier.itemCount} item${supplier.itemCount == 1 ? '' : 's'} supplied',
                        style: const TextStyle(
                          fontSize: 12.5,
                          color: Colors.black54,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        supplier.phone,
                        style: const TextStyle(
                          fontSize: 12.5,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                _StatusPill(
                  text: supplier.isActive ? 'Active' : 'Inactive',
                  background: supplier.isActive
                      ? const Color(0xFFDCF2DE)
                      : const Color(0xFFEAEAEA),
                  textColor: supplier.isActive ? const Color(0xFF2E8B3D) : Colors.black45,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ItemsList extends StatelessWidget {
  final List<ItemRecord> items;
  final Function(ItemRecord) onTap;

  const _ItemsList({required this.items, required this.onTap});

  String _formatStock(double q) => q % 1 == 0 ? q.toInt().toString() : q.toString();

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Center(child: Text('No items yet. Tap "Add Item" to create one.'));
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      itemCount: items.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final item = items[index];
        final lowStock = item.currentStock < kLowStockThreshold;
        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => onTap(item),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [
                BoxShadow(color: Color(0x0F000000), blurRadius: 6, offset: Offset(0, 2)),
              ],
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.name, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.dark)),
                      const SizedBox(height: 3),
                      Text('${item.categoryName} · ${item.unitName}', style: const TextStyle(fontSize: 12.5, color: Colors.black54)),
                      const SizedBox(height: 3),
                      Text('Supplier: ${item.supplierName}', style: const TextStyle(fontSize: 12, color: Colors.black45)),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '${_formatStock(item.currentStock)} ${item.unitName}',
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.dark),
                    ),
                    const SizedBox(height: 6),
                    _StatusPill(
                      text: lowStock ? 'Low Stock' : 'In Stock',
                      background: lowStock ? const Color(0xFFFBDCE0) : const Color(0xFFDCF2DE),
                      textColor: lowStock ? const Color(0xFFD23B5C) : const Color(0xFF2E8B3D),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ItemDetailsModal extends StatelessWidget {
  final ItemRecord item;
  final VoidCallback? onChanged;

  const _ItemDetailsModal({required this.item, this.onChanged});

  Future<void> _confirmDelete(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Text("Delete item?"),
        content: Text("This will permanently remove \"${item.name}\" and its transaction history."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogCtx, false), child: const Text("Cancel")),
          TextButton(onPressed: () => Navigator.pop(dialogCtx, true), child: const Text("Delete", style: TextStyle(color: Colors.red))),
        ],
      ),
    );

    if (confirmed != true) return;
    if (!context.mounted) return;

    final success = await InventoryService.deleteItem(item.itemId);
    if (!context.mounted) return;

    if (success) {
      onChanged?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Item deleted.")));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Failed to delete item. Please try again.")));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text("Item Details", style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
                InkWell(onTap: () => Navigator.pop(context), child: const Icon(Icons.close)),
              ],
            ),
            const SizedBox(height: 30),
            _detail("Item Name", item.name),
            _detail("Category", item.categoryName),
            _detail("Unit", item.unitName),
            _detail("Supplier", item.supplierName),
            _detail(
              "Current Stock",
              "${item.currentStock % 1 == 0 ? item.currentStock.toInt() : item.currentStock} ${item.unitName}",
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton(
                  onPressed: () => _confirmDelete(context),
                  child: const Text("Delete", style: TextStyle(color: Colors.red)),
                ),
                Row(
                  children: [
                    TextButton(onPressed: () => Navigator.pop(context), child: const Text("Cancel", style: TextStyle(color: Colors.black54))),
                    const SizedBox(width: 12),
                    ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context);
                        Future.delayed(const Duration(milliseconds: 100), () {
                          showDialog(
                            context: context,
                            barrierDismissible: false,
                            builder: (_) => _EditItemFullModal(item: item, onSaved: onChanged),
                          );
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 13),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text("Edit Item"),
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

  Widget _detail(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700, color: Colors.grey)),
          const SizedBox(height: 5),
          Text(value, style: const TextStyle(fontSize: 16, color: Colors.black87)),
        ],
      ),
    );
  }
}

class _EditItemFullModal extends StatefulWidget {
  final ItemRecord item;
  final VoidCallback? onSaved;

  const _EditItemFullModal({required this.item, this.onSaved});

  @override
  State<_EditItemFullModal> createState() => _EditItemFullModalState();
}

class _EditItemFullModalState extends State<_EditItemFullModal> {
  late final TextEditingController nameController;
  late final TextEditingController stockController;

  int? selectedCategoryId;
  int? selectedUnitId;
  int? selectedSupplierId;

  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _units = [];
  List<Map<String, dynamic>> _suppliers = [];

  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    nameController = TextEditingController(text: widget.item.name);
    stockController = TextEditingController(
      text: widget.item.currentStock % 1 == 0
          ? widget.item.currentStock.toInt().toString()
          : widget.item.currentStock.toString(),
    );
    selectedCategoryId = widget.item.categoryId;
    selectedUnitId = widget.item.unitId;
    selectedSupplierId = widget.item.supplierId;
    _loadDropdownData();
  }

  @override
  void dispose() {
    nameController.dispose();
    stockController.dispose();
    super.dispose();
  }

  Future<void> _loadDropdownData() async {
    try {
      final results = await Future.wait([
        InventoryService.fetchCategories(),
        InventoryService.fetchUnits(),
        InventoryService.fetchSuppliers(),
      ]);
      if (mounted) {
        setState(() {
          _categories = results[0];
          _units = results[1];
          _suppliers = results[2];
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = "Failed to load options. Please try again.";
          _loading = false;
        });
      }
    }
  }

  Future<void> _save() async {
    final newName = nameController.text.trim();
    final newStock = double.tryParse(stockController.text.trim());

    if (newName.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Please enter an item name.")));
      return;
    }
    if (selectedCategoryId == null || selectedUnitId == null || selectedSupplierId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Please select category, unit, and supplier.")));
      return;
    }
    if (newStock == null || newStock < 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Please enter a valid stock amount.")));
      return;
    }

    setState(() => _saving = true);

    final success = await InventoryService.updateItem(
      itemId: widget.item.itemId,
      itemName: newName,
      categoryId: selectedCategoryId,
      supplierId: selectedSupplierId,
      unitId: selectedUnitId,
      currentStock: newStock,
    );

    if (!mounted) return;
    setState(() => _saving = false);

    if (success) {
      widget.onSaved?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Item updated.")));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Failed to update item. Please try again.")));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24)),
        child: _loading
            ? const SizedBox(height: 200, child: Center(child: CircularProgressIndicator()))
            : _error != null
                ? SizedBox(
                    height: 200,
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, style: const TextStyle(color: Colors.red)),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: () {
                              setState(() {
                                _loading = true;
                                _error = null;
                              });
                              _loadDropdownData();
                            },
                            child: const Text("Retry"),
                          ),
                        ],
                      ),
                    ),
                  )
                : _buildForm(context),
      ),
    );
  }

  Widget _buildForm(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text("Edit item", style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
              InkWell(onTap: _saving ? null : () => Navigator.pop(context), child: const Icon(Icons.close)),
            ],
          ),
          const SizedBox(height: 25),
          _field("Item Name", TextField(controller: nameController, decoration: _decoration("Item Name"))),
          const SizedBox(height: 20),
          _field(
            "Item Category",
            _dbDropdown(
              value: selectedCategoryId,
              hint: "Choose Category...",
              items: _categories,
              idKey: "inventory_category_id",
              nameKey: "inventory_category_name",
              onChanged: (id, name) => setState(() => selectedCategoryId = id),
            ),
          ),
          const SizedBox(height: 20),
          _field(
            "Item Unit",
            _dbDropdown(
              value: selectedUnitId,
              hint: "Choose Unit...",
              items: _units,
              idKey: "unit_id",
              nameKey: "unit_name",
              onChanged: (id, name) => setState(() => selectedUnitId = id),
            ),
          ),
          const SizedBox(height: 20),
          _field(
            "Item Supplier",
            _dbDropdown(
              value: selectedSupplierId,
              hint: "Choose Supplier...",
              items: _suppliers,
              idKey: "supplier_id",
              nameKey: "supplier_name",
              onChanged: (id, name) => setState(() => selectedSupplierId = id),
            ),
          ),
          const SizedBox(height: 20),
          _field(
            "Current Stock",
            TextField(
              controller: stockController,
              keyboardType: TextInputType.number,
              decoration: _decoration("Enter stock amount"),
            ),
          ),
          const SizedBox(height: 30),
          const Divider(),
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              TextButton(
                onPressed: _saving ? null : () => Navigator.pop(context),
                child: const Text("Cancel", style: TextStyle(color: Colors.black54, fontSize: 18)),
              ),
              ElevatedButton(
                onPressed: _saving ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: kDarkPill,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 15),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _saving
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text("Save Changes", style: TextStyle(fontSize: 18)),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _dbDropdown({
    required int? value,
    required String hint,
    required List<Map<String, dynamic>> items,
    required String idKey,
    required String nameKey,
    required void Function(int id, String name) onChanged,
  }) {
    // If the item's stored category/unit/supplier id no longer exists in
    // the freshly loaded list (e.g. it was deleted elsewhere), fall back
    // to no selection instead of crashing the dropdown.
    final validValue = items.any((i) => i[idKey] == value) ? value : null;

    return Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(10)),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          isExpanded: true,
          value: validValue,
          hint: Text(hint, style: TextStyle(color: Colors.grey.shade400)),
          items: items.map((item) {
            return DropdownMenuItem<int>(value: item[idKey] as int, child: Text(item[nameKey] as String? ?? ""));
          }).toList(),
          onChanged: (id) {
            if (id == null) return;
            final match = items.firstWhere((i) => i[idKey] == id);
            onChanged(id, match[nameKey] as String? ?? "");
          },
        ),
      ),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey)),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String text;
  final Color background;
  final Color textColor;

  const _StatusPill({required this.text, required this.background, required this.textColor});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
      child: Text(text, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: textColor)),
    );
  }
}

// NEW: standalone "Add Item" modal. Item name is free text; category, unit,
// and supplier are dropdowns sourced straight from the database via the
// same InventoryService calls the transaction modal already uses. New
// items are created with 0 stock — use "Edit Item" from the details modal
// (or an Add Transaction) to bring stock in afterwards.
class _AddItemModal extends StatefulWidget {
  final VoidCallback? onSaved;

  const _AddItemModal({this.onSaved});

  @override
  State<_AddItemModal> createState() => _AddItemModalState();
}

class _AddItemModalState extends State<_AddItemModal> {
  final itemNameController = TextEditingController();

  int? selectedCategoryId;
  String? selectedCategoryName;

  int? selectedUnitId;
  String? selectedUnitName;

  int? selectedSupplierId;
  String? selectedSupplierName;

  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _units = [];
  List<Map<String, dynamic>> _suppliers = [];

  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadDropdownData();
  }

  Future<void> _loadDropdownData() async {
    try {
      final results = await Future.wait([
        InventoryService.fetchCategories(),
        InventoryService.fetchUnits(),
        InventoryService.fetchSuppliers(),
      ]);
      if (mounted) {
        setState(() {
          _categories = results[0];
          _units = results[1];
          _suppliers = results[2];
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = "Failed to load options. Please try again.";
          _loading = false;
        });
      }
    }
  }

  @override
  void dispose() {
    itemNameController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (itemNameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter an item name.")),
      );
      return;
    }
    if (selectedCategoryId == null || selectedUnitId == null || selectedSupplierId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please select category, unit, and supplier.")),
      );
      return;
    }

    setState(() => _saving = true);

    final id = await InventoryService.createItem(
      itemName: itemNameController.text.trim(),
      categoryId: selectedCategoryId!,
      supplierId: selectedSupplierId!,
      unitId: selectedUnitId!,
      initialStock: 0,
    );

    if (!mounted) return;
    setState(() => _saving = false);

    if (id != null) {
      widget.onSaved?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Item added.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to add item. Please try again.")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: _loading
            ? const SizedBox(
                height: 200,
                child: Center(child: CircularProgressIndicator()),
              )
            : _error != null
                ? SizedBox(
                    height: 200,
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, style: const TextStyle(color: Colors.red)),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: () {
                              setState(() {
                                _loading = true;
                                _error = null;
                              });
                              _loadDropdownData();
                            },
                            child: const Text("Retry"),
                          ),
                        ],
                      ),
                    ),
                  )
                : _buildForm(context),
      ),
    );
  }

  Widget _buildForm(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              "Add new item",
              style: TextStyle(fontSize: 30, fontWeight: FontWeight.bold),
            ),
            InkWell(
              onTap: () => Navigator.pop(context),
              child: const Icon(Icons.close),
            ),
          ],
        ),

        const SizedBox(height: 28),

        _field(
          "Item Name",
          TextField(
            controller: itemNameController,
            decoration: _decoration("Item Name"),
          ),
        ),

        const SizedBox(height: 22),

        _field(
          "Item Category",
          _dbDropdown(
            value: selectedCategoryId,
            hint: "Choose Category...",
            items: _categories,
            idKey: "inventory_category_id",
            nameKey: "inventory_category_name",
            onChanged: (id, name) {
              setState(() {
                selectedCategoryId = id;
                selectedCategoryName = name;
              });
            },
          ),
        ),

        const SizedBox(height: 22),

        _field(
          "Item Unit",
          _dbDropdown(
            value: selectedUnitId,
            hint: "Choose Unit...",
            items: _units,
            idKey: "unit_id",
            nameKey: "unit_name",
            onChanged: (id, name) {
              setState(() {
                selectedUnitId = id;
                selectedUnitName = name;
              });
            },
          ),
        ),

        const SizedBox(height: 22),

        _field(
          "Item Supplier",
          _dbDropdown(
            value: selectedSupplierId,
            hint: "Choose Supplier...",
            items: _suppliers,
            idKey: "supplier_id",
            nameKey: "supplier_name",
            onChanged: (id, name) {
              setState(() {
                selectedSupplierId = id;
                selectedSupplierName = name;
              });
            },
          ),
        ),

        const SizedBox(height: 30),

        const Divider(),

        const SizedBox(height: 20),

        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: _saving ? null : () => Navigator.pop(context),
              child: const Text(
                "Cancel",
                style: TextStyle(color: Colors.black54, fontSize: 18),
              ),
            ),
            ElevatedButton(
              onPressed: _saving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: kDarkPill,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Text("Add Item", style: TextStyle(fontSize: 18)),
            ),
          ],
        ),
      ],
    );
  }

  Widget _dbDropdown({
    required int? value,
    required String hint,
    required List<Map<String, dynamic>> items,
    required String idKey,
    required String nameKey,
    required void Function(int id, String name) onChanged,
  }) {
    return Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(10),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          isExpanded: true,
          value: value,
          hint: Text(hint, style: TextStyle(color: Colors.grey.shade400)),
          items: items.map((item) {
            return DropdownMenuItem<int>(
              value: item[idKey] as int,
              child: Text(item[nameKey] as String? ?? ""),
            );
          }).toList(),
          onChanged: (id) {
            if (id == null) return;
            final match = items.firstWhere((i) => i[idKey] == id);
            onChanged(id, match[nameKey] as String? ?? "");
          },
        ),
      ),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey),
        ),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
    );
  }
}

class _AddTransactionModal extends StatefulWidget {
  final VoidCallback? onSaved;

  const _AddTransactionModal({this.onSaved});

  @override
  State<_AddTransactionModal> createState() => _AddTransactionModalState();
}

class _AddTransactionModalState extends State<_AddTransactionModal> {
  final dateController = TextEditingController();
  final quantityController = TextEditingController(text: "1");

  int quantity = 1;
  String transactionType = "IN";
  DateTime? selectedDate;

  // The item is now picked from a dropdown of existing inventory items
  // (created ahead of time via "Add Item") instead of being typed/created
  // here. Category, unit, and supplier already live on the item itself in
  // the database, so this modal no longer needs to collect them.
  int? selectedItemId;
  String? selectedItemName;
  String? selectedItemUnitName;
  // Current stock of the selected item, used to cap/validate OUT quantity
  // so you can never take out more than what's actually on hand.
  double? selectedItemCurrentStock;

  int? selectedProjectId;
  String? selectedProjectName;

  List<Map<String, dynamic>> _items = [];
  List<Map<String, dynamic>> _projects = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadDropdownData();
  }

  Future<void> _loadDropdownData() async {
    try {
      final results = await Future.wait([
        InventoryService.fetchInventoryItems(),
        InventoryService.fetchProjects(),
      ]);
      if (mounted) {
        // The API's inventory-items-list can return more than one row per
        // item (e.g. once it's been joined against multiple transactions),
        // which duplicated entries in this dropdown. The item picker should
        // reflect the item table 1:1, so dedupe by item_id here — first
        // occurrence wins since current_stock is a property of the item,
        // not of any individual transaction.
        final seenIds = <dynamic>{};
        final dedupedItems = <Map<String, dynamic>>[];
        for (final item in results[0]) {
          if (seenIds.add(item['item_id'])) {
            dedupedItems.add(item);
          }
        }
        setState(() {
          _items = dedupedItems;
          _projects = results[1];
          _loading = false;
        });
      }
    } catch (e, st) {
      debugPrint("Dropdown load error: $e");
      debugPrint("$st");
      if (mounted) {
        setState(() {
          _error = "Failed to load options. Please try again.";
          _loading = false;
        });
      }
    }
  }

  // Shows validation messages as a small dialog stacked ON TOP of this
  // modal (since showDialog always layers above whatever's already open),
  // instead of a SnackBar — which renders on the Scaffold behind the modal
  // and can go unnoticed.
  void _showValidationError(String message) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text("Missing information"),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("OK"),
          ),
        ],
      ),
    );
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: selectedDate ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
    );
    if (picked != null) {
      setState(() {
        selectedDate = picked;
        dateController.text =
            "${picked.day.toString().padLeft(2, '0')}-"
            "${picked.month.toString().padLeft(2, '0')}-"
            "${picked.year}";
      });
    }
  }

  @override
  void dispose() {
    dateController.dispose();
    quantityController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: _loading
            ? const SizedBox(
                height: 200,
                child: Center(child: CircularProgressIndicator()),
              )
            : _error != null
                ? SizedBox(
                    height: 200,
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, style: const TextStyle(color: Colors.red)),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: () {
                              setState(() {
                                _loading = true;
                                _error = null;
                              });
                              _loadDropdownData();
                            },
                            child: const Text("Retry"),
                          ),
                        ],
                      ),
                    ),
                  )
                : _buildForm(context),
      ),
    );
  }

  // Reordered: Transaction Type is decided first, since it controls whether
  // the Project field even shows. IN never has a project (stock is just
  // replenished); OUT always needs one (stock leaves TO a project).
  Widget _buildForm(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              "Add new transaction",
              style: TextStyle(fontSize: 30, fontWeight: FontWeight.bold),
            ),
            InkWell(
              onTap: () => Navigator.pop(context),
              child: const Icon(Icons.close),
            ),
          ],
        ),

        const SizedBox(height: 28),

        Row(
          children: [
            Expanded(
              child: _field(
                "Transaction Type",
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Radio(
                          value: "IN",
                          groupValue: transactionType,
                          onChanged: (v) => setState(() {
                            transactionType = "IN";
                            // Stock IN doesn't belong to a project — clear
                            // any previous OUT selection.
                            selectedProjectId = null;
                            selectedProjectName = null;
                          }),
                        ),
                        const Text("IN"),
                        const SizedBox(width: 25),
                        Radio(
                          value: "OUT",
                          groupValue: transactionType,
                          onChanged: (v) => setState(() => transactionType = "OUT"),
                        ),
                        const Text("OUT"),
                      ],
                    ),
                    Text(
                      transactionType == "IN" ? "Item Stock in" : "Item Stock out",
                      style: const TextStyle(color: Colors.grey),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 20),
            Expanded(
              child: _field(
                "Transaction Date",
                InkWell(
                  onTap: _pickDate,
                  child: IgnorePointer(
                    child: TextField(
                      controller: dateController,
                      decoration: _decoration("DD-MM-YYYY").copyWith(
                        suffixIcon: const Icon(Icons.calendar_month),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),

        const SizedBox(height: 22),

        // Project only applies to OUT transactions.
        if (transactionType == "OUT") ...[
          _field(
            "Project",
            _dbDropdown(
              value: selectedProjectId,
              hint: "Choose Project...",
              items: _projects,
              idKey: "project_id",
              nameKey: "project_name",
              onChanged: (id, name) {
                setState(() {
                  selectedProjectId = id;
                  selectedProjectName = name;
                });
              },
            ),
          ),
          const SizedBox(height: 22),
        ],

        _field(
          "Item Name",
          _dbDropdown(
            value: selectedItemId,
            hint: "Choose Item...",
            items: _items,
            idKey: "item_id",
            nameKey: "item_name",
            onChanged: (id, name) {
              final match = _items.firstWhere((i) => i["item_id"] == id);
              final rawStock = match["current_stock"];
              final stock = rawStock is num
                  ? rawStock.toDouble()
                  : double.tryParse("$rawStock");
              setState(() {
                selectedItemId = id;
                selectedItemName = name;
                selectedItemUnitName = match["unit_name"] as String?;
                selectedItemCurrentStock = stock;
                // Switching items resets the quantity so a leftover value
                // from a previous, higher-stock item can't slip through.
                quantity = 1;
                quantityController.text = "1";
              });
            },
          ),
        ),

        // Shows how much is on hand for the selected item so it's clear
        // what the OUT quantity is limited to.
        if (selectedItemId != null && selectedItemCurrentStock != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              "Available stock: ${selectedItemCurrentStock! % 1 == 0 ? selectedItemCurrentStock!.toInt() : selectedItemCurrentStock} ${selectedItemUnitName ?? ''}",
              style: const TextStyle(fontSize: 12.5, color: Colors.black54),
            ),
          ),

        const SizedBox(height: 22),

        _field(
          "Item Quantity",
          Row(
            children: [
              _smallBtn("-", () {
                if (quantity > 1) {
                  setState(() {
                    quantity--;
                    quantityController.text = quantity.toString();
                  });
                }
              }),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: quantityController,
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  decoration: _decoration("Quantity").copyWith(
                    suffixText: selectedItemUnitName,
                  ),
                  onChanged: (value) {
                    final number = int.tryParse(value);
                    if (number != null) quantity = number;
                  },
                ),
              ),
              const SizedBox(width: 10),
              _smallBtn("+", () {
                // OUT can't add stock beyond what's available.
                if (transactionType == "OUT" &&
                    selectedItemCurrentStock != null &&
                    quantity >= selectedItemCurrentStock!) {
                  _showValidationError(
                    "Only ${selectedItemCurrentStock! % 1 == 0 ? selectedItemCurrentStock!.toInt() : selectedItemCurrentStock} ${selectedItemUnitName ?? ''} available for this item.",
                  );
                  return;
                }
                setState(() {
                  quantity++;
                  quantityController.text = quantity.toString();
                });
              }),
            ],
          ),
        ),

        const SizedBox(height: 30),

        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text(
                "Cancel",
                style: TextStyle(color: Colors.black54, fontSize: 18),
              ),
            ),
            ElevatedButton(
              onPressed: () {
                // Every field must be filled before moving to Review —
                // nothing here is optional.
                if (transactionType == "OUT" && selectedProjectId == null) {
                  _showValidationError("Please choose a project for this stock out.");
                  return;
                }
                if (selectedItemId == null) {
                  _showValidationError("Please choose an item.");
                  return;
                }
                final parsedQuantity = int.tryParse(quantityController.text.trim());
                if (parsedQuantity == null || parsedQuantity <= 0) {
                  _showValidationError("Please enter a valid quantity greater than 0.");
                  return;
                }
                if (transactionType == "OUT" &&
                    selectedItemCurrentStock != null &&
                    parsedQuantity > selectedItemCurrentStock!) {
                  _showValidationError(
                    "Quantity can't exceed the available stock "
                    "(${selectedItemCurrentStock! % 1 == 0 ? selectedItemCurrentStock!.toInt() : selectedItemCurrentStock} ${selectedItemUnitName ?? ''} on hand).",
                  );
                  return;
                }
                quantity = parsedQuantity;
                if (dateController.text.trim().isEmpty) {
                  _showValidationError("Please choose a transaction date.");
                  return;
                }
                showDialog(
                  context: context,
                  barrierDismissible: false,
                  builder: (_) => _ReviewTransactionModal(
                    onSaved: widget.onSaved,
                    projectId: transactionType == "OUT" ? selectedProjectId : null,
                    itemId: selectedItemId!,
                    projectName: transactionType == "OUT" ? (selectedProjectName ?? "-") : "-",
                    itemName: selectedItemName ?? "-",
                    quantity: quantity,
                    unitName: selectedItemUnitName ?? "-",
                    transactionType: transactionType,
                    transactionDate: dateController.text.isEmpty ? "-" : dateController.text,
                  ),
                );
              },
              child: const Text(
                "Review",
                style: TextStyle(fontSize: 18),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _dbDropdown({
    required int? value,
    required String hint,
    required List<Map<String, dynamic>> items,
    required String idKey,
    required String nameKey,
    required void Function(int id, String name) onChanged,
  }) {
    return Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(10),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          isExpanded: true,
          value: value,
          hint: Text(hint, style: TextStyle(color: Colors.grey.shade400)),
          items: items.map((item) {
            return DropdownMenuItem<int>(
              value: item[idKey] as int,
              child: Text(item[nameKey] as String? ?? ""),
            );
          }).toList(),
          onChanged: (id) {
            if (id == null) return;
            final match = items.firstWhere((i) => i[idKey] == id);
            onChanged(id, match[nameKey] as String? ?? "");
          },
        ),
      ),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey),
        ),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  Widget _smallBtn(String text, VoidCallback tap) {
    return InkWell(
      onTap: tap,
      child: Container(
        width: 32,
        height: 38,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: kDarkPill,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(text, style: const TextStyle(color: Colors.white, fontSize: 20)),
      ),
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
    );
  }
}

class _ReviewTransactionModal extends StatefulWidget {
  final VoidCallback? onSaved;

  final int? projectId;
  // The item now always already exists (picked from the dropdown in
  // _AddTransactionModal), so this modal only records a transaction
  // against it — it no longer creates a new inventory item.
  final int itemId;
  final String projectName;
  final String itemName;
  final int quantity;
  final String unitName;
  final String transactionType;
  final String transactionDate;

  const _ReviewTransactionModal({
    this.onSaved,
    this.projectId,
    required this.itemId,
    required this.projectName,
    required this.itemName,
    required this.quantity,
    required this.unitName,
    required this.transactionType,
    required this.transactionDate,
  });

  @override
  State<_ReviewTransactionModal> createState() => _ReviewTransactionModalState();
}

class _ReviewTransactionModalState extends State<_ReviewTransactionModal> {
  bool _saving = false;

  String _formatDateForApi(String date) {
    if (date == "-" || date.isEmpty) return date;
    final parts = date.split("-");
    if (parts.length != 3) return date;
    return "${parts[2]}-${parts[1]}-${parts[0]}"; // YYYY-MM-DD
  }

  Future<void> _finishTransaction(BuildContext context) async {
    setState(() => _saving = true);

    final success = await InventoryService.saveTransaction(
      itemId: widget.itemId,
      projectId: widget.projectId,
      type: widget.transactionType,
      quantity: widget.quantity.toDouble(),
      date: _formatDateForApi(widget.transactionDate),
    );

    setState(() => _saving = false);

    if (success) {
      widget.onSaved?.call();
      // Grab the messenger before popping — after the pops below, this
      // widget's own context is no longer in the tree.
      final messenger = ScaffoldMessenger.of(context);
      // Close just the two dialogs opened for this flow (this Review modal,
      // then the Add Transaction modal beneath it) instead of popping all
      // the way back to the app's first route — popUntil(isFirst) was
      // going past the Inventory screen back to Login on stacks where
      // Inventory isn't the root route.
      Navigator.of(context).pop(); // close Review modal
      Navigator.of(context).pop(); // close Add Transaction modal
      messenger.showSnackBar(
        const SnackBar(content: Text("Transaction saved successfully.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to save transaction. Please try again.")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    // RESPONSIVE FIX: the old version used a fixed 170px width per data box
    // and a fixed padding: all(30), which overflowed (yellow/black stripes)
    // on narrower phones. This version:
    //  - caps the dialog's max width/height using MediaQuery so it never
    //    exceeds the viewport
    //  - wraps content in SingleChildScrollView so tall content scrolls
    //    instead of overflowing vertically
    //  - uses LayoutBuilder to size each data box to a fraction of the
    //    *actual* available width instead of a hardcoded pixel value
    //  - lets the bottom action buttons wrap onto a second line on very
    //    narrow screens instead of overflowing horizontally
    final media = MediaQuery.of(context);
    final screenWidth = media.size.width;
    final screenHeight = media.size.height;
    final isCompact = screenWidth < 380;

    final dialogMaxWidth = screenWidth < 600 ? screenWidth * 0.94 : 520.0;
    final contentPadding = isCompact ? 18.0 : 30.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: dialogMaxWidth,
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(contentPadding),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        "Review transaction details",
                        style: TextStyle(
                          fontSize: isCompact ? 22 : 30,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    InkWell(
                      onTap: _saving ? null : () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    ),
                  ],
                ),

                SizedBox(height: isCompact ? 20 : 35),

                LayoutBuilder(
                  builder: (context, constraints) {
                    const spacing = 24.0;
                    // 1 column on very narrow dialogs, 2 columns otherwise.
                    final isSingleColumn = constraints.maxWidth < 300;
                    final itemWidth = isSingleColumn
                        ? constraints.maxWidth
                        : (constraints.maxWidth - spacing) / 2;

                    return Wrap(
                      spacing: spacing,
                      runSpacing: 20,
                      children: [
                        // Project only shown for OUT — IN transactions
                        // aren't attached to a project.
                        if (widget.transactionType == "OUT")
                          _data("Project", widget.projectName, itemWidth),
                        _data("Item Name", widget.itemName.isEmpty ? "-" : widget.itemName, itemWidth),
                        _data("Item Quantity", "${widget.quantity}", itemWidth),
                        _data("Item Unit", widget.unitName, itemWidth),
                        _data(
                          "Transaction Type",
                          "${widget.transactionType}\n${widget.transactionType == "IN" ? "Item Stock in" : "Item Stock out"}",
                          itemWidth,
                        ),
                        _data("Transaction Date", widget.transactionDate, itemWidth),
                      ],
                    );
                  },
                ),

                const Divider(height: 40),

                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  runSpacing: 12,
                  children: [
                    TextButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      child: const Text(
                        "Cancel",
                        style: TextStyle(fontSize: 16, color: Colors.black54),
                      ),
                    ),
                    Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: [
                        ElevatedButton(
                          onPressed: _saving ? null : () => Navigator.pop(context),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                          ),
                          child: const Text("← Back"),
                        ),
                        ElevatedButton(
                          onPressed: _saving ? null : () => _finishTransaction(context),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                          ),
                          child: _saving
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text("Save Transaction"),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _data(String title, String value, double width) {
    return SizedBox(
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700, color: Colors.grey)),
          const SizedBox(height: 8),
          Text(value, style: const TextStyle(fontSize: 16, color: Colors.black54)),
        ],
      ),
    );
  }
}

class _InventoryDetailsModal extends StatelessWidget {
  final InventoryItem item;
  final VoidCallback? onChanged;

  const _InventoryDetailsModal({
    required this.item,
    this.onChanged,
  });

  String _formatDate(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');

    return '$y-$m-$d';
  }

  Future<void> _confirmDelete(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Text("Delete item?"),
        content: Text("This will permanently remove \"${item.name}\" and its transaction history."),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogCtx, false),
            child: const Text("Cancel"),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogCtx, true),
            child: const Text("Delete", style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirmed != true || item.itemId == null) return;
    if (!context.mounted) return;

    final success = await InventoryService.deleteItem(item.itemId!);
    if (!context.mounted) return;

    if (success) {
      onChanged?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Item deleted.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to delete item. Please try again.")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final isIn = item.status == StockStatus.stockIn;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
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
                  "Inventory Details",
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                InkWell(
                  onTap: () {
                    Navigator.pop(context);
                  },
                  child: const Icon(Icons.close),
                )
              ],
            ),

            const SizedBox(height: 30),

            _detail("Item Name", item.name),
            _detail("Category", item.category),
            _detail("Unit", item.unit),
            _detail("Transaction Quantity",
                "${item.quantity % 1 == 0 ? item.quantity.toInt() : item.quantity} ${item.unit}"),
            _detail("Current Stock (item total)", "${item.stock} ${item.unit}"),
            if (!isIn && (item.projectName ?? '').isNotEmpty && item.projectName != '-')
              _detail("Project", item.projectName!),

            Row(
              children: [
                const Text(
                  "Status",
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: Colors.grey,
                  ),
                ),
                const SizedBox(width: 20),
                _StatusPill(
                  text: isIn ? "IN" : "OUT",
                  background: isIn ? const Color(0xFFDCF2DE) : const Color(0xFFFBDCE0),
                  textColor: isIn ? const Color(0xFF2E8B3D) : const Color(0xFFD23B5C),
                )
              ],
            ),

            const SizedBox(height: 15),

            _detail("Transaction Date", _formatDate(item.date)),

            const SizedBox(height: 25),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton(
                  onPressed: () => _confirmDelete(context),
                  child: const Text(
                    "Delete",
                    style: TextStyle(color: Colors.red),
                  ),
                ),
                Row(
                  children: [
                    TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text(
                        "Cancel",
                        style: TextStyle(color: Colors.black54),
                      ),
                    ),
                    const SizedBox(width: 12),
                    ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context);
                        Future.delayed(const Duration(milliseconds: 100), () {
                          showDialog(
                            context: context,
                            barrierDismissible: false,
                            builder: (_) => _EditInventoryModal(
                              item: item,
                              onSaved: onChanged,
                            ),
                          );
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 13,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text("Edit Item"),
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

  Widget _detail(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              color: Colors.black87,
            ),
          )
        ],
      ),
    );
  }
}

class _SupplierDetailsModal extends StatelessWidget {
  final Supplier supplier;
  final VoidCallback? onChanged;

  const _SupplierDetailsModal({
    required this.supplier,
    this.onChanged,
  });

  Future<void> _confirmDelete(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Text("Delete supplier?"),
        content: Text("This will permanently remove \"${supplier.name}\"."),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogCtx, false),
            child: const Text("Cancel"),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogCtx, true),
            child: const Text("Delete", style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirmed != true || supplier.supplierId == null) return;
    if (!context.mounted) return;

    final result = await InventoryService.deleteSupplier(supplier.supplierId!);
    if (!context.mounted) return;

    if (result.success) {
      onChanged?.call();
      Navigator.pop(context);
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(result.message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final active = supplier.isActive;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
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
                  "Supplier Details",
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                InkWell(
                  onTap: () {
                    Navigator.pop(context);
                  },
                  child: const Icon(Icons.close),
                ),
              ],
            ),

            const SizedBox(height: 30),

            _supplierDetail("Supplier Name", supplier.name),
            _supplierDetail("Total Items Supplied", "${supplier.itemCount} items"),
            _supplierDetail("Contact Number", supplier.phone),
            _supplierDetail("Address", supplier.address),

            Row(
              children: [
                const Text(
                  "Status",
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: Colors.grey,
                  ),
                ),
                const SizedBox(width: 20),
                _StatusPill(
                  text: active ? "Active" : "Inactive",
                  background: active ? const Color(0xFFDCF2DE) : const Color(0xFFEAEAEA),
                  textColor: active ? const Color(0xFF2E8B3D) : Colors.black45,
                ),
              ],
            ),

            const SizedBox(height: 25),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton(
                  onPressed: () => _confirmDelete(context),
                  child: const Text(
                    "Delete",
                    style: TextStyle(color: Colors.red),
                  ),
                ),
                Row(
                  children: [
                    TextButton(
                      onPressed: () {
                        Navigator.pop(context);
                      },
                      child: const Text(
                        "Cancel",
                        style: TextStyle(
                          color: Colors.black54,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context);

                        Future.delayed(
                          const Duration(milliseconds: 100),
                          () {
                            showDialog(
                              context: context,
                              barrierDismissible: false,
                              builder: (_) => _EditSupplierModal(
                                supplier: supplier,
                                onSaved: onChanged,
                              ),
                            );
                          },
                        );
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 13,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text("Edit Supplier"),
                    ),
                  ],
                ),
              ],
            )
          ],
        ),
      ),
    );
  }

  Widget _supplierDetail(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: Colors.grey,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              color: Colors.black87,
            ),
          ),
        ],
      ),
    );
  }
}

class _AddSupplierModal extends StatefulWidget {
  final VoidCallback? onSaved;

  const _AddSupplierModal({this.onSaved});

  @override
  State<_AddSupplierModal> createState() => _AddSupplierModalState();
}

class _AddSupplierModalState extends State<_AddSupplierModal> {
  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final addressController = TextEditingController();

  bool _saving = false;

  @override
  void dispose() {
    nameController.dispose();
    phoneController.dispose();
    addressController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (nameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter a supplier name.")),
      );
      return;
    }

    setState(() => _saving = true);

    final id = await InventoryService.createSupplier(
      name: nameController.text.trim(),
      contactNumber: phoneController.text.trim().isEmpty ? null : phoneController.text.trim(),
      address: addressController.text.trim().isEmpty ? null : addressController.text.trim(),
    );

    if (!mounted) return;
    setState(() => _saving = false);

    if (id != null) {
      widget.onSaved?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Supplier added.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to add supplier. Please try again.")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
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
                  "Add Supplier",
                  style: TextStyle(
                    fontSize: 30,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                InkWell(
                  onTap: () {
                    Navigator.pop(context);
                  },
                  child: const Icon(Icons.close),
                )
              ],
            ),

            const SizedBox(height: 30),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Supplier Name",
                    TextField(
                      controller: nameController,
                      decoration: _decoration("Enter supplier name..."),
                    ),
                  ),
                ),
                const SizedBox(width: 20),
              ],
            ),

            const SizedBox(height: 22),

            _field(
              "Address",
              TextField(
                controller: addressController,
                decoration: _decoration("Enter supplier address..."),
                maxLines: 2,
              ),
            ),

            const SizedBox(height: 22),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Contact Number",
                    TextField(
                      controller: phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: _decoration("+63"),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 30),

            const Divider(),

            const SizedBox(height: 20),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton(
                  onPressed: _saving ? null : () {
                    Navigator.pop(context);
                  },
                  child: const Text(
                    "Cancel",
                    style: TextStyle(
                      fontSize: 18,
                      color: Colors.black54,
                    ),
                  ),
                ),
                ElevatedButton(
                  onPressed: _saving ? null : _save,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kDarkPill,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 26,
                      vertical: 15,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text("Add Supplier"),
                )
              ],
            )
          ],
        ),
      ),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.w600,
            color: Colors.grey,
          ),
        ),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
    );
  }
}

class _EditSupplierModal extends StatefulWidget {
  final Supplier supplier;
  final VoidCallback? onSaved;

  const _EditSupplierModal({
    required this.supplier,
    this.onSaved,
  });

  @override
  State<_EditSupplierModal> createState() => _EditSupplierModalState();
}

class _EditSupplierModalState extends State<_EditSupplierModal> {
  late TextEditingController nameController;
  late TextEditingController phoneController;
  late TextEditingController addressController;

  bool _saving = false;

  @override
  void initState() {
    super.initState();
    nameController = TextEditingController(text: widget.supplier.name);
    phoneController = TextEditingController(text: widget.supplier.phone == '-' ? '' : widget.supplier.phone);
    addressController = TextEditingController(text: widget.supplier.address == '-' ? '' : widget.supplier.address);
  }

  @override
  void dispose() {
    nameController.dispose();
    phoneController.dispose();
    addressController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (widget.supplier.supplierId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Missing supplier id — can't save.")),
      );
      return;
    }
    if (nameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter a supplier name.")),
      );
      return;
    }

    setState(() => _saving = true);

    final success = await InventoryService.updateSupplier(
      supplierId: widget.supplier.supplierId!,
      name: nameController.text.trim(),
      contactNumber: phoneController.text.trim(),
      address: addressController.text.trim(),
    );

    if (!mounted) return;
    setState(() => _saving = false);

    if (success) {
      widget.onSaved?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Supplier updated.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to update supplier. Please try again.")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
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
                  "Edit supplier details",
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                InkWell(
                  onTap: () {
                    Navigator.pop(context);
                  },
                  child: const Icon(Icons.close),
                )
              ],
            ),

            const SizedBox(height: 25),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Current Supplier Name",
                    _textValue(widget.supplier.name),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: _field(
                    "Supplier Name",
                    TextField(
                      controller: nameController,
                      decoration: _decoration("Supplier Name"),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 20),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Current Supplier Address",
                    _textValue(widget.supplier.address),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: _field(
                    "Address",
                    TextField(
                      controller: addressController,
                      decoration: _decoration("Address"),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 20),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Current Supplier Contact no.",
                    _textValue(widget.supplier.phone),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: _field(
                    "Contact no.",
                    TextField(
                      controller: phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: _decoration("Contact no."),
                    ),
                  ),
                ),
              ],
            ),

            const Divider(
              height: 40,
            ),

            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: _saving ? null : () {
                    Navigator.pop(context);
                  },
                  child: const Text(
                    "Cancel",
                    style: TextStyle(
                      color: Colors.black54,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                ElevatedButton(
                  onPressed: _saving ? null : _save,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kDarkPill,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 22,
                      vertical: 13,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text("Save Changes"),
                )
              ],
            )
          ],
        ),
      ),
    );
  }

  Widget _textValue(String text) {
    return Container(
      height: 50,
      alignment: Alignment.centerLeft,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: const Color(0xffF7F7F7),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: Colors.grey.shade300,
        ),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Colors.black87,
        ),
      ),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.w600,
            color: Colors.grey,
          ),
        ),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(
          color: Colors.grey.shade300,
        ),
      ),
    );
  }
}

class _EditInventoryModal extends StatefulWidget {
  final InventoryItem item;
  final VoidCallback? onSaved;

  const _EditInventoryModal({required this.item, this.onSaved});

  @override
  State<_EditInventoryModal> createState() => _EditInventoryModalState();
}

class _EditInventoryModalState extends State<_EditInventoryModal> {
  late TextEditingController nameController;
  late TextEditingController stockController;

  bool _saving = false;

  @override
  void initState() {
    super.initState();
    nameController = TextEditingController(text: widget.item.name);
    stockController = TextEditingController(text: widget.item.stock.toString());
  }

  @override
  void dispose() {
    nameController.dispose();
    stockController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (widget.item.itemId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Missing item id — can't save.")),
      );
      return;
    }

    final newName = nameController.text.trim();
    final newStock = double.tryParse(stockController.text.trim());

    if (newName.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter an item name.")),
      );
      return;
    }
    if (newStock == null || newStock < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter a valid stock amount.")),
      );
      return;
    }

    setState(() => _saving = true);

    final success = await InventoryService.updateItem(
      itemId: widget.item.itemId!,
      itemName: newName,
      currentStock: newStock,
    );

    if (!mounted) return;
    setState(() => _saving = false);

    if (success) {
      widget.onSaved?.call();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Item updated.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to update item. Please try again.")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(12),
      child: Container(
        padding: const EdgeInsets.all(30),
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
                  "Edit item details",
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                ),
                InkWell(
                  onTap: () => Navigator.pop(context),
                  child: const Icon(Icons.close),
                ),
              ],
            ),

            const SizedBox(height: 25),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Current Item Name",
                    _textValue(widget.item.name),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: _field(
                    "Item Name",
                    TextField(
                      controller: nameController,
                      decoration: _decoration("Enter item name"),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 20),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Current Category",
                    _textValue(widget.item.category),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: _field(
                    "Current Unit",
                    _textValue(widget.item.unit),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 20),

            Row(
              children: [
                Expanded(
                  child: _field(
                    "Current Stock",
                    _textValue("${widget.item.stock} ${widget.item.unit}"),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: _field(
                    "New Stock",
                    TextField(
                      controller: stockController,
                      keyboardType: TextInputType.number,
                      decoration: _decoration("Enter stock amount"),
                    ),
                  ),
                ),
              ],
            ),

            const Divider(height: 40),

            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: _saving ? null : () => Navigator.pop(context),
                  child: const Text(
                    "Cancel",
                    style: TextStyle(color: Colors.black54),
                  ),
                ),
                const SizedBox(width: 12),
                ElevatedButton(
                  onPressed: _saving ? null : _save,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kDarkPill,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 22,
                      vertical: 13,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text("Save Changes"),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _textValue(String text) {
    return Container(
      height: 50,
      alignment: Alignment.centerLeft,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: const Color(0xffF7F7F7),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Text(text, style: const TextStyle(color: Colors.black87)),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey),
        ),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
    );
  }
}