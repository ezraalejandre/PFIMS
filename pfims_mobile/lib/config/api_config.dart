import 'package:flutter/foundation.dart' show kIsWeb;

class ApiConfig {
  // Change this ONE value whenever your laptop's local IP changes
  // (only matters for running on a physical phone).
  // Run `ipconfig` (Windows) or `ifconfig`/`ip a` (Mac/Linux) to find it.
  static const String _lanHost = "192.168.1.20";
  static const String _port = "8000";

  static String get _host => kIsWeb ? "127.0.0.1" : _lanHost;

  static String get baseUrl => "http://$_host:$_port/api";
}