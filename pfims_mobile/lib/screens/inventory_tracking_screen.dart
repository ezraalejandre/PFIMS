import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../widgets/app_bottom_nav_bar.dart';
import '../widgets/app_header.dart';
import '../services/inventory_service.dart';

const Color kDarkPill = Color(0xFF14161F);

enum StockStatus { stockIn, stockOut }

class InventoryItem {
  final int? itemId;
  final String name;
  final String category;
  final String unit;
  final int stock;
  final StockStatus status;
  final DateTime date;

  const InventoryItem({
    this.itemId,
    required this.name,
    required this.category,
    required this.unit,
    required this.stock,
    required this.status,
    required this.date,
  });

  factory InventoryItem.fromJson(Map<String, dynamic> json) {
    final typeStr = (json['transaction_type'] as String?)?.toUpperCase();
    final dateStr = json['transaction_date'] as String?;
    final rawStock = json['current_stock'];

    return InventoryItem(
      itemId: json['item_id'] is int
          ? json['item_id'] as int
          : int.tryParse('${json['item_id']}'),
      name: json['item_name'] as String? ?? '',
      category: json['inventory_category_name'] as String? ?? '-',
      unit: json['unit_name'] as String? ?? '-',
      stock: rawStock is int
          ? rawStock
          : (double.tryParse('$rawStock')?.round() ?? 0),
      status: typeStr == 'OUT' ? StockStatus.stockOut : StockStatus.stockIn,
      date: dateStr != null
          ? (DateTime.tryParse(dateStr) ?? DateTime.now())
          : DateTime.now(),
    );
  }
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

  List<Supplier> _suppliers = [];
  bool _loadingSuppliers = true;
  String? _suppliersError;

  @override
  void initState() {
    super.initState();

    _loadInventoryItems();
    _loadSuppliers();

    _tabController = TabController(
      length: 2,
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

  Future<void> _loadInventoryItems() async {
    setState(() {
      _loadingItems = true;
      _itemsError = null;
    });
    try {
      final data = await InventoryService.fetchInventoryItems();
      if (mounted) {
        setState(() {
          _items = data.map((json) => InventoryItem.fromJson(json)).toList();
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

  int get _totalItems => _items.length;

  // TODO: replace with real unit-cost * stock calculation once pricing data exists.
  double get _totalValue => 31963;

  int get _lowStockCount => _items.where((i) => i.stock < 20).length;

  String _formatCurrency(double value) {
    final wholeNumber = value.toStringAsFixed(0);
    final withCommas = wholeNumber.replaceAllMapped(
      RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
      (m) => '${m[1]},',
    );
    return '\$$withCommas';
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
                      ElevatedButton.icon(
                        onPressed: () {
                          if (_currentTab == 0) {
                            showDialog(
                              context: context,
                              barrierDismissible: false,
                              builder: (_) => _AddTransactionModal(
                                onSaved: _loadInventoryItems,
                              ),
                            );
                          } else {
                            showDialog(
                              context: context,
                              barrierDismissible: false,
                              builder: (_) => _AddSupplierModal(
                                onSaved: _loadSuppliers,
                              ),
                            );
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: kDarkPill,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 10,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(24),
                          ),
                        ),
                        icon: const Icon(
                          Icons.add,
                          size: 16,
                        ),
                        label: Text(
                          _currentTab == 0 ? 'Add Transaction' : 'Add Supplier',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _StatsRow(
                    totalItems: _totalItems,
                    totalValueLabel: _formatCurrency(_totalValue),
                    lowStock: _lowStockCount,
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
                  _loadingItems
                      ? const Center(child: CircularProgressIndicator())
                      : _itemsError != null
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(_itemsError!),
                                  const SizedBox(height: 8),
                                  ElevatedButton(
                                    onPressed: _loadInventoryItems,
                                    child: const Text("Retry"),
                                  ),
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
                                  builder: (_) => _InventoryDetailsModal(
                                    item: item,
                                    onChanged: _loadInventoryItems,
                                  ),
                                );
                              },
                            ),
                  _loadingSuppliers
                      ? const Center(child: CircularProgressIndicator())
                      : _suppliersError != null
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(_suppliersError!),
                                  const SizedBox(height: 8),
                                  ElevatedButton(
                                    onPressed: _loadSuppliers,
                                    child: const Text("Retry"),
                                  ),
                                ],
                              ),
                            )
                          : _SuppliersList(
                              suppliers: _suppliers,
                              onChanged: _loadSuppliers,
                            ),
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

class _StatsRow extends StatelessWidget {
  final int totalItems;
  final String totalValueLabel;
  final int lowStock;

  const _StatsRow({
    required this.totalItems,
    required this.totalValueLabel,
    required this.lowStock,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _StatCard(label: 'TOTAL ITEMS', value: '$totalItems', caption: 'All transactions'),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _StatCard(label: 'TOTAL VALUE', value: totalValueLabel, caption: 'Inventory value'),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _StatCard(
            label: 'LOW STOCK',
            value: '$lowStock',
            caption: 'Restocking',
            valueColor: Colors.green.shade700,
          ),
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

  const _StatCard({
    required this.label,
    required this.value,
    required this.caption,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
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
          Text(
            label,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.black45, letterSpacing: .3),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: valueColor ?? AppColors.dark),
          ),
          const SizedBox(height: 2),
          Text(caption, style: const TextStyle(fontSize: 10, color: Colors.black38)),
        ],
      ),
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
                        Text('Stock: ${item.stock}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.dark)),
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

class _AddTransactionModal extends StatefulWidget {
  final VoidCallback? onSaved;

  const _AddTransactionModal({this.onSaved});

  @override
  State<_AddTransactionModal> createState() => _AddTransactionModalState();
}

class _AddTransactionModalState extends State<_AddTransactionModal> {
  final itemController = TextEditingController();
  final dateController = TextEditingController();
  final quantityController = TextEditingController(text: "1");

  int quantity = 1;
  String transactionType = "IN";
  DateTime? selectedDate;

  int? selectedCategoryId;
  String? selectedCategoryName;

  int? selectedUnitId;
  String? selectedUnitName;

  int? selectedSupplierId;
  String? selectedSupplierName;

  int? selectedProjectId;
  String? selectedProjectName;

  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _units = [];
  List<Map<String, dynamic>> _suppliers = [];
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
        InventoryService.fetchCategories(),
        InventoryService.fetchUnits(),
        InventoryService.fetchSuppliers(),
        InventoryService.fetchProjects(),
      ]);
      if (mounted) {
        setState(() {
          _categories = results[0];
          _units = results[1];
          _suppliers = results[2];
          _projects = results[3];
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
    itemController.dispose();
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

        Row(
          children: [
            Expanded(
              child: _field(
                "Item Name",
                TextField(
                  controller: itemController,
                  decoration: _decoration("Item Name"),
                ),
              ),
            ),
            const SizedBox(width: 20),
            Expanded(
              child: _field(
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
            ),
          ],
        ),

        const SizedBox(height: 22),

        Row(
          children: [
            Expanded(
              child: _field(
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
                        decoration: _decoration("Quantity"),
                        onChanged: (value) {
                          final number = int.tryParse(value);
                          if (number != null) quantity = number;
                        },
                      ),
                    ),
                    const SizedBox(width: 10),
                    _smallBtn("+", () {
                      setState(() {
                        quantity++;
                        quantityController.text = quantity.toString();
                      });
                    }),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 20),
            Expanded(
              child: _field(
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
            ),
          ],
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

        const Divider(height: 45),

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
                          onChanged: (v) => setState(() => transactionType = "IN"),
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
                showDialog(
                  context: context,
                  barrierDismissible: false,
                  builder: (_) => _ReviewTransactionModal(
                    onSaved: widget.onSaved,
                    projectId: selectedProjectId,
                    categoryId: selectedCategoryId,
                    supplierId: selectedSupplierId,
                    unitId: selectedUnitId,
                    projectName: selectedProjectName ?? "-",
                    itemName: itemController.text,
                    categoryName: selectedCategoryName ?? "-",
                    supplierName: selectedSupplierName ?? "-",
                    quantity: quantity,
                    unitName: selectedUnitName ?? "-",
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
  final int? categoryId;
  final int? supplierId;
  final int? unitId;
  final String projectName;
  final String itemName;
  final String categoryName;
  final String supplierName;
  final int quantity;
  final String unitName;
  final String transactionType;
  final String transactionDate;

  const _ReviewTransactionModal({
    this.onSaved,
    this.projectId,
    this.categoryId,
    this.supplierId,
    this.unitId,
    required this.projectName,
    required this.itemName,
    required this.categoryName,
    required this.supplierName,
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
    if (widget.itemName.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter an item name.")),
      );
      return;
    }
    if (widget.categoryId == null || widget.supplierId == null || widget.unitId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please fill in all required fields.")),
      );
      return;
    }

    setState(() => _saving = true);

    final itemId = await InventoryService.createItem(
      itemName: widget.itemName.trim(),
      categoryId: widget.categoryId!,
      supplierId: widget.supplierId!,
      unitId: widget.unitId!,
      initialStock: widget.quantity.toDouble(),
    );

    if (itemId == null) {
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to create item. Please try again.")),
      );
      return;
    }

    final success = await InventoryService.saveTransaction(
      itemId: itemId,
      projectId: widget.projectId,
      type: widget.transactionType,
      quantity: widget.quantity.toDouble(),
      date: _formatDateForApi(widget.transactionDate),
    );

    setState(() => _saving = false);

    if (success) {
      widget.onSaved?.call();
      Navigator.of(context).popUntil((route) => route.isFirst);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Transaction saved successfully.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Item created but transaction failed. Please try again.")),
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
                        _data("Project", widget.projectName, itemWidth),
                        _data("Item Name", widget.itemName.isEmpty ? "-" : widget.itemName, itemWidth),
                        _data("Item Category", widget.categoryName, itemWidth),
                        _data("Item Supplier", widget.supplierName, itemWidth),
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
            _detail("Current Stock", "${item.stock} ${item.unit}"),

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