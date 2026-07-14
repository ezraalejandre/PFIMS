import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart'; // adjust path to wherever ApiConfig actually lives

class NotificationService {
  static Future<Map<String, dynamic>> fetchNotifications() async {
    final response = await http.get(Uri.parse('${ApiConfig.baseUrl}/notifications'));
    if (response.statusCode != 200) {
      throw Exception('Failed to load notifications');
    }
    return json.decode(response.body) as Map<String, dynamic>;
  }

  static Future<int> fetchUnreadCount() async {
    final response = await http.get(Uri.parse('${ApiConfig.baseUrl}/notifications/unread-count'));
    if (response.statusCode != 200) return 0;
    final data = json.decode(response.body) as Map<String, dynamic>;
    return data['unread_count'] as int? ?? 0;
  }

  static Future<bool> markRead(int id) async {
    final response = await http.post(Uri.parse('${ApiConfig.baseUrl}/notifications/$id/read'));
    return response.statusCode == 200;
  }

  static Future<bool> markAllRead() async {
    final response = await http.post(Uri.parse('${ApiConfig.baseUrl}/notifications/mark-all-read'));
    return response.statusCode == 200;
  }

  static Future<bool> delete(int id) async {
    final response = await http.delete(Uri.parse('${ApiConfig.baseUrl}/notifications/$id'));
    return response.statusCode == 200;
  }

  static Future<bool> clearAll() async {
    final response = await http.delete(Uri.parse('${ApiConfig.baseUrl}/notifications'));
    return response.statusCode == 200;
  }
}