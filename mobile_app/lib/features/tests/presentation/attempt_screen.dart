import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/format.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../application/attempt_controller.dart';
import '../data/test_repository.dart';
import 'widgets/answer_tile.dart';

const _langs = [('en', 'EN', 'English'), ('ml', 'മല', 'മലയാളം'), ('hi', 'हि', 'हिन्दी')];
const _purple = Color(0xFF7B4BD6);

/// Exam-hall screen: one running section at a time, each with its own timer.
class AttemptScreen extends ConsumerStatefulWidget {
  const AttemptScreen({super.key, required this.testId});
  final int testId;

  @override
  ConsumerState<AttemptScreen> createState() => _AttemptScreenState();
}

class _AttemptScreenState extends ConsumerState<AttemptScreen> {
  AttemptController get _c => ref.read(attemptControllerProvider.notifier);
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(() => _c.start(widget.testId, ref.read(testLanguageProvider)));
  }

  // ---------- overlays pause the timer while open ----------
  Future<T?> _overlay<T>(Future<T?> Function() show) async {
    _c.setRunning(false);
    final r = await show();
    if (mounted) _c.setRunning(true);
    return r;
  }

  Future<void> _submit() async {
    if (_submitting) return;
    setState(() => _submitting = true);
    final id = await runAction(context, _c.submit);
    if (!mounted) return;
    setState(() => _submitting = false);
    if (id != null) {
      ref.invalidate(testListProvider);
      context.pushReplacement(R.result(id));
    }
  }

  Future<void> _pause(AttemptSession s) async {
    final yes = await _overlay(() => showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Pause test?'),
            content: SizedBox(
              width: 420,
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Your answers are saved. Section time stops while paused. Resume any time from the test list.'),
                const SizedBox(height: 12),
                _SectionTable(s: s),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
              FilledButton(style: FilledButton.styleFrom(backgroundColor: AppColors.red), onPressed: () => Navigator.pop(ctx, true), child: const Text('Yes, pause')),
            ],
          ),
        ));
    if (yes == true && mounted) {
      _c.setRunning(false);
      await _c.sync();
      ref.invalidate(testListProvider);
      if (mounted) context.pop();
    }
  }

  Future<void> _confirmSubmitSection(AttemptSession s) async {
    final last = s.isLastSection;
    final ok = await _overlay(() => showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text('Submit ${s.section.s('name')}?'),
            content: Text(last
                ? 'This is the last section, so the whole test will be submitted.'
                : "You can't come back to this section. ${s.sections[s.s + 1].s('name')} starts right away with a fresh ${mmss(s.sections[s.s + 1].i('minutes') * 60)}. Unused time (${mmss(s.left[s.s])}) is not carried forward."),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
              FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Submit section')),
            ],
          ),
        ));
    if (ok != true) return;
    if (last) {
      await _submit();
    } else {
      _c.nextSection();
    }
  }

  Future<void> _confirmSubmitTest(AttemptSession s) async {
    final remaining = [for (var k = s.s + 1; k < s.sections.length; k++) s.sections[k].s('name')];
    final ok = await _overlay(() => showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Submit test?'),
            content: SizedBox(
              width: 420,
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (remaining.isNotEmpty)
                  Text('${remaining.length} section(s) not started yet (${remaining.join(', ')}). They will get 0 marks.', style: const TextStyle(color: AppColors.red, fontWeight: FontWeight.w700)),
                const Text("You can't change answers after submitting."),
                const SizedBox(height: 12),
                _SectionTable(s: s),
              ]),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep going')),
              FilledButton(style: FilledButton.styleFrom(backgroundColor: AppColors.red), onPressed: () => Navigator.pop(ctx, true), child: const Text('Submit')),
            ],
          ),
        ));
    if (ok == true) await _submit();
  }

  void _onEvent(AttemptSession s) {
    final ev = s.event;
    if (ev == null) return;
    _c.clearEvent();
    showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _SectionEndedDialog(
        last: ev == AttemptEvent.testEnded,
        finished: s.section.s('name'),
        next: s.isLastSection ? '' : s.sections[s.s + 1].s('name'),
      ),
    ).then((next) {
      if (!mounted) return;
      if (ev == AttemptEvent.testEnded) {
        _submit();
      } else if (next == true) {
        _c.nextSection();
        showSoon(context, '${s.sections[s.s].s('name')} started · ${mmss(s.sections[s.s].i('minutes') * 60)}');
      } else {
        _c.nextSection();
        _pause(ref.read(attemptControllerProvider).value!);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(attemptControllerProvider, (prev, next) {
      final s = next.value;
      if (s != null && s.event != null) _onEvent(s);
    });
    final async = ref.watch(attemptControllerProvider);
    final lang = ref.watch(testLanguageProvider);
    final p = context.palette;

    return AsyncView<AttemptSession?>(
      value: async,
      loading: const Scaffold(body: LoadingView()),
      onRetry: () => _c.start(widget.testId, lang),
      data: (s) {
        if (s == null) return const Scaffold(body: LoadingView());
        final q = s.question;
        final st = s.current;
        final enOnly = s.section.b('en_only') && lang != 'en';
        final low = s.left[s.s] <= (s.sectionSeconds < 120 ? 10 : 60);
        final opts = (q['options'] as List? ?? const []);

        return PopScope(
          canPop: false,
          onPopInvokedWithResult: (didPop, _) {
            if (!didPop) _pause(s);
          },
          child: Scaffold(
            backgroundColor: p.bg,
            appBar: AppBar(
              backgroundColor: p.card,
              surfaceTintColor: Colors.transparent,
              automaticallyImplyLeading: false,
              titleSpacing: 8,
              toolbarHeight: 60,
              title: Row(children: [
                Material(
                  color: AppColors.red,
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => _pause(s),
                    child: const SizedBox(width: 36, height: 36, child: Icon(Icons.pause_rounded, color: Colors.white)),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: InkWell(
                    onTap: () => _overlay(() => _sectionSheet(s)),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Flexible(child: Text(s.section.s('name'), overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800))),
                        Icon(Icons.arrow_drop_down_rounded, color: p.muted),
                      ]),
                      Text.rich(TextSpan(children: [
                        TextSpan(text: 'Total left ', style: TextStyle(color: p.muted)),
                        TextSpan(text: mmss(s.totalLeft), style: const TextStyle(color: AppColors.red, fontWeight: FontWeight.w800)),
                      ]), style: const TextStyle(fontSize: 11.5)),
                    ]),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: low ? const Color(0xFFFCE4E7) : AppColors.peach, borderRadius: BorderRadius.circular(10)),
                  child: Column(children: [
                    Text(mmss(s.left[s.s]), style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: low ? AppColors.red : AppColors.peachInk, fontFeatures: const [FontFeature.tabularFigures()])),
                    Text('SECTION', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: low ? AppColors.red : AppColors.peachInk)),
                  ]),
                ),
                const SizedBox(width: 6),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(minimumSize: const Size(40, 38), padding: const EdgeInsets.symmetric(horizontal: 8)),
                  onPressed: () => _overlay(() => _langSheet()),
                  child: Text(_langs.firstWhere((l) => l.$1 == lang).$2, style: const TextStyle(fontWeight: FontWeight.w800)),
                ),
                IconButton(tooltip: 'Question palette', onPressed: () => _overlay(() => _paletteSheet()), icon: Icon(Icons.grid_view_rounded, color: p.brand)),
              ]),
              bottom: PreferredSize(
                preferredSize: const Size.fromHeight(10),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 6),
                  child: Row(children: [
                    for (var k = 0; k < s.sections.length; k++) ...[
                      if (k > 0) const SizedBox(width: 4),
                      Expanded(
                        child: ProgressBar(
                          s.done[k] ? 1 : (k == s.s ? (1 - s.left[k] / (s.sections[k].i('minutes', 1) * 60)).clamp(0.0, 1.0) : 0),
                          height: 4,
                          color: s.done[k] ? AppColors.green : AppColors.primary2,
                        ),
                      ),
                    ],
                  ]),
                ),
              ),
            ),
            body: PageWidth(
              child: ListView(padding: const EdgeInsets.fromLTRB(16, 14, 16, 24), children: [
                if (enOnly)
                  Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(color: p.bg, borderRadius: BorderRadius.circular(10), border: Border.all(color: p.line)),
                    child: Text('🌐 The ${s.section.s('name')} section is shown only in English.', style: TextStyle(fontSize: 12, color: p.muted)),
                  ),
                Row(children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                    decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(8)),
                    child: Text('Question type: Multiple choice', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: p.muted)),
                  ),
                  const Spacer(),
                  TextButton.icon(
                    style: TextButton.styleFrom(backgroundColor: st.marked ? _purple : null, foregroundColor: st.marked ? Colors.white : p.ink),
                    onPressed: _c.toggleMark,
                    icon: Icon(st.marked ? Icons.star_rounded : Icons.star_border_rounded, size: 18),
                    label: Text(st.marked ? 'Marked' : 'Review'),
                  ),
                ]),
                const SizedBox(height: 12),
                Row(children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(8)),
                    child: Text('${s.i + 1}', style: const TextStyle(fontWeight: FontWeight.w800)),
                  ),
                  const SizedBox(width: 8),
                  const Text('Question', style: TextStyle(fontWeight: FontWeight.w700)),
                  const Spacer(),
                  Tag('+${num1(q.n('marks', 1))}', tone: Tone.mint),
                  if (q.n('negative') > 0) ...[const SizedBox(width: 6), Tag('−${num1(q.n('negative'))}', tone: Tone.red)],
                ]),
                const SizedBox(height: 10),
                Text(tr(q['q'], enOnly ? 'en' : lang), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, height: 1.55)),
                const SizedBox(height: 16),
                for (var k = 0; k < opts.length; k++)
                  AnswerTile(
                    letter: 'ABCDEFGH'[k],
                    text: tr(opts[k], enOnly ? 'en' : lang),
                    state: st.answer == k ? AnswerState.selected : AnswerState.idle,
                    onTap: () => _c.select(k),
                  ),
              ]),
            ),
            bottomNavigationBar: FooterBar(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Row(children: [
                  AppButton('Report', emoji: '⚑', small: true, outlined: true, color: AppColors.red, onPressed: () => _overlay(() => _reportSheet(s))),
                  const SizedBox(width: 8),
                  Expanded(child: AppButton('Clear response', small: true, outlined: true, color: AppColors.primary2, onPressed: st.answer == null ? null : _c.clear)),
                ]),
                const SizedBox(height: 8),
                AppButton(_submitting ? 'Submitting…' : 'Save & Next', expand: true, onPressed: _submitting
                    ? null
                    : () {
                        if (!_c.next()) {
                          showSoon(context, 'Last question of this section');
                          _overlay(() => _paletteSheet());
                        }
                      }),
              ]),
            ),
          ),
        );
      },
    );
  }

  // ---------- sheets ----------
  Future<void> _sectionSheet(AttemptSession s) => showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (ctx) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Text('Select section', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
              for (var k = 0; k < s.sections.length; k++)
                ListTile(
                  enabled: k == s.s,
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(k == s.s ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: k == s.s ? AppColors.primary2 : null),
                  title: Text(s.sections[k].s('name')),
                  trailing: Text(s.done[k] ? 'Completed ✓' : (k == s.s ? 'Running · ${mmss(s.left[k])}' : '🔒 Starts later'), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                  onTap: () => Navigator.pop(ctx),
                ),
              const SizedBox(height: 6),
              const Text('Sections are timed separately and run in order.', style: TextStyle(fontSize: 12)),
            ]),
          ),
        ),
      );

  Future<void> _langSheet() => showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (ctx) => Consumer(builder: (ctx, ref, _) {
          final lang = ref.watch(testLanguageProvider);
          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Text('Question language', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                for (final l in _langs)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(lang == l.$1 ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: lang == l.$1 ? AppColors.primary2 : null),
                    title: Text(l.$3),
                    onTap: () {
                      ref.read(testLanguageProvider.notifier).set(l.$1);
                      Navigator.pop(ctx);
                    },
                  ),
                const Text('Your answers stay the same when you switch.', style: TextStyle(fontSize: 12)),
              ]),
            ),
          );
        }),
      );

  Future<void> _reportSheet(AttemptSession s) {
    var reason = 0;
    final note = TextEditingController();
    return showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Padding(
          padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(ctx).bottom + 16),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Report question ${s.i + 1}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            for (final (k, r) in s.reportReasons.indexed)
              Padding(padding: const EdgeInsets.only(bottom: 8), child: ChoiceChip(label: SizedBox(width: double.infinity, child: Text(r)), selected: reason == k, onSelected: (_) => set(() => reason = k))),
            TextField(controller: note, minLines: 2, maxLines: 4, decoration: const InputDecoration(hintText: 'Tell us more (optional)', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            AppButton('Send report', expand: true, onPressed: () async {
              Navigator.pop(ctx);
              await runAction(context, () => _c.report(reason, note.text), success: 'Thanks! Our team will check this question.');
            }),
          ]),
        ),
      ),
    );
  }

  Future<void> _paletteSheet() => showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        isScrollControlled: true,
        builder: (ctx) => Consumer(builder: (ctx, ref, _) {
          final s = ref.watch(attemptControllerProvider).value;
          if (s == null) return const SizedBox(height: 120);
          final c = s.counts(s.s);
          final pal = ctx.palette;
          Color bg(QState x) => switch (x.status) { 'a' => AppColors.green, 'na' => AppColors.red, 'm' || 'ma' => _purple, _ => pal.sheet };
          return SafeArea(
            child: ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(ctx).height * 0.85),
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(children: [
                      for (var k = 0; k < s.sections.length; k++)
                        Padding(
                          padding: const EdgeInsets.only(right: 16),
                          child: Text('${s.sections[k].s('name')}${s.done[k] ? ' ✓' : (k > s.s ? ' 🔒' : '')}',
                              style: TextStyle(fontWeight: FontWeight.w800, color: k == s.s ? AppColors.primary2 : pal.muted, decoration: k == s.s ? TextDecoration.underline : null)),
                        ),
                    ]),
                  ),
                  const SizedBox(height: 10),
                  Row(children: [
                    Text('Questions: ${s.perSection} · Answered: ${c['answered']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
                    const Spacer(),
                    Text('Left ${mmss(s.left[s.s])}', style: const TextStyle(color: AppColors.red, fontWeight: FontWeight.w800)),
                  ]),
                  const SizedBox(height: 12),
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: s.perSection,
                    gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 56, mainAxisSpacing: 9, crossAxisSpacing: 9),
                    itemBuilder: (_, k) {
                      final x = s.q[s.s][k];
                      final fill = bg(x);
                      return InkWell(
                        borderRadius: BorderRadius.circular(10),
                        onTap: () {
                          Navigator.pop(ctx);
                          ref.read(attemptControllerProvider.notifier).goTo(k);
                        },
                        child: Container(
                          alignment: Alignment.center,
                          decoration: BoxDecoration(color: fill, borderRadius: BorderRadius.circular(10), border: k == s.i ? Border.all(color: AppColors.primary2, width: 2.5) : null),
                          child: Stack(clipBehavior: Clip.none, children: [
                            Text('${k + 1}', style: TextStyle(fontWeight: FontWeight.w800, color: fill == pal.sheet ? pal.muted : Colors.white)),
                            if (x.status == 'ma') const Positioned(right: -10, bottom: -8, child: Icon(Icons.circle, size: 9, color: Color(0xFF7CF0B5))),
                          ]),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  Wrap(spacing: 12, runSpacing: 6, children: [
                    _legend(AppColors.green, 'Answered'),
                    _legend(AppColors.red, 'Not answered'),
                    _legend(_purple, 'Marked for review'),
                    _legend(pal.sheet, 'Not visited'),
                  ]),
                  const SizedBox(height: 14),
                  Row(children: [
                    Expanded(child: AppButton('Submit section', outlined: true, color: AppColors.primary2, onPressed: () {
                      Navigator.pop(ctx);
                      _confirmSubmitSection(s);
                    })),
                    const SizedBox(width: 8),
                    Expanded(child: AppButton('Submit test', onPressed: () {
                      Navigator.pop(ctx);
                      _confirmSubmitTest(s);
                    })),
                  ]),
                ]),
              ),
            ),
          );
        }),
      );

  Widget _legend(Color c, String t) => Row(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 12, height: 12, decoration: BoxDecoration(color: c, borderRadius: BorderRadius.circular(3))),
        const SizedBox(width: 5),
        Text(t, style: const TextStyle(fontSize: 11.5)),
      ]);
}

/// Section-wise answered / skipped / marked / not visited.
class _SectionTable extends StatelessWidget {
  const _SectionTable({required this.s});
  final AttemptSession s;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    const heads = ['Section', 'Ans', 'Skip', 'Mark', 'Not seen'];
    final keys = ['answered', 'skipped', 'marked', 'not_visited'];
    final h = TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: p.muted);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        headingRowHeight: 34,
        dataRowMinHeight: 32,
        dataRowMaxHeight: 36,
        columnSpacing: 14,
        horizontalMargin: 6,
        columns: [for (final t in heads) DataColumn(label: Text(t, style: h))],
        rows: [
          for (var k = 0; k < s.sections.length; k++)
            DataRow(
              color: WidgetStatePropertyAll(k == s.s ? AppColors.lavender : null),
              cells: [
                DataCell(Text('${s.sections[k].s('short')}${s.done[k] ? ' ✓' : ''}', style: const TextStyle(fontWeight: FontWeight.w700))),
                for (final key in keys) DataCell(Text('${s.counts(k)[key]}')),
              ],
            ),
        ],
      ),
    );
  }
}

/// Shown when a section's time runs out. Auto-continues after 10 s.
class _SectionEndedDialog extends StatefulWidget {
  const _SectionEndedDialog({required this.last, required this.finished, required this.next});
  final bool last;
  final String finished, next;

  @override
  State<_SectionEndedDialog> createState() => _SectionEndedDialogState();
}

class _SectionEndedDialogState extends State<_SectionEndedDialog> {
  int _n = 10;
  Timer? _t;

  @override
  void initState() {
    super.initState();
    _t = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return;
      setState(() => _n--);
      if (_n <= 0) {
        t.cancel();
        Navigator.pop(context, true);
      }
    });
  }

  @override
  void dispose() {
    _t?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      icon: const Text('⏰', style: TextStyle(fontSize: 36)),
      title: Text(widget.last ? 'Test time is over' : 'Section time ended', textAlign: TextAlign.center),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(
          widget.last ? 'All sections are finished. Your test is being submitted.' : 'Time for ${widget.finished} is over and it has been submitted. Press Next to start ${widget.next}.',
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 10),
        Text('${widget.last ? 'Opening result' : 'Starting'} in $_n s', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
      ]),
      actions: [
        if (!widget.last) TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Pause test')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(widget.last ? 'See result' : 'Next section')),
      ],
    );
  }
}
