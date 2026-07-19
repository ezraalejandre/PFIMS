import 'package:flutter/material.dart';
import '../widgets/app_header.dart' show kBrandOrange;
import '../services/notification_service.dart';

enum NotificationFilter { all, alerts, system }

enum NotificationKind { warning, overdue, success, info, maintenance, systemUpdate }

class AppNotification {
  final int id;
  final String title;
  final String message;
  final String type;
  final DateTime createdAt;
  final NotificationKind kind;
  final NotificationFilter filter;
  bool isRead;

  AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.createdAt,
    required this.kind,
    required this.filter,
    this.isRead = false,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['notification_id'] is int
          ? json['notification_id'] as int
          : int.tryParse('${json['notification_id']}') ?? 0,
      title: json['title'] as String? ?? '',
      message: json['message'] as String? ?? '',
      type: json['type'] as String? ?? '',
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? '') ?? DateTime.now(),
      kind: _kindFromString(json['kind'] as String? ?? 'info'),
      filter: (json['filter'] as String? ?? 'alerts') == 'system'
          ? NotificationFilter.system
          : NotificationFilter.alerts,
      isRead: json['is_read'] == true || json['is_read'] == 1,
    );
  }

  static NotificationKind _kindFromString(String value) {
    switch (value) {
      case 'warning':
        return NotificationKind.warning;
      case 'overdue':
        return NotificationKind.overdue;
      case 'success':
        return NotificationKind.success;
      case 'maintenance':
        return NotificationKind.maintenance;
      case 'system_update':
        return NotificationKind.systemUpdate;
      default:
        return NotificationKind.info;
    }
  }

  String get timeLabel {
    final now = DateTime.now();
    final diff = now.difference(createdAt);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) return '${diff.inHours} hr${diff.inHours == 1 ? '' : 's'} ago';
    if (diff.inDays == 1) return 'Yesterday';
    return '${createdAt.month}/${createdAt.day}/${createdAt.year}';
  }

  bool get isToday {
    final now = DateTime.now();
    return createdAt.year == now.year && createdAt.month == now.month && createdAt.day == now.day;
  }
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  NotificationFilter _selectedFilter = NotificationFilter.all;

  List<AppNotification> _notifications = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await NotificationService.fetchNotifications();
      final list = (data['notifications'] as List<dynamic>? ?? [])
          .map((json) => AppNotification.fromJson(json as Map<String, dynamic>))
          .toList();
      if (mounted) {
        setState(() {
          _notifications = list;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'Failed to load notifications. Pull to refresh.';
          // _error = 'Failed to load notifications: $e';
          _loading = false;
        });
      }
    }
  }

  int get _unreadCount => _notifications.where((n) => !n.isRead).length;

  List<AppNotification> _applyFilter(List<AppNotification> items) {
    if (_selectedFilter == NotificationFilter.all) return items;
    return items.where((n) => n.filter == _selectedFilter).toList();
  }

  Future<void> _markAllRead() async {
    setState(() {
      for (final n in _notifications) {
        n.isRead = true;
      }
    });
    await NotificationService.markAllRead();
  }

  Future<void> _clearAll() async {
    final removed = List<AppNotification>.from(_notifications);
    setState(() => _notifications.clear());
    await NotificationService.clearAll();
    // (removed unused, kept for clarity of intent — nothing to roll back to
    // since the API call above is fire-and-forget here)
    removed.length;
  }

  Future<void> _dismiss(AppNotification notification) async {
    setState(() => _notifications.remove(notification));
    await NotificationService.delete(notification.id);
  }

  Future<void> _onTapNotification(AppNotification notification) async {
    if (!notification.isRead) {
      setState(() => notification.isRead = true);
      await NotificationService.markRead(notification.id);
    }
  }

  @override
Widget build(BuildContext context) {
  final filtered = _applyFilter(_notifications);
  final today = filtered.where((n) => n.isToday).toList();
  final earlier = filtered.where((n) => !n.isToday).toList();
  final isEmpty = today.isEmpty && earlier.isEmpty;

  return Scaffold(
    backgroundColor: const Color(0xFFF5F5F5),
    body: Column(
      children: [
        _NotificationsHeader(
          // unreadCount: _unreadCount,
          onMarkAllRead: _markAllRead,
          onClearAll: _clearAll,
        ),
        _FilterChipsRow(
          selected: _selectedFilter,
          onChanged: (filter) => setState(() => _selectedFilter = filter),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? ListView(
                        children: [
                          const SizedBox(height: 80),
                          Center(
                            child: Column(
                              children: [
                                Text(_error!),
                                const SizedBox(height: 8),
                                ElevatedButton(onPressed: _load, child: const Text('Retry')),
                              ],
                            ),
                          ),
                        ],
                      )
                    : isEmpty
                        ? const _EmptyState()
                        : ListView(
                            padding: const EdgeInsets.only(bottom: 24),
                            children: [
                              if (today.isNotEmpty) ...[
                                const _SectionLabel('TODAY'),
                                ...today.map(
                                  (n) => _NotificationTile(
                                    notification: n,
                                    onDismiss: () => _dismiss(n),
                                    onTap: () => _onTapNotification(n),
                                  ),
                                ),
                              ],
                              if (earlier.isNotEmpty) ...[
                                const _SectionLabel('EARLIER'),
                                ...earlier.map(
                                  (n) => _NotificationTile(
                                    notification: n,
                                    onDismiss: () => _dismiss(n),
                                    onTap: () => _onTapNotification(n),
                                  ),
                                ),
                              ],
                            ],
                          ),
          ),
        ),
      ],
    ),
  );
}
}

class _NotificationsHeader extends StatelessWidget {
  // final int unreadCount;
  final VoidCallback onMarkAllRead;
  final VoidCallback onClearAll;

  const _NotificationsHeader({
    // required this.unreadCount,
    required this.onMarkAllRead,
    required this.onClearAll,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(4, 6, 12, 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.black87),
                  onPressed: () => Navigator.of(context).maybePop(),
                ),
                Expanded(
                  child: Text(
                    'NOTIFICATIONS',
                    overflow: TextOverflow.ellipsis,
                    maxLines: 1,
                    style: const TextStyle(
                        fontSize: 17, fontWeight: FontWeight.w800, color: Colors.black87),
                  ),
                ),
                // if (unreadCount > 0) ...[
                //   const SizedBox(width: 6),
                //   Container(
                //     padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                //     decoration: const BoxDecoration(color: kBrandOrange, shape: BoxShape.circle),
                //     constraints: const BoxConstraints(minWidth: 20),
                //     // child: Text(
                //     //   '$unreadCount',
                //     //   textAlign: TextAlign.center,
                //     //   style: const TextStyle(
                //     //       color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700),
                //     // ),
                //   ),
                //   const SizedBox(width: 8),
                // ],
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(left: 48, top: 2, bottom: 6),
              child: Text(
                'alerts & system updates',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(left: 48),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  return Wrap(
                    spacing: 4,
                    runSpacing: 2,
                    children: [
                      TextButton(
                        onPressed: onMarkAllRead,
                        style: TextButton.styleFrom(
                          foregroundColor: kBrandOrange,
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: const Text('Mark all read',
                            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                      ),
                      TextButton(
                        onPressed: onClearAll,
                        style: TextButton.styleFrom(
                          foregroundColor: Colors.black54,
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: const Text('Clear all',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilterChipsRow extends StatelessWidget {
  final NotificationFilter selected;
  final ValueChanged<NotificationFilter> onChanged;

  const _FilterChipsRow({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Row(
        children: [
          _FilterChip(
            label: 'All',
            isSelected: selected == NotificationFilter.all,
            onTap: () => onChanged(NotificationFilter.all),
          ),
          const SizedBox(width: 8),
          _FilterChip(
            label: 'Alerts',
            isSelected: selected == NotificationFilter.alerts,
            onTap: () => onChanged(NotificationFilter.alerts),
          ),
          const SizedBox(width: 8),
          _FilterChip(
            label: 'System',
            isSelected: selected == NotificationFilter.system,
            onTap: () => onChanged(NotificationFilter.system),
          ),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _FilterChip({required this.label, required this.isSelected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: isSelected ? kBrandOrange : Colors.white,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: isSelected ? kBrandOrange : Colors.grey.shade300),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: isSelected ? Colors.white : Colors.black54,
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;

  const _SectionLabel(this.text);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: const Color(0xFFEDEDED),
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
      child: Text(
        text,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.grey.shade500, letterSpacing: .5),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  final AppNotification notification;
  final VoidCallback onDismiss;
  final VoidCallback onTap;

  const _NotificationTile({required this.notification, required this.onDismiss, required this.onTap});

  ({IconData icon, Color color}) get _iconStyle {
    switch (notification.kind) {
      case NotificationKind.warning:
        return (icon: Icons.warning_rounded, color: const Color(0xFFE8A23D));
      case NotificationKind.overdue:
        return (icon: Icons.error_rounded, color: const Color(0xFFD23B5C));
      case NotificationKind.success:
        return (icon: Icons.check_circle_rounded, color: const Color(0xFF2E8B3D));
      case NotificationKind.info:
        return (icon: Icons.info_rounded, color: const Color(0xFF3B82D2));
      case NotificationKind.maintenance:
        return (icon: Icons.error_rounded, color: const Color(0xFFD23B5C));
      case NotificationKind.systemUpdate:
        return (icon: Icons.build_rounded, color: const Color(0xFF8B5CF6));
    }
  }

  @override
  Widget build(BuildContext context) {
    final style = _iconStyle;
    final isUnread = !notification.isRead;

    return Dismissible(
      key: ValueKey(notification.id),
      direction: DismissDirection.endToStart,
      onDismissed: (_) => onDismiss(),
      background: Container(
        color: const Color(0xFFD23B5C),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: const Icon(Icons.delete_outline, color: Colors.white),
      ),
      child: InkWell(
        onTap: onTap,
        child: Container(
          color: isUnread ? const Color(0xFFFFF8EF) : Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: style.color.withValues(alpha: 0.12), shape: BoxShape.circle),
                child: Icon(style.icon, color: style.color, size: 19),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      notification.title,
                      style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: Colors.black87),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      notification.message,
                      style: TextStyle(fontSize: 13, color: Colors.grey.shade700, height: 1.3),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(Icons.access_time_rounded, size: 12, color: Colors.grey.shade400),
                        const SizedBox(width: 4),
                        Text(
                          notification.timeLabel,
                          style: TextStyle(fontSize: 11.5, color: Colors.grey.shade400),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              if (isUnread)
                Container(
                  width: 8,
                  height: 8,
                  margin: const EdgeInsets.only(top: 4),
                  decoration: const BoxDecoration(color: kBrandOrange, shape: BoxShape.circle),
                )
              else
                GestureDetector(
                  onTap: onDismiss,
                  child: Icon(Icons.close, size: 16, color: Colors.grey.shade400),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        const SizedBox(height: 100),
        Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.notifications_off_outlined, size: 40, color: Colors.grey.shade400),
              const SizedBox(height: 10),
              Text('No notifications', style: TextStyle(color: Colors.grey.shade500, fontSize: 14)),
            ],
          ),
        ),
      ],
    );
  }
}
