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

  // NEW: fetch every individual transaction row (not just the latest per
  // item). Used to render the Inventory tab as a transaction history list
  // instead of one row per item.
  static Future<List<Map<String, dynamic>>> fetchTransactions() async {
    final response = await http.get(Uri.parse("$baseUrl/inventory-transactions"));
    if (response.statusCode == 200) {
      final List data = jsonDecode(response.body);
      return data.cast<Map<String, dynamic>>();
    }
    throw Exception("Failed to fetch transactions: ${response.statusCode}");
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

  // NEW: partial update of an inventory item. Only send the fields that
  // changed — pass null for anything you don't want to touch.
  static Future<bool> updateItem({
    required int itemId,
    String? itemName,
    int? categoryId,
    int? supplierId,
    int? unitId,
    double? currentStock,
  }) async {
    final body = <String, dynamic>{};
    if (itemName != null) body["item_name"] = itemName;
    if (categoryId != null) body["inventory_category_id"] = categoryId;
    if (supplierId != null) body["supplier_id"] = supplierId;
    if (unitId != null) body["unit_id"] = unitId;
    if (currentStock != null) body["current_stock"] = currentStock;

    final response = await http.put(
      Uri.parse("$baseUrl/inventory-items/$itemId"),
      headers: {"Content-Type": "application/json"},
      body: jsonEncode(body),
    );

    print("UPDATE ITEM STATUS: ${response.statusCode}");
    print("UPDATE ITEM BODY: ${response.body}");
    return response.statusCode == 200;
  }

  // NEW: delete an inventory item.
  static Future<bool> deleteItem(int itemId) async {
    final response = await http.delete(Uri.parse("$baseUrl/inventory-items/$itemId"));
    print("DELETE ITEM STATUS: ${response.statusCode}");
    print("DELETE ITEM BODY: ${response.body}");
    return response.statusCode == 200;
  }

  static Future<List<Map<String, dynamic>>> fetchInventoryItems() async {
    final response = await http.get(Uri.parse("$baseUrl/inventory-items-list"));
    if (response.statusCode == 200) {
      final List data = jsonDecode(response.body);
      return data.cast<Map<String, dynamic>>();
    }
    throw Exception("Failed to fetch inventory items: ${response.statusCode}");
  }

  // ---------------------------------------------------------------------
  // Suppliers
  // ---------------------------------------------------------------------

  // NEW: create a supplier. Returns the new supplier_id, or null on failure.
  static Future<int?> createSupplier({
    required String name,
    String? contactNumber,
    String? address,
  }) async {
    final response = await http.post(
      Uri.parse("$baseUrl/suppliers"),
      headers: {"Content-Type": "application/json"},
      body: jsonEncode({
        "supplier_name": name,
        "contact_number": contactNumber,
        "address": address,
      }),
    );

    print("CREATE SUPPLIER STATUS: ${response.statusCode}");
    print("CREATE SUPPLIER BODY: ${response.body}");

    if (response.statusCode == 201) {
      final data = jsonDecode(response.body);
      return data["supplier_id"] is int
          ? data["supplier_id"] as int
          : int.tryParse("${data["supplier_id"]}");
    }
    return null;
  }

  // NEW: partial update of a supplier.
  static Future<bool> updateSupplier({
    required int supplierId,
    String? name,
    String? contactNumber,
    String? address,
  }) async {
    final body = <String, dynamic>{};
    if (name != null) body["supplier_name"] = name;
    if (contactNumber != null) body["contact_number"] = contactNumber;
    if (address != null) body["address"] = address;

    final response = await http.put(
      Uri.parse("$baseUrl/suppliers/$supplierId"),
      headers: {"Content-Type": "application/json"},
      body: jsonEncode(body),
    );

    print("UPDATE SUPPLIER STATUS: ${response.statusCode}");
    print("UPDATE SUPPLIER BODY: ${response.body}");
    return response.statusCode == 200;
  }

  // NEW: delete a supplier. Returns a (success, message) pair so the UI can
  // surface a specific error (e.g. supplier still linked to items).
  static Future<({bool success, String message})> deleteSupplier(int supplierId) async {
    final response = await http.delete(Uri.parse("$baseUrl/suppliers/$supplierId"));
    print("DELETE SUPPLIER STATUS: ${response.statusCode}");
    print("DELETE SUPPLIER BODY: ${response.body}");

    if (response.statusCode == 200) {
      return (success: true, message: "Supplier deleted.");
    }

    try {
      final data = jsonDecode(response.body);
      return (success: false, message: data["message"]?.toString() ?? "Failed to delete supplier.");
    } catch (_) {
      return (success: false, message: "Failed to delete supplier.");
    }
  }
}