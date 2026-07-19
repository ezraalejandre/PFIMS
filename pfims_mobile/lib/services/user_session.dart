import 'package:shared_preferences/shared_preferences.dart';

class UserSession {
  UserSession._();

  static String email = '';
  static String role = '';
  static String? photoDataUri;

  static Object? get token => null;

  static const _kRememberMe = 'remember_me';
  static const _kEmail = 'saved_email';
  static const _kRole = 'saved_role';

  static void updateFromProfile(Map<String, dynamic> user) {
    final userEmail = user['email'] as String?;
    if (userEmail != null && userEmail.isNotEmpty) {
      email = userEmail;
    }
    final userRole = user['role'] as String?;
    if (userRole != null && userRole.isNotEmpty) {
      role = userRole;
    }
    if (user.containsKey('profile_photo')) {
      photoDataUri = user['profile_photo'] as String?;
    }
  }

  // Call after a successful login. Persists email+role to disk only if
  // rememberMe is true; otherwise makes sure no stale session survives.
  static Future<void> persistLogin({
    required String email,
    required String role,
    required bool rememberMe,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    if (rememberMe) {
      await prefs.setBool(_kRememberMe, true);
      await prefs.setString(_kEmail, email);
      await prefs.setString(_kRole, role);
    } else {
      await prefs.remove(_kRememberMe);
      await prefs.remove(_kEmail);
      await prefs.remove(_kRole);
    }
  }

  // Returns the saved (email, role) if remember-me was on, else null.
  static Future<({String email, String role})?> loadPersistedLogin() async {
    final prefs = await SharedPreferences.getInstance();
    final remembered = prefs.getBool(_kRememberMe) ?? false;
    if (!remembered) return null;

    final savedEmail = prefs.getString(_kEmail);
    final savedRole = prefs.getString(_kRole);
    if (savedEmail == null || savedEmail.isEmpty) return null;

    return (email: savedEmail, role: savedRole ?? '');
  }

  // Call on explicit logout.
  static Future<void> clear() async {
    email = '';
    role = '';
    photoDataUri = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kRememberMe);
    await prefs.remove(_kEmail);
    await prefs.remove(_kRole);
  }
}