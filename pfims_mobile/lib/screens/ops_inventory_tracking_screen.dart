import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../widgets/ops_bottom_nav_bar.dart';
import '../widgets/app_header.dart';

const Color kDarkPill = Color(0xFF14161F);

enum StockStatus { stockIn, stockOut }

class InventoryItem {
  final String name;
  final String category;
  final String unit;
  final int stock;
  final StockStatus status;
  final DateTime date;

  const InventoryItem({
    required this.name,
    required this.category,
    required this.unit,
    required this.stock,
    required this.status,
    required this.date,
  });
}

class Supplier {
  final String name;
  final String category;
  final int itemCount;
  final String phone;
  final bool isActive;

  const Supplier({
    required this.name,
    required this.category,
    required this.itemCount,
    required this.phone,
    required this.isActive,
  });
}

class OpsInventoryTrackingScreen extends StatefulWidget {
  const OpsInventoryTrackingScreen({super.key});

  @override
  State<OpsInventoryTrackingScreen> createState() => _OpsInventoryTrackingScreenState();
}

class _OpsInventoryTrackingScreenState extends State<OpsInventoryTrackingScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  final List<InventoryItem> _items = [
    InventoryItem(
      name: 'Portland Cement',
      category: 'Cement',
      unit: 'bags',
      stock: 450,
      status: StockStatus.stockIn,
      date: DateTime(2026, 5, 1),
    ),
    InventoryItem(
      name: 'Steel Rebar 12mm',
      category: 'Steel',
      unit: 'pcs',
      stock: 220,
      status: StockStatus.stockOut,
      date: DateTime(2026, 5, 2),
    ),
    InventoryItem(
      name: 'White Latex Paint',
      category: 'Paint',
      unit: 'gallons',
      stock: 95,
      status: StockStatus.stockIn,
      date: DateTime(2026, 5, 3),
    ),
    InventoryItem(
      name: 'Gravel 3/4',
      category: 'Aggregates',
      unit: 'tons',
      stock: 70,
      status: StockStatus.stockOut,
      date: DateTime(2026, 5, 4),
    ),
    InventoryItem(
      name: 'Concrete Hollow Blocks',
      category: 'Masonry',
      unit: 'pcs',
      stock: 1200,
      status: StockStatus.stockIn,
      date: DateTime(2026, 5, 5),
    ),
    InventoryItem(
      name: 'PVC Pipe 2-inch',
      category: 'Plumbing',
      unit: 'pcs',
      stock: 60,
      status: StockStatus.stockOut,
      date: DateTime(2026, 5, 6),
    ),
    InventoryItem(
      name: 'Electrical Wire 5.5mm',
      category: 'Electrical',
      unit: 'rolls',
      stock: 45,
      status: StockStatus.stockIn,
      date: DateTime(2026, 5, 7),
    ),
  ];

  final List<Supplier> _suppliers = const [
    Supplier(
      name: 'Holcim Philippines',
      category: 'Cement',
      itemCount: 3,
      phone: '+63 2 8888 1234',
      isActive: true,
    ),
    Supplier(
      name: 'Metro Steel Supply',
      category: 'Steel',
      itemCount: 2,
      phone: '+63 2 7777 5678',
      isActive: true,
    ),
    Supplier(
      name: 'ColorPro Paint Center',
      category: 'Paint',
      itemCount: 4,
      phone: '+63 32 345 6789',
      isActive: true,
    ),
    Supplier(
      name: 'Laguna Aggregate Supplier',
      category: 'Aggregates',
      itemCount: 2,
      phone: '+63 49 502 1234',
      isActive: true,
    ),
    Supplier(
      name: 'SolidBlocks Manufacturing',
      category: 'Masonry',
      itemCount: 1,
      phone: '+63 2 5555 9012',
      isActive: true,
    ),
    Supplier(
      name: 'AquaFlow Industrial Supply',
      category: 'Plumbing',
      itemCount: 3,
      phone: '+63 2 4444 3456',
      isActive: false,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  int get _totalItems => _items.length;

  int get _lowStockCount => _items.where((i) => i.stock < 20).length;

  String _formatDate(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const AppHeader(),
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
                          // TODO: hook up to "add transaction" flow.
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
                          'Add Transaction',
                          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _StatsRow(
                    totalItems: _totalItems,
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
                  _InventoryList(items: _items, formatDate: _formatDate),
                  _SuppliersList(suppliers: _suppliers),
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: const OpsBottomNavBar(currentIndex: 2),
    );
  }
}

class _StatsRow extends StatelessWidget {
  final int totalItems;
  final int lowStock;

  const _StatsRow({
    required this.totalItems,
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

  const _InventoryList({required this.items, required this.formatDate});

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
        return Container(
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
        );
      },
    );
  }
}

class _SuppliersList extends StatelessWidget {
  final List<Supplier> suppliers;

  const _SuppliersList({required this.suppliers});

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
        return Container(
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
                    Text(supplier.name, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.dark)),
                    const SizedBox(height: 3),
                    Text(
                      '${supplier.category} · ${supplier.itemCount} item${supplier.itemCount == 1 ? '' : 's'}',
                      style: const TextStyle(fontSize: 12.5, color: Colors.black54),
                    ),
                    const SizedBox(height: 3),
                    Text(supplier.phone, style: const TextStyle(fontSize: 12.5, color: Colors.black54)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _StatusPill(
                text: supplier.isActive ? 'Active' : 'Inactive',
                background: supplier.isActive ? const Color(0xFFDCF2DE) : const Color(0xFFEAEAEA),
                textColor: supplier.isActive ? const Color(0xFF2E8B3D) : Colors.black45,
              ),
            ],
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


