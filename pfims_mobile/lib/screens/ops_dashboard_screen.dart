import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:pfims_mobile/screens/notifications_screen.dart'
    show NotificationsScreen;
import '../widgets/ops_bottom_nav_bar.dart';

const Color kBrandOrange = Color(0xFFF2811D);
const Color kDarkNavy = Color(0xFF1A1F36);
const Color kPositiveGreen = Color(0xFF27AE60);
const Color kCardBg = Colors.white;
const Color kPageBg = Color(0xFFF2F3F5);

// ---- Restricted stat cards (no Budget) ----
class _StatCardData {
  final String label;
  final String value;
  final String subtitle;
  final String? badge;
  const _StatCardData(
      {required this.label,
      required this.value,
      required this.subtitle,
      this.badge});
}

const List<_StatCardData> _statCards = [
  _StatCardData(
    label: "Active Projects",
    value: "24",
    subtitle: "6 completed this month",
    badge: "12%",
  ),
  _StatCardData(
    label: "Equipment",
    value: "156",
    subtitle: "12 under maintenance",
  ),
];

const List<String> _months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"];
const List<double> _completionTrend = [2, 5, 4, 7, 6, 8];

class _ActiveProjectData {
  final String name;
  final double percent;
  const _ActiveProjectData({required this.name, required this.percent});
}

const List<_ActiveProjectData> _activeProjects = [
  _ActiveProjectData(name: "Riverside Commercial Complex", percent: 0.78),
  _ActiveProjectData(name: "Downtown Office Tower", percent: 0.45),
  _ActiveProjectData(name: "Suburban Housing Development", percent: 0.92),
];

class OpsDashboardScreen extends StatefulWidget {
  const OpsDashboardScreen({super.key});

  @override
  State<OpsDashboardScreen> createState() => _OpsDashboardScreenState();
}

class _OpsDashboardScreenState extends State<OpsDashboardScreen> {
  final PageController _statsController =
      PageController(viewportFraction: .48);

  @override
  void dispose() {
    _statsController.dispose();
    super.dispose();
  }

  void _pageStats(int direction) {
    final next = (_statsController.page ?? 0) + direction;
    _statsController.animateTo(
      next.clamp(0, _statCards.length - 1) *
          (_statsController.position.viewportDimension *
              _statsController.viewportFraction),
      duration: const Duration(milliseconds: 250),
      curve: Curves.easeOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kPageBg,
      appBar: _OpsHeader(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          // Stat card carousel (Active Projects + Equipment only)
          SizedBox(
            height: 104,
            child: PageView.builder(
              controller: _statsController,
              itemCount: _statCards.length,
              padEnds: false,
              itemBuilder: (context, i) => Padding(
                padding: const EdgeInsets.only(right: 12),
                child: _StatCard(data: _statCards[i]),
              ),
            ),
          ),
          const SizedBox(height: 10),
          Container(
            height: 32,
            padding: const EdgeInsets.symmetric(horizontal: 10),
            decoration: BoxDecoration(
              color: kDarkNavy,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                GestureDetector(
                  onTap: () => _pageStats(-1),
                  child: const Icon(Icons.chevron_left,
                      color: Colors.white70, size: 18),
                ),
                Expanded(
                  child: Container(
                    height: 4,
                    margin: const EdgeInsets.symmetric(horizontal: 8),
                    decoration: BoxDecoration(
                      color: Colors.white24,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () => _pageStats(1),
                  child: const Icon(Icons.chevron_right,
                      color: Colors.white70, size: 18),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Project Completion Trend
          _SectionCard(
            title: "PROJECT COMPLETION TREND",
            child: SizedBox(
              height: 200,
              child: BarChart(
                BarChartData(
                  maxY: 12,
                  alignment: BarChartAlignment.spaceAround,
                  gridData: FlGridData(
                    show: true,
                    drawVerticalLine: false,
                    horizontalInterval: 3,
                    getDrawingHorizontalLine: (_) =>
                        FlLine(color: Colors.grey[200], strokeWidth: 1),
                  ),
                  borderData: FlBorderData(show: false),
                  titlesData: FlTitlesData(
                    topTitles: const AxisTitles(
                        sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(
                        sideTitles: SideTitles(showTitles: false)),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        interval: 3,
                        reservedSize: 26,
                        getTitlesWidget: (v, meta) => Text(
                          v.toInt().toString(),
                          style:
                              TextStyle(fontSize: 11, color: Colors.grey[500]),
                        ),
                      ),
                    ),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 24,
                        getTitlesWidget: (v, meta) => Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(
                            _months[v.toInt()],
                            style:
                                TextStyle(fontSize: 11, color: Colors.grey[500]),
                          ),
                        ),
                      ),
                    ),
                  ),
                  barGroups: List.generate(
                    _completionTrend.length,
                    (i) => BarChartGroupData(
                      x: i,
                      barRods: [
                        BarChartRodData(
                          toY: _completionTrend[i],
                          color: kDarkNavy,
                          width: 22,
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Active Projects list
          const Text(
            "ACTIVE PROJECTS",
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              letterSpacing: .3,
              color: Colors.black87,
            ),
          ),
          const SizedBox(height: 12),
          ..._activeProjects.map(
            (p) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _ActiveProjectCard(data: p),
            ),
          ),
        ],
      ),
      bottomNavigationBar: const OpsBottomNavBar(currentIndex: 0),
    );
  }
}

class _OpsHeader extends StatelessWidget implements PreferredSizeWidget {
  static const int _unreadCount = 4;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
              color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          children: [
            Image.asset("assets/images/logo.jpg",
                width: 36, height: 36, fit: BoxFit.contain),
            const SizedBox(width: 10),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text("E.V. CATAPANG",
                      style: TextStyle(
                          color: kBrandOrange,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          letterSpacing: .3)),
                  Text("DESIGN-CONSTRUCTION & SUPPLY",
                      style: TextStyle(
                          color: Colors.black54,
                          fontSize: 9.5,
                          fontWeight: FontWeight.w600,
                          letterSpacing: .2)),
                ],
              ),
            ),
            // OPS badge in header
            Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: kBrandOrange.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(6),
              ),
              child: const Text("OPS",
                  style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: kBrandOrange,
                      letterSpacing: .4)),
            ),
            const SizedBox(width: 6),
            _NotificationBell(unreadCount: _unreadCount),
            const SizedBox(width: 6),
            Material(
              color: Colors.transparent,
              shape: const CircleBorder(),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => Navigator.of(context).pushNamed('/profile'),
                customBorder: const CircleBorder(),
                child: const Padding(
                  padding: EdgeInsets.all(4),
                  child: CircleAvatar(
                    radius: 18,
                    backgroundColor: kBrandOrange,
                    child: Icon(Icons.person, color: Colors.white, size: 20),
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

class _NotificationBell extends StatelessWidget {
  final int unreadCount;
  const _NotificationBell({required this.unreadCount});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      shape: const CircleBorder(),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const NotificationsScreen())),
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              const Icon(Icons.notifications_none_rounded,
                  color: kBrandOrange, size: 26),
              if (unreadCount > 0)
                Positioned(
                  top: -4,
                  right: -4,
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    constraints:
                        const BoxConstraints(minWidth: 16, minHeight: 16),
                    decoration: const BoxDecoration(
                        color: Color(0xFFE53935), shape: BoxShape.circle),
                    child: Text(
                      unreadCount > 9 ? '9+' : '$unreadCount',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          height: 1),
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
        boxShadow: [
          BoxShadow(
              color: Colors.black.withValues(alpha: .04),
              blurRadius: 10,
              offset: const Offset(0, 4))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  letterSpacing: .3,
                  color: Colors.black87)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final _StatCardData data;
  const _StatCard({required this.data});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withValues(alpha: .04),
              blurRadius: 10,
              offset: const Offset(0, 4))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(data.label,
                    style:
                        TextStyle(fontSize: 11.5, color: Colors.grey[600]),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
              ),
              if (data.badge != null)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(
                    color: kPositiveGreen.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.arrow_upward,
                          size: 9, color: kPositiveGreen),
                      Text(data.badge!,
                          style: const TextStyle(
                              fontSize: 9.5,
                              fontWeight: FontWeight.w600,
                              color: kPositiveGreen)),
                    ],
                  ),
                ),
            ],
          ),
          const Spacer(),
          Text(data.value,
              style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  color: Colors.black87)),
          const SizedBox(height: 2),
          Text(data.subtitle,
              style: TextStyle(fontSize: 10.5, color: Colors.grey[500]),
              maxLines: 1,
              overflow: TextOverflow.ellipsis),
        ],
      ),
    );
  }
}

class _ActiveProjectCard extends StatelessWidget {
  final _ActiveProjectData data;
  const _ActiveProjectCard({required this.data});

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
              offset: const Offset(0, 4))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(data.name,
              style: const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.bold,
                  color: Colors.black87)),
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
                    valueColor:
                        const AlwaysStoppedAnimation<Color>(kBrandOrange),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text("${(data.percent * 100).round()}%",
                  style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            ],
          ),
        ],
      ),
    );
  }
}