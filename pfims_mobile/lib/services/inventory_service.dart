import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class InventoryService {
   static final String baseUrl = ApiConfig.baseUrl;

  static Future<List<Map<String, dynamic>>> fetchCategories() async {
    final response = await http.get(Uri.parse("$baseUrl/inventory-categories"));
    if (response.statusCode == 200) {
      final List data = jsonDecode(response.body);
      return data.cast<Map<String, dynamic>>();
    }
    throw Exception("Failed to fetch categories: ${response.statusCode}");
  }

  static Future<List<Map<String, dynamic>>> fetchUnits() async {
    final response = await http.get(Uri.parse("$baseUrl/units"));
    if (response.statusCode == 200) {
      final List data = jsonDecode(response.body);
      return data.cast<Map<String, dynamic>>();
    }
    throw Exception("Failed to fetch units: ${response.statusCode}");
  }

  static Future<List<Map<String, dynamic>>> fetchSuppliers() async {
    final response = await http.get(Uri.parse("$baseUrl/suppliers"));
    if (response.statusCode == 200) {
      final List data = jsonDecode(response.body);
      return data.cast<Map<String, dynamic>>();
    }
    throw Exception("Failed to fetch suppliers: ${response.statusCode}");
  }

  static Future<bool> saveTransaction({
    required int itemId,
    int? projectId,
    required String type,
    required double quantity,
    required String date,
  }) async {
    final response = await http.post(
      Uri.parse("$baseUrl/inventory-transactions"),
      headers: {"Content-Type": "application/json"},
      body: jsonEncode({
        "item_id": itemId,
        "project_id": projectId,
        "transaction_type": type,
        "quantity": quantity,
        "transaction_date": date,
      }),
    );

    print("STATUS: ${response.statusCode}");
    print("BODY: ${response.body}");
    return response.statusCode == 201;
  }

  static Future<List<Map<String, dynamic>>> fetchProjects() async {
  final response = await http.get(Uri.parse("$baseUrl/projects"));
  if (response.statusCode == 200) {
    final List data = jsonDecode(response.body);
    return data.cast<Map<String, dynamic>>();
  }
  throw Exception("Failed to fetch projects: ${response.statusCode}");
}

static Future<List<Map<String, dynamic>>> fetchItems({
  int? categoryId,
  int? supplierId,
}) async {
  String url = "$baseUrl/inventory-items?";
  if (categoryId != null) url += "category_id=$categoryId&";
  if (supplierId != null) url += "supplier_id=$supplierId&";

  final response = await http.get(Uri.parse(url));
  if (response.statusCode == 200) {
    final List data = jsonDecode(response.body);
    return data.cast<Map<String, dynamic>>();
  }
  throw Exception("Failed to fetch items: ${response.statusCode}");
}

static Future<int?> createItem({
  required String itemName,
  required int categoryId,
  required int supplierId,
  required int unitId,
  required double initialStock,
}) async {
  final response = await http.post(
    Uri.parse("$baseUrl/inventory-items"),
    headers: {"Content-Type": "application/json"},
    body: jsonEncode({
      "item_name": itemName,
      "inventory_category_id": categoryId,
      "supplier_id": supplierId,
      "unit_id": unitId,
      "current_stock": initialStock,
      "reorder_level": 0,
    }),
  );

  if (response.statusCode == 201) {
    final data = jsonDecode(response.body);
    return data["item_id"] as int?;
  }
  return null;
}

static Future<List<Map<String, dynamic>>> fetchInventoryItems() async {
  final response = await http.get(Uri.parse("$baseUrl/inventory-items-list"));
  if (response.statusCode == 200) {
    final List data = jsonDecode(response.body);
    return data.cast<Map<String, dynamic>>();
  }
  throw Exception("Failed to fetch inventory items: ${response.statusCode}");
}

}

