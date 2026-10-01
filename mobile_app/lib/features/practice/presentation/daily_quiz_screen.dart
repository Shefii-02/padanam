import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/practice_repository.dart';

/// Today's daily quiz (built at 6 AM from the exam's question bank) + streak.
class DailyQuizScreen extends ConsumerWidget {
  const DailyQuizScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return SubPage(
      title: 'Daily quiz',
      subtitle: 'A new quiz every morning at 6 AM',
      body: AsyncView<Json>(
        value: ref.watch(dailyQuizProvider),
        onRetry: () => ref.invalidate(dailyQuizProvider),
        data: (d) {
          final quizzes = d.l('quizzes');
          const days = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
          return RefreshIndicator(
            onRefresh: () => ref.refresh(dailyQuizProvider.future),
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
              AppCard(
                color: AppColors.peach,
                bordered: false,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(children: [
                    const Text('🔥', style: TextStyle(fontSize: 30)),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${d.i('streak')}-day streak', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.peachInk)),
                        const Text('Take the quiz every day to keep it going', style: TextStyle(fontSize: 12.5, color: AppColors.peachInk)),
                      ]),
                    ),
                  ]),
                  const SizedBox(height: 12),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    for (final w in d.l('week'))
                      Column(children: [
                        CircleAvatar(
                          radius: 15,
                          backgroundColor: w.b('done') ? AppColors.saffron : Colors.white,
                          child: w.b('done') ? const Icon(Icons.check_rounded, size: 16, color: Colors.white) : null,
                        ),
                        const SizedBox(height: 4),
                        Text(days[(DateTime.tryParse(w.s('date'))?.weekday ?? 1) - 1], style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.peachInk)),
                      ]),
                  ]),
                ]),
              ),
              const SectionTitle("Today's quizzes"),
              if (quizzes.isEmpty) const EmptyNote("Today's quiz isn't ready yet. It opens at 6 AM – we'll notify you."),
              for (final q in quizzes)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: AppCard(
                    onTap: () => q.b('done') ? context.push(R.result(q.s('attempt'))) : context.push(R.instructions(q.i('test_id'))),
                    child: Row(children: [
                      IconBox(q.b('done') ? '✅' : '⚡', tone: q.b('done') ? Tone.mint : Tone.peach, size: 48),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(q.s('title', 'Daily quiz'), style: const TextStyle(fontWeight: FontWeight.w800)),
                          Text([if (q.s('category').isNotEmpty) q.s('category'), '${q.i('questions')} questions', '${q.i('duration_min')} min'].join(' · '),
                              style: TextStyle(fontSize: 12.5, color: p.muted)),
                        ]),
                      ),
                      q.b('done')
                          ? Tag('Score ${q.s('score')}', tone: Tone.mint)
                          : const Tag('START', tone: Tone.lav),
                    ]),
                  ),
                ),
            ]),
          );
        },
      ),
    );
  }
}
