import 'package:flutter/material.dart';

import 'inventory_tracking_screen.dart';

class OpsInventoryTrackingScreen extends StatelessWidget {
  final String email;

  const OpsInventoryTrackingScreen({super.key, this.email = ''});

  @override
  Widget build(BuildContext context) {
    return InventoryTrackingScreen(
      email: email,
      operationsMode: true,
    );
  }
}
