import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../exams/data/exam_repository.dart';
import '../data/practice_repository.dart';

/// Previous year question papers as timed tests.
class PreviousPapersScreen extends ConsumerWidget {
  const PreviousPapersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final exam = ref.watch(currentExamProvider);
    final p = context.palette;
    return SubPage(
      title: 'Previous year papers',
      body: AsyncView<Json>(
        value: ref.watch(pyqProvider(exam)),
        onRetry: () => ref.invalidate(pyqProvider(exam)),
        data: (d) {
          final tests = d.l('tests');
          if (tests.isEmpty) return const EmptyView(emoji: '📚', title: 'No papers yet', subtitle: 'Previous year papers for your exam will appear here.');
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            itemCount: tests.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final t = tests[i];
              final done = t.s('status') == 'done';
              return AppCard(
                onTap: () => done ? context.push(R.result(t.s('attempt_id'))) : context.push(R.instructions(t.i('id'))),
                child: Row(children: [
                  IconBox(done ? '✅' : '📜', tone: done ? Tone.mint : Tone.lav),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(t.s('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text('${t.i('questions')} Qs · ${t.i('marks')} marks · ${t.s('duration_text')}', style: TextStyle(fontSize: 12.5, color: p.muted)),
                    ]),
                  ),
                  Tag(done ? 'Result' : (t.s('status') == 'inc' ? 'Resume' : 'Start'), tone: done ? Tone.mint : Tone.lav),
                ]),
              );
            },
          );
        },
      ),
    );
  }
}
