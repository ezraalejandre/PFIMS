import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class OpsBottomNavBar extends StatelessWidget {
  final int currentIndex;

  // The logged-in user's email, forwarded as route arguments on every tab
  // switch. Defaults to '' for call sites that haven't been updated yet.
  final String email;

  const OpsBottomNavBar({
    super.key,
    required this.currentIndex,
    this.email = '',
  });

  @override
  Widget build(BuildContext context) {
    const destinations = <NavigationDestination>[
      NavigationDestination(
        icon: Icon(Icons.dashboard_outlined),
        selectedIcon: Icon(Icons.dashboard),
        label: "Dashboard",
      ),
      NavigationDestination(
        icon: Icon(Icons.folder_outlined),
        selectedIcon: Icon(Icons.folder),
        label: "Projects",
      ),
      NavigationDestination(
        icon: Icon(Icons.inventory_2_outlined),
        selectedIcon: Icon(Icons.inventory_2),
        label: "Inventory",
      ),
    ];

    const routes = [
      "/ops-dashboard",
      "/ops-projects",
      "/ops-inventory",
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