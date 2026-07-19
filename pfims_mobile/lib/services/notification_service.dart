import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart'; // adjust path to wherever ApiConfig actually lives
import 'user_session.dart';

class NotificationService {
  static final ValueNotifier<int> unreadCount = ValueNotifier<int>(0);
  static Timer? _pollTimer;
  static bool _isFetchingCount = false;
  static const Duration pollInterval = Duration(seconds: 5);

  static void startUnreadCountPolling() {
    _pollTimer?.cancel();
    refreshUnreadCount();
    _pollTimer = Timer.periodic(pollInterval, (_) => refreshUnreadCount());
  }

  static void stopUnreadCountPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  static Future<void> refreshUnreadCount() async {
    if (_isFetchingCount) return;
    _isFetchingCount = true;
    try {
      unreadCount.value = await fetchUnreadCount();
    } catch (_) {
      // Keep the last visible badge if the API is temporarily unavailable.
    } finally {
      _isFetchingCount = false;
    }
  }

  static Future<Map<String, dynamic>> fetchNotifications() async {
    final response = await http.get(Uri.parse('${ApiConfig.baseUrl}/notifications'));
    if (response.statusCode != 200) {
      throw Exception('Failed to load notifications');
    }
    final data = json.decode(response.body) as Map<String, dynamic>;
    final notifications = (data['notifications'] as List<dynamic>? ?? [])
        .where((notification) => !_hideForCurrentRole(notification))
        .toList();
    return {
      ...data,
      'notifications': notifications,
    };
  }

  static Future<int> fetchUnreadCount() async {
    if (_isOperationsRole) {
      final data = await fetchNotifications();
      final notifications = data['notifications'] as List<dynamic>? ?? [];
      return notifications.where((notification) {
        if (notification is! Map<String, dynamic>) return false;
        final isRead = notification['is_read'];
        return !(isRead == true || isRead == 1 || isRead == '1');
      }).length;
    }

    final response = await http.get(Uri.parse('${ApiConfig.baseUrl}/notifications/unread-count'));
    if (response.statusCode != 200) return 0;
    final data = json.decode(response.body) as Map<String, dynamic>;
    final count = data['unread_count'];
    if (count is int) return count;
    return int.tryParse('$count') ?? 0;
  }

  static bool get _isOperationsRole {
    final role = UserSession.role.trim().toLowerCase();
    return role == 'operations' || role == 'operation' || role == 'ops';
  }

  static bool _hideForCurrentRole(dynamic notification) {
    if (!_isOperationsRole || notification is! Map<String, dynamic>) {
      return false;
    }

    final type = '${notification['type'] ?? ''}'.toLowerCase();
    final title = '${notification['title'] ?? ''}'.toLowerCase();
    final message = '${notification['message'] ?? ''}'.toLowerCase();

    return type.contains('expense') ||
        type.contains('budget') ||
        title.contains('expense') ||
        title.contains('budget') ||
        message.contains('expense') ||
        message.contains('budget');
  }

  static Future<bool> markRead(int id) async {
    final response = await http.post(Uri.parse('${ApiConfig.baseUrl}/notifications/$id/read'));
    final ok = response.statusCode == 200;
    if (ok) await refreshUnreadCount();
    return ok;
  }

  static Future<bool> markAllRead() async {
    final response = await http.post(Uri.parse('${ApiConfig.baseUrl}/notifications/mark-all-read'));
    final ok = response.statusCode == 200;
    if (ok) unreadCount.value = 0;
    return ok;
  }

  static Future<bool> delete(int id) async {
    final response = await http.delete(Uri.parse('${ApiConfig.baseUrl}/notifications/$id'));
    final ok = response.statusCode == 200;
    if (ok) await refreshUnreadCount();
    return ok;
  }

  static Future<bool> clearAll() async {
    final response = await http.delete(Uri.parse('${ApiConfig.baseUrl}/notifications'));
    final ok = response.statusCode == 200;
    if (ok) unreadCount.value = 0;
    return ok;
  }
}
