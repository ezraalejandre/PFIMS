import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../screens/notifications_screen.dart';
import '../services/notification_service.dart';
import '../services/user_session.dart';

const Color kBrandOrange = Color(0xFFF2811D);

class AppHeader extends StatefulWidget implements PreferredSizeWidget {
  const AppHeader({
    super.key,
    this.showBackButton = false,
    this.email = '',
    this.photoDataUri,
  });

  final bool showBackButton;
  final String email;
  final String? photoDataUri;

  @override
  State<AppHeader> createState() => _AppHeaderState();

  @override
  Size get preferredSize => const Size.fromHeight(64);
}

class _AppHeaderState extends State<AppHeader> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    NotificationService.startUnreadCountPolling();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      NotificationService.startUnreadCountPolling();
    } else if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      NotificationService.stopUnreadCountPolling();
    }
  }

  String get _resolvedEmail =>
      widget.email.isNotEmpty ? widget.email : UserSession.email;

  Uint8List? _decodePhoto() {
    final uri = widget.photoDataUri ?? UserSession.photoDataUri;
    if (uri == null || uri.isEmpty) return null;
    final commaIndex = uri.indexOf(',');
    if (commaIndex == -1) return null;
    try {
      return base64Decode(uri.substring(commaIndex + 1));
    } catch (_) {
      return null;
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const NotificationsScreen()),
    );
    NotificationService.refreshUnreadCount();
  }

  @override
  Widget build(BuildContext context) {
    final photoBytes = _decodePhoto();

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Color(0x14000000),
            blurRadius: 6,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          children: [
            if (widget.showBackButton) ...[
              IconButton(
                icon: const Icon(Icons.arrow_back, color: Colors.black87),
                onPressed: () => Navigator.of(context).maybePop(),
                splashRadius: 22,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
              const SizedBox(width: 8),
            ],
            Image.asset(
              'assets/images/logo.jpg',
              width: 36,
              height: 36,
              fit: BoxFit.contain,
            ),
            const SizedBox(width: 10),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'E.V. CATAPANG',
                    style: TextStyle(
                      color: kBrandOrange,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                      letterSpacing: .3,
                    ),
                  ),
                  Text(
                    'DESIGN-CONSTRUCTION & SUPPLY',
                    style: TextStyle(
                      color: Colors.black54,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w600,
                      letterSpacing: .2,
                    ),
                  ),
                ],
              ),
            ),
            NotificationBell(onTap: _openNotifications),
            const SizedBox(width: 6),
            Material(
              color: Colors.transparent,
              shape: const CircleBorder(),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () {
                  Navigator.of(context)
                      .pushNamed('/profile', arguments: _resolvedEmail);
                },
                customBorder: const CircleBorder(),
                child: Padding(
                  padding: const EdgeInsets.all(4),
                  child: CircleAvatar(
                    radius: 18,
                    backgroundColor: kBrandOrange,
                    backgroundImage:
                        photoBytes != null ? MemoryImage(photoBytes) : null,
                    child: photoBytes == null
                        ? const Icon(Icons.person, color: Colors.white, size: 20)
                        : null,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class NotificationBell extends StatelessWidget {
  const NotificationBell({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<int>(
      valueListenable: NotificationService.unreadCount,
      builder: (context, unreadCount, _) {
        return Material(
          color: Colors.transparent,
          shape: const CircleBorder(),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(6),
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  const Icon(
                    Icons.notifications_none_rounded,
                    color: kBrandOrange,
                    size: 26,
                  ),
                  if (unreadCount > 0)
                    Positioned(
                      top: -4,
                      right: -4,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        constraints:
                            const BoxConstraints(minWidth: 16, minHeight: 16),
                        decoration: const BoxDecoration(
                          color: Color(0xFFE53935),
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          unreadCount > 99 ? '99+' : '$unreadCount',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            height: 1,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
