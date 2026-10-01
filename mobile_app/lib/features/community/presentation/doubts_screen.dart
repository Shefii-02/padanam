import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_client.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../learning/data/learning_repository.dart';
import '../data/doubts_repository.dart';

/// My doubts. With [courseId] it shows the course's answered doubts too.
class DoubtsScreen extends ConsumerStatefulWidget {
  const DoubtsScreen({super.key, this.courseId});
  final int? courseId;

  @override
  ConsumerState<DoubtsScreen> createState() => _DoubtsScreenState();
}

class _DoubtsScreenState extends ConsumerState<DoubtsScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final course = widget.courseId;
    final value = course != null && _tab == 1 ? ref.watch(courseDoubtsProvider(course)) : ref.watch(myDoubtsProvider);
    return SubPage(
      title: 'Doubts',
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push(R.askDoubt(courseId: course)),
        icon: const Icon(Icons.add_comment_rounded),
        label: const Text('Ask a doubt'),
      ),
      body: Column(children: [
        if (course != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: SegmentTabs(items: const ['My doubts', 'Answered in this course'], selected: _tab, onChanged: (i) => setState(() => _tab = i)),
          ),
        Expanded(
          child: AsyncView<Paged>(
            value: value,
            onRetry: () => ref.invalidate(myDoubtsProvider),
            data: (page) => page.items.isEmpty
                ? const EmptyView(emoji: '🙋', title: 'No doubts yet', subtitle: 'Stuck on a question? Ask your teachers – you get a notification when they reply.')
                : RefreshIndicator(
                    onRefresh: () async {
                      ref.invalidate(myDoubtsProvider);
                      if (course != null) ref.invalidate(courseDoubtsProvider(course));
                    },
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
                      itemCount: page.items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (_, i) => DoubtCard(d: page.items[i]),
                    ),
                  ),
          ),
        ),
      ]),
    );
  }
}

class DoubtCard extends StatelessWidget {
  const DoubtCard({super.key, required this.d});
  final Json d;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final answered = d.sn('answer') != null;
    return AppCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          Expanded(
            child: Text([if (d.sn('asked_by') != null) d.s('asked_by'), d.s('course', 'General'), if (d.s('subject').isNotEmpty) d.s('subject')].join(' · '),
                style: TextStyle(fontSize: 12.5, color: p.muted, fontWeight: FontWeight.w600)),
          ),
          Tag(answered ? 'Answered' : 'Waiting', tone: answered ? Tone.mint : Tone.peach),
        ]),
        const SizedBox(height: 8),
        Text(d.s('text'), style: const TextStyle(fontSize: 14.5, height: 1.5)),
        if (d.sn('image_url') != null) ...[
          const SizedBox(height: 8),
          ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(d.s('image_url'), height: 160, fit: BoxFit.cover)),
        ],
        if (answered) ...[
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: AppColors.mint, borderRadius: BorderRadius.circular(12)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(d.s('answered_by', 'Teacher'), style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.green, fontSize: 12.5)),
              const SizedBox(height: 4),
              SelectableText(d.s('answer'), style: const TextStyle(height: 1.5)),
            ]),
          ),
        ],
      ]),
    );
  }
}

/// Ask a doubt: course, subject, text, optional photo.
class AskDoubtScreen extends ConsumerStatefulWidget {
  const AskDoubtScreen({super.key, this.courseId, this.context});
  final int? courseId;
  final String? context;

  @override
  ConsumerState<AskDoubtScreen> createState() => _AskDoubtScreenState();
}

class _AskDoubtScreenState extends ConsumerState<AskDoubtScreen> {
  late int? _course = widget.courseId;
  late final _text = TextEditingController(text: widget.context == null ? '' : 'About “${widget.context}”: ');
  final _subject = TextEditingController();
  String? _image;
  bool _busy = false;

  @override
  void dispose() {
    _text.dispose();
    _subject.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_text.text.trim().length < 5) {
      showSoon(context, 'Please write your question');
      return;
    }
    setState(() => _busy = true);
    final r = await runAction(context, () => ref.read(doubtsRepositoryProvider).ask(courseId: _course, subject: _subject.text.trim(), text: _text.text.trim(), imagePath: _image),
        success: 'Doubt sent. A teacher will reply soon');
    if (!mounted) return;
    setState(() => _busy = false);
    if (r != null) {
      ref.invalidate(myDoubtsProvider);
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final courses = ref.watch(myCoursesProvider).value?.where((c) => c.s('role') == 'student' && c.s('status') == 'active').toList() ?? const [];
    return SubPage(
      title: 'Ask a doubt',
      footer: AppButton(_busy ? 'Sending…' : 'Send to teachers', expand: true, onPressed: _busy ? null : _send),
      children: [
        DropdownButtonFormField<int?>(
          value: courses.any((c) => c.i('course_id') == _course) ? _course : null,
          decoration: const InputDecoration(labelText: 'Course'),
          items: [
            const DropdownMenuItem(value: null, child: Text('General question')),
            for (final c in courses) DropdownMenuItem(value: c.i('course_id'), child: Text(c.s('title'), overflow: TextOverflow.ellipsis)),
          ],
          onChanged: (v) => setState(() => _course = v),
        ),
        const SizedBox(height: 12),
        TextField(controller: _subject, decoration: const InputDecoration(labelText: 'Subject (optional)', hintText: 'GK, Maths, English…')),
        const SizedBox(height: 12),
        TextField(controller: _text, minLines: 5, maxLines: 10, maxLength: 2000, decoration: const InputDecoration(labelText: 'Your question', alignLabelWithHint: true)),
        const SizedBox(height: 8),
        Row(children: [
          AppButton(_image == null ? 'Add a photo' : 'Change photo', outlined: true, small: true, emoji: '📷', onPressed: () async {
            final x = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 70, maxWidth: 1600);
            if (x != null) setState(() => _image = x.path);
          }),
          if (_image != null) ...[
            const SizedBox(width: 8),
            TextButton(onPressed: () => setState(() => _image = null), child: const Text('Remove')),
          ],
        ]),
      ],
    );
  }
}
