import 'package:flutter/material.dart';
import '../widgets/app_header.dart';
// `login_screen.dart` declares its own `kBrandOrange`; hide it here since
// this file already gets the canonical one from app_header.dart.
import 'login_screen.dart' hide kBrandOrange;
import 'notifications_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  // TODO: replace with real data from your backend / auth / state management.
  String _fullName = 'Elito V. Catapang';
  String _email = 'e.catapang@evc-dcs.com';
  String _phone = '+63 917 555 0123';
  String _location = 'Cebu City, Philippines';

  static const String _role = 'PROJECT MANAGER';
  static const String _employeeId = 'EVC-PM-0042';
  static const String _appVersion = 'EVC-DCS v2.4.1 · Build 241';

  String get _initials {
    final parts = _fullName.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty) return '';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1)).toUpperCase();
  }

  Future<void> _editField({
    required String label,
    required String currentValue,
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

    if (result != null && result.isNotEmpty && result != currentValue) {
      setState(() => onSaved(result));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$label updated')),
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
      // TODO: wire to real sign-out / auth logic (clear tokens, session, etc.) once available.
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F5F5),
      appBar: const AppHeader(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back, color: Colors.black87),
                onPressed: () => Navigator.of(context).maybePop(),
                splashRadius: 22,
              ),
              const SizedBox(width: 6),
              const Text(
                'PROFILE',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF1A1A1A)),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text('account & settings management', style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
          const SizedBox(height: 16),

          // ---- Identity card ----
          _Card(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Stack(
                  children: [
                    CircleAvatar(
                      radius: 36,
                      backgroundColor: kBrandOrange,
                      child: Text(
                        _initials,
                        style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800),
                      ),
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: GestureDetector(
                        onTap: () => _showPlaceholder('Change photo'),
                        child: Container(
                          padding: const EdgeInsets.all(5),
                          decoration: const BoxDecoration(color: Color(0xFF1A1A2E), shape: BoxShape.circle),
                          child: const Icon(Icons.camera_alt, color: Colors.white, size: 13),
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
                        child: const Text(
                          _role,
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: kBrandOrange),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text('Employee ID: $_employeeId', style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // ---- Contact information ----
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
                  onEdit: () => _editField(
                    label: 'Full Name',
                    currentValue: _fullName,
                    onSaved: (v) => _fullName = v,
                  ),
                ),
                _ContactField(
                  icon: Icons.email_outlined,
                  label: 'Email Address',
                  value: _email,
                  onEdit: () => _editField(
                    label: 'Email Address',
                    currentValue: _email,
                    keyboardType: TextInputType.emailAddress,
                    onSaved: (v) => _email = v,
                  ),
                ),
                _ContactField(
                  icon: Icons.phone_outlined,
                  label: 'Phone Number',
                  value: _phone,
                  onEdit: () => _editField(
                    label: 'Phone Number',
                    currentValue: _phone,
                    keyboardType: TextInputType.phone,
                    onSaved: (v) => _phone = v,
                  ),
                ),
                _ContactField(
                  icon: Icons.location_on_outlined,
                  label: 'Location',
                  value: _location,
                  isLast: true,
                  onEdit: () => _editField(
                    label: 'Location',
                    currentValue: _location,
                    onSaved: (v) => _location = v,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // ---- Account section ----
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
                  subtitle: 'Password, 2FA, sessions',
                  onTap: () => _showPlaceholder('Privacy & Security'),
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

          // ---- Session section ----
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
        ],
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

  const _ContactField({
    required this.icon,
    required this.label,
    required this.value,
    required this.onEdit,
    this.isLast = false,
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
                  style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: Color(0xFF1A1A1A)),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onEdit,
            icon: Icon(Icons.edit_outlined, size: 18, color: Colors.grey.shade500),
            visualDensity: VisualDensity.compact,
            splashRadius: 20,
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
                    Text(subtitle, style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600)),
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