
import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class AppBottomNavBar extends StatelessWidget {
  final int currentIndex;
  final bool showBudget;

  const AppBottomNavBar({
    super.key,
    required this.currentIndex,
    this.showBudget = true,
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
      "/ops-dashboard",
      "/ops-projects",
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
        Navigator.pushReplacementNamed(context, routes[i]);
      },
    );
  }
}
