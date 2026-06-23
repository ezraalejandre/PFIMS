import 'package:flutter/material.dart';

import 'theme/app_theme.dart';

import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/ops_dashboard_screen.dart';
import 'screens/acct_dashboard_screen.dart';
import 'screens/project_tracking_screen.dart';
import 'screens/ops_project_tracking_screen.dart';
import 'screens/budget_tracking_screen.dart';
import 'screens/acct_budget_tracking_screen.dart';
import 'screens/inventory_tracking_screen.dart';
import 'screens/ops_inventory_tracking_screen.dart';
import 'screens/notifications_screen.dart';
import 'screens/profile_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  runApp(
    const PFIMSMobile(),
  );
}

class PFIMSMobile extends StatelessWidget {
  const PFIMSMobile({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'PFIMS',

      debugShowCheckedModeBanner: false,

      theme: AppTheme.theme,

      initialRoute: '/login',

      routes: {
        '/login': (_) => const LoginScreen(),
        '/dashboard': (_) => const DashboardScreen(),
        '/ops-dashboard': (_) => const OpsDashboardScreen(),
        '/acct-dashboard': (_) => const AcctDashboardScreen(),
        '/projects': (_) => const ProjectTrackingScreen(),
        '/ops-projects': (_) => const OpsProjectTrackingScreen(),
        '/budget': (_) => const BudgetTrackingScreen(),
        '/acct-budget': (_) => const AcctBudgetTrackingScreen(),
        '/inventory': (_) => const InventoryTrackingScreen(),
        '/ops-inventory': (_) => const OpsInventoryTrackingScreen(),
        '/notifications': (_) => const NotificationsScreen(),
        '/profile': (_) => const ProfileScreen(),
      },
    );
  }
}
