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

class ResultScreen extends ConsumerStatefulWidget {
  const ResultScreen({super.key, required this.attemptId});
  final String attemptId;

  @override
  ConsumerState<ResultScreen> createState() => _ResultScreenState();
}

class _ResultScreenState extends ConsumerState<ResultScreen> {
  int _sec = 0;
  int _rating = 0;

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(resultProvider(widget.attemptId));
    final r0 = data.value;
    final when = DateTime.tryParse(r0?.s('attempted_on') ?? '')?.toLocal();
    return SubPage(
      title: r0?.s('title') ?? 'Result',
      subtitle: when == null ? null : 'Attempted on ${when.day} ${monthAbbr(when)} ${when.year}',
      onLeading: () => context.canPop() ? context.pop() : context.go(R.tests),
      footer: Row(children: [
        Expanded(child: AppButton('Analysis', outlined: true, color: AppColors.primary2, onPressed: () => context.push(R.analysis(widget.attemptId)))),
        const SizedBox(width: 8),
        Expanded(child: AppButton('View solutions', onPressed: () => context.push(R.solutions(widget.attemptId)))),
      ]),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(resultProvider(widget.attemptId)),
        data: (r) {
          if (r['published'] == false) {
            return EmptyView(emoji: '⏳', title: 'Answers saved', subtitle: r.s('message', 'The result will be published soon.'));
          }
          final t = r.m('total');
          final secs = r.l('sections');
          final s = secs.isEmpty ? const <String, dynamic>{} : secs[_sec.clamp(0, secs.length - 1)];
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
            const SectionTitle('Overall performance'),
            _Metric(bg: AppColors.mint, color: AppColors.green, value: t.n('score') / t.n('max', 200), big: num1(t.n('score')), of: '/ ${num1(t.n('max', 200))}', label: 'Your score', neg: t.n('negative')),
            _Metric(bg: const Color(0xFFEFE4FC), color: const Color(0xFF7B4BD6), value: t.n('time') / t.n('time_max', 3600), big: mmss(t.i('time')), of: '/ ${mmss(t.i('time_max', 3600))}', label: 'Time spent'),
            Row(children: [
              _Box(bg: AppColors.peach, icon: '🏅', value: groupIndian(r.i('rank')), of: '/ ${groupIndian(r.i('candidates'))}', label: 'Your rank'),
              const SizedBox(width: 8),
              _Box(bg: const Color(0xFFFCE4E7), icon: '📊', value: num1(r.n('percentile')), of: '/ 100', label: 'Percentile'),
              const SizedBox(width: 8),
              _Box(bg: AppColors.lavender, icon: '🎯', value: '${num1(r.n('accuracy'))}%', of: '', label: 'Accuracy'),
            ]),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: AppButton('Share', emoji: '↗', outlined: true, color: AppColors.primary2, onPressed: () => showSoon(context, 'Score card ready to share'))),
              const SizedBox(width: 8),
              Expanded(child: AppButton('Re-attempt', emoji: '↻', color: AppColors.red, onPressed: () => context.push(R.instructions(6)))),
            ]),
            const SectionTitle('Sectional summary'),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                ChipBar(items: [for (final x in secs) x.s('name')], selected: _sec, onChanged: (i) => setState(() => _sec = i)),
                const SizedBox(height: 12),
                _Metric(bg: AppColors.mint, color: AppColors.green, value: s.n('score') / s.n('max', 50), big: num1(s.n('score')), of: '/ ${num1(s.n('max', 50))}', label: 'Section score', neg: s.n('negative')),
                _Metric(bg: const Color(0xFFEFE4FC), color: const Color(0xFF7B4BD6), value: s.n('time') / s.n('time_max', 900), big: mmss(s.i('time')), of: '/ ${mmss(s.i('time_max', 900))}', label: 'Time spent'),
                Row(children: [
                  _Count(s.i('correct'), 'Correct', Tone.mint),
                  const SizedBox(width: 8),
                  _Count(s.i('wrong'), 'Wrong', Tone.red),
                  const SizedBox(width: 8),
                  _Count(s.i('skipped'), 'Skipped', Tone.grey),
                ]),
                const SizedBox(height: 10),
                Text('Accuracy ${num1(s.n('accuracy'))}%', style: TextStyle(fontSize: 12.5, color: context.palette.muted, fontWeight: FontWeight.w600)),
              ]),
            ),
            const SizedBox(height: 14),
            AppCard(
              child: Column(children: [
                const Text('Rate this test', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                Text('How was your experience with this test?', style: TextStyle(fontSize: 12.5, color: context.palette.muted)),
                const SizedBox(height: 8),
                Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  for (var n = 1; n <= 5; n++)
                    IconButton(
                      tooltip: '$n star',
                      onPressed: () {
                        setState(() => _rating = n);
                        showSoon(context, 'Thanks for rating!');
                      },
                      icon: Icon(n <= _rating ? Icons.star_rounded : Icons.star_border_rounded, size: 34, color: AppColors.gold),
                    ),
                ]),
              ]),
            ),
            const SizedBox(height: 14),
            AppButton('Open performance dashboard', emoji: '📈', outlined: true, color: AppColors.primary2, expand: true, onPressed: () => context.push(R.performance)),
          ]);
        },
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.bg, required this.color, required this.value, required this.big, required this.of, required this.label, this.neg = 0});
  final Color bg, color;
  final double value, neg;
  final String big, of, label;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(16)),
      child: Row(children: [
        ProgressRing(value: value.isNaN ? 0 : value, size: 46, color: color, track: Colors.white, child: const SizedBox()),
        const SizedBox(width: 14),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text.rich(TextSpan(children: [
              TextSpan(text: big, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF141A33))),
              TextSpan(text: '  $of', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF5E6583))),
            ])),
            Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF5E6583), fontWeight: FontWeight.w600)),
          ]),
        ),
        if (neg > 0)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
            decoration: BoxDecoration(color: AppColors.red, borderRadius: BorderRadius.circular(7)),
            child: Text('Neg. −${num1(neg)}', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
          ),
      ]),
    );
  }
}

class _Box extends StatelessWidget {
  const _Box({required this.bg, required this.icon, required this.value, required this.of, required this.label});
  final Color bg;
  final String icon, value, of, label;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(16)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(icon, style: const TextStyle(fontSize: 22)),
            const SizedBox(height: 6),
            FittedBox(
              child: Text.rich(TextSpan(children: [
                TextSpan(text: value, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF141A33))),
                TextSpan(text: ' $of', style: const TextStyle(fontSize: 12, color: Color(0xFF5E6583))),
              ])),
            ),
            Text(label, style: const TextStyle(fontSize: 11.5, color: Color(0xFF5E6583), fontWeight: FontWeight.w600)),
          ]),
        ),
      );
}

class _Count extends StatelessWidget {
  const _Count(this.value, this.label, this.tone);
  final int value;
  final String label;
  final Tone tone;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            Text('$value', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: tone.fg)),
            Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: tone.fg)),
          ]),
        ),
      );
}
