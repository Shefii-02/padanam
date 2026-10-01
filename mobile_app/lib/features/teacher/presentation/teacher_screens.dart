import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_client.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../../live/presentation/live_screens.dart';
import '../data/teacher_repository.dart';

/// Teacher mode: today's classes (go live), waiting doubts, quick links.
class TeacherHomeScreen extends ConsumerWidget {
  const TeacherHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final user = ref.watch(currentUserProvider);
    final doubts = ref.watch(teacherDoubtsProvider('open'));
    return SubPage(
      title: 'Teacher mode',
      subtitle: 'Hello, ${user?.firstName ?? ''}',
      body: AsyncView<Paged>(
        value: ref.watch(myClassesProvider),
        onRetry: () => ref.invalidate(myClassesProvider),
        data: (page) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(myClassesProvider);
            ref.invalidate(teacherDoubtsProvider('open'));
          },
          child: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
            Row(children: [
              Expanded(child: _Stat('${page.items.length}', 'Classes coming up')),
              const SizedBox(width: 10),
              Expanded(child: _Stat(doubts.value == null ? '…' : '${doubts.value!.pagination['total'] ?? doubts.value!.items.length}', 'Doubts waiting', onTap: () => context.push(R.teacherDoubts))),
            ]),
            const SectionTitle('My classes'),
            if (page.items.isEmpty) const EmptyNote('No classes scheduled for you. The office schedules them in the admin panel.'),
            for (final l in page.items)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text(l.s('status') == 'live' ? 'LIVE NOW' : liveWhen(l.s('starts_at')), style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: l.s('status') == 'live' ? AppColors.red : AppColors.primary2)),
                    const SizedBox(height: 4),
                    Text(l.s('title'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5)),
                    Text('${l.s('course')} · ${l.s('batch')}', style: TextStyle(fontSize: 12.5, color: p.muted)),
                    const SizedBox(height: 10),
                    AppButton(
                      l.s('status') == 'live' ? 'Manage live class' : 'Go live',
                      expand: true,
                      color: AppColors.red,
                      onPressed: () => context.push(R.goLive(l.i('id')), extra: l),
                    ),
                  ]),
                ),
              ),
            const SectionTitle('Quick actions'),
            ListRow(leading: const IconBox('🙋', tone: Tone.peach), title: 'Answer doubts', onTap: () => context.push(R.teacherDoubts), trailing: Icon(Icons.chevron_right_rounded, color: p.muted)),
            ListRow(leading: const IconBox('💬', tone: Tone.mint), title: 'Batch chats', onTap: () => context.go(R.chat), trailing: Icon(Icons.chevron_right_rounded, color: p.muted)),
            ListRow(leading: const IconBox('🎓', tone: Tone.lav), title: 'Courses I teach', onTap: () => context.go(R.courses), trailing: Icon(Icons.chevron_right_rounded, color: p.muted)),
            const SizedBox(height: 8),
            Text('Tests, OMR checking and content upload are in the admin panel on a computer.', style: TextStyle(fontSize: 12, color: p.muted)),
          ]),
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.value, this.label, {this.onTap});
  final String value, label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => AppCard(
        onTap: onTap,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
          Text(label, style: TextStyle(fontSize: 12, color: context.palette.muted, fontWeight: FontWeight.w700)),
        ]),
      );
}

/// Go live: open Meet → stream to YouTube → paste link → students are notified. End saves the recording.
class GoLiveScreen extends ConsumerStatefulWidget {
  const GoLiveScreen({super.key, required this.liveId, this.info});
  final int liveId;
  final Json? info;

  @override
  ConsumerState<GoLiveScreen> createState() => _GoLiveScreenState();
}

class _GoLiveScreenState extends ConsumerState<GoLiveScreen> {
  final _url = TextEditingController();
  late bool _live = widget.info?.s('status') == 'live';
  bool _busy = false;
  Json? _att;

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  Future<void> _start() async {
    setState(() => _busy = true);
    final r = await runAction(context, () => ref.read(teacherRepositoryProvider).goLive(widget.liveId, _url.text.trim()), success: 'You are live. Students were notified');
    if (mounted) {
      setState(() {
        _busy = false;
        if (r != null) _live = true;
      });
    }
    ref.invalidate(myClassesProvider);
  }

  Future<void> _end() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('End class?'),
        content: const Text('The YouTube video is saved as the recording in the course folder.'),
        actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('End class'))],
      ),
    );
    if (ok != true || !mounted) return;
    final r = await runAction(context, () => ref.read(teacherRepositoryProvider).end(widget.liveId), success: 'Class ended – recording saved');
    ref.invalidate(myClassesProvider);
    if (r != null && mounted) context.pop();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final i = widget.info ?? const <String, dynamic>{};
    return SubPage(
      title: _live ? 'You are live' : 'Go live',
      subtitle: i.s('title'),
      footer: _live
          ? AppButton('End class & save recording', expand: true, color: AppColors.red, onPressed: _end)
          : AppButton(_busy ? 'Starting…' : 'Start class & notify students', expand: true, color: AppColors.red, onPressed: _busy || !_url.text.contains('youtu') ? null : _start),
      children: [
        if (!_live) ...[
          AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              for (final (n, t) in [
                (1, 'Open the class Google Meet and start the meeting.'),
                (2, 'In Meet: Activities → Live streaming → Padanam channel → visibility Unlisted.'),
                (3, 'Copy the YouTube link and paste it below.'),
              ])
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    CircleAvatar(radius: 13, backgroundColor: AppColors.lavender, child: Text('$n', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary))),
                    const SizedBox(width: 10),
                    Expanded(child: Text(t, style: const TextStyle(height: 1.45))),
                  ]),
                ),
              if (i.sn('meet_url') != null) ...[
                const SizedBox(height: 6),
                AppButton('Open Google Meet', outlined: true, onPressed: () => launchUrl(Uri.parse(i.s('meet_url')), mode: LaunchMode.externalApplication)),
              ],
            ]),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _url,
            onChanged: (_) => setState(() {}),
            keyboardType: TextInputType.url,
            decoration: InputDecoration(
              labelText: 'YouTube live link',
              hintText: 'https://youtube.com/live/…',
              suffixIcon: IconButton(
                tooltip: 'Paste',
                icon: const Icon(Icons.content_paste_rounded),
                onPressed: () async {
                  final d = await Clipboard.getData('text/plain');
                  if (d?.text != null) setState(() => _url.text = d!.text!.trim());
                },
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text('Students get a “Live now” notification. The first time, YouTube may take up to 24 hours to enable live streaming on the channel.', style: TextStyle(fontSize: 12, color: p.muted)),
        ] else ...[
          AppCard(
            color: AppColors.primary,
            child: const Row(children: [
              Icon(Icons.sensors_rounded, color: Colors.white, size: 30),
              SizedBox(width: 12),
              Expanded(child: Text('Class is live. Students can watch in the app.', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800))),
            ]),
          ),
          const SizedBox(height: 12),
          AppButton(_att == null ? 'Show attendance' : 'Refresh attendance', outlined: true, expand: true, onPressed: () async {
            final a = await runAction(context, () => ref.read(teacherRepositoryProvider).attendance(widget.liveId));
            if (a != null && mounted) setState(() => _att = a);
          }),
          if (_att != null) ...[
            const SizedBox(height: 10),
            Text('${_att!.i('present')} of ${_att!.i('enrolled')} students joined (${_att!.s('percent')}%)', style: const TextStyle(fontWeight: FontWeight.w800)),
            for (final s in _att!.l('students').take(50)) ListRow(title: s.s('name'), subtitle: s.s('phone')),
          ],
        ],
      ],
    );
  }
}

/// Answer doubts from my courses.
class TeacherDoubtsScreen extends ConsumerStatefulWidget {
  const TeacherDoubtsScreen({super.key});

  @override
  ConsumerState<TeacherDoubtsScreen> createState() => _TeacherDoubtsScreenState();
}

class _TeacherDoubtsScreenState extends ConsumerState<TeacherDoubtsScreen> {
  int _tab = 0;
  final _answers = <int, TextEditingController>{};

  @override
  void dispose() {
    for (final c in _answers.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final status = _tab == 0 ? 'open' : 'answered';
    return SubPage(
      title: 'Doubts',
      body: Column(children: [
        Padding(padding: const EdgeInsets.fromLTRB(16, 4, 16, 8), child: SegmentTabs(items: const ['Waiting', 'Answered'], selected: _tab, onChanged: (i) => setState(() => _tab = i))),
        Expanded(
          child: AsyncView<Paged>(
            value: ref.watch(teacherDoubtsProvider(status)),
            onRetry: () => ref.invalidate(teacherDoubtsProvider(status)),
            data: (page) => page.items.isEmpty
                ? EmptyView(emoji: '🎉', title: _tab == 0 ? 'No doubts waiting' : 'Nothing answered yet')
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                    itemCount: page.items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (_, i) {
                      final d = page.items[i];
                      final c = _answers.putIfAbsent(d.i('id'), TextEditingController.new);
                      return AppCard(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                          Text('${d.m('student').s('name', 'Student')} · ${d.s('course', 'General')}${d.s('subject').isEmpty ? '' : ' · ${d.s('subject')}'}',
                              style: TextStyle(fontSize: 12.5, color: p.muted, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          Text(d.s('text'), style: const TextStyle(fontSize: 14.5, height: 1.5)),
                          if (d.sn('image_url') != null) ...[
                            const SizedBox(height: 8),
                            ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(d.s('image_url'), height: 180, fit: BoxFit.cover)),
                          ],
                          const SizedBox(height: 10),
                          if (d.sn('answer') != null)
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(color: AppColors.mint, borderRadius: BorderRadius.circular(12)),
                              child: Text(d.s('answer'), style: const TextStyle(height: 1.5)),
                            )
                          else ...[
                            TextField(controller: c, minLines: 2, maxLines: 6, decoration: const InputDecoration(hintText: 'Type your answer…')),
                            const SizedBox(height: 8),
                            AppButton('Send answer', expand: true, onPressed: () async {
                              if (c.text.trim().length < 2) return;
                              final r = await runAction(context, () => ref.read(teacherRepositoryProvider).answer(d.i('id'), c.text.trim()), success: 'Answer sent');
                              if (r != null) ref.invalidate(teacherDoubtsProvider('open'));
                            }),
                          ],
                        ]),
                      );
                    },
                  ),
          ),
        ),
      ]),
    );
  }
}
