import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/format.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/test_repository.dart';
import 'widgets/charts.dart';

class AnalysisScreen extends ConsumerStatefulWidget {
  const AnalysisScreen({super.key, required this.attemptId});
  final String attemptId;

  @override
  ConsumerState<AnalysisScreen> createState() => _AnalysisScreenState();
}

class _AnalysisScreenState extends ConsumerState<AnalysisScreen> {
  int _metric = 0;
  int _dist = -1; // -1 overall

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final data = ref.watch(analysisProvider(widget.attemptId));
    return SubPage(
      title: 'Test analysis',
      subtitle: 'Compared with all students',
      footer: AppButton('View solutions', expand: true, onPressed: () => context.push(R.solutions(widget.attemptId))),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(analysisProvider(widget.attemptId)),
        data: (a) {
          final sections = a.l('sections');
          final colors = [for (final s in sections) hex(s.s('color'))];
          final cmp = a.m('comparison');
          final metrics = [for (final m in (cmp['metrics'] as List? ?? const [])) if (m is List && m.length > 1) m];
          final key = metrics.isEmpty ? 'score' : '${metrics[_metric.clamp(0, metrics.length - 1)][0]}';
          final mvr = a.m('marks_vs_rank');
          final pos = (mvr.n('score') / mvr.n('max', 200)).clamp(0.0, 1.0);
          final dist = _dist < 0 ? a.m('distribution').m('overall') : a.m('distribution').l('sections')[_dist];
          final total = (dist.i('correct') + dist.i('wrong') + dist.i('skipped')).clamp(1, 9999);
          final lb = a.m('leaderboard');
          final me = lb.m('me');

          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Text('Comparison', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                ChartLegend(items: [for (var k = 0; k < sections.length; k++) (colors[k], sections[k].s('name'))]),
                const SizedBox(height: 10),
                ChipBar(items: [for (final m in metrics) '${m[1]}'], selected: _metric, onChanged: (i) => setState(() => _metric = i)),
                const SizedBox(height: 14),
                GroupedBars(
                  groups: const ['You', 'Average', 'Topper'],
                  values: [cmp.m('you').ln(key), cmp.m('average').ln(key), cmp.m('topper').ln(key)],
                  colors: colors,
                ),
              ]),
            ),
            const SizedBox(height: 12),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  const Expanded(child: Text('Marks vs rank', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
                  Tag('Your rank: ${groupIndian(mvr.i('rank'))}'),
                ]),
                const SizedBox(height: 30),
                LayoutBuilder(builder: (context, box) {
                  final x = box.maxWidth * pos;
                  return SizedBox(
                    height: 24,
                    child: Stack(clipBehavior: Clip.none, children: [
                      Positioned(left: 0, right: 0, top: 8, child: ProgressBar(pos, color: AppColors.primary2, height: 8)),
                      Positioned(
                        left: (x - 10).clamp(0.0, box.maxWidth - 20),
                        top: 2,
                        child: Container(width: 20, height: 20, decoration: BoxDecoration(color: p.card, shape: BoxShape.circle, border: Border.all(color: AppColors.primary2, width: 4))),
                      ),
                      Positioned(
                        left: (x - 40).clamp(0.0, box.maxWidth - 80),
                        top: -26,
                        child: Container(
                          width: 80,
                          padding: const EdgeInsets.symmetric(vertical: 3),
                          decoration: BoxDecoration(color: p.ink, borderRadius: BorderRadius.circular(8)),
                          child: Text('${num1(mvr.n('score'))} marks', textAlign: TextAlign.center, style: TextStyle(color: p.card, fontSize: 11.5, fontWeight: FontWeight.w800)),
                        ),
                      ),
                    ]),
                  );
                }),
                const SizedBox(height: 6),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  for (final v in [0, 40, 80, 120, 160, 200]) Text('$v', style: TextStyle(fontSize: 10.5, color: p.muted)),
                ]),
                const SizedBox(height: 10),
                Text([for (final m in mvr.l('milestones')) '${m.i('marks')} marks ${m.s('label')}'].join(' · '), style: TextStyle(fontSize: 12, color: p.muted)),
              ]),
            ),
            const SizedBox(height: 12),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  const Expanded(child: Text('Question distribution', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
                  DropdownButton<int>(
                    value: _dist,
                    underline: const SizedBox(),
                    items: [
                      const DropdownMenuItem(value: -1, child: Text('Overall')),
                      for (var k = 0; k < sections.length; k++) DropdownMenuItem(value: k, child: Text(sections[k].s('short'))),
                    ],
                    onChanged: (v) => setState(() => _dist = v ?? -1),
                  ),
                ]),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: SizedBox(
                    height: 12,
                    child: Row(children: [
                      if (dist.i('correct') > 0) Expanded(flex: dist.i('correct'), child: Container(color: AppColors.green)),
                      if (dist.i('wrong') > 0) Expanded(flex: dist.i('wrong'), child: Container(color: AppColors.red)),
                      if (dist.i('skipped') > 0) Expanded(flex: dist.i('skipped'), child: Container(color: p.sheet)),
                    ]),
                  ),
                ),
                const SizedBox(height: 10),
                Row(children: [
                  _count('${dist.i('correct')}', 'Correct', Tone.mint),
                  const SizedBox(width: 8),
                  _count('${dist.i('wrong')}', 'Wrong', Tone.red),
                  const SizedBox(width: 8),
                  _count('${dist.i('skipped')}', 'Unattempted', Tone.grey),
                ]),
                const SizedBox(height: 6),
                Text('$total questions', style: TextStyle(fontSize: 11.5, color: p.muted)),
              ]),
            ),
            const SizedBox(height: 12),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Text('By difficulty', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                for (final d in a.l('difficulty')) ...[
                  Row(children: [
                    Expanded(child: Text(d.s('level'), style: const TextStyle(fontWeight: FontWeight.w700))),
                    Text('${d.i('correct')} / ${d.i('total')} correct', style: TextStyle(fontSize: 12.5, color: p.muted)),
                  ]),
                  const SizedBox(height: 5),
                  ProgressBar(d.i('correct') / d.i('total', 1), height: 9, color: d.s('level') == 'Easy' ? AppColors.green : (d.s('level') == 'Moderate' ? AppColors.gold : AppColors.red)),
                  const SizedBox(height: 12),
                ],
              ]),
            ),
            const SizedBox(height: 12),
            AppCard(
              padding: const EdgeInsets.only(top: 14),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Padding(padding: EdgeInsets.symmetric(horizontal: 14), child: Text('Leaderboard', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
                const SizedBox(height: 6),
                Padding(padding: const EdgeInsets.symmetric(horizontal: 14), child: DividedColumn(children: [
                  for (final (i, r) in lb.l('top').indexed)
                    ListRow(
                      padding: const EdgeInsets.symmetric(vertical: 9),
                      leading: Row(mainAxisSize: MainAxisSize.min, children: [
                        SizedBox(width: 22, child: Text('${i + 1}', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, color: p.muted))),
                        const SizedBox(width: 8),
                        IconBox(r.s('name').substring(0, 1), tone: Tone.lav, size: 34, radius: 10),
                      ]),
                      title: r.s('name'),
                      trailing: Pill('${r.n('score').toStringAsFixed(2)} / ${num1(lb.n('max', 100))}'),
                    ),
                ])),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: const BoxDecoration(color: AppColors.lavender, borderRadius: BorderRadius.vertical(bottom: Radius.circular(20))),
                  child: Row(children: [
                    CircleAvatar(radius: 17, backgroundColor: AppColors.primary, child: Text(me.s('initials', 'ME'), style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w800))),
                    const SizedBox(width: 12),
                    Expanded(child: Text('You · Rank ${groupIndian(me.i('rank'))}', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF15205F)))),
                    Text('${me.n('score').toStringAsFixed(2)} / ${num1(lb.n('max', 100))}', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF15205F))),
                  ]),
                ),
              ]),
            ),
          ]);
        },
      ),
    );
  }

  Widget _count(String v, String l, Tone t) => Expanded(
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: t.bg, borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            Text(v, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: t.fg)),
            Text(l, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: t.fg)),
          ]),
        ),
      );
}
