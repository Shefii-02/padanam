import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/format.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/test_repository.dart';
import 'widgets/answer_tile.dart';

const _langs = [('en', 'EN'), ('ml', 'മല'), ('hi', 'हि')];

class SolutionsScreen extends ConsumerStatefulWidget {
  const SolutionsScreen({super.key, required this.attemptId});
  final String attemptId;

  @override
  ConsumerState<SolutionsScreen> createState() => _SolutionsScreenState();
}

class _SolutionsScreenState extends ConsumerState<SolutionsScreen> {
  int _s = 0, _i = 0;
  bool _open = true;
  final _helpful = <String, bool>{};

  String _state(Json q) => q['your_answer'] == null ? 'u' : (q.i('your_answer') == q.i('answer') ? 'c' : 'w');

  (String, String) _speed(Json q) {
    final t = q.i('time'), avg = q.i('avg_time', 1);
    if (t < avg * 0.7) return ('⚡', 'Fast');
    if (t <= avg * 1.3) return ('🙂', 'On time');
    return ('🐢', 'Slow');
  }

  void _step(List<Json> sections, int d) {
    var s = _s, i = _i + d;
    final n = sections[s].l('questions').length;
    if (i < 0) {
      if (s == 0) return;
      s--;
      i = sections[s].l('questions').length - 1;
    } else if (i >= n) {
      if (s == sections.length - 1) return;
      s++;
      i = 0;
    }
    setState(() {
      _s = s;
      _i = i;
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final lang = ref.watch(testLanguageProvider);
    final data = ref.watch(solutionsProvider(widget.attemptId));
    final sections = data.value?.l('sections') ?? const <Json>[];
    return SubPage(
      title: sections.isEmpty ? 'Solutions' : sections[_s].s('name'),
      subtitle: sections.isEmpty ? null : 'Question ${_i + 1} of ${sections[_s].l('questions').length}',
      actions: [
        OutlinedButton(
          style: OutlinedButton.styleFrom(minimumSize: const Size(40, 36), padding: const EdgeInsets.symmetric(horizontal: 8)),
          onPressed: () {
            final k = _langs.indexWhere((l) => l.$1 == lang);
            ref.read(testLanguageProvider.notifier).set(_langs[(k + 1) % _langs.length].$1);
          },
          child: Text(_langs.firstWhere((l) => l.$1 == lang).$2, style: const TextStyle(fontWeight: FontWeight.w800)),
        ),
        IconButton(tooltip: 'Solution palette', onPressed: sections.isEmpty ? null : () => _palette(sections), icon: Icon(Icons.grid_view_rounded, color: p.brand)),
      ],
      footer: Row(children: [
        AppButton('‹ Prev', outlined: true, color: AppColors.primary2, onPressed: sections.isEmpty || (_s == 0 && _i == 0) ? null : () => _step(sections, -1)),
        const SizedBox(width: 8),
        Expanded(child: AppButton('Test summary', outlined: true, color: AppColors.primary2, onPressed: () => context.pop())),
        const SizedBox(width: 8),
        AppButton('Next ›', onPressed: sections.isEmpty ? null : () => _step(sections, 1)),
      ]),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(solutionsProvider(widget.attemptId)),
        data: (d) {
          if (sections.isEmpty) return const EmptyNote('No solutions available.');
          final sec = sections[_s];
          final q = sec.l('questions')[_i];
          final st = _state(q);
          final sp = _speed(q);
          final l = sec.b('en_only') ? 'en' : lang;
          final opts = q['options'] as List? ?? const [];
          final key = '$_s-$_i';
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
            AppCard(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Row(children: [
                Text('⏱ You ${mmss(q.i('time'))}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
                const SizedBox(width: 10),
                Text('Avg ${mmss(q.i('avg_time'))}', style: TextStyle(color: p.muted, fontSize: 12.5)),
                const SizedBox(width: 8),
                Tooltip(message: sp.$2, child: Text(sp.$1)),
                const Spacer(),
                Text('👥 ${q.i('percent_correct')}% got it right', style: const TextStyle(color: AppColors.green, fontWeight: FontWeight.w700, fontSize: 12)),
              ]),
            ),
            const SizedBox(height: 14),
            Row(children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(8)),
                child: Text('${_i + 1}', style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
              const SizedBox(width: 8),
              Tag(q.s('difficulty'), tone: q.s('difficulty') == 'Easy' ? Tone.mint : (q.s('difficulty') == 'Moderate' ? Tone.peach : Tone.red)),
              if (q.b('marked')) ...[const SizedBox(width: 6), const Tag('Marked', tone: Tone.purple)],
              const Spacer(),
              switch (st) {
                'c' => const Tag('+2.0', tone: Tone.mint),
                'w' => const Tag('−0.5', tone: Tone.red),
                _ => const Tag('Skipped · 0', tone: Tone.grey),
              },
            ]),
            const SizedBox(height: 10),
            Text(tr(q['q'], l), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, height: 1.55)),
            const SizedBox(height: 16),
            for (var k = 0; k < opts.length; k++)
              Stack(children: [
                AnswerTile(
                  letter: 'ABCD'[k],
                  text: tr(opts[k], l),
                  state: k == q.i('answer') ? AnswerState.right : (q['your_answer'] != null && k == q.i('your_answer') ? AnswerState.wrong : AnswerState.idle),
                ),
                if (k == q.i('answer') || (q['your_answer'] != null && k == q.i('your_answer')))
                  Positioned(
                    right: 12,
                    top: 14,
                    child: Tag(
                      k == q.i('answer') ? (q['your_answer'] != null && q.i('your_answer') == k ? 'Your answer ✓' : 'Correct') : 'Your answer',
                      tone: k == q.i('answer') ? Tone.mint : Tone.red,
                    ),
                  ),
              ]),
            TextButton.icon(
              onPressed: () => setState(() => _open = !_open),
              icon: const Icon(Icons.visibility_outlined),
              label: Text(_open ? 'Hide solution' : 'View solution'),
            ),
            if (_open)
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: AppColors.lavender, borderRadius: BorderRadius.circular(16)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Solution', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF15205F))),
                  const SizedBox(height: 4),
                  Text(tr(q['solution'], l), style: const TextStyle(fontSize: 13.5, height: 1.6, color: Color(0xFF15205F))),
                ]),
              ),
            const SizedBox(height: 14),
            Row(children: [
              const Text('Was this solution helpful?', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              const SizedBox(width: 8),
              ChoiceChip(label: const Text('👍 Yes'), selected: _helpful[key] == true, onSelected: (_) => setState(() => _helpful[key] = true)),
              const SizedBox(width: 6),
              ChoiceChip(label: const Text('👎 No'), selected: _helpful[key] == false, onSelected: (_) => setState(() => _helpful[key] = false)),
            ]),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(onPressed: () => showSoon(context, 'Report sent. We will review this question.'), child: const Text('⚑ Report this question', style: TextStyle(color: AppColors.red))),
            ),
          ]);
        },
      ),
    );
  }

  void _palette(List<Json> sections) {
    var filter = 'all';
    var sec = _s;
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(builder: (ctx, set) {
        final p = ctx.palette;
        final qs = sections[sec].l('questions');
        final shown = [
          for (var k = 0; k < qs.length; k++)
            if (filter == 'all' || _state(qs[k]) == filter || (filter == 'm' && qs[k].b('marked'))) k,
        ];
        final c = qs.where((q) => _state(q) == 'c').length, w = qs.where((q) => _state(q) == 'w').length;
        Color bg(String s) => s == 'c' ? AppColors.green : (s == 'w' ? AppColors.red : p.sheet);
        return SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              ChipBar(items: [for (final s in sections) s.s('name')], selected: sec, onChanged: (i) => set(() => sec = i)),
              const SizedBox(height: 10),
              Text('Questions: ${qs.length} · Attempted: ${c + w} · $c correct · $w wrong', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
              const SizedBox(height: 10),
              ChipBar(
                items: const ['All', 'Correct', 'Incorrect', 'Unattempted', 'Marked'],
                selected: const ['all', 'c', 'w', 'u', 'm'].indexOf(filter),
                onChanged: (i) => set(() => filter = const ['all', 'c', 'w', 'u', 'm'][i]),
              ),
              const SizedBox(height: 12),
              if (shown.isEmpty) const EmptyNote('No questions match this filter.'),
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: shown.length,
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 56, mainAxisSpacing: 10, crossAxisSpacing: 10),
                itemBuilder: (_, k) {
                  final idx = shown[k];
                  final q = qs[idx];
                  final fill = bg(_state(q));
                  return InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () {
                      Navigator.pop(ctx);
                      setState(() {
                        _s = sec;
                        _i = idx;
                      });
                    },
                    child: Stack(clipBehavior: Clip.none, children: [
                      Container(
                        alignment: Alignment.center,
                        decoration: BoxDecoration(color: fill, borderRadius: BorderRadius.circular(10), border: sec == _s && idx == _i ? Border.all(color: AppColors.primary2, width: 2.5) : null),
                        child: Text('${idx + 1}', style: TextStyle(fontWeight: FontWeight.w800, color: fill == p.sheet ? p.muted : Colors.white)),
                      ),
                      Positioned(top: -6, right: -4, child: Text(_speed(q).$1, style: const TextStyle(fontSize: 11))),
                    ]),
                  );
                },
              ),
              const SizedBox(height: 12),
              const Text('Green = correct · Red = incorrect · Grey = unattempted    ⚡ Fast  🙂 On time  🐢 Slow', style: TextStyle(fontSize: 11.5)),
            ]),
          ),
        );
      }),
    );
  }
}
