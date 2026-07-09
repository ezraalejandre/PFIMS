// Lightweight in-memory cache of the signed-in user's identity, so widgets
// like AppHeader can show the current profile photo (and email) without
// every single screen having to fetch and thread it through route
// arguments individually.
//
// This is intentionally simple — static fields, not a full state
// management solution. It resets on app restart/hot restart, which is
// fine because login and ProfileScreen's initial load both re-populate it
// right away.
class UserSession {
  UserSession._();

  static String email = '';

  // Base64 data URI, e.g. "data:image/jpeg;base64,...", same shape
  // AuthController::profile()/uploadProfilePhoto() return. Null means no
  // photo (or not loaded yet) — callers should fall back to an icon.
  static String? photoDataUri;

  // Call this after any successful ApiService.getProfile(...) response
  // (the `user` map inside it) to keep the cache in sync with the
  // backend. Only overwrites fields that are actually present, so a
  // partial map won't wipe out data set elsewhere.
  static void updateFromProfile(Map<String, dynamic> user) {
    final userEmail = user['email'] as String?;
    if (userEmail != null && userEmail.isNotEmpty) {
      email = userEmail;
    }
    if (user.containsKey('profile_photo')) {
      photoDataUri = user['profile_photo'] as String?;
    }
  }

  static void clear() {
    email = '';
    photoDataUri = null;
  }
}