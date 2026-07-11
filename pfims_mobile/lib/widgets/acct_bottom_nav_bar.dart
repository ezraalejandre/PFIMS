import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class AcctBottomNavBar extends StatelessWidget {
  final int currentIndex;

  // The logged-in user's email, forwarded as route arguments on every tab
  // switch. Defaults to '' for call sites that haven't been updated yet.
  final String email;

  const AcctBottomNavBar({
    super.key,
    required this.currentIndex,
    this.email = '',
  });

  @override
  Widget build(BuildContext context) {
    const routes = [
      '/acct-dashboard',
      '/acct-budget',
    ];

    return NavigationBar(
      height: 72,
      backgroundColor: Colors.white,
      selectedIndex: currentIndex,
      indicatorColor: AppColors.orange.withValues(alpha: .2),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.dashboard_outlined),
          label: 'Dashboard',
        ),
        NavigationDestination(
          icon: Icon(Icons.account_balance_wallet_outlined),
          label: 'Budget',
        ),
      ],
      onDestinationSelected: (index) {
        if (index == currentIndex) return;

        Navigator.pushReplacementNamed(
          context,
          routes[index],
          arguments: email,
        );
      },
    );
  }
}