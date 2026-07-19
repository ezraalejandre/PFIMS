import 'package:flutter/material.dart';

import 'project_tracking_screen.dart';

class OpsProjectTrackingScreen extends StatelessWidget {
  final String email;

  const OpsProjectTrackingScreen({super.key, this.email = ''});

  @override
  Widget build(BuildContext context) {
    return ProjectTrackingScreen(
      email: email,
      operationsMode: true,
    );
  }
}
