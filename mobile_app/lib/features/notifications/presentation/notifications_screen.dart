import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../home/data/home_repository.dart';
import '../../learning/data/learning_repository.dart';
import '../data/notifications_repository.dart';

const _channelEmoji = {
  'class_alert': '🔴', 'live_class': '🔴', 'course_update': '📚', 'test_result': '📊', 'reminder': '⏰', 'offer': '🏷️', 'announcement': '📣',
  'payment': '💳', 'chat': '💬',
};

/// Notification inbox – tap opens the screen the notification points to.
class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return SubPage(
      title: 'Notifications',
      actions: [
        TextButton(
          onPressed: () async {
            await runAction(context, () => ref.read(notificationsRepositoryProvider).markAllRead());
            ref.invalidate(inboxProvider);
            ref.invalidate(homeProvider);
          },
          child: const Text('Mark all read'),
        ),
        IconButton(tooltip: 'Settings', icon: Icon(Icons.tune_rounded, color: p.brand), onPressed: () => context.push(R.notificationSettings)),
      ],
      body: AsyncView<Paged>(
        value: ref.watch(inboxProvider),
        onRetry: () => ref.invalidate(inboxProvider),
        data: (page) => page.items.isEmpty
            ? const EmptyView(emoji: '🔔', title: 'No notifications yet', subtitle: 'Class alerts, new notes, results and offers appear here.')
            : RefreshIndicator(
                onRefresh: () => ref.refresh(inboxProvider.future),
                child: ListView.separated(
                  itemCount: page.items.length,
                  separatorBuilder: (_, __) => Divider(height: 1, color: p.line),
                  itemBuilder: (_, i) {
                    final n = page.items[i];
                    final unread = !n.b('read');
                    return Material(
                      color: unread ? p.card : Colors.transparent,
                      child: InkWell(
                        onTap: () {
                          ref.read(notificationsRepositoryProvider).opened(n.i('id')).ignore();
                          final link = n.sn('deep_link');
                          if (link != null && link.isNotEmpty) context.open(link);
                          ref.invalidate(inboxProvider);
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            IconBox(n.s('icon').isNotEmpty ? n.s('icon') : (_channelEmoji[n.s('channel')] ?? '🔔'), tone: Tone.lav, size: 40),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(n.s('title'), style: TextStyle(fontWeight: unread ? FontWeight.w800 : FontWeight.w600)),
                                const SizedBox(height: 2),
                                Text(n.s('body'), style: TextStyle(fontSize: 13, color: p.muted, height: 1.4)),
                                const SizedBox(height: 4),
                                Text(_ago(n.s('created_at')), style: TextStyle(fontSize: 11.5, color: p.muted)),
                              ]),
                            ),
                            if (unread) const Padding(padding: EdgeInsets.only(top: 6), child: CircleAvatar(radius: 4, backgroundColor: AppColors.primary2)),
                          ]),
                        ),
                      ),
                    );
                  },
                ),
              ),
      ),
    );
  }

  static String _ago(String iso) {
    final d = DateTime.tryParse(iso)?.toLocal();
    if (d == null) return '';
    final s = DateTime.now().difference(d);
    if (s.inMinutes < 1) return 'just now';
    if (s.inHours < 1) return '${s.inMinutes} min ago';
    if (s.inDays < 1) return '${s.inHours} h ago';
    return '${s.inDays} d ago';
  }
}

/// Which notifications to receive + class alerts per course.
class NotificationSettingsScreen extends ConsumerWidget {
  const NotificationSettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final courses = ref.watch(myCoursesProvider).value?.where((c) => c.s('role') == 'student' && c.s('status') == 'active').toList() ?? const <Json>[];
    return SubPage(
      title: 'Notification settings',
      body: AsyncView<List<Json>>(
        value: ref.watch(notificationPrefsProvider),
        onRetry: () => ref.invalidate(notificationPrefsProvider),
        data: (prefs) => ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
          if (courses.isNotEmpty) ...[
            const SectionTitle('Class alerts (full-screen ring)'),
            Text('Rings 5 minutes before every live class of the course.', style: TextStyle(fontSize: 12.5, color: p.muted)),
            const SizedBox(height: 8),
            AppCard(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Column(children: [
                for (final c in courses) _CourseAlert(course: c),
              ]),
            ),
          ],
          const SectionTitle('Notifications'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Column(children: [
              for (final c in prefs)
                SwitchListTile(
                  title: Text(c.s('name'), style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: c.s('description').isEmpty ? null : Text(c.s('description')),
                  value: c.b('enabled', true),
                  onChanged: c.b('can_disable')
                      ? (v) async {
                          await runAction(context, () => ref.read(notificationsRepositoryProvider).setPreference(c.s('key'), v));
                          ref.invalidate(notificationPrefsProvider);
                        }
                      : null,
                ),
            ]),
          ),
        ]),
      ),
    );
  }
}

class _CourseAlert extends ConsumerStatefulWidget {
  const _CourseAlert({required this.course});
  final Json course;

  @override
  ConsumerState<_CourseAlert> createState() => _CourseAlertState();
}

class _CourseAlertState extends ConsumerState<_CourseAlert> {
  late bool _on = widget.course.b('class_alerts', true);

  @override
  Widget build(BuildContext context) => SwitchListTile(
        title: Text(widget.course.s('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
        value: _on,
        onChanged: (v) async {
          setState(() => _on = v);
          final r = await runAction(context, () => ref.read(learningRepositoryProvider).classAlerts(widget.course.i('course_id'), v));
          if (r == null && mounted) setState(() => _on = !v);
        },
      );
}
