import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../widgets/app_bottom_nav_bar.dart';
import '../widgets/ops_bottom_nav_bar.dart';
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
  final int stock;
  final double quantity;
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
  final bool operationsMode;

  const InventoryTrackingScreen({
    super.key,
    this.email = '',
    this.operationsMode = false,
  });

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

  final TextEditingController _inventorySearchController = TextEditingController();
  final TextEditingController _itemsSearchController = TextEditingController();
  final TextEditingController _suppliersSearchController = TextEditingController();

  String _inventoryQuery = '';
  String _itemsQuery = '';
  String _suppliersQuery = '';

  // null = All
  StockStatus? _inventoryStatusFilter;

  // Pagination — one page cursor per tab, reset whenever that tab's
  // filtered list could change shape (search, status filter, refresh).
  static const int _pageSize = 10;
  int _inventoryPage = 0;
  int _itemsPage = 0;
  int _suppliersPage = 0;

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
    _inventorySearchController.dispose();
    _itemsSearchController.dispose();
    _suppliersSearchController.dispose();
    super.dispose();
  }

  Future<void> _loadInventoryItems() async {
    setState(() {
      _loadingItems = true;
      _itemsError = null;
    });
    try {
      final data = await InventoryService.fetchTransactions();
      if (mounted) {
        final parsed = data.map((json) => InventoryItem.fromJson(json)).toList();
        parsed.sort((a, b) => (b.transactionId ?? 0).compareTo(a.transactionId ?? 0));
        setState(() {
          _items = parsed;
          _loadingItems = false;
          _inventoryPage = 0;
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
          _suppliersPage = 0;
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
        _itemsPage = 0;
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

  void _refreshAfterItemChange() {
    _loadItemRecords();
    _loadInventoryItems();
  }

  int get _totalItemsCount => _itemRecords.length;

  int get _lowStockItemsCount =>
      _itemRecords.where((i) => i.currentStock < kLowStockThreshold).length;

  int get _totalSuppliersCount => _suppliers.length;

  List<InventoryItem> get _filteredInventoryItems {
    final query = _inventoryQuery.trim().toLowerCase();
    return _items.where((item) {
      if (_inventoryStatusFilter != null && item.status != _inventoryStatusFilter) {
        return false;
      }
      if (query.isEmpty) return true;
      return item.name.toLowerCase().contains(query) ||
          item.category.toLowerCase().contains(query) ||
          item.unit.toLowerCase().contains(query) ||
          (item.projectName ?? '').toLowerCase().contains(query);
    }).toList();
  }

  List<ItemRecord> get _filteredItemRecords {
    final query = _itemsQuery.trim().toLowerCase();
    if (query.isEmpty) return _itemRecords;
    return _itemRecords.where((item) {
      return item.name.toLowerCase().contains(query) ||
          item.categoryName.toLowerCase().contains(query) ||
          item.unitName.toLowerCase().contains(query) ||
          item.supplierName.toLowerCase().contains(query);
    }).toList();
  }

  List<Supplier> get _filteredSuppliers {
    final query = _suppliersQuery.trim().toLowerCase();
    if (query.isEmpty) return _suppliers;
    return _suppliers.where((supplier) {
      return supplier.name.toLowerCase().contains(query) ||
          supplier.phone.toLowerCase().contains(query) ||
          supplier.address.toLowerCase().contains(query);
    }).toList();
  }

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

  Widget _buildSearchAndFilterBar(bool isCompact) {
    switch (_currentTab) {
      case 0:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SearchField(
              controller: _inventorySearchController,
              hint: 'Search by item, category, or project...',
              onChanged: (v) => setState(() {
                _inventoryQuery = v;
                _inventoryPage = 0;
              }),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                _FilterChip(
                  label: 'All',
                  selected: _inventoryStatusFilter == null,
                  onTap: () => setState(() {
                    _inventoryStatusFilter = null;
                    _inventoryPage = 0;
                  }),
                ),
                _FilterChip(
                  label: 'Stock In',
                  selected: _inventoryStatusFilter == StockStatus.stockIn,
                  onTap: () => setState(() {
                    _inventoryStatusFilter = StockStatus.stockIn;
                    _inventoryPage = 0;
                  }),
                ),
                _FilterChip(
                  label: 'Stock Out',
                  selected: _inventoryStatusFilter == StockStatus.stockOut,
                  onTap: () => setState(() {
                    _inventoryStatusFilter = StockStatus.stockOut;
                    _inventoryPage = 0;
                  }),
                ),
              ],
            ),
          ],
        );
      case 1:
        return _SearchField(
          controller: _itemsSearchController,
          hint: 'Search by item, category, or supplier...',
          onChanged: (v) => setState(() {
            _itemsQuery = v;
            _itemsPage = 0;
          }),
        );
      case 2:
        return _SearchField(
          controller: _suppliersSearchController,
          hint: 'Search by supplier, phone, or address...',
          onChanged: (v) => setState(() {
            _suppliersQuery = v;
            _suppliersPage = 0;
          }),
        );
      default:
        return const SizedBox.shrink();
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;

    final filteredInventory = _filteredInventoryItems;
    final inventoryTotal = filteredInventory.length;
    final inventoryMaxPage = inventoryTotal == 0 ? 0 : (inventoryTotal - 1) ~/ _pageSize;
    final inventoryPage = _inventoryPage.clamp(0, inventoryMaxPage);
    final inventoryStart = inventoryPage * _pageSize;
    final inventoryEnd = (inventoryStart + _pageSize) > inventoryTotal
        ? inventoryTotal
        : (inventoryStart + _pageSize);
    final pagedInventory = filteredInventory.sublist(inventoryStart, inventoryEnd);

    final filteredItems = _filteredItemRecords;
    final itemsTotal = filteredItems.length;
    final itemsMaxPage = itemsTotal == 0 ? 0 : (itemsTotal - 1) ~/ _pageSize;
    final itemsPage = _itemsPage.clamp(0, itemsMaxPage);
    final itemsStart = itemsPage * _pageSize;
    final itemsEnd = (itemsStart + _pageSize) > itemsTotal ? itemsTotal : (itemsStart + _pageSize);
    final pagedItems = filteredItems.sublist(itemsStart, itemsEnd);

    final filteredSuppliers = _filteredSuppliers;
    final suppliersTotal = filteredSuppliers.length;
    final suppliersMaxPage = suppliersTotal == 0 ? 0 : (suppliersTotal - 1) ~/ _pageSize;
    final suppliersPage = _suppliersPage.clamp(0, suppliersMaxPage);
    final suppliersStart = suppliersPage * _pageSize;
    final suppliersEnd = (suppliersStart + _pageSize) > suppliersTotal
        ? suppliersTotal
        : (suppliersStart + _pageSize);
    final pagedSuppliers = filteredSuppliers.sublist(suppliersStart, suppliersEnd);

    return Scaffold(
      appBar: AppHeader(email: widget.email),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
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
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      widget.operationsMode ? 'INVENTORY RECORDS' : 'INVENTORY',
                                      style: const TextStyle(
                                        fontSize: 27,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.dark,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    const Text(
                                      'construction operation overview',
                                      style: TextStyle(fontSize: 14, color: Colors.grey),
                                    ),
                                  ],
                                ),
                              ),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                crossAxisAlignment: WrapCrossAlignment.center,
                                children: [
                                  if (_currentTab == 1)
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
                                        side: const BorderSide(color: kDarkPill, width: 1.4),
                                        padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 12),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                                        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                                      ),
                                      icon: const Icon(Icons.inventory_2_outlined, size: 19),
                                      label: const Text('Add Item'),
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
                                        padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 12),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                                        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                                      ),
                                      icon: const Icon(Icons.add, size: 19),
                                      label: Text(
                                        _currentTab == 0 ? 'Add Transaction' : 'Add Supplier',
                                      ),
                                    ),
                                ],
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          _StatsGrid(
                            totalItems: _totalItemsCount,
                            lowStock: _lowStockItemsCount,
                            totalSuppliers: _totalSuppliersCount,
                            movementsIn: _movementsInThisMonth,
                            movementsOut: _movementsOutThisMonth,
                            onSelectTab: _goToTab,
                          ),
                          const SizedBox(height: 12),
                          TabBar(
                            controller: _tabController,
                            labelColor: AppColors.orange,
                            unselectedLabelColor: Colors.black54,
                            indicatorColor: AppColors.orange,
                            indicatorSize: TabBarIndicatorSize.label,
                            labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
                            unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 17),
                            tabs: const [
                              Tab(text: 'Inventory'),
                              Tab(text: 'Items'),
                              Tab(text: 'Suppliers'),
                            ],
                          ),
                          const SizedBox(height: 10),
                          _buildSearchAndFilterBar(isCompact),
                        ],
                      ),
                    ),
                    SizedBox(
                      height: screenHeight * 0.55,
                      child: TabBarView(
                        controller: _tabController,
                        children: [
                          // Inventory Tab
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
                                  : Column(
                                      children: [
                                        Expanded(
                                          child: _InventoryList(
                                            items: pagedInventory,
                                            formatDate: _formatDate,
                                            onTap: (item) {
                                              showDialog(
                                                context: context,
                                                barrierDismissible: false,
                                                builder: (_) => _InventoryDetailsModal(
                                                  item: item,
                                                  rootContext: context,
                                                  onChanged: _loadInventoryItems,
                                                ),
                                              );
                                            },
                                          ),
                                        ),
                                        Container(
                                          decoration: BoxDecoration(
                                            border: Border(top: BorderSide(color: Colors.grey.shade200)),
                                          ),
                                          child: _PaginationFooter(
                                            start: inventoryStart,
                                            end: inventoryEnd,
                                            total: inventoryTotal,
                                            label: 'transactions',
                                            canGoBack: inventoryPage > 0,
                                            canGoForward: inventoryEnd < inventoryTotal,
                                            onBack: () => setState(() => _inventoryPage = inventoryPage - 1),
                                            onForward: () => setState(() => _inventoryPage = inventoryPage + 1),
                                          ),
                                        ),
                                      ],
                                    ),
                          // Items Tab
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
                                  : Column(
                                      children: [
                                        Expanded(
                                          child: _ItemsList(
                                            items: pagedItems,
                                            onTap: (item) {
                                              showDialog(
                                                context: context,
                                                barrierDismissible: false,
                                                builder: (_) => _ItemDetailsModal(
                                                  item: item,
                                                  rootContext: context,
                                                  onChanged: _refreshAfterItemChange,
                                                ),
                                              );
                                            },
                                          ),
                                        ),
                                        Container(
                                          decoration: BoxDecoration(
                                            border: Border(top: BorderSide(color: Colors.grey.shade200)),
                                          ),
                                          child: _PaginationFooter(
                                            start: itemsStart,
                                            end: itemsEnd,
                                            total: itemsTotal,
                                            label: 'items',
                                            canGoBack: itemsPage > 0,
                                            canGoForward: itemsEnd < itemsTotal,
                                            onBack: () => setState(() => _itemsPage = itemsPage - 1),
                                            onForward: () => setState(() => _itemsPage = itemsPage + 1),
                                          ),
                                        ),
                                      ],
                                    ),
                          // Suppliers Tab
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
                                  : Column(
                                      children: [
                                        Expanded(
                                          child: _SuppliersList(suppliers: pagedSuppliers, onChanged: _loadSuppliers),
                                        ),
                                        Container(
                                          decoration: BoxDecoration(
                                            border: Border(top: BorderSide(color: Colors.grey.shade200)),
                                          ),
                                          child: _PaginationFooter(
                                            start: suppliersStart,
                                            end: suppliersEnd,
                                            total: suppliersTotal,
                                            label: 'suppliers',
                                            canGoBack: suppliersPage > 0,
                                            canGoForward: suppliersEnd < suppliersTotal,
                                            onBack: () => setState(() => _suppliersPage = suppliersPage - 1),
                                            onForward: () => setState(() => _suppliersPage = suppliersPage + 1),
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
            ),
          ],
        ),
      ),
      bottomNavigationBar: widget.operationsMode
          ? OpsBottomNavBar(currentIndex: 2, email: widget.email)
          : AppBottomNavBar(currentIndex: 3, email: widget.email),
    );
  }
}

/// Pagination footer — "start–end out of total <label>" alongside prev/next
/// controls. Shared by the Inventory, Items, and Suppliers tabs. Styled to
/// match budget_tracking_screen's flat, borderless footer (no background
/// fill/shadow of its own — callers wrap it with a top divider instead).
class _PaginationFooter extends StatelessWidget {
  final int start;
  final int end;
  final int total;
  final String label;
  final bool canGoBack;
  final bool canGoForward;
  final VoidCallback onBack;
  final VoidCallback onForward;

  const _PaginationFooter({
    required this.start,
    required this.end,
    required this.total,
    required this.label,
    required this.canGoBack,
    required this.canGoForward,
    required this.onBack,
    required this.onForward,
  });

  @override
  Widget build(BuildContext context) {
    if (total == 0) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            "${start + 1}-$end out of $total $label",
            style: TextStyle(fontSize: 11.5, color: Colors.grey[600], fontWeight: FontWeight.w500),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              InkWell(
                borderRadius: BorderRadius.circular(20),
                onTap: canGoBack ? onBack : null,
                child: Container(
                  width: 30,
                  height: 30,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.grey[300]!),
                  ),
                  child: Icon(Icons.chevron_left, size: 18,
                      color: canGoBack ? Colors.black87 : Colors.grey[350]),
                ),
              ),
              const SizedBox(width: 8),
              InkWell(
                borderRadius: BorderRadius.circular(20),
                onTap: canGoForward ? onForward : null,
                child: Container(
                  width: 30,
                  height: 30,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.grey[300]!),
                  ),
                  child: Icon(Icons.chevron_right, size: 18,
                      color: canGoForward ? Colors.black87 : Colors.grey[350]),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// Stats Grid
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
    final isCompact = MediaQuery.of(context).size.width < 380;
    final spacing = isCompact ? 6.0 : 10.0;
    final padding = isCompact ? 8.0 : 12.0;

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
                padding: padding,
              ),
            ),
            SizedBox(width: spacing),
            Expanded(
              child: _StatCard(
                label: 'LOW STOCK',
                value: '$lowStock',
                caption: lowStock == 0 ? 'All items healthy' : 'Below $kLowStockThreshold units',
                valueColor: lowStock == 0 ? Colors.green.shade700 : const Color(0xFFD23B5C),
                icon: Icons.warning_amber_rounded,
                onTap: () => onSelectTab(1),
                padding: padding,
              ),
            ),
          ],
        ),
        SizedBox(height: spacing),
        Row(
          children: [
            Expanded(
              child: _StatCard(
                label: 'SUPPLIERS',
                value: '$totalSuppliers',
                caption: 'Active partners',
                icon: Icons.local_shipping_outlined,
                onTap: () => onSelectTab(2),
                padding: padding,
              ),
            ),
            SizedBox(width: spacing),
            Expanded(
              child: _StatCard(
                label: 'THIS MONTH',
                value: '$movementsIn in / $movementsOut out',
                caption: 'Stock movements',
                icon: Icons.swap_vert_rounded,
                onTap: () => onSelectTab(0),
                padding: padding,
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
  final double padding;

  const _StatCard({
    required this.label,
    required this.value,
    required this.caption,
    this.valueColor,
    this.icon,
    this.onTap,
    this.padding = 12,
  });

  @override
  Widget build(BuildContext context) {
    final isCompact = MediaQuery.of(context).size.width < 380;

    final card = Container(
      padding: EdgeInsets.all(padding),
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
                  style: TextStyle(
                    fontSize: isCompact ? 8 : 10,
                    fontWeight: FontWeight.w700,
                    color: Colors.black45,
                    letterSpacing: .3,
                  ),
                ),
              ),
              if (icon != null) Icon(icon, size: isCompact ? 12 : 14, color: Colors.black26),
            ],
          ),
          SizedBox(height: isCompact ? 4 : 6),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: TextStyle(
                fontSize: isCompact ? 16 : 20,
                fontWeight: FontWeight.w800,
                color: valueColor ?? AppColors.dark,
              ),
            ),
          ),
          SizedBox(height: isCompact ? 2 : 4),
          Text(
            caption,
            style: TextStyle(
              fontSize: isCompact ? 8 : 10,
              color: Colors.black38,
            ),
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

// Inventory List
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
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final item = items[index];
        final isIn = item.status == StockStatus.stockIn;
        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => onTap(item),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.grey[200]!),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.dark)),
                      const SizedBox(height: 2),
                      Text('${item.category} · ${item.unit}', style: const TextStyle(fontSize: 11.5, color: Colors.black54)),
                      if (!isIn && (item.projectName ?? '').isNotEmpty && item.projectName != '-')
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text('For: ${item.projectName}', style: const TextStyle(fontSize: 10.5, color: Colors.black45)),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${isIn ? '+' : '-'}${_formatQty(item.quantity)} ${item.unit}',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.dark),
                        ),
                        const SizedBox(width: 6),
                        _StatusPill(
                          text: isIn ? 'IN' : 'OUT',
                          background: isIn ? const Color(0xFFDCF2DE) : const Color(0xFFFBDCE0),
                          textColor: isIn ? const Color(0xFF2E8B3D) : const Color(0xFFD23B5C),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(formatDate(item.date), style: const TextStyle(fontSize: 10, color: Colors.black38)),
                    const SizedBox(height: 2),
                    Text('Stock now: ${item.stock}', style: const TextStyle(fontSize: 9.5, color: Colors.black38)),
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

// Suppliers List
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
      separatorBuilder: (_, _) => const SizedBox(height: 8),
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
                rootContext: context,
                onChanged: onChanged,
              ),
            );
          },
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.grey[200]!),
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
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: AppColors.dark,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${supplier.itemCount} item${supplier.itemCount == 1 ? '' : 's'} supplied',
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: Colors.black54,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        supplier.phone,
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
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

// Items List
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
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final item = items[index];
        final lowStock = item.currentStock < kLowStockThreshold;
        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => onTap(item),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.grey[200]!),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.dark)),
                      const SizedBox(height: 2),
                      Text('${item.categoryName} · ${item.unitName}', style: const TextStyle(fontSize: 11.5, color: Colors.black54)),
                      const SizedBox(height: 2),
                      Text('Supplier: ${item.supplierName}', style: const TextStyle(fontSize: 10.5, color: Colors.black45)),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '${_formatStock(item.currentStock)} ${item.unitName}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.dark),
                    ),
                    const SizedBox(height: 4),
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

// Item Details Modal
class _ItemDetailsModal extends StatelessWidget {
  final ItemRecord item;
  final VoidCallback? onChanged;
  final BuildContext rootContext;

  const _ItemDetailsModal({
    required this.item,
    required this.rootContext,
    this.onChanged,
  });

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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 20.0 : 30.0;
    final fontSize = isCompact ? 22.0 : 26.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24)),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text("Item Details", style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.bold)),
                    InkWell(onTap: () => Navigator.pop(context), child: const Icon(Icons.close)),
                  ],
                ),
                const SizedBox(height: 20),
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
                        const SizedBox(width: 8),
                        ElevatedButton(
                          onPressed: () {
                            Navigator.pop(context);
                            if (!rootContext.mounted) return;
                            showDialog(
                              context: rootContext,
                              barrierDismissible: false,
                              builder: (_) => _EditItemFullModal(item: item, onSaved: onChanged),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
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
        ),
      ),
    );
  }

  Widget _detail(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700, color: Colors.grey)),
          const SizedBox(height: 4),
          Text(value, style: const TextStyle(fontSize: 15, color: Colors.black87)),
        ],
      ),
    );
  }
}

// Edit Item Full Modal
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 16.0 : 30.0;
    final fontSize = isCompact ? 22.0 : 26.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  : SingleChildScrollView(
                      child: _buildForm(context, isCompact, fontSize),
                    ),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context, bool isCompact, double fontSize) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text("Edit item", style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.bold)),
            InkWell(onTap: _saving ? null : () => Navigator.pop(context), child: const Icon(Icons.close)),
          ],
        ),
        const SizedBox(height: 20),
        _field("Item Name", TextField(controller: nameController, decoration: _decoration("Item Name"))),
        const SizedBox(height: 16),
        _field(
          "Item Category",
          _SearchableDropdownField(
            value: selectedCategoryId,
            hint: "Choose Category...",
            items: _categories,
            idKey: "inventory_category_id",
            nameKey: "inventory_category_name",
            onChanged: (id, name) => setState(() => selectedCategoryId = id),
          ),
        ),
        const SizedBox(height: 16),
        _field(
          "Item Unit",
          _SearchableDropdownField(
            value: selectedUnitId,
            hint: "Choose Unit...",
            items: _units,
            idKey: "unit_id",
            nameKey: "unit_name",
            onChanged: (id, name) => setState(() => selectedUnitId = id),
          ),
        ),
        const SizedBox(height: 16),
        _field(
          "Item Supplier",
          _SearchableDropdownField(
            value: selectedSupplierId,
            hint: "Choose Supplier...",
            items: _suppliers,
            idKey: "supplier_id",
            nameKey: "supplier_name",
            onChanged: (id, name) => setState(() => selectedSupplierId = id),
          ),
        ),
        const SizedBox(height: 16),
        _field(
          "Current Stock",
          TextField(
            controller: stockController,
            keyboardType: TextInputType.number,
            decoration: _decoration("Enter stock amount"),
          ),
        ),
        const SizedBox(height: 24),
        const Divider(),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: _saving ? null : () => Navigator.pop(context),
              child: const Text("Cancel", style: TextStyle(color: Colors.black54)),
            ),
            ElevatedButton(
              onPressed: _saving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: kDarkPill,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _saving
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text("Save Changes"),
            ),
          ],
        ),
      ],
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey, fontSize: 13)),
        const SizedBox(height: 6),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}

// Search Field (used for the Inventory / Items / Suppliers list filters)
class _SearchField extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;

  const _SearchField({
    required this.controller,
    required this.hint,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      onChanged: onChanged,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(fontSize: 13, color: Colors.grey.shade400),
        prefixIcon: const Icon(Icons.search, size: 20),
        suffixIcon: controller.text.isNotEmpty
            ? InkWell(
                onTap: () {
                  controller.clear();
                  onChanged('');
                },
                child: const Icon(Icons.close, size: 18),
              )
            : null,
        filled: true,
        fillColor: AppColors.card,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      ),
      style: const TextStyle(fontSize: 13.5),
    );
  }
}

// Filter Chip (All / Stock In / Stock Out)
class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: selected ? kDarkPill : AppColors.card,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: selected ? kDarkPill : Colors.grey.shade300),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11.5,
            fontWeight: FontWeight.w700,
            color: selected ? Colors.white : Colors.black54,
          ),
        ),
      ),
    );
  }
}

// Searchable dropdown field — tapping opens a bottom sheet with a search
// box so long lists of items/categories/units/suppliers/projects are easy
// to filter instead of scrolling a plain dropdown menu. The sheet itself
// caps the visible option list to roughly five rows (see
// _SearchPickerSheet) so it never balloons to fill the screen — beyond
// that it scrolls, with a visible scrollbar as a clear affordance.
class _SearchableDropdownField extends StatelessWidget {
  final int? value;
  final String hint;
  final List<Map<String, dynamic>> items;
  final String idKey;
  final String nameKey;
  final String? subtitleKey;
  final void Function(int id, String name) onChanged;

  const _SearchableDropdownField({
    required this.value,
    required this.hint,
    required this.items,
    required this.idKey,
    required this.nameKey,
    required this.onChanged,
    this.subtitleKey,
  });

  @override
  Widget build(BuildContext context) {
    final matches = items.where((i) => i[idKey] == value);
    final selectedName = matches.isNotEmpty ? (matches.first[nameKey] as String? ?? '') : null;

    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: () async {
        final result = await showModalBottomSheet<Map<String, dynamic>>(
          context: context,
          isScrollControlled: true,
          backgroundColor: Colors.transparent,
          builder: (_) => _SearchPickerSheet(
            items: items,
            nameKey: nameKey,
            subtitleKey: subtitleKey,
            title: hint,
          ),
        );
        if (result != null) {
          final rawId = result[idKey];
          final resolvedId = rawId is int ? rawId : int.tryParse('$rawId');
          if (resolvedId != null) {
            onChanged(resolvedId, result[nameKey] as String? ?? '');
          }
        }
      },
      child: Container(
        height: 46,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade300),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                selectedName?.isNotEmpty == true ? selectedName! : hint,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 14,
                  color: (selectedName?.isNotEmpty == true) ? Colors.black87 : Colors.grey.shade400,
                ),
              ),
            ),
            Icon(Icons.search, size: 18, color: Colors.grey.shade500),
          ],
        ),
      ),
    );
  }
}

class _SearchPickerSheet extends StatefulWidget {
  final List<Map<String, dynamic>> items;
  final String nameKey;
  final String? subtitleKey;
  final String title;

  const _SearchPickerSheet({
    required this.items,
    required this.nameKey,
    required this.title,
    this.subtitleKey,
  });

  @override
  State<_SearchPickerSheet> createState() => _SearchPickerSheetState();
}

class _SearchPickerSheetState extends State<_SearchPickerSheet> {
  final _controller = TextEditingController();
  String _query = '';

  // Roughly five rows tall (~56dp each including the divider) before the
  // list scrolls — keeps the sheet compact and predictable regardless of
  // how many options the list holds.
  static const double _listMaxHeight = 280;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = _query.trim().toLowerCase();
    final filtered = query.isEmpty
        ? widget.items
        : widget.items.where((item) {
            final name = (item[widget.nameKey] as String? ?? '').toLowerCase();
            final subtitle = widget.subtitleKey != null
                ? (item[widget.subtitleKey!] as String? ?? '').toLowerCase()
                : '';
            return name.contains(query) || subtitle.contains(query);
          }).toList();

    final screenHeight = MediaQuery.of(context).size.height;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        constraints: BoxConstraints(maxHeight: screenHeight * 0.65),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      widget.title,
                      style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                    ),
                  ),
                  InkWell(onTap: () => Navigator.pop(context), child: const Icon(Icons.close)),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: TextField(
                controller: _controller,
                autofocus: true,
                onChanged: (v) => setState(() => _query = v),
                decoration: InputDecoration(
                  hintText: 'Search...',
                  prefixIcon: const Icon(Icons.search, size: 20),
                  suffixIcon: _query.isNotEmpty
                      ? InkWell(
                          onTap: () {
                            _controller.clear();
                            setState(() => _query = '');
                          },
                          child: const Icon(Icons.close, size: 18),
                        )
                      : null,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
            ),
            const SizedBox(height: 8),
            if (filtered.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 40),
                child: Text(
                  _query.isEmpty ? 'No options available.' : 'No matches for "${_query.trim()}"',
                  style: const TextStyle(color: Colors.black45),
                ),
              )
            else
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: _listMaxHeight),
                child: Scrollbar(
                  thumbVisibility: filtered.length > 5,
                  child: ListView.separated(
                    shrinkWrap: true,
                    padding: const EdgeInsets.only(bottom: 8),
                    itemCount: filtered.length,
                    separatorBuilder: (context, index) => Divider(height: 1, color: Colors.grey.shade200),
                    itemBuilder: (context, index) {
                      final item = filtered[index];
                      final subtitle = widget.subtitleKey != null
                          ? item[widget.subtitleKey!] as String?
                          : null;
                      return ListTile(
                        title: Text(item[widget.nameKey] as String? ?? ''),
                        subtitle: (subtitle != null && subtitle.isNotEmpty) ? Text(subtitle) : null,
                        onTap: () => Navigator.pop(context, item),
                      );
                    },
                  ),
                ),
              ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }
}

// Status Pill
class _StatusPill extends StatelessWidget {
  final String text;
  final Color background;
  final Color textColor;

  const _StatusPill({required this.text, required this.background, required this.textColor});

  @override
  Widget build(BuildContext context) {
    final isCompact = MediaQuery.of(context).size.width < 380;

    return Container(
      padding: EdgeInsets.symmetric(horizontal: isCompact ? 6 : 10, vertical: isCompact ? 3 : 5),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
      child: Text(
        text,
        style: TextStyle(
          fontSize: isCompact ? 9 : 11,
          fontWeight: FontWeight.w700,
          color: textColor,
        ),
      ),
    );
  }
}

// Add Item Modal
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 16.0 : 30.0;
    final fontSize = isCompact ? 24.0 : 30.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  : SingleChildScrollView(
                      child: _buildForm(context, isCompact, fontSize),
                    ),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context, bool isCompact, double fontSize) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              "Add new item",
              style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.bold),
            ),
            InkWell(
              onTap: () => Navigator.pop(context),
              child: const Icon(Icons.close),
            ),
          ],
        ),
        const SizedBox(height: 20),
        _field(
          "Item Name",
          TextField(
            controller: itemNameController,
            decoration: _decoration("Item Name"),
          ),
        ),
        const SizedBox(height: 16),
        _field(
          "Item Category",
          _SearchableDropdownField(
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
        const SizedBox(height: 16),
        _field(
          "Item Unit",
          _SearchableDropdownField(
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
        const SizedBox(height: 16),
        _field(
          "Item Supplier",
          _SearchableDropdownField(
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
        const SizedBox(height: 24),
        const Divider(),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: _saving ? null : () => Navigator.pop(context),
              child: Text(
                "Cancel",
                style: TextStyle(fontSize: isCompact ? 16 : 18, color: Colors.black54),
              ),
            ),
            ElevatedButton(
              onPressed: _saving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: kDarkPill,
                foregroundColor: Colors.white,
                padding: EdgeInsets.symmetric(
                  horizontal: isCompact ? 16 : 26,
                  vertical: isCompact ? 10 : 15,
                ),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : Text(
                      "Add Item",
                      style: TextStyle(fontSize: isCompact ? 16 : 18),
                    ),
            ),
          ],
        ),
      ],
    );
  }


  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey, fontSize: 13),
        ),
        const SizedBox(height: 6),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}

// Add Transaction Modal
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

  int? selectedItemId;
  String? selectedItemName;
  String? selectedItemUnitName;
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 16.0 : 30.0;
    final fontSize = isCompact ? 24.0 : 30.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  : SingleChildScrollView(
                      child: _buildForm(context, isCompact, fontSize),
                    ),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context, bool isCompact, double fontSize) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              "Add new transaction",
              style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.bold),
            ),
            InkWell(
              onTap: () => Navigator.pop(context),
              child: const Icon(Icons.close),
            ),
          ],
        ),
        const SizedBox(height: 20),
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
                            selectedProjectId = null;
                            selectedProjectName = null;
                          }),
                          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        const Text("IN"),
                        const SizedBox(width: 16),
                        Radio(
                          value: "OUT",
                          groupValue: transactionType,
                          onChanged: (v) => setState(() => transactionType = "OUT"),
                          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        const Text("OUT"),
                      ],
                    ),
                    Text(
                      transactionType == "IN" ? "Item Stock in" : "Item Stock out",
                      style: const TextStyle(color: Colors.grey, fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _field(
                "Transaction Date",
                InkWell(
                  onTap: _pickDate,
                  child: IgnorePointer(
                    child: TextField(
                      controller: dateController,
                      decoration: _decoration("DD-MM-YYYY").copyWith(
                        suffixIcon: const Icon(Icons.calendar_month, size: 18),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                      ),
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (transactionType == "OUT") ...[
          _field(
            "Project",
            _SearchableDropdownField(
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
          const SizedBox(height: 16),
        ],
        _field(
          "Item Name",
          _SearchableDropdownField(
            value: selectedItemId,
            hint: "Choose Item...",
            items: _items,
            idKey: "item_id",
            nameKey: "item_name",
            subtitleKey: "unit_name",
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
                quantity = 1;
                quantityController.text = "1";
              });
            },
          ),
        ),
        if (selectedItemId != null && selectedItemCurrentStock != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              "Available stock: ${selectedItemCurrentStock! % 1 == 0 ? selectedItemCurrentStock!.toInt() : selectedItemCurrentStock} ${selectedItemUnitName ?? ''}",
              style: const TextStyle(fontSize: 11.5, color: Colors.black54),
            ),
          ),
        const SizedBox(height: 16),
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
              }, isCompact),
              const SizedBox(width: 8),
              Expanded(
                child: TextField(
                  controller: quantityController,
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  decoration: _decoration("Quantity").copyWith(
                    suffixText: selectedItemUnitName,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                  ),
                  style: const TextStyle(fontSize: 14),
                  onChanged: (value) {
                    final number = int.tryParse(value);
                    if (number != null) quantity = number;
                  },
                ),
              ),
              const SizedBox(width: 8),
              _smallBtn("+", () {
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
              }, isCompact),
            ],
          ),
        ),
        const SizedBox(height: 24),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(
                "Cancel",
                style: TextStyle(fontSize: isCompact ? 16 : 18, color: Colors.black54),
              ),
            ),
            ElevatedButton(
              onPressed: () {
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
              style: ElevatedButton.styleFrom(
                backgroundColor: kDarkPill,
                foregroundColor: Colors.white,
                padding: EdgeInsets.symmetric(
                  horizontal: isCompact ? 16 : 26,
                  vertical: isCompact ? 10 : 15,
                ),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: Text(
                "Review",
                style: TextStyle(fontSize: isCompact ? 16 : 18),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey, fontSize: 13),
        ),
        const SizedBox(height: 6),
        child,
      ],
    );
  }

  Widget _smallBtn(String text, VoidCallback tap, bool isCompact) {
    return InkWell(
      onTap: tap,
      child: Container(
        width: isCompact ? 28 : 32,
        height: isCompact ? 34 : 38,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: kDarkPill,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(text, style: TextStyle(color: Colors.white, fontSize: isCompact ? 16 : 20)),
      ),
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}

// Review Transaction Modal
class _ReviewTransactionModal extends StatefulWidget {
  final VoidCallback? onSaved;

  final int? projectId;
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
    return "${parts[2]}-${parts[1]}-${parts[0]}";
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
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).pop();
      Navigator.of(context).pop();
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
                    const spacing = 20.0;
                    final isSingleColumn = constraints.maxWidth < 280;
                    final itemWidth = isSingleColumn
                        ? constraints.maxWidth
                        : (constraints.maxWidth - spacing) / 2;

                    return Wrap(
                      spacing: spacing,
                      runSpacing: 16,
                      children: [
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
                const Divider(height: 30),
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  runSpacing: 10,
                  children: [
                    TextButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      child: const Text(
                        "Cancel",
                        style: TextStyle(fontSize: 16, color: Colors.black54),
                      ),
                    ),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        ElevatedButton(
                          onPressed: _saving ? null : () => Navigator.pop(context),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          ),
                          child: const Text("← Back"),
                        ),
                        ElevatedButton(
                          onPressed: _saving ? null : () => _finishTransaction(context),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
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
          const SizedBox(height: 6),
          Text(value, style: const TextStyle(fontSize: 15, color: Colors.black54)),
        ],
      ),
    );
  }
}

// Inventory Details Modal
class _InventoryDetailsModal extends StatelessWidget {
  final InventoryItem item;
  final VoidCallback? onChanged;
  final BuildContext rootContext;

  const _InventoryDetailsModal({
    required this.item,
    required this.rootContext,
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 20.0 : 30.0;
    final fontSize = isCompact ? 22.0 : 26.0;
    final isIn = item.status == StockStatus.stockIn;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      "Inventory Details",
                      style: TextStyle(
                        fontSize: fontSize,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    )
                  ],
                ),
                const SizedBox(height: 20),
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
                    const SizedBox(width: 16),
                    _StatusPill(
                      text: isIn ? "IN" : "OUT",
                      background: isIn ? const Color(0xFFDCF2DE) : const Color(0xFFFBDCE0),
                      textColor: isIn ? const Color(0xFF2E8B3D) : const Color(0xFFD23B5C),
                    )
                  ],
                ),
                const SizedBox(height: 12),
                _detail("Transaction Date", _formatDate(item.date)),
                const SizedBox(height: 20),
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
                        const SizedBox(width: 8),
                        ElevatedButton(
                          onPressed: () {
                            Navigator.pop(context);
                            if (!rootContext.mounted) return;
                            showDialog(
                              context: rootContext,
                              barrierDismissible: false,
                              builder: (_) => _EditInventoryModal(
                                item: item,
                                onSaved: onChanged,
                              ),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 10,
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
        ),
      ),
    );
  }

  Widget _detail(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
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
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 15,
              color: Colors.black87,
            ),
          )
        ],
      ),
    );
  }
}

// Supplier Details Modal
class _SupplierDetailsModal extends StatelessWidget {
  final Supplier supplier;
  final VoidCallback? onChanged;
  final BuildContext rootContext;

  const _SupplierDetailsModal({
    required this.supplier,
    required this.rootContext,
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 20.0 : 30.0;
    final fontSize = isCompact ? 22.0 : 26.0;
    final active = supplier.isActive;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      "Supplier Details",
                      style: TextStyle(
                        fontSize: fontSize,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
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
                    const SizedBox(width: 16),
                    _StatusPill(
                      text: active ? "Active" : "Inactive",
                      background: active ? const Color(0xFFDCF2DE) : const Color(0xFFEAEAEA),
                      textColor: active ? const Color(0xFF2E8B3D) : Colors.black45,
                    ),
                  ],
                ),
                const SizedBox(height: 20),
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
                            style: TextStyle(
                              color: Colors.black54,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        ElevatedButton(
                          onPressed: () {
                            Navigator.pop(context);
                            if (!rootContext.mounted) return;
                            showDialog(
                              context: rootContext,
                              barrierDismissible: false,
                              builder: (_) => _EditSupplierModal(
                                supplier: supplier,
                                onSaved: onChanged,
                              ),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: kDarkPill,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 10,
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
        ),
      ),
    );
  }

  Widget _supplierDetail(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
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
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 15,
              color: Colors.black87,
            ),
          ),
        ],
      ),
    );
  }
}

// Add Supplier Modal
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 16.0 : 30.0;
    final fontSize = isCompact ? 24.0 : 30.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      "Add Supplier",
                      style: TextStyle(
                        fontSize: fontSize,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                _field(
                  "Supplier Name",
                  TextField(
                    controller: nameController,
                    decoration: _decoration("Enter supplier name..."),
                  ),
                ),
                const SizedBox(height: 16),
                _field(
                  "Address",
                  TextField(
                    controller: addressController,
                    decoration: _decoration("Enter supplier address..."),
                    maxLines: 2,
                  ),
                ),
                const SizedBox(height: 16),
                _field(
                  "Contact Number",
                  TextField(
                    controller: phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: _decoration("+63"),
                  ),
                ),
                const SizedBox(height: 24),
                const Divider(),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    TextButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      child: Text(
                        "Cancel",
                        style: TextStyle(
                          fontSize: isCompact ? 16 : 18,
                          color: Colors.black54,
                        ),
                      ),
                    ),
                    ElevatedButton(
                      onPressed: _saving ? null : _save,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        padding: EdgeInsets.symmetric(
                          horizontal: isCompact ? 16 : 26,
                          vertical: isCompact ? 10 : 15,
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
                          : Text(
                              "Add Supplier",
                              style: TextStyle(fontSize: isCompact ? 16 : 18),
                            ),
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

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.w600,
            color: Colors.grey,
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 6),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}

// Edit Supplier Modal
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 16.0 : 30.0;
    final fontSize = isCompact ? 22.0 : 26.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      "Edit supplier details",
                      style: TextStyle(
                        fontSize: fontSize,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    )
                  ],
                ),
                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        "Current Supplier Name",
                        _textValue(widget.supplier.name),
                      ),
                    ),
                    const SizedBox(width: 12),
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
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        "Current Supplier Address",
                        _textValue(widget.supplier.address),
                      ),
                    ),
                    const SizedBox(width: 12),
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
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        "Current Supplier Contact no.",
                        _textValue(widget.supplier.phone),
                      ),
                    ),
                    const SizedBox(width: 12),
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
                const Divider(height: 30),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      child: const Text(
                        "Cancel",
                        style: TextStyle(
                          color: Colors.black54,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton(
                      onPressed: _saving ? null : _save,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 18,
                          vertical: 10,
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
        ),
      ),
    );
  }

  Widget _textValue(String text) {
    return Container(
      height: 46,
      alignment: Alignment.centerLeft,
      padding: const EdgeInsets.symmetric(horizontal: 10),
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
          fontSize: 14,
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
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 6),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(
          color: Colors.grey.shade300,
        ),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
    );
  }
}

// Edit Inventory Modal
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
    final screenHeight = MediaQuery.of(context).size.height;
    final isCompact = MediaQuery.of(context).size.width < 380;
    final padding = isCompact ? 16.0 : 30.0;
    final fontSize = isCompact ? 22.0 : 26.0;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: screenHeight * 0.85,
        ),
        child: Container(
          padding: EdgeInsets.all(padding),
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
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      "Edit item details",
                      style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.bold),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        "Current Item Name",
                        _textValue(widget.item.name),
                      ),
                    ),
                    const SizedBox(width: 12),
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
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        "Current Category",
                        _textValue(widget.item.category),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        "Current Unit",
                        _textValue(widget.item.unit),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        "Current Stock",
                        _textValue("${widget.item.stock} ${widget.item.unit}"),
                      ),
                    ),
                    const SizedBox(width: 12),
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
                const Divider(height: 30),
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
                    const SizedBox(width: 8),
                    ElevatedButton(
                      onPressed: _saving ? null : _save,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 18,
                          vertical: 10,
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
        ),
      ),
    );
  }

  Widget _textValue(String text) {
    return Container(
      height: 46,
      alignment: Alignment.centerLeft,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: const Color(0xffF7F7F7),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.black87, fontSize: 14),
      ),
    );
  }

  Widget _field(String title, Widget child) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.grey, fontSize: 13),
        ),
        const SizedBox(height: 6),
        child,
      ],
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
    );
  }
}
