import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
// import 'dart:io';
import 'package:image_picker/image_picker.dart';
import 'package:flutter/foundation.dart';

class ApiService {
  // Base URL for API endpoints
  static final String baseUrl = ApiConfig.baseUrl;


static Future<Map<String, dynamic>> login(
  String email,
  String password,
) async {
  http.Response response;

  try {
    response = await http.post(
      Uri.parse("$baseUrl/login"),
      body: {
        "email": email,
        "password": password,
      },
    );
  } catch (e) {
    // Covers SocketException, TimeoutException, ClientException, etc. —
    // anything that means the request never reached/returned from the
    // server (no internet, server down, wrong host, timeout).
    throw Exception("No internet connection or server unreachable");
  }

  if (response.statusCode == 401 || response.statusCode == 422) {
    // Server reached fine, but credentials were wrong.
    throw Exception("Invalid email or password");
  }

  if (response.statusCode != 200) {
    // Reached the server but something else went wrong (500, etc.)
    throw Exception("Something went wrong. Please try again");
  }

  return jsonDecode(response.body);
}


static Future<Map<String,dynamic>> changePassword(
  String email,
  String password,
) async {
  final response = await http.post(
    Uri.parse("$baseUrl/change-password"),   // was "$baseUrl/api/change-password"
    body: {
      "email": email,
      "new_password": password,
    },
  );

  return jsonDecode(response.body);
}


static Future<Map<String,dynamic>> getProfile(
  String email,
) async {
  final response = await http.post(
    Uri.parse("$baseUrl/profile"),           // was "$baseUrl/api/profile"
    body: {
      "email": email,
    },
  );

  return jsonDecode(response.body);
}


static Future<Map<String, dynamic>> uploadProfilePhoto(
  String email,
  XFile photo,
) async {
  final uri = Uri.parse("$baseUrl/profile/photo");
  final bytes = await photo.readAsBytes();

  final request = http.MultipartRequest('POST', uri)
    ..fields['email'] = email
    ..files.add(
      http.MultipartFile.fromBytes(
        'photo',
        bytes,
        filename: photo.name, // e.g. "image.jpg"
      ),
    );

  http.StreamedResponse streamed;
  try {
    streamed = await request.send();
  } catch (e) {
    throw Exception("No internet connection or server unreachable");
  }

final response = await http.Response.fromStream(streamed);

if (!(response.headers['content-type'] ?? '').contains('application/json')) {
  throw Exception("Server returned an unexpected response (${response.statusCode}). Check the API route.");
}

debugPrint("STATUS: ${response.statusCode}");
debugPrint("BODY: ${response.body}");

final body = jsonDecode(response.body) as Map<String, dynamic>;

  return body;
}


  // Add these three methods inside your existing ApiService class
// (the same class that already has ApiService.login(...)).
// Assumes `baseUrl`, `http`, and `dart:convert` are already set up the
// same way they are for login().

  // Step 1: validates the email exists/is well-formed, and sends a 6-digit
  // OTP to it. Throws an Exception with a user-facing message on failure
  // (e.g. "Email not found", "Invalid email format").
  static Future<void> sendForgotPasswordOtp(String email) async {
    final url = Uri.parse("$baseUrl/forgot-password/send-otp");

    final response = await http.post(
      url,
      headers: {"Content-Type": "application/json"},
      body: jsonEncode({"email": email}),
    );

    final body = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode != 200) {
      throw Exception(body['message'] ?? "Unable to send verification code");
    }
  }

  // Step 2: verifies the 6-digit OTP is correct and not expired.
  static Future<void> verifyForgotPasswordOtp(
    String email,
    String otp,
  ) async {
    final url = Uri.parse("$baseUrl/forgot-password/verify-otp");

    final response = await http.post(
      url,
      headers: {"Content-Type": "application/json"},
      body: jsonEncode({"email": email, "otp": otp}),
    );

    final body = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode != 200) {
      throw Exception(body['message'] ?? "Invalid or expired code");
    }
  }

  // Step 3: sets the new password. The OTP is sent again here as proof
  // the email was verified in step 2 (the backend re-checks it).
  static Future<void> resetPasswordWithOtp(
    String email,
    String otp,
    String newPassword,
  ) async {
    final url = Uri.parse("$baseUrl/forgot-password/reset");

    final response = await http.post(
      url,
      headers: {"Content-Type": "application/json"},
      body: jsonEncode({
        "email": email,
        "otp": otp,
        "password": newPassword,
        "password_confirmation": newPassword,
      }),
    );

    final body = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode != 200) {
      throw Exception(body['message'] ?? "Unable to reset password");
    }
  }

}