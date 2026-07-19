import 'package:flutter/material.dart';
import '../widgets/app_header.dart';
import 'login_screen.dart' hide kBrandOrange;
import 'notifications_screen.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_service.dart';
import '../services/user_session.dart';
import 'dart:typed_data';
import 'dart:convert';
import 'security_settings_screen.dart';

class ProfileScreen extends StatefulWidget {
  final String email;

  const ProfileScreen({super.key, required this.email});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String _fullName = '';
  late String _email;
  String _phone = '';
  String _location = '';
  String _role = '';

  bool _isLoading = true;
  String? _loadError;

  static const String _appVersion = 'EVC-DCS v2.4.1 · Build 241';

  Uint8List? _profilePhotoBytes;
  final ImagePicker _picker = ImagePicker();
  bool _isUploadingPhoto = false;

  String? _savingField;

  @override
  void initState() {
    super.initState();
    _email = widget.email;
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    setState(() {
      _isLoading = true;
      _loadError = null;
    });

    try {
      final result = await ApiService.getProfile(_email);

      Map<String, dynamic>? user;
      if (result['user'] is Map<String, dynamic>) {
        user = result['user'] as Map<String, dynamic>;
      } else if (result['data'] is Map<String, dynamic>) {
        user = result['data'] as Map<String, dynamic>;
      } else if (result.containsKey('email') || result.containsKey('name')) {
        user = result;
      }

      if (user == null) {
        setState(() {
          _loadError =
              'Could not load profile. Unexpected response format from server (keys: ${result.keys.join(", ")}).';
        });
        return;
      }

      final Map<String, dynamic> profile = user;

      setState(() {
        _fullName = profile['name'] ?? '';
        _email = profile['email'] ?? _email;
        _phone = profile['phone'] ?? '';
        _location = profile['location'] ?? '';
        _role = (profile['role'] ?? '').toString().toUpperCase();
        _profilePhotoBytes = _decodeDataUri(profile['profile_photo']);
      });
      UserSession.updateFromProfile(profile);
    } catch (e) {
      setState(() => _loadError = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      setState(() => _isLoading = false);
    }
  }

  String get _initials {
    final parts = _fullName.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1)).toUpperCase();
  }

  Uint8List? _decodeDataUri(String? dataUri) {
    if (dataUri == null || dataUri.isEmpty) return null;
    final commaIndex = dataUri.indexOf(',');
    if (commaIndex == -1) return null;
    try {
      return base64Decode(dataUri.substring(commaIndex + 1));
    } catch (_) {
      return null;
    }
  }

  Future<void> _changePhoto() async {
    final choice = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (choice == null) return;

    final picked = await _picker.pickImage(
      source: choice,
      maxWidth: 800,
      imageQuality: 85,
    );
    if (picked == null) return;

    final bytes = await picked.readAsBytes();
    setState(() {
      _profilePhotoBytes = bytes;
      _isUploadingPhoto = true;
    });

    try {
      final result = await ApiService.uploadProfilePhoto(_email, picked);
      if (!mounted) return;
      setState(() {
        _profilePhotoBytes = _decodeDataUri(result['profile_photo']) ?? _profilePhotoBytes;
      });
      UserSession.photoDataUri = result['profile_photo'] as String?;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Photo updated')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _isUploadingPhoto = false);
    }
  }

  Future<void> _editField({
    required String label,
    required String currentValue,
    required String fieldKey,
    required ValueChanged<String> onSaved,
    TextInputType keyboardType = TextInputType.text,
  }) async {
    final controller = TextEditingController(text: currentValue);
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Edit $label'),
        content: TextField(
          controller: controller,
          keyboardType: keyboardType,
          autofocus: true,
          decoration: InputDecoration(labelText: label),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: kBrandOrange),
            onPressed: () => Navigator.of(context).pop(controller.text.trim()),
            child: const Text('Save'),
          ),
        ],
      ),
    );

    if (result == null || result.isEmpty || result == currentValue) return;

    setState(() => _savingField = fieldKey);

    try {
      await ApiService.updateProfileField(_email, fieldKey, result);
      if (!mounted) return;
      setState(() {
        onSaved(result);
        _savingField = null;
      });
      if (fieldKey == 'email') {
        UserSession.email = result;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$label updated')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _savingField = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  void _showPlaceholder(String label) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$label — coming soon')),
    );
  }

  Future<void> _confirmLogOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Log out'),
        content: const Text('Are you sure you want to sign out of this account?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFFD23B5C)),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Log out'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenHeight = MediaQuery.of(context).size.height;
    
    return Scaffold(
      backgroundColor: const Color(0xFFF5F5F5),
      body: Column(
        children: [
          // Fixed Header - doesn't scroll
          _ProfileHeader(
            onBack: () => Navigator.of(context).maybePop(),
          ),
          // Scrollable content
          Expanded(
            child: RefreshIndicator(
              onRefresh: _loadProfile,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                child: _isLoading
                    ? const Padding(
                        padding: EdgeInsets.symmetric(vertical: 48),
                        child: Center(child: CircularProgressIndicator()),
                      )
                    : _loadError != null
                        ? _Card(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.error_outline, color: Color(0xFFD23B5C)),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        _loadError!,
                                        style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1A1A1A)),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                SizedBox(
                                  width: double.infinity,
                                  child: FilledButton(
                                    style: FilledButton.styleFrom(backgroundColor: kBrandOrange),
                                    onPressed: _loadProfile,
                                    child: const Text('Retry'),
                                  ),
                                ),
                              ],
                            ),
                          )
                        : Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _Card(
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Stack(
                                      children: [
                                        CircleAvatar(
                                          radius: 36,
                                          backgroundColor: kBrandOrange,
                                          backgroundImage: _profilePhotoBytes != null
                                              ? MemoryImage(_profilePhotoBytes!)
                                              : null,
                                          child: _profilePhotoBytes == null
                                              ? Text(
                                                  _initials,
                                                  style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800),
                                                )
                                              : null,
                                        ),
                                        Positioned(
                                          bottom: 0,
                                          right: 0,
                                          child: GestureDetector(
                                            onTap: _isUploadingPhoto ? null : () => _changePhoto(),
                                            child: Container(
                                              padding: const EdgeInsets.all(5),
                                              decoration: const BoxDecoration(color: Color(0xFF1A1A2E), shape: BoxShape.circle),
                                              child: _isUploadingPhoto
                                                  ? const SizedBox(
                                                      width: 13,
                                                      height: 13,
                                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                                    )
                                                  : const Icon(Icons.camera_alt, color: Colors.white, size: 13),
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            _fullName,
                                            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF1A1A1A)),
                                          ),
                                          const SizedBox(height: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                            decoration: BoxDecoration(
                                              color: kBrandOrange.withOpacity(0.14),
                                              borderRadius: BorderRadius.circular(20),
                                            ),
                                            child: Text(
                                              _role,
                                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: kBrandOrange),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              _Card(
                                padding: EdgeInsets.zero,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Padding(
                                      padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                                      child: Text(
                                        'CONTACT INFORMATION',
                                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.black45, letterSpacing: .4),
                                      ),
                                    ),
                                    _ContactField(
                                      icon: Icons.person_outline,
                                      label: 'Full Name',
                                      value: _fullName,
                                      isSaving: _savingField == 'name',
                                      onEdit: () => _editField(
                                        label: 'Full Name',
                                        currentValue: _fullName,
                                        fieldKey: 'name',
                                        onSaved: (v) => _fullName = v,
                                      ),
                                    ),
                                    _ContactField(
                                      icon: Icons.email_outlined,
                                      label: 'Email Address',
                                      value: _email,
                                      isSaving: _savingField == 'email',
                                      onEdit: () => _editField(
                                        label: 'Email Address',
                                        currentValue: _email,
                                        fieldKey: 'email',
                                        keyboardType: TextInputType.emailAddress,
                                        onSaved: (v) => _email = v,
                                      ),
                                    ),
                                    _ContactField(
                                      icon: Icons.phone_outlined,
                                      label: 'Phone Number',
                                      value: _phone,
                                      isSaving: _savingField == 'phone',
                                      onEdit: () => _editField(
                                        label: 'Phone Number',
                                        currentValue: _phone,
                                        fieldKey: 'phone',
                                        keyboardType: TextInputType.phone,
                                        onSaved: (v) => _phone = v,
                                      ),
                                    ),
                                    _ContactField(
                                      icon: Icons.location_on_outlined,
                                      label: 'Location',
                                      value: _location,
                                      isLast: true,
                                      isSaving: _savingField == 'location',
                                      onEdit: () => _editField(
                                        label: 'Location',
                                        currentValue: _location,
                                        fieldKey: 'location',
                                        onSaved: (v) => _location = v,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              _Card(
                                padding: EdgeInsets.zero,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Padding(
                                      padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                                      child: Text(
                                        'ACCOUNT',
                                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.black45, letterSpacing: .4),
                                      ),
                                    ),
                                    _ActionRow(
                                      icon: Icons.notifications_none_rounded,
                                      title: 'Notifications',
                                      subtitle: 'Manage alerts & reminders',
                                      onTap: () {
                                        Navigator.of(context).push(
                                          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
                                        );
                                      },
                                    ),
                                    _ActionRow(
                                    icon: Icons.shield_outlined,
                                    title: 'Privacy & Security',
                                    subtitle: 'Password',
                                    onTap: () {
                                      Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => const SecuritySettingsScreen(),
                                        ),
                                      );
                                    },
                                  ),
                                    _ActionRow(
                                      icon: Icons.help_outline,
                                      title: 'Help & Support',
                                      subtitle: 'FAQs, contact us',
                                      isLast: true,
                                      onTap: () => _showPlaceholder('Help & Support'),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              _Card(
                                padding: EdgeInsets.zero,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Padding(
                                      padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                                      child: Text(
                                        'SESSION',
                                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.black45, letterSpacing: .4),
                                      ),
                                    ),
                                    _ActionRow(
                                      icon: Icons.logout,
                                      title: 'Log Out',
                                      subtitle: 'Sign out of this account',
                                      isLast: true,
                                      iconBackground: const Color(0xFFFBDCE0),
                                      iconColor: const Color(0xFFD23B5C),
                                      titleColor: const Color(0xFFD23B5C),
                                      onTap: _confirmLogOut,
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              Center(
                                child: Text(_appVersion, style: TextStyle(fontSize: 11.5, color: Colors.grey.shade400)),
                              ),
                              const SizedBox(height: 8),
                            ],
                          ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// Fixed Profile Header - doesn't scroll
class _ProfileHeader extends StatelessWidget {
  final VoidCallback onBack;

  const _ProfileHeader({required this.onBack});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 12, 16, 12),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.black87),
                  onPressed: onBack,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
                ),
                const Text(
                  'PROFILE',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: Colors.black87,
                  ),
                ),
              ],
            ),
            const Padding(
              padding: EdgeInsets.only(left: 44),
              child: Text(
                'account & settings management',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;

  const _Card({required this.child, this.padding = const EdgeInsets.all(16)});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [
          BoxShadow(color: Color(0x0F000000), blurRadius: 10, offset: Offset(0, 4)),
        ],
      ),
      child: child,
    );
  }
}

class _ContactField extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final VoidCallback onEdit;
  final bool isLast;
  final bool isSaving;

  const _ContactField({
    required this.icon,
    required this.label,
    required this.value,
    required this.onEdit,
    this.isLast = false,
    this.isSaving = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      decoration: BoxDecoration(
        border: isLast ? null : Border(bottom: BorderSide(color: Colors.grey.shade200)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 19, color: Colors.grey.shade500),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: Color(0xFF1A1A1A)),
                ),
              ],
            ),
          ),
          if (isSaving)
            const Padding(
              padding: EdgeInsets.all(12),
              child: SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            )
          else
            IconButton(
              onPressed: onEdit,
              icon: Icon(Icons.edit_outlined, size: 18, color: Colors.grey.shade500),
              visualDensity: VisualDensity.compact,
              splashRadius: 20,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            ),
        ],
      ),
    );
  }
}

class _ActionRow extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool isLast;
  final Color? iconBackground;
  final Color? iconColor;
  final Color? titleColor;

  const _ActionRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.isLast = false,
    this.iconBackground,
    this.iconColor,
    this.titleColor,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 12, 12),
          decoration: BoxDecoration(
            border: isLast ? null : Border(bottom: BorderSide(color: Colors.grey.shade200)),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: iconBackground ?? kBrandOrange.withOpacity(0.14),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 18, color: iconColor ?? kBrandOrange),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                        color: titleColor ?? const Color(0xFF1A1A1A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: Colors.grey.shade400),
            ],
          ),
        ),
      ),
    );
  }
}