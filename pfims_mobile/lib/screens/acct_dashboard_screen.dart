import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:pfims_mobile/screens/notifications_screen.dart'
    show NotificationsScreen;
import 'package:pfims_mobile/widgets/app_header.dart' show NotificationBell;
import '../widgets/acct_bottom_nav_bar.dart';

const Color kBrandOrange = Color(0xFFF2811D);
const Color kCardBg = Colors.white;
const Color kPageBg = Color(0xFFF2F3F5);

class _StatCardData {
  final String label;
  final String value;
  final String subtitle;

  const _StatCardData({
    required this.label,
    required this.value,
    required this.subtitle,
  });
}

const List<_StatCardData> _statCards = [
  _StatCardData(
    label: 'Total Budget',
    value: 'PHP 67,000,000',
    subtitle: 'PHP 2.1M remaining',
  ),
  _StatCardData(
    label: 'Net Variance',
    value: '-PHP 440',
    subtitle: 'vs. planned budget',
  ),
];

const List<String> _months = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
];

const List<double> _budgetVsSpending = [
  450,
  462,
  470,
  492,
  512,
  580,
];

class AcctDashboardScreen extends StatelessWidget {
  // The logged-in user's email, forwarded to /profile via the header's
  // profile avatar. Pass this in via route arguments when navigating here
  // (see main.dart's onGenerateRoute) so the profile page loads correctly.
  final String email;

  const AcctDashboardScreen({super.key, this.email = ''});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kPageBg,
      appBar: _DashboardHeader(email: email),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          const _PageHeader(
            title: 'DASHBOARD',
            subtitle: 'financial overview',
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 120,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _statCards.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: 12),
              itemBuilder: (context, index) {
                return SizedBox(
                  width: 220,
                  child: _StatCard(
                    data: _statCards[index],
                  ),
                );
              },
            ),
          ),

          const SizedBox(height: 20),

          _SectionCard(
            title: 'TOTAL BUDGET',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'PHP 67,000,000',
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: kBrandOrange,
                  ),
                ),
                SizedBox(height: 8),
                Text(
                  'Overall allocated budget across all projects.',
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.black54,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          _SectionCard(
            title: 'BUDGET ALLOCATION VS SPENDING',
            child: SizedBox(
              height: 220,
              child: LineChart(
                LineChartData(
                  minY: 0,
                  maxY: 600,
                  gridData: FlGridData(
                    show: true,
                    drawVerticalLine: false,
                    horizontalInterval: 150,
                    getDrawingHorizontalLine: (_) => FlLine(
                      color: Colors.grey.shade200,
                      strokeWidth: 1,
                    ),
                  ),
                  borderData: FlBorderData(show: false),
                  titlesData: FlTitlesData(
                    topTitles: const AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: false,
                      ),
                    ),
                    rightTitles: const AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: false,
                      ),
                    ),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        interval: 150,
                        reservedSize: 34,
                        getTitlesWidget: (value, meta) {
                          return Text(
                            value.toInt().toString(),
                            style: TextStyle(
                              fontSize: 11,
                              color: Colors.grey.shade500,
                            ),
                          );
                        },
                      ),
                    ),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 24,
                        getTitlesWidget: (value, meta) {
                          final index = value.toInt();
                          if (index < 0 || index >= _months.length) {
                            return const SizedBox();
                          }
                          return Padding(
                            padding: const EdgeInsets.only(
                              top: 6,
                            ),
                            child: Text(
                              _months[index],
                              style: TextStyle(
                                fontSize: 11,
                                color: Colors.grey.shade500,
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ),
                  lineBarsData: [
                    LineChartBarData(
                      spots: List.generate(
                        _budgetVsSpending.length,
                        (i) => FlSpot(
                          i.toDouble(),
                          _budgetVsSpending[i],
                        ),
                      ),
                      isCurved: true,
                      color: kBrandOrange,
                      barWidth: 3,
                      dotData: const FlDotData(
                        show: true,
                      ),
                      belowBarData: BarAreaData(
                        show: false,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: AcctBottomNavBar(
        currentIndex: 0,
        email: email,
      ),
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

class _DashboardHeader extends StatelessWidget
    implements PreferredSizeWidget {
  const _DashboardHeader({required this.email});

  final String email;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: 16,
        vertical: 10,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 6,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          children: [
            Image.asset(
              'assets/images/logo.jpg',
              width: 36,
              height: 36,
              fit: BoxFit.contain,
            ),
            const SizedBox(width: 10),
            const Expanded(
              child: Column(
                crossAxisAlignment:
                    CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'E.V. CATAPANG',
                    style: TextStyle(
                      color: kBrandOrange,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                  Text(
                    'DESIGN-CONSTRUCTION & SUPPLY',
                    style: TextStyle(
                      color: Colors.black54,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            NotificationBell(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const NotificationsScreen(),
                  ),
                );
              },
            ),
            const SizedBox(width: 6),
            Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => Navigator.pushNamed(
                  context,
                  '/profile',
                  arguments: email,
                ),
                child: const Padding(
                  padding: EdgeInsets.all(4),
                  child: CircleAvatar(
                    radius: 18,
                    backgroundColor: kBrandOrange,
                    child: Icon(
                      Icons.person,
                      color: Colors.white,
                    ),
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
  Size get preferredSize =>
      const Size.fromHeight(64);
}

class _SectionCard extends StatelessWidget {
  final String title;
  final Widget child;

  const _SectionCard({
    required this.title,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final _StatCardData data;

  const _StatCard({
    required this.data,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kCardBg,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,
        children: [
          Text(
            data.label,
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey.shade600,
            ),
          ),
          const Spacer(),
          Text(
            data.value,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
            ),
          ),
          Text(
            data.subtitle,
            style: TextStyle(
              fontSize: 11,
              color: Colors.grey.shade500,
            ),
          ),
        ],
      ),
    );
  }
}


