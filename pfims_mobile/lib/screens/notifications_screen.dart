import 'package:flutter/material.dart';
import '../widgets/app_header.dart' show kBrandOrange;

enum NotificationFilter { all, alerts, system }

enum NotificationKind { warning, overdue, success, info, maintenance, systemUpdate }

class AppNotification {
  final String title;
  final String message;
  final String timeLabel;
  final NotificationKind kind;
  final NotificationFilter filter;
  bool isRead;

  AppNotification({
    required this.title,
    required this.message,
    required this.timeLabel,
    required this.kind,
    required this.filter,
    this.isRead = false,
  });
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  NotificationFilter _selectedFilter = NotificationFilter.all;

  // TODO: replace with real data from your backend / state management.
  final List<AppNotification> _today = [
    AppNotification(
      title: 'Budget Threshold Reached',
      message: 'Northgate Tower Phase 2 has consumed 88% of its allocated budget.',
      timeLabel: 'Just now',
      kind: NotificationKind.warning,
      filter: NotificationFilter.alerts,
    ),
    AppNotification(
      title: 'Overdue Task',
      message: 'Steel reinforcement inspection for Block C was due 2 days ago.',
      timeLabel: '1 hr ago',
      kind: NotificationKind.overdue,
      filter: NotificationFilter.alerts,
    ),
    AppNotification(
      title: 'Milestone Completed',
      message: 'Foundation works for Harbor View Residences signed off by QA.',
      timeLabel: '3 hrs ago',
      kind: NotificationKind.success,
      filter: NotificationFilter.system,
    ),
    AppNotification(
      title: 'New Comment',
      message: "Engr. Santos left a note on Project #EVC-081: 'Rebar delivery rescheduled to Friday.'",
      timeLabel: '5 hrs ago',
      kind: NotificationKind.info,
      filter: NotificationFilter.system,
    ),
  ];

  final List<AppNotification> _yesterday = [
    AppNotification(
      title: 'Equipment Maintenance Due',
      message: 'Tower crane TC-04 is scheduled for its 500-hour service check.',
      timeLabel: 'Yesterday · 4:12 PM',
      kind: NotificationKind.maintenance,
      filter: NotificationFilter.alerts,
      isRead: true,
    ),
    AppNotification(
      title: 'System Update Applied',
      message: 'EVC-DCS was updated to v2.4.1. See release notes for details.',
      timeLabel: 'Yesterday · 1:00 AM',
      kind: NotificationKind.systemUpdate,
      filter: NotificationFilter.system,
      isRead: true,
    ),
  ];

  int get _unreadCount =>
      [..._today, ..._yesterday].where((n) => !n.isRead).length;

  List<AppNotification> _applyFilter(List<AppNotification> items) {
    if (_selectedFilter == NotificationFilter.all) return items;
    return items.where((n) => n.filter == _selectedFilter).toList();
  }

  void _markAllRead() {
    setState(() {
      for (final n in [..._today, ..._yesterday]) {
        n.isRead = true;
      }
    });
  }

  void _clearAll() {
    setState(() {
      _today.clear();
      _yesterday.clear();
    });
  }

  void _dismiss(AppNotification notification) {
    setState(() {
      _today.remove(notification);
      _yesterday.remove(notification);
    });
  }

  @override
  Widget build(BuildContext context) {
    final todayFiltered = _applyFilter(_today);
    final yesterdayFiltered = _applyFilter(_yesterday);
    final isEmpty = todayFiltered.isEmpty && yesterdayFiltered.isEmpty;

    return Scaffold(
      backgroundColor: const Color(0xFFF5F5F5),
      appBar: _NotificationsAppBar(
        unreadCount: _unreadCount,
        onMarkAllRead: _markAllRead,
        onClearAll: _clearAll,
      ),
      body: Column(
        children: [
          _FilterChipsRow(
            selected: _selectedFilter,
            onChanged: (filter) => setState(() => _selectedFilter = filter),
          ),
          Expanded(
            child: isEmpty
                ? const _EmptyState()
                : ListView(
                    padding: const EdgeInsets.only(bottom: 24),
                    children: [
                      if (todayFiltered.isNotEmpty) ...[
                        const _SectionLabel('TODAY'),
                        ...todayFiltered.map(
                          (n) => _NotificationTile(notification: n, onDismiss: () => _dismiss(n)),
                        ),
                      ],
                      if (yesterdayFiltered.isNotEmpty) ...[
                        const _SectionLabel('YESTERDAY'),
                        ...yesterdayFiltered.map(
                          (n) => _NotificationTile(notification: n, onDismiss: () => _dismiss(n)),
                        ),
                      ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _NotificationsAppBar extends StatelessWidget implements PreferredSizeWidget {
  final int unreadCount;
  final VoidCallback onMarkAllRead;
  final VoidCallback onClearAll;

  const _NotificationsAppBar({
    required this.unreadCount,
    required this.onMarkAllRead,
    required this.onClearAll,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 6, 16, 10),
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
          children: [
            Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.black87),
                  onPressed: () => Navigator.of(context).maybePop(),
                ),
                const Text(
                  'NOTIFICATIONS',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Colors.black87),
                ),
                const SizedBox(width: 8),
                if (unreadCount > 0)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: const BoxDecoration(color: kBrandOrange, shape: BoxShape.circle),
                    constraints: const BoxConstraints(minWidth: 22),
                    child: Text(
                      '$unreadCount',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700),
                    ),
                  ),
                const Spacer(),
                TextButton(
                  onPressed: onMarkAllRead,
                  style: TextButton.styleFrom(
                    foregroundColor: kBrandOrange,
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                  ),
                  child: const Text('Mark all read', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
                ),
                TextButton(
                  onPressed: onClearAll,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.black54,
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                  ),
                  child: const Text('Clear all', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12.5)),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(left: 48),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'alerts & system updates',
                  style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(78);
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

  const _NotificationTile({required this.notification, required this.onDismiss});

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
      key: ValueKey(identityHashCode(notification)),
      direction: DismissDirection.endToStart,
      onDismissed: (_) => onDismiss(),
      background: Container(
        color: const Color(0xFFD23B5C),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: const Icon(Icons.delete_outline, color: Colors.white),
      ),
      child: Container(
        color: isUnread ? const Color(0xFFFFF8EF) : Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(color: style.color.withOpacity(0.12), shape: BoxShape.circle),
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
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.notifications_off_outlined, size: 40, color: Colors.grey.shade400),
          const SizedBox(height: 10),
          Text('No notifications', style: TextStyle(color: Colors.grey.shade500, fontSize: 14)),
        ],
      ),
    );
  }
}