import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class ProjectsService {
  static final String baseUrl = ApiConfig.baseUrl;

  /// Creates a new project row in `project_tbl` via POST /api/projects.
  ///
  /// [startDate] / [estimatedEndDate] are sent as "YYYY-MM-DD" strings to
  /// match Laravel's `date` validation rule. [workerCount] is optional —
  /// pass null if the field was left blank/invalid.
  ///
  /// Returns the newly created row (as returned by the backend, including
  /// `project_id`). Throws an Exception with a user-facing message on
  /// failure (validation errors, unreachable server, etc.).
  static Future<Map<String, dynamic>> createProject({
    required String projectName,
    required String clientName,
    required String projectManager,
    required DateTime startDate,
    required DateTime estimatedEndDate,
    int? workerCount,
  }) async {
    final url = Uri.parse("$baseUrl/projects");

    http.Response response;
    try {
      response = await http.post(
        url,
        headers: {"Content-Type": "application/json"},
        body: jsonEncode({
          "project_name": projectName,
          "client_name": clientName,
          "project_manager": projectManager,
          "start_date": _formatDate(startDate),
          "estimated_end_date": _formatDate(estimatedEndDate),
          "worker_count": ?workerCount,
        }),
      );
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    Map<String, dynamic> body;
    try {
      body = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (e) {
      throw Exception(
        "Server returned an unexpected response (${response.statusCode}). Check the API route.",
      );
    }

    if (response.statusCode == 422) {
      // Laravel validation error shape: {"message": "...", "errors": {field: [msg, ...]}}
      final errors = body['errors'] as Map<String, dynamic>?;
      final firstError = (errors != null && errors.isNotEmpty)
          ? (errors.values.first as List).first.toString()
          : (body['message']?.toString() ?? "Invalid project details");
      throw Exception(firstError);
    }

    if (response.statusCode != 201 && response.statusCode != 200) {
      throw Exception(
        body['message']?.toString() ?? "Unable to create project",
      );
    }

    return body;
  }

  /// Fetches the lightweight project list (id + name only) already exposed
  /// by GET /api/projects — e.g. for populating a dropdown elsewhere.
  static Future<List<Map<String, dynamic>>> getProjectNames() async {
    final url = Uri.parse("$baseUrl/projects");

    http.Response response;
    try {
      response = await http.get(url);
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    if (response.statusCode != 200) {
      throw Exception("Unable to load projects (${response.statusCode})");
    }

    final List<dynamic> list = jsonDecode(response.body) as List<dynamic>;
    return list.cast<Map<String, dynamic>>();
  }

  static String _formatDate(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');
    return "$y-$m-$d";
  }

/// Fetches the full project list (all columns) for the Project Tracking
  /// screen, via GET /api/projects/list.
  ///
  /// A cache-busting query param + no-cache headers are used because
  /// Flutter Web's http client goes through the browser's HTTP cache —
  /// without this, a create/edit/delete can succeed on the server but the
  /// very next fetch still returns the stale cached list until a manual
  /// page reload.
  static Future<List<Map<String, dynamic>>> getProjects() async {
    final url = Uri.parse("$baseUrl/projects/list").replace(
      queryParameters: {'_': DateTime.now().millisecondsSinceEpoch.toString()},
    );

    http.Response response;
    try {
      response = await http.get(
        url,
        headers: {
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          'Pragma': 'no-cache',
        },
      );
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    if (response.statusCode != 200) {
      throw Exception("Unable to load projects (${response.statusCode})");
    }

    final List<dynamic> list = jsonDecode(response.body) as List<dynamic>;
    return list.cast<Map<String, dynamic>>();
  }

  static Future<Map<String, dynamic>> updateProject({
    required int projectId,
    String? projectName,
    String? clientName,
    String? projectManager,
    DateTime? startDate,
    DateTime? estimatedEndDate,
    DateTime? actualEndDate,
    bool clearActualEndDate = false,
    int? workerCount,
    String? phase,
    String? status,
    double? completionPercentage,
  }) async {
    final url = Uri.parse("$baseUrl/projects/$projectId");

    final Map<String, dynamic> payload = {};
    if (projectName != null) payload['project_name'] = projectName;
    if (clientName != null) payload['client_name'] = clientName;
    if (projectManager != null) payload['project_manager'] = projectManager;
    if (startDate != null) payload['start_date'] = _formatDate(startDate);
    if (estimatedEndDate != null) {
      payload['estimated_end_date'] = _formatDate(estimatedEndDate);
    }
    if (clearActualEndDate) {
      payload['actual_end_date'] = null;
    } else if (actualEndDate != null) {
      payload['actual_end_date'] = _formatDate(actualEndDate);
    }
    if (workerCount != null) payload['worker_count'] = workerCount;
    if (phase != null) payload['phase'] = phase;
    if (status != null) payload['status'] = status;
    if (completionPercentage != null) {
      payload['completion_percentage'] = completionPercentage;
    }

    http.Response response;
    try {
      response = await http.put(
        url,
        headers: {"Content-Type": "application/json"},
        body: jsonEncode(payload),
      );
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    Map<String, dynamic> body;
    try {
      body = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (e) {
      throw Exception(
        "Server returned an unexpected response (${response.statusCode}). Check the API route.",
      );
    }

    if (response.statusCode == 422) {
      final errors = body['errors'] as Map<String, dynamic>?;
      final firstError = (errors != null && errors.isNotEmpty)
          ? (errors.values.first as List).first.toString()
          : (body['message']?.toString() ?? "Invalid project details");
      throw Exception(firstError);
    }

    if (response.statusCode == 404) {
      throw Exception(body['message']?.toString() ?? "Project not found");
    }

    if (response.statusCode != 200) {
      throw Exception(body['message']?.toString() ?? "Unable to update project");
    }

    return body;
  }

  static Future<void> deleteProject(int projectId) async {
    final url = Uri.parse("$baseUrl/projects/$projectId");

    http.Response response;
    try {
      response = await http.delete(url);
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    if (response.statusCode == 404) {
      throw Exception("Project not found");
    }

    if (response.statusCode != 200) {
      Map<String, dynamic>? body;
      try {
        body = jsonDecode(response.body) as Map<String, dynamic>;
      } catch (_) {}
      throw Exception(body?['message']?.toString() ?? "Unable to delete project");
    }
  }
}