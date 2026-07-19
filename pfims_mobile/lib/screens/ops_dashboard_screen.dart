import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../services/dashboard_service.dart';
import '../widgets/app_header.dart';
import '../widgets/ops_bottom_nav_bar.dart';

const Color kBrandOrange = Color(0xFFF2811D);
const Color kDarkNavy = Color(0xFF1A1F36);
const Color kPositiveGreen = Color(0xFF27AE60);
const Color kWarningAmber = Color(0xFFE67E22);
const Color kNegativeRed = Color(0xFFE53935);
const Color kCardBg = Colors.white;
const Color kPageBg = Color(0xFFF2F3F5);

class OpsDashboardScreen extends StatefulWidget {
  final String email;

  const OpsDashboardScreen({super.key, this.email = ''});

  @override
  State<OpsDashboardScreen> createState() => _OpsDashboardScreenState();
}

class _OpsDashboardScreenState extends State<OpsDashboardScreen> {
  final PageController _statsController = PageController(viewportFraction: .52);
  late Future<DashboardData> _dashboardFuture;

  @override
  void initState() {
    super.initState();
    _dashboardFuture = DashboardService.fetchDashboard();
  }

  @override
  void dispose() {
    _statsController.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    final next = DashboardService.fetchDashboard();
    setState(() => _dashboardFuture = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
                    "Couldn't load dashboard data.\n${snapshot.error}",
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey[600]),
                  ),
                  const SizedBox(height: 12),
                  Center(child: TextButton(onPressed: _refresh, child: const Text('Try again'))),
                ],
              );
            }

            final data = snapshot.data!;
            final opsStats = data.statCards
                .where((card) =>
                    card.label == 'Active Projects' ||
                    card.label == 'Equipment Units' ||
                    card.label == 'Workforce')
                .toList();

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                const _PageHeader(),
                const SizedBox(height: 14),
                SizedBox(
                  height: 122,
                  child: PageView.builder(
                    controller: _statsController,
                    itemCount: opsStats.length,
                    padEnds: false,
                    itemBuilder: (context, index) {
                      return Padding(
                        padding: const EdgeInsets.only(right: 12),
                        child: _StatCard(data: opsStats[index]),
                      );
                    },
                  ),
                ),
                const SizedBox(height: 20),
                _SectionCard(
                  title: 'PROJECT COMPLETION TREND',
                  child: SizedBox(height: 200, child: _CompletionBarChart(trend: data.completionTrend)),
                ),
                const SizedBox(height: 20),
                const Text(
                  'ACTIVE PROJECTS',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    letterSpacing: .3,
                    color: Colors.black87,
                  ),
                ),
                const SizedBox(height: 12),
                if (data.activeProjects.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Text('No active projects right now.', style: TextStyle(color: Colors.grey[500])),
                  )
                else
                  ...data.activeProjects.take(5).map(
                        (project) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _ActiveProjectCard(data: project),
                        ),
                      ),
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: OpsBottomNavBar(currentIndex: 0, email: widget.email),
    );
  }
}

class _PageHeader extends StatelessWidget {
  const _PageHeader();

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'DASHBOARD',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: Colors.black87,
                  letterSpacing: .3,
                ),
              ),
              SizedBox(height: 2),
              Text(
                'operations overview',
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(
            color: kBrandOrange.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(6),
          ),
          child: const Text(
            'OPS',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: kBrandOrange,
              letterSpacing: .4,
            ),
          ),
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  final DashboardStat data;

  const _StatCard({required this.data});

  Color get _badgeColor {
    switch (data.badgeType) {
      case 'warning':
        return kWarningAmber;
      case 'negative':
        return kNegativeRed;
      default:
        return kPositiveGreen;
    }
  }

  @override
  Widget build(BuildContext context) {
    final badge = data.badge;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  data.label,
                  style: TextStyle(fontSize: 11.5, color: Colors.grey[600]),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (badge != null && badge.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(
                    color: _badgeColor.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    badge,
                    style: TextStyle(
                      fontSize: 9.5,
                      fontWeight: FontWeight.w600,
                      color: _badgeColor,
                    ),
                  ),
                ),
            ],
          ),
          const Spacer(),
          Text(
            data.value,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.black87,
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
              reservedSize: 28,
              getTitlesWidget: (value, meta) => Text(
                value.toInt().toString(),
                style: TextStyle(fontSize: 11, color: Colors.grey[500]),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 24,
              getTitlesWidget: (value, meta) {
                final index = value.toInt();
                if (index < 0 || index >= trend.months.length) return const SizedBox();
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    trend.months[index],
                    style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                  ),
                );
              },
            ),
          ),
        ),
        barGroups: List.generate(
          values.length,
          (index) => BarChartGroupData(
            x: index,
            barRods: [
              BarChartRodData(
                toY: values[index],
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
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              letterSpacing: .3,
              color: Colors.black87,
            ),
          ),
          const SizedBox(height: 12),
          child,
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
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      data.name,
                      style: const TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.bold,
                        color: Colors.black87,
                      ),
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
              _ProjectPill(label: data.status, color: _statusColor),
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
              Text(
                '${(data.percent * 100).round()}%',
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _ProjectPill(label: data.phase, color: kDarkNavy),
              _ProjectPill(label: 'Est. end ${data.estimatedEndDate}', color: Colors.grey.shade700),
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