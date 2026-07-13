import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class FinanceService {
  static final String baseUrl = ApiConfig.baseUrl;

  /// Fetches all projects (id + name) for populating project dropdowns,
  /// via GET /api/projects.
  static Future<List<Map<String, dynamic>>> getProjects() async {
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

  /// Fetches all expense categories (id + name), via
  /// GET /api/expense-categories.
  static Future<List<Map<String, dynamic>>> getExpenseCategories() async {
    final url = Uri.parse("$baseUrl/expense-categories");

    http.Response response;
    try {
      response = await http.get(url);
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    if (response.statusCode != 200) {
      throw Exception(
        "Unable to load expense categories (${response.statusCode})",
      );
    }

    final List<dynamic> list = jsonDecode(response.body) as List<dynamic>;
    return list.cast<Map<String, dynamic>>();
  }

  /// Sets a project's budget via POST /api/budgets. The backend upserts —
  /// one budget row per project — so resubmitting for the same project
  /// updates the existing amount instead of duplicating it.
  static Future<Map<String, dynamic>> createBudget({
    required int projectId,
    required double amount,
  }) async {
    final url = Uri.parse("$baseUrl/budgets");

    http.Response response;
    try {
      response = await http.post(
        url,
        headers: {"Content-Type": "application/json"},
        body: jsonEncode({
          "project_id": projectId,
          "budget_amount": amount,
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
      final errors = body['errors'] as Map<String, dynamic>?;
      final firstError = (errors != null && errors.isNotEmpty)
          ? (errors.values.first as List).first.toString()
          : (body['message']?.toString() ?? "Invalid budget details");
      throw Exception(firstError);
    }

    if (response.statusCode != 201 && response.statusCode != 200) {
      throw Exception(body['message']?.toString() ?? "Unable to save budget");
    }

    return body;
  }

  /// Creates a new expense row in `expense_tbl` via POST /api/expenses.
  /// The backend decides which amount column (labor/material/equipment/
  /// other) to fill based on the category, so the client just sends a
  /// single `amount`.
  ///
  /// No project is attached to an expense anymore — expenses are logged
  /// independently of any specific project.
  ///
  /// Returns the newly created row, joined with category_name so the
  /// caller can build a display entry without a second fetch.
  static Future<Map<String, dynamic>> createExpense({
    required int expenseCategoryId,
    required String description,
    required double amount,
    required DateTime date,
    String? remarks,
  }) async {
    final url = Uri.parse("$baseUrl/expenses");

    http.Response response;
    try {
      response = await http.post(
        url,
        headers: {"Content-Type": "application/json"},
        body: jsonEncode({
          "expense_category_id": expenseCategoryId,
          "expense_description": description,
          "amount": amount,
          "expense_date": _formatDate(date),
          if (remarks != null && remarks.isNotEmpty) "remarks": remarks,
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
      final errors = body['errors'] as Map<String, dynamic>?;
      final firstError = (errors != null && errors.isNotEmpty)
          ? (errors.values.first as List).first.toString()
          : (body['message']?.toString() ?? "Invalid expense details");
      throw Exception(firstError);
    }

    if (response.statusCode != 201 && response.statusCode != 200) {
      throw Exception(body['message']?.toString() ?? "Unable to create expense");
    }

    return body;
  }

  static String _formatDate(DateTime date) {
    final y = date.year.toString().padLeft(4, '0');
    final m = date.month.toString().padLeft(2, '0');
    final d = date.day.toString().padLeft(2, '0');
    return "$y-$m-$d";
  }

  /// Fetches all logged expenses (joined with project + category names),
  /// via GET /api/expenses. Budget-only rows are excluded server-side.
  static Future<List<Map<String, dynamic>>> getExpenses() async {
    final url = Uri.parse("$baseUrl/expenses");

    http.Response response;
    try {
      response = await http.get(url);
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    if (response.statusCode != 200) {
      throw Exception("Unable to load expenses (${response.statusCode})");
    }

    final List<dynamic> list = jsonDecode(response.body) as List<dynamic>;
    return list.cast<Map<String, dynamic>>();
  }

  /// Updates an existing expense via PUT /api/expenses/{id}. Like create,
  /// the backend decides which amount column to fill based on category —
  /// if the category changed, the old column is cleared server-side.
  /// No project is sent — expenses aren't tied to a project.
  static Future<Map<String, dynamic>> updateExpense({
    required int expenseId,
    required int expenseCategoryId,
    required String description,
    required double amount,
    required DateTime date,
    String? remarks,
  }) async {
    final url = Uri.parse("$baseUrl/expenses/$expenseId");

    http.Response response;
    try {
      response = await http.put(
        url,
        headers: {"Content-Type": "application/json"},
        body: jsonEncode({
          "expense_category_id": expenseCategoryId,
          "expense_description": description,
          "amount": amount,
          "expense_date": _formatDate(date),
          if (remarks != null && remarks.isNotEmpty) "remarks": remarks,
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
      final errors = body['errors'] as Map<String, dynamic>?;
      final firstError = (errors != null && errors.isNotEmpty)
          ? (errors.values.first as List).first.toString()
          : (body['message']?.toString() ?? "Invalid expense details");
      throw Exception(firstError);
    }

    if (response.statusCode == 404) {
      throw Exception(body['message']?.toString() ?? "Expense not found");
    }

    if (response.statusCode != 200) {
      throw Exception(body['message']?.toString() ?? "Unable to update expense");
    }

    return body;
  }

  static Future<void> deleteExpense(int expenseId) async {
    final url = Uri.parse("$baseUrl/expenses/$expenseId");

    http.Response response;
    try {
      response = await http.delete(url);
    } catch (e) {
      throw Exception("No internet connection or server unreachable");
    }

    if (response.statusCode == 404) {
      throw Exception("Expense not found");
    }

    if (response.statusCode != 200) {
      Map<String, dynamic>? body;
      try {
        body = jsonDecode(response.body) as Map<String, dynamic>;
      } catch (_) {}
      throw Exception(body?['message']?.toString() ?? "Unable to delete expense");
    }
  }

  /// Fetches all budgets (joined with project_name), via GET /api/budgets.
static Future<List<Map<String, dynamic>>> getBudgets() async {
  final url = Uri.parse("$baseUrl/budgets");
  http.Response response;
  try {
    response = await http.get(url);
  } catch (e) {
    throw Exception("No internet connection or server unreachable");
  }
  if (response.statusCode != 200) {
    throw Exception("Unable to load budgets (${response.statusCode})");
  }
  final List<dynamic> list = jsonDecode(response.body) as List<dynamic>;
  return list.cast<Map<String, dynamic>>();
}
}