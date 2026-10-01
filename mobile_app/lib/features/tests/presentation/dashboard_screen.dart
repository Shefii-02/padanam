import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/test_repository.dart';
import 'widgets/charts.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  int _diff = 0;
  int _sec = 0;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final data = ref.watch(dashboardProvider);
    return SubPage(
      title: 'Performance dashboard',
      subtitle: 'Last 30 days · all mock tests',
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(dashboardProvider),
        data: (d) {
          final labels = d.ls('labels');
          final diffs = d.m('difficulties');
          final names = diffs.keys.toList();
          final cur = diffs.m(names.isEmpty ? 'Overall' : names[_diff.clamp(0, names.length - 1)]);
          final sections = d.l('sections');
          final s = sections.isEmpty ? const <String, dynamic>{} : sections[_sec.clamp(0, sections.length - 1)];
          final tpq = cur.ln('tpq');
          final maxT = [1.5, ...tpq].reduce((a, b) => a > b ? a : b);
          Widget diffChips() => ChipBar(items: names, selected: _diff, onChanged: (i) => setState(() => _diff = i));

          return RefreshIndicator(
            onRefresh: () => ref.refresh(dashboardProvider.future),
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
              _card('📈 Marks trend', trailing: '% of max marks', children: [
                LineChart(series: [LineSeries(d.ln('marks_percent'), AppColors.primary2)], labels: labels, maxY: 100),
              ]),
              _card('🎯 Attempt & accuracy', children: [
                diffChips(),
                const SizedBox(height: 10),
                const ChartLegend(items: [(AppColors.green, 'Attempted %'), (AppColors.primary2, 'Accuracy %')]),
                const SizedBox(height: 8),
                LineChart(series: [LineSeries(cur.ln('attempt'), AppColors.green), LineSeries(cur.ln('accuracy'), AppColors.primary2)], labels: labels, maxY: 100),
              ]),
              _card('⏱ Average time per question', children: [
                diffChips(),
                const SizedBox(height: 10),
                ChartLegend(items: [(AppColors.gold, 'Goal (${d.n('goal_tpq')} min)'), (AppColors.red, 'Achieved')]),
                const SizedBox(height: 8),
                LineChart(
                  series: [LineSeries([for (final _ in labels) d.n('goal_tpq')], AppColors.gold, dashed: true), LineSeries(tpq, AppColors.red)],
                  labels: labels,
                  maxY: maxT,
                  unit: 'm',
                ),
              ]),
              _card('📋 Sectional report', children: [
                ChipBar(items: [for (final x in sections) x.s('name')], selected: _sec, onChanged: (i) => setState(() => _sec = i)),
                const SizedBox(height: 16),
                Row(children: [
                  _ring(s.n('cumulative_accuracy'), AppColors.primary2, 'Cumulative\naccuracy'),
                  _ring(s.n('attempt_accuracy'), AppColors.green, 'Attempt\naccuracy'),
                  _ring(s.n('time_used'), AppColors.gold, 'Time used\n(goal 100%)'),
                ]),
              ]),
              _card('💪 Subject strength', trailing: 'From accuracy', children: [
                DividedColumn(children: [
                  for (final x in sections)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 11),
                      child: Row(children: [
                        Icon(Icons.circle, size: 10, color: hex(x.s('color'))),
                        const SizedBox(width: 10),
                        Expanded(child: Text(x.s('name'), style: const TextStyle(fontWeight: FontWeight.w700))),
                        Text('${x.n('accuracy')}%', style: TextStyle(fontSize: 12, color: p.muted)),
                        const SizedBox(width: 8),
                        Tag(x.s('level'), tone: switch (x.s('level')) { 'Strong' => Tone.mint, 'Average' => Tone.peach, _ => Tone.red }),
                      ]),
                    ),
                ]),
              ]),
            ]),
          );
        },
      ),
    );
  }

  Widget _card(String title, {String? trailing, required List<Widget> children}) => AppCard(
        margin: const EdgeInsets.only(bottom: 12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Expanded(child: Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
            if (trailing != null) Text(trailing, style: TextStyle(fontSize: 12, color: context.palette.muted)),
          ]),
          const SizedBox(height: 12),
          ...children,
        ]),
      );

  Widget _ring(double v, Color c, String label) => Expanded(
        child: Column(children: [
          ProgressRing(value: v, size: 76, stroke: 7, color: c, child: Text('${(v * 100).round()}%', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
          const SizedBox(height: 6),
          Text(label, textAlign: TextAlign.center, style: TextStyle(fontSize: 11, color: context.palette.muted, fontWeight: FontWeight.w700)),
        ]),
      );
}
