import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class AppBottomNavBar extends StatelessWidget {
  final int currentIndex;
  final bool showBudget;

  // The logged-in user's email, forwarded as route arguments on every tab
  // switch so screens like /profile (reached via AppHeader on the
  // destination screen) keep knowing who's signed in. Defaults to '' for
  // call sites that haven't been updated yet.
  final String email;

  const AppBottomNavBar({
    super.key,
    required this.currentIndex,
    this.showBudget = true,
    this.email = '',
  });

  @override
  Widget build(BuildContext context) {
    final destinations = <NavigationDestination>[
      const NavigationDestination(
        icon: Icon(Icons.dashboard_outlined),
        label: "Dashboard",
      ),
      const NavigationDestination(
        icon: Icon(Icons.folder_outlined),
        label: "Projects",
      ),
      if (showBudget)
        const NavigationDestination(
          icon: Icon(Icons.account_balance_wallet_outlined),
          label: "Budget",
        ),
      const NavigationDestination(
        icon: Icon(Icons.inventory_2_outlined),
        label: "Inventory",
      ),
    ];

    final routes = [
      "/dashboard",       // 👈 was /ops-dashboard
      "/projects",        // 👈 was /ops-projects
      if (showBudget) "/budget",
      showBudget ? "/inventory" : "/ops-inventory",
    ];

    return NavigationBar(
      height: 72,
      backgroundColor: Colors.white,
      selectedIndex: currentIndex,
      indicatorColor: AppColors.orange.withValues(alpha: .2),
      destinations: destinations,
      onDestinationSelected: (i) {
        Navigator.pushReplacementNamed(context, routes[i], arguments: email);
      },
    );
  }
}