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

      // Switched from a static `routes` map to `onGenerateRoute` so we can
      // read the logged-in user's email out of `settings.arguments` and
      // forward it to screens that need it (currently just /profile).
      //
      // Call sites should now navigate like:
      //   Navigator.pushNamed(context, '/profile', arguments: email);
      // instead of the old Navigator.pushNamed(context, '/profile') with
      // no arguments (which is what produced the "User not found" bug —
      // ProfileScreen was always built with email: '').
      onGenerateRoute: (settings) {
        switch (settings.name) {
          case '/login':
            return MaterialPageRoute(builder: (_) => const LoginScreen());

          case '/dashboard':
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => DashboardScreen(email: email));

          case '/ops-dashboard':
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => OpsDashboardScreen(email: email));

          case '/acct-dashboard':
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => AcctDashboardScreen(email: email));

          case '/projects':
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => ProjectTrackingScreen(email: email));

          case '/ops-projects':
            return MaterialPageRoute(builder: (_) => const OpsProjectTrackingScreen());

          case '/budget':
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => BudgetTrackingScreen(email: email));

          case '/acct-budget':
            return MaterialPageRoute(builder: (_) => const AcctBudgetTrackingScreen());

          case '/inventory':
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => InventoryTrackingScreen(email: email));

          case '/ops-inventory':
            return MaterialPageRoute(builder: (_) => const OpsInventoryTrackingScreen());

          case '/notifications':
            return MaterialPageRoute(builder: (_) => const NotificationsScreen());

          case '/profile':
            // Expect settings.arguments to be the logged-in user's email
            // (a String), passed by whoever navigates here, e.g.:
            //   Navigator.pushNamed(context, '/profile', arguments: userEmail);
            final email = settings.arguments as String? ?? '';
            return MaterialPageRoute(builder: (_) => ProfileScreen(email: email));

          default:
            return MaterialPageRoute(builder: (_) => const LoginScreen());
        }
      },
    );
  }
}