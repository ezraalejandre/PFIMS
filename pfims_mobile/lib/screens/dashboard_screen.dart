import 'package:flutter/material.dart';
import 'package:flutter/services.dart'; // for SystemNavigator
import 'package:fl_chart/fl_chart.dart';
import 'package:pfims_mobile/screens/notifications_screen.dart' show NotificationsScreen;
import '../widgets/app_bottom_nav_bar.dart';
import '../services/user_session.dart';
import '../services/dashboard_service.dart';
import 'dart:convert';
import 'dart:typed_data';
import '../widgets/app_header.dart' show AppHeader;
const Color kBrandOrange = Color(0xFFF2811D);
const Color kDarkNavy = Color(0xFF1A1F36);
const Color kPositiveGreen = Color(0xFF27AE60);
const Color kWarningAmber = Color(0xFFE67E22);
const Color kNegativeRed = Color(0xFFE53935);
const Color kCardBg = Colors.white;
const Color kPageBg = Color(0xFFF2F3F5);

Color _badgeColor(String? type) => switch (type) {
      'warning' => kWarningAmber,
      'negative' => kNegativeRed,
      _ => kPositiveGreen,
    };

IconData _badgeIcon(String? type) => switch (type) {
      'warning' => Icons.warning_amber_rounded,
      'negative' => Icons.arrow_downward,
      _ => Icons.arrow_upward,
    };

class DashboardScreen extends StatefulWidget {
  final String email;
  const DashboardScreen({super.key, this.email = ''});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final PageController _statsController = PageController(viewportFraction: .58);
  late Future<DashboardData> _dashboardFuture;

  @override
  void initState() {
    super.initState();
    _dashboardFuture = DashboardService.fetchDashboard();
  }

  Future<void> _refresh() async {
    final next = DashboardService.fetchDashboard();
    setState(() => _dashboardFuture = next);
    await next;
  }

  @override
  void dispose() {
    _statsController.dispose();
    super.dispose();
  }

  void _pageStats(int direction, int cardCount) {
    final next = (_statsController.page ?? 0) + direction;
    _statsController.animateTo(
      next.clamp(0, cardCount - 1) *
          (_statsController.position.viewportDimension * _statsController.viewportFraction),
      duration: const Duration(milliseconds: 250),
      curve: Curves.easeOut,
    );
  }

    @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvoked: (didPop) async {
  if (didPop) return;
       
        final shouldExit = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text("Exit app?"),
            content: const Text("Are you sure you want to exit?"),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(false),
                child: const Text("Cancel"),
              ),
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(true),
                child: const Text("Exit"),
              ),
            ],
          ),
        );
        if (shouldExit == true) {
          SystemNavigator.pop();
        }
      },
      child: Scaffold(
        backgroundColor: kPageBg,
        appBar: AppHeader(email: widget.email),
        body: RefreshIndicator(
          onRefresh: _refresh,
          child: FutureBuilder<DashboardData>(
            future: _dashboardFuture,
            builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  const SizedBox(height: 120),
                  Icon(Icons.cloud_off, size: 40, color: Colors.grey[400]),
                  const SizedBox(height: 12),
                  Text(
                    'Couldn\'t load dashboard data.\n${snapshot.error}',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey[600]),
                  ),
                  const SizedBox(height: 12),
                  Center(child: TextButton(onPressed: _refresh, child: const Text('Try again'))),
                ],
              );
            }

            final data = snapshot.data!;
            final dashboardStats = data.statCards
                .where((card) =>
                    card.label == 'Active Projects' ||
                    card.label == 'Total Budget')
                .toList();

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                const _PageHeader(
                  title: 'DASHBOARD',
                  subtitle: 'construction operation overview',
                ),
                const SizedBox(height: 14),
               SizedBox(
                  height: 140,
                  child: ScrollConfiguration(
                    behavior: ScrollConfiguration.of(context).copyWith(
                      scrollbars: false,
                      overscroll: false,
                    ),
                    child: PageView.builder(
                      controller: _statsController,
                      itemCount: dashboardStats.length,
                      padEnds: false,
                      itemBuilder: (context, i) {
                        return Padding(
                          padding: const EdgeInsets.only(right: 12),
                          child: _StatCard(data: dashboardStats[i]),
                        );
                      },
                    ),
                  ),
                ),
                // const SizedBox(height: 10),
                // if (dashboardStats.length > 1)
                //   Container(
                //     height: 32,
                //     padding: const EdgeInsets.symmetric(horizontal: 10),
                //     decoration: BoxDecoration(color: kDarkNavy, borderRadius: BorderRadius.circular(16)),
                //     child: Row(
                //       children: [
                //         GestureDetector(
                //           onTap: () => _pageStats(-1, dashboardStats.length),
                //           child: const Icon(Icons.chevron_left, color: Colors.white70, size: 18),
                //         ),
                //         Expanded(
                //           child: Container(
                //             height: 4,
                //             margin: const EdgeInsets.symmetric(horizontal: 8),
                //             decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2)),
                //           ),
                //         ),
                //         GestureDetector(
                //           onTap: () => _pageStats(1, dashboardStats.length),
                //           child: const Icon(Icons.chevron_right, color: Colors.white70, size: 18),
                //         ),
                //       ],
                //     ),
                //   ),
                const SizedBox(height: 20),
                _SectionCard(
                  title: "PROJECT COMPLETION TREND",
                  child: SizedBox(height: 200, child: _CompletionBarChart(trend: data.completionTrend)),
                ),
                const SizedBox(height: 16),
                _SectionCard(
                  title: "ALLOCATED BUDGET VS EXPENSES",
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(height: 220, child: _BudgetVsExpenseChart(data: data.budgetVsExpense)),
                      const SizedBox(height: 10),
                      Row(
                        children: const [
                          _LegendDot(color: kDarkNavy, label: 'Allocated budget'),
                          SizedBox(width: 16),
                          _LegendDot(color: kBrandOrange, label: 'Expenses (right scale)'),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  "ACTIVE PROJECTS",
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, letterSpacing: .3, color: Colors.black87),
                ),
                const SizedBox(height: 12),
                if (data.activeProjects.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Text('No active projects right now.', style: TextStyle(color: Colors.grey[500])),
                  )
                else
                  ...data.activeProjects.map(
                    (p) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ActiveProjectCard(data: p),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: AppBottomNavBar(currentIndex: 0, email: widget.email),
      )
    );
  }
}

class _PageHeader extends StatelessWidget {
  final String title;
  final String subtitle;

  const _PageHeader({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: Colors.black87,
            letterSpacing: .3,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          subtitle,
          style: TextStyle(
            fontSize: 13,
            color: Colors.grey[600],
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _LegendDot extends StatelessWidget {
  final Color color;
  final String label;
  const _LegendDot({required this.color, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 8, height: 8, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 6),
        Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
      ],
    );
  }
}

class _CompletionBarChart extends StatelessWidget {
  final TrendData trend;
  const _CompletionBarChart({required this.trend});

  @override
  Widget build(BuildContext context) {
    final values = trend.values.map((v) => v.clamp(0, 100).toDouble()).toList();

    final maxVal = values.isEmpty ? 100.0 : values.reduce((a, b) => a > b ? a : b);
    final maxY = maxVal <= 0 ? 100.0 : (maxVal * 1.2).clamp(20.0, 100.0).ceilToDouble();
    final interval = (maxY / 4).clamp(5.0, double.infinity);

    return BarChart(
      BarChartData(
        maxY: maxY,
        alignment: BarChartAlignment.spaceAround,
        gridData: FlGridData(
          show: true,
          drawVerticalLine: false,
          horizontalInterval: interval,
          getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey[200]!, strokeWidth: 1),
        ),
        borderData: FlBorderData(show: false),
        titlesData: FlTitlesData(
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              interval: interval,
              reservedSize: 26,
              getTitlesWidget: (v, meta) => Text(
                v.toInt().toString(),
                style: const TextStyle(fontSize: 11, color: Colors.grey),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 24,
              getTitlesWidget: (v, meta) {
                final i = v.toInt();
                if (i < 0 || i >= trend.months.length) return const SizedBox();
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    trend.months[i],
                    style: const TextStyle(fontSize: 11, color: Colors.grey),
                  ),
                );
              },
            ),
          ),
        ),
        barGroups: List.generate(
          values.length,
          (i) => BarChartGroupData(
            x: i,
            barRods: [
              BarChartRodData(
                toY: values[i],
                color: kDarkNavy,
                width: 22,
                borderRadius: BorderRadius.circular(4),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BudgetVsExpenseChart extends StatelessWidget {
  final BudgetVsExpenseData data;
  const _BudgetVsExpenseChart({required this.data});

  @override
  Widget build(BuildContext context) {
    final budget = data.allocatedBudget.map((v) => v.toDouble()).toList();
    final expenses = data.expenses.map((v) => v.toDouble()).toList();
    final pointCount = data.months.isEmpty || budget.isEmpty || expenses.isEmpty
        ? 0
        : [data.months.length, budget.length, expenses.length]
            .reduce((min, length) => length < min ? length : min);

    // Budgets and expenses can differ by several orders of magnitude.  The
    // expense series uses the right-hand scale so small real expenses remain visible.
    final maxBudget = budget.isEmpty ? 0.0 : budget.reduce((a, b) => a > b ? a : b);
    final maxExpense = expenses.isEmpty ? 0.0 : expenses.reduce((a, b) => a > b ? a : b);
    final maxY = maxBudget <= 0 ? 100.0 : (maxBudget * 1.2).ceilToDouble();
    final expenseScaleMax = maxExpense <= 0 ? 1.0 : (maxExpense * 1.2).ceilToDouble();
    final double interval = (maxY / 4).clamp(1.0, double.infinity);

    return LineChart(
      LineChartData(
        minY: 0,
        maxY: maxY,
        gridData: FlGridData(
          show: true,
          drawVerticalLine: false,
          horizontalInterval: interval,
          getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey[200], strokeWidth: 1),
        ),
        borderData: FlBorderData(show: false),
        titlesData: FlTitlesData(
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              interval: interval,
              reservedSize: 74,
              getTitlesWidget: (v, meta) => Text(
                _compactPeso(v / maxY * expenseScaleMax),
                style: TextStyle(fontSize: 10, color: kBrandOrange.withValues(alpha: .85)),
              ),
            ),
          ),
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              interval: interval,
              reservedSize: 74,
              getTitlesWidget: (v, meta) => Text(
                _compactPeso(v),
                style: TextStyle(fontSize: 10, color: Colors.grey[500]),
                textAlign: TextAlign.right,
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 24,
              getTitlesWidget: (v, meta) {
                final i = v.toInt();
                if (i < 0 || i >= pointCount) return const SizedBox();
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(data.months[i], style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                );
              },
            ),
          ),
        ),
        lineBarsData: [
          LineChartBarData(
            spots: List.generate(pointCount, (i) => FlSpot(i.toDouble(), budget[i])),
            isCurved: false,
            color: kDarkNavy,
            barWidth: 3,
            dotData: const FlDotData(show: true),
            belowBarData: BarAreaData(show: false),
          ),
          LineChartBarData(
            spots: List.generate(
              pointCount,
              (i) => FlSpot(i.toDouble(), expenses[i] / expenseScaleMax * maxY),
            ),
            isCurved: false,
            color: kBrandOrange,
            barWidth: 3,
            dotData: const FlDotData(show: true),
            belowBarData: BarAreaData(show: false),
          ),
        ],
        lineTouchData: LineTouchData(
          touchTooltipData: LineTouchTooltipData(
            getTooltipItems: (touchedSpots) => touchedSpots.map((spot) {
              final isBudget = spot.barIndex == 0;
              final label = isBudget ? 'Allocated Budget' : 'Expenses';
              final actualValue = isBudget ? spot.y : spot.y / maxY * expenseScaleMax;
              return LineTooltipItem('$label\n${_fullPeso(actualValue)}', const TextStyle(color: Colors.white, fontSize: 12));
            }).toList(),
          ),
        ),
      ),
    );
  }

  String _compactPeso(double value) {
    if (value >= 1000000) return '₱${(value / 1000000).toStringAsFixed(1)}M';
    if (value >= 1000) return '₱${(value / 1000).toStringAsFixed(0)}K';
    return '₱${value.toStringAsFixed(0)}';
  }

  String _fullPeso(double value) {
    final digits = value.toStringAsFixed(0);
    return '₱${digits.replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},')}';
  }
}

class _DashboardHeader extends StatelessWidget implements PreferredSizeWidget {
  const _DashboardHeader({required this.email});
  final String email;
  static const int _unreadCount = 4;
  String get _resolvedEmail => email.isNotEmpty ? email : UserSession.email;

  Uint8List? _decodePhoto() {
    final uri = UserSession.photoDataUri;
    if (uri == null || uri.isEmpty) return null;
    final commaIndex = uri.indexOf(',');
    if (commaIndex == -1) return null;
    try {
      return base64Decode(uri.substring(commaIndex + 1));
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final photoBytes = _decodePhoto();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2))],
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          children: [
            Image.asset("assets/images/logo.jpg", width: 36, height: 36, fit: BoxFit.contain),
            const SizedBox(width: 10),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text("E.V. CATAPANG",
                      style: TextStyle(color: kBrandOrange, fontWeight: FontWeight.bold, fontSize: 14, letterSpacing: .3)),
                  Text("DESIGN-CONSTRUCTION & SUPPLY",
                      style: TextStyle(color: Colors.black54, fontSize: 9.5, fontWeight: FontWeight.w600, letterSpacing: .2)),
                ],
              ),
            ),
            _DashboardNotificationBell(unreadCount: _unreadCount),
            const SizedBox(width: 6),
            Material(
              color: Colors.transparent,
              shape: const CircleBorder(),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => Navigator.of(context).pushNamed('/profile', arguments: _resolvedEmail),
                customBorder: const CircleBorder(),
                child: Padding(
                  padding: const EdgeInsets.all(4),
                  child: CircleAvatar(
                    radius: 18,
                    backgroundColor: kBrandOrange,
                    backgroundImage: photoBytes != null ? MemoryImage(photoBytes) : null,
                    child: photoBytes == null ? const Icon(Icons.person, color: Colors.white, size: 20) : null,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(64);
}

class _DashboardNotificationBell extends StatelessWidget {
  final int unreadCount;
  const _DashboardNotificationBell({required this.unreadCount});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      shape: const CircleBorder(),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen())),
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              const Icon(Icons.notifications_none_rounded, color: kBrandOrange, size: 26),
              if (unreadCount > 0)
                Positioned(
                  top: -4,
                  right: -4,
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                    decoration: const BoxDecoration(color: Color(0xFFE53935), shape: BoxShape.circle),
                    child: Text(
                      unreadCount > 9 ? '9+' : '$unreadCount',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700, height: 1),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final Widget child;
  const _SectionCard({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .04), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, letterSpacing: .3, color: Colors.black87)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final DashboardStat data;
  const _StatCard({required this.data});

  @override
  Widget build(BuildContext context) {
    final color = _badgeColor(data.badgeType);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .04), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            data.label,
            style: TextStyle(fontSize: 11.5, color: Colors.grey[600]),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          if (data.badge != null) ...[
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: color.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(_badgeIcon(data.badgeType), size: 9, color: color),
                  const SizedBox(width: 2),
                  Text(data.badge!,
                      style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w600, color: color)),
                ],
              ),
            ),
          ],
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              data.value,
              maxLines: 1,
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.black87),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.subtitle,
            style: TextStyle(fontSize: 10.5, color: Colors.grey[500]),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _ActiveProjectCard extends StatelessWidget {
  final ActiveProject data;
  const _ActiveProjectCard({required this.data});

  Color get _statusColor {
    switch (data.status.toLowerCase()) {
      case 'delayed':
        return kNegativeRed;
      case 'at risk':
        return kWarningAmber;
      case 'completed':
        return kPositiveGreen;
      default:
        return kDarkNavy;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .04), blurRadius: 10, offset: const Offset(0, 4))],
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
                      data.name,
                      style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.bold, color: Colors.black87),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      data.clientName,
                      style: TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text("Budget", style: TextStyle(fontSize: 10.5, color: Colors.grey[500])),
                  Text(data.budget, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: kBrandOrange)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: data.percent,
                    minHeight: 8,
                    backgroundColor: Colors.grey[200],
                    valueColor: const AlwaysStoppedAnimation<Color>(kBrandOrange),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text("${(data.percent * 100).round()}%", style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _ProjectPill(label: data.phase, color: kDarkNavy),
              _ProjectPill(label: data.status, color: _statusColor),
              _ProjectPill(label: 'Est. end ${data.estimatedEndDate}', color: Colors.grey.shade700),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(child: _ProjectDate(label: 'Start', value: data.startDate)),
              Expanded(child: _ProjectDate(label: 'Actual End', value: data.actualEndDate)),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProjectPill extends StatelessWidget {
  final String label;
  final Color color;

  const _ProjectPill({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .1),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _ProjectDate extends StatelessWidget {
  final String label;
  final String value;

  const _ProjectDate({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(fontSize: 10.5, color: Colors.grey[500])),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(fontSize: 12, color: Colors.black87, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
