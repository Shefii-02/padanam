import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../exams/data/exam_repository.dart';
import '../data/test_repository.dart';

/// Tab: mock tests of the current exam.
class TestListScreen extends ConsumerStatefulWidget {
  const TestListScreen({super.key});

  @override
  ConsumerState<TestListScreen> createState() => _TestListScreenState();
}

class _TestListScreenState extends ConsumerState<TestListScreen> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final exam = ref.watch(currentExamProvider);
    final key = (exam, _filter);
    final data = ref.watch(testListProvider(key));
    return Scaffold(
      backgroundColor: p.bg,
      appBar: AppBar(
        backgroundColor: p.bg,
        surfaceTintColor: Colors.transparent,
        automaticallyImplyLeading: false,
        title: const Text('Mock tests', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [
          TextButton.icon(onPressed: () => context.push(R.performance), icon: const Icon(Icons.insights_rounded), label: const Text('Dashboard')),
          const SizedBox(width: 6),
        ],
      ),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(testListProvider(key)),
        data: (d) {
          final head = d.m('exam');
          final colors = [for (final h in head.ls('colors')) hex(h)];
          final filters = [for (final f in (d['filters'] as List? ?? const [])) if (f is List && f.length > 1) f];
          return RefreshIndicator(
            onRefresh: () => ref.refresh(testListProvider(key).future),
            child: PageWidth(
              child: ListView(padding: EdgeInsets.fromLTRB(context.hPad, 4, context.hPad, 24), children: [
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(20), gradient: LinearGradient(colors: colors.length > 1 ? colors : [AppColors.primary, AppColors.primary2])),
                  child: Row(children: [
                    IconBox(head.s('emoji'), color: Colors.white24, size: 50, radius: 14),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(head.s('title'), style: const TextStyle(color: Colors.white, fontSize: 15.5, fontWeight: FontWeight.w800)),
                        Text(head.s('subtitle'), style: const TextStyle(color: Colors.white70, fontSize: 12)),
                      ]),
                    ),
                    AppButton('Change', small: true, color: Colors.white24, onPressed: () => context.push(R.exams)),
                  ]),
                ),
                const SizedBox(height: 14),
                ChipBar(
                  items: [for (final f in filters) '${f[1]}'],
                  selected: filters.indexWhere((f) => f[0] == _filter).clamp(0, 99),
                  onChanged: (i) => setState(() => _filter = '${filters[i][0]}'),
                ),
                const SizedBox(height: 12),
                if (d.l('tests').isEmpty) const EmptyNote('No tests here yet.'),
                for (final t in d.l('tests')) _TestTile(t: t),
              ]),
            ),
          );
        },
      ),
    );
  }
}

class _TestTile extends StatelessWidget {
  const _TestTile({required this.t});
  final Json t;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final (icon, label, tone) = switch (t.s('status')) {
      'done' => ('✓', 'RESULT', Tone.mint),
      'inc' => ('⏸', 'RESUME', Tone.peach),
      'lock' => ('🔒', 'SOON', Tone.grey),
      _ => ('▶', 'START', Tone.lav),
    };
    void open() {
      switch (t.s('status')) {
        case 'done':
          context.push(R.result(t.s('attempt_id', 'demo')));
        case 'inc':
          context.push(R.attempt(t.i('id')));
        case 'lock':
          showSoon(context, 'This test opens soon');
        default:
          context.push(R.instructions(t.i('id')));
      }
    }

    return AppCard(
      margin: const EdgeInsets.only(bottom: 10),
      radius: 18,
      onTap: open,
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Wrap(spacing: 6, children: [
              t.b('live') ? const Tag('● Live', tone: Tone.red) : Tag(t.s('difficulty'), tone: Tone.mint),
              Tag(t.ls('languages').join(' · ')),
            ]),
            const SizedBox(height: 6),
            Text(t.s('title'), style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, height: 1.35)),
            const SizedBox(height: 3),
            Text('${t.i('questions')} Qs · ${t.i('marks')} marks · ${t.s('duration_text')} · ${t.s('date')}', style: TextStyle(fontSize: 12, color: p.muted)),
          ]),
        ),
        const SizedBox(width: 10),
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(14)),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(icon, style: TextStyle(color: tone.fg, fontSize: 17, fontWeight: FontWeight.w800)),
            Text(label, style: TextStyle(color: tone.fg, fontSize: 8.5, fontWeight: FontWeight.w800)),
          ]),
        ),
      ]),
    );
  }
}
