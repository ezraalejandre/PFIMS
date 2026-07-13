import 'dart:convert';
import 'package:http/http.dart' as http;
import 'user_session.dart';
import '../config/api_config.dart';

class DashboardStat {
  final String label;
  final String value;
  final String subtitle;
  final String? badge;
  final String? badgeType;

  DashboardStat({
    required this.label,
    required this.value,
    required this.subtitle,
    this.badge,
    this.badgeType,
  });

  factory DashboardStat.fromJson(Map<String, dynamic> json) => DashboardStat(
        label: json['label']?.toString() ?? '',
        value: json['value']?.toString() ?? '',
        subtitle: json['subtitle']?.toString() ?? '',
        badge: json['badge']?.toString(),
        badgeType: json['badge_type']?.toString(),
      );
}

class TrendData {
  final List<String> months;
  final List<double> values;

  TrendData({required this.months, required this.values});

  factory TrendData.fromJson(Map<String, dynamic> json) => TrendData(
        months: List<String>.from((json['months'] as List?) ?? []),
        values: (json['values'] as List?)
                ?.map((v) => (v as num?)?.toDouble() ?? 0.0)
                .toList() ??
            [],
      );
}

class BudgetVsSpendingData {
  final List<String> months;
  final List<double> budget;
  final List<double> spending;

  BudgetVsSpendingData({
    required this.months,
    required this.budget,
    required this.spending,
  });

  factory BudgetVsSpendingData.fromJson(Map<String, dynamic> json) => BudgetVsSpendingData(
        months: List<String>.from((json['months'] as List?) ?? []),
        budget: (json['budget'] as List?)
                ?.map((v) => (v as num?)?.toDouble() ?? 0.0)
                .toList() ??
            [],
        spending: (json['spending'] as List?)
                ?.map((v) => (v as num?)?.toDouble() ?? 0.0)
                .toList() ??
            [],
      );
}

class ActiveProject {
  final String name;
  final String budget;
  final double percent;

  ActiveProject({
    required this.name,
    required this.budget,
    required this.percent,
  });

  factory ActiveProject.fromJson(Map<String, dynamic> json) {
    final completion = (json['completion_percentage'] ?? json['percent'] ?? 0).toDouble();
    return ActiveProject(
      name: (json['project_name'] ?? json['name'] ?? 'Unnamed Project').toString(),
      budget: _formatBudget(json['budget'] ?? json['estimated_budget']),
      percent: (completion / 100).clamp(0.0, 1.0),
    );
  }

  static String _formatBudget(dynamic value) {
    if (value == null) return '₱0';
    final num = double.tryParse(value.toString()) ?? 0;
    return '₱${num.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]},')}';
  }
}

class DashboardData {
  final List<DashboardStat> statCards;
  final TrendData completionTrend;
  final BudgetVsSpendingData budgetVsSpending;
  final List<ActiveProject> activeProjects;

  DashboardData({
    required this.statCards,
    required this.completionTrend,
    required this.budgetVsSpending,
    required this.activeProjects,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) => DashboardData(
        statCards: (json['stat_cards'] as List?)
                ?.map((e) => DashboardStat.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        completionTrend: TrendData.fromJson(json['completion_trend'] as Map<String, dynamic>? ?? {}),
        budgetVsSpending: BudgetVsSpendingData.fromJson(json['budget_vs_spending'] as Map<String, dynamic>? ?? {}),
        activeProjects: (json['active_projects'] as List?)
                ?.map((e) => ActiveProject.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
      );
}

class DashboardService {
  static final String _baseUrl = ApiConfig.baseUrl;

  static Future<DashboardData> fetchDashboard() async {
    final token = UserSession.token;

    final response = await http.get(
      Uri.parse('$_baseUrl/dashboard'),
      headers: {
        'Accept': 'application/json',
        if (token is String && token.isNotEmpty) 'Authorization': 'Bearer $token',
      },
    );

    if (response.statusCode != 200) {
      throw Exception('Failed to load dashboard (status ${response.statusCode})');
    }

    final body = jsonDecode(response.body);
    if (body is! Map<String, dynamic>) {
      throw Exception('Invalid dashboard response format');
    }

    return DashboardData.fromJson(body);
  }
}