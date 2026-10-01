import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../store/presentation/courses_tab_screen.dart';
import '../data/exam_repository.dart';

/// One exam category: courses, free tests, exam info (dates, eligibility, pattern, posts) and articles.
class ExamHubScreen extends ConsumerStatefulWidget {
  const ExamHubScreen({super.key, required this.examId});
  final String examId;

  @override
  ConsumerState<ExamHubScreen> createState() => _ExamHubScreenState();
}

class _ExamHubScreenState extends ConsumerState<ExamHubScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final hub = ref.watch(examHubProvider(widget.examId));
    return SubPage(
      title: hub.value?.s('name') ?? 'Exam',
      body: AsyncView<Json>(
        value: hub,
        onRetry: () => ref.invalidate(examHubProvider(widget.examId)),
        data: (h) => ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
          SegmentTabs(items: const ['Courses', 'Free tests', 'Exam info', 'Articles'], selected: _tab, onChanged: (i) => setState(() => _tab = i)),
          const SizedBox(height: 14),
          if (_tab == 0) ...[
            if (h.l('courses').isEmpty) const EmptyNote('No courses for this exam yet.'),
            for (final c in h.l('courses'))
              Padding(padding: const EdgeInsets.only(bottom: 12), child: CourseCard(course: {...c, 'students_count': c.i('students')})),
          ],
          if (_tab == 1) ...[
            if (h.l('free_tests').isEmpty) const EmptyNote('No free tests yet.'),
            for (final t in h.l('free_tests'))
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  onTap: () => context.push(R.instructions(t.i('id'))),
                  child: Row(children: [
                    IconBox(t.s('kind') == 'pyq' ? '📜' : '🧪', tone: Tone.lav),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(t.s('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text('${t.i('questions')} Qs · ${t.i('duration_min')} min', style: TextStyle(fontSize: 12.5, color: p.muted)),
                      ]),
                    ),
                    const Tag('FREE', tone: Tone.mint),
                  ]),
                ),
              ),
          ],
          if (_tab == 2) ...[
            if (h.l('exams').isEmpty) const EmptyNote('Exam details will be added soon.'),
            for (final e in h.l('exams'))
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: AppCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text(e.s('full_name', e.s('name')), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                    if (e.sn('next_exam_date') != null) ...[
                      const SizedBox(height: 6),
                      Tag('Exam on ${e.s('next_exam_date')} · ${e.i('days_left')} days left', tone: Tone.peach),
                    ],
                    ..._kv('Eligibility', e['eligibility']),
                    ..._kv('Exam pattern', e['pattern']),
                    ..._kv('Posts', e['posts']),
                  ]),
                ),
              ),
          ],
          if (_tab == 3) ...[
            if (h.l('articles').isEmpty) const EmptyNote('No articles yet.'),
            for (final a in h.l('articles'))
              ListRow(title: a.s('title'), subtitle: a.s('published_at').split('T').first, onTap: () => context.push(R.article(a.s('slug'))), trailing: Icon(Icons.chevron_right_rounded, color: p.muted)),
          ],
        ]),
      ),
    );
  }

  /// Renders free-form JSON (map / list / text) from the admin panel.
  List<Widget> _kv(String title, dynamic v) {
    if (v == null || (v is Map && v.isEmpty) || (v is List && v.isEmpty)) return const [];
    final lines = <String>[
      if (v is Map) for (final e in v.entries) '${e.key}: ${e.value is List ? (e.value as List).join(', ') : e.value}',
      if (v is List) for (final e in v) e is Map ? e.values.join(' · ') : '$e',
      if (v is String) v,
    ];
    return [
      const SizedBox(height: 12),
      Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
      const SizedBox(height: 4),
      for (final l in lines) Padding(padding: const EdgeInsets.symmetric(vertical: 2), child: Text('• $l', style: const TextStyle(height: 1.45))),
    ];
  }
}
