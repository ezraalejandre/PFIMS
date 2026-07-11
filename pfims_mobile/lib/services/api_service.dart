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


  // Updates the logged-in user's password. The current password is sent
  // along so the backend can verify it (Hash::check) before allowing the
  // change; the new password is hashed server-side (Hash::make) before
  // storing — plaintext is never persisted. Throws an Exception with a
  // user-facing message on failure (e.g. wrong current password, user not
  // found, new password too short/invalid).
  static Future<Map<String, dynamic>> changePassword(
    String email,
    String currentPassword,
    String newPassword,
  ) async {
    http.Response response;

    try {
      response = await http.post(
        Uri.parse("$baseUrl/change-password"),
        body: {
          "email": email,
          "current_password": currentPassword,
          "new_password": newPassword,
        },
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

    if (response.statusCode == 404) {
      throw Exception(body['message'] ?? "Account not found");
    }

    if (response.statusCode == 401) {
      throw Exception(body['message'] ?? "Current password is incorrect");
    }

    if (response.statusCode == 422) {
      throw Exception(body['message'] ?? "Invalid password");
    }

    if (response.statusCode != 200) {
      throw Exception(body['message'] ?? "Unable to update password");
    }

    return body;
  }


static Future<Map<String,dynamic>> getProfile(
  String email,
) async {
  http.Response response;

  try {
    response = await http.post(
      Uri.parse("$baseUrl/profile"),           // was "$baseUrl/api/profile"
      body: {
        "email": email,
      },
    );
  } catch (e) {
    throw Exception("No internet connection or server unreachable");
  }

  // TEMP DEBUG: remove once the /profile response shape is confirmed.
  debugPrint("PROFILE STATUS: ${response.statusCode}");
  debugPrint("PROFILE BODY: ${response.body}");

  Map<String, dynamic> body;
  try {
    body = jsonDecode(response.body) as Map<String, dynamic>;
  } catch (e) {
    throw Exception(
      "Server returned an unexpected response (${response.statusCode}). Check the API route.",
    );
  }

  if (response.statusCode != 200) {
    throw Exception(body['message']?.toString() ?? "Unable to load profile (${response.statusCode})");
  }

  return body;
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


  // ---------------------------------------------------------------------
  // Profile field updates
  // ---------------------------------------------------------------------
  // Saves a single profile field (e.g. "name", "phone", "location",
  // "email") to the backend. Assumed route: POST /profile/update with
  // {email, field, value}. Update the route/keys here if your backend's
  // actual update-profile endpoint differs.
  //
  // `email` is the CURRENT email of the logged-in user, used to identify
  // the account being updated — even when the field being changed is
  // "email" itself (in that case `value` is the new email address).
  static Future<Map<String, dynamic>> updateProfileField(
    String email,
    String field,
    String value,
  ) async {
    http.Response response;

    try {
      response = await http.post(
        Uri.parse("$baseUrl/profile/update"),
        body: {
          "email": email,
          "field": field,
          "value": value,
        },
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
      throw Exception(body['message'] ?? "Invalid value for $field");
    }

    if (response.statusCode != 200) {
      throw Exception(body['message'] ?? "Unable to update $field");
    }

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