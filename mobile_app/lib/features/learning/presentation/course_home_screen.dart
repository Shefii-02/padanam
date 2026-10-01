import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/learning_repository.dart';
import 'widgets/content_tile.dart';

/// Inside a course: tabs (Classes, Live, Tests, Notes, Articles, Doubts, Group chat) → folders → items.
/// A sub-folder opens the same screen again with folderId.
class CourseHomeScreen extends ConsumerStatefulWidget {
  const CourseHomeScreen({super.key, required this.courseId, this.title, this.tab, this.folderId});
  final int courseId;
  final String? title, tab;
  final int? folderId;

  @override
  ConsumerState<CourseHomeScreen> createState() => _CourseHomeScreenState();
}

class _CourseHomeScreenState extends ConsumerState<CourseHomeScreen> {
  late String? _tab = widget.tab;

  ({int courseId, String? tab, int? folderId}) get _args => (courseId: widget.courseId, tab: _tab, folderId: widget.folderId);

  void _openTab(String key) {
    if (key == 'doubts') {
      context.push(R.courseDoubts(widget.courseId));
      return;
    }
    if (key == 'chat') {
      context.go(R.chat);
      return;
    }
    setState(() => _tab = key);
  }

  void _openItem(Json item) {
    if (item.b('locked')) {
      showSoon(context, item.s('lock_reason', 'This is locked'));
      return;
    }
    context.push(R.content(item.i('id')));
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final data = ref.watch(browseProvider(_args));
    final folderTitle = data.value?.m('folder').sn('title');
    return SubPage(
      title: folderTitle ?? widget.title ?? 'Course',
      subtitle: folderTitle != null ? widget.title : null,
      actions: [
        if (widget.folderId == null) IconButton(tooltip: 'Class alerts', icon: Icon(Icons.notifications_active_rounded, color: p.brand), onPressed: () => _alerts(context)),
      ],
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(browseProvider(_args)),
        data: (d) {
          final tabs = d.l('tabs');
          final current = d.s('tab');
          final folders = d.l('folders');
          final items = d.l('items');
          return RefreshIndicator(
            onRefresh: () => ref.refresh(browseProvider(_args).future),
            child: ListView(padding: EdgeInsets.fromLTRB(context.hPad, 0, context.hPad, 24), children: [
              if (widget.folderId == null && tabs.length > 1)
                SizedBox(
                  height: 48,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: tabs.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 8),
                    itemBuilder: (_, i) {
                      final t = tabs[i];
                      final on = t.s('key') == current;
                      return ChoiceChip(
                        label: Text(t.s('label')),
                        selected: on,
                        onSelected: (_) => _openTab(t.s('key')),
                        selectedColor: AppColors.primary,
                        labelStyle: TextStyle(color: on ? Colors.white : p.ink, fontWeight: FontWeight.w700),
                        showCheckmark: false,
                      );
                    },
                  ),
                ),
              if (d.l('breadcrumbs').length > 1)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(d.l('breadcrumbs').map((b) => b.s('title')).join('  ›  '), style: TextStyle(fontSize: 12, color: p.muted)),
                ),
              const SizedBox(height: 8),
              for (final f in folders)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    onTap: f.b('locked')
                        ? () => showSoon(context, f.sn('unlock_at') != null ? 'Opens on ${f.s('unlock_at').split('T').first}' : 'This folder is locked')
                        : () => context.push(R.learn(widget.courseId, title: widget.title, tab: current, folderId: f.i('id'))),
                    child: Row(children: [
                      IconBox(f.b('locked') ? '🔒' : '📁', tone: Tone.lav),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(f.s('title'), style: const TextStyle(fontWeight: FontWeight.w800)),
                          Text(f.b('locked') && f.sn('unlock_at') != null ? 'Opens ${f.s('unlock_at').split('T').first}' : '${f.i('items')} items',
                              style: TextStyle(fontSize: 12.5, color: p.muted)),
                        ]),
                      ),
                      Icon(Icons.chevron_right_rounded, color: p.muted),
                    ]),
                  ),
                ),
              if (items.isNotEmpty)
                AppCard(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  child: Column(children: [
                    for (var i = 0; i < items.length; i++) ...[
                      if (i > 0) Divider(height: 1, color: p.line),
                      ContentTile(item: items[i], onTap: () => _openItem(items[i])),
                    ],
                  ]),
                ),
              if (folders.isEmpty && items.isEmpty) const SizedBox(height: 300, child: EmptyView(emoji: '📭', title: 'Nothing here yet', subtitle: 'Your teachers will add classes and notes soon.')),
            ]),
          );
        },
      ),
    );
  }

  Future<void> _alerts(BuildContext context) async {
    final on = await showModalBottomSheet<bool>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const ListTile(title: Text('Class alerts for this course', style: TextStyle(fontWeight: FontWeight.w800)), subtitle: Text('A full-screen ring 5 minutes before every live class.')),
          ListTile(leading: const Icon(Icons.notifications_active_rounded), title: const Text('Turn on'), onTap: () => Navigator.pop(context, true)),
          ListTile(leading: const Icon(Icons.notifications_off_rounded), title: const Text('Turn off'), onTap: () => Navigator.pop(context, false)),
        ]),
      ),
    );
    if (on == null || !context.mounted) return;
    await runAction(context, () => ref.read(learningRepositoryProvider).classAlerts(widget.courseId, on), success: on ? 'Class alerts on' : 'Class alerts off');
  }
}
