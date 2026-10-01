import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/exam_repository.dart';

/// All exam categories (Kerala PSC, SSC, RRB, KTET…) with their exams and next exam dates.
class ExamsScreen extends ConsumerWidget {
  const ExamsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return SubPage(
      title: 'Exams',
      body: AsyncView<List<Json>>(
        value: ref.watch(examsListProvider),
        onRetry: () => ref.invalidate(examsListProvider),
        data: (cats) => RefreshIndicator(
          onRefresh: () => ref.refresh(examsListProvider.future),
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            itemCount: cats.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) {
              final c = cats[i];
              return AppCard(
                onTap: () {
                  ref.read(currentExamProvider.notifier).set(c.s('id'));
                  context.push(R.exam(c.s('id')));
                },
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(children: [
                    IconBox(c.s('emoji', '📘'), color: hex(c.sn('color'), AppColors.lavender).withValues(alpha: .25), size: 48),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(c.s('name'), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                        Text('${c.i('courses')} courses', style: TextStyle(fontSize: 12.5, color: p.muted)),
                      ]),
                    ),
                    if (c.b('mine')) const Tag('My exam', tone: Tone.mint),
                  ]),
                  if (c.l('exams').isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Wrap(spacing: 6, runSpacing: 6, children: [
                      for (final e in c.l('exams'))
                        Tag(e['days_left'] == null ? e.s('name') : '${e.s('name')} · ${e.i('days_left')} days', tone: e['days_left'] != null && e.i('days_left') < 60 ? Tone.peach : Tone.lav),
                    ]),
                  ],
                ]),
              );
            },
          ),
        ),
      ),
    );
  }
}
