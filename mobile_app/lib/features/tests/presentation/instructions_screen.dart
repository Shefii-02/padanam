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

class InstructionsScreen extends ConsumerStatefulWidget {
  const InstructionsScreen({super.key, required this.testId});
  final int testId;

  @override
  ConsumerState<InstructionsScreen> createState() => _InstructionsScreenState();
}

class _InstructionsScreenState extends ConsumerState<InstructionsScreen> {
  bool _agree = false;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final data = ref.watch(instructionsProvider(widget.testId));
    final lang = ref.watch(testLanguageProvider);
    return SubPage(
      title: 'Instructions',
      footer: () {
        final d = data.value;
        final me = d?.m('me') ?? const <String, dynamic>{};
        if (d != null && me.b('can_start') == false) {
          return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(me.s('reason', 'This test is not available'), textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
            if (me.sn('last_attempt') != null) ...[
              const SizedBox(height: 8),
              AppButton('See my result', expand: true, onPressed: () => context.pushReplacement(R.result(me.s('last_attempt')))),
            ],
          ]);
        }
        if (d?.s('mode') == 'omr') {
          return AppButton('Upload OMR sheet', expand: true, onPressed: _agree ? () => context.pushReplacement(R.omr(widget.testId, title: d?.s('title'))) : null);
        }
        final resume = me.sn('in_progress_attempt') != null;
        return AppButton(resume ? 'Resume test' : 'Start test', expand: true, onPressed: _agree || resume ? () => context.pushReplacement(R.attempt(widget.testId)) : null);
      }(),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(instructionsProvider(widget.testId)),
        data: (d) {
          final sections = d.l('sections');
          Widget pill(String v, String l) => Expanded(
                child: AppCard(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  child: Column(children: [
                    Text(v, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: p.brand)),
                    Text(l, style: TextStyle(fontSize: 11, color: p.muted)),
                  ]),
                ),
              );
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
            Text(d.s('title'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, height: 1.4)),
            const SizedBox(height: 12),
            Row(children: [
              pill('${d.i('questions')}', 'Questions'),
              const SizedBox(width: 8),
              pill('${d.i('marks')}', 'Max marks'),
              const SizedBox(width: 8),
              pill('${d.i('duration_minutes')} min', 'Duration'),
            ]),
            const SizedBox(height: 14),
            AppCard(
              padding: const EdgeInsets.fromLTRB(12, 4, 12, 4),
              child: DividedColumn(children: [
                _row(context, '#', 'Section', 'Qs', 'Marks', 'Time', header: true),
                for (final (i, s) in sections.indexed) _row(context, '${i + 1}', s.s('name'), '${s.i('questions')}', num1(s.n('marks')), '${s.i('minutes')} min'),
                _row(context, '', 'Total', '${d.i('questions')}', '${d.i('marks')}', '${d.i('duration_minutes')} min', bold: true),
              ]),
            ),
            const SizedBox(height: 14),
            AppCard(
              color: AppColors.peach,
              bordered: false,
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('⏱️', style: TextStyle(fontSize: 20)),
                const SizedBox(width: 10),
                Expanded(child: Text(d.s('sectional_note'), style: const TextStyle(fontSize: 13, height: 1.5, color: Color(0xFF5A2E00)))),
              ]),
            ),
            const SizedBox(height: 14),
            for (final (i, r) in d.ls('rules').indexed)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  SizedBox(width: 22, child: Text('${i + 1}.', style: const TextStyle(fontWeight: FontWeight.w700))),
                  Expanded(child: Text(r, style: const TextStyle(fontSize: 13, height: 1.55))),
                ]),
              ),
            const SizedBox(height: 8),
            Row(children: [
              const Text('Default language', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(width: 12),
              DropdownButton<String>(
                value: lang,
                items: [for (final l in d.l('languages')) DropdownMenuItem(value: l.s('code'), child: Text(l.s('label')))],
                onChanged: (v) {
                  if (v != null) ref.read(testLanguageProvider.notifier).set(v);
                },
              ),
            ]),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _agree,
              onChanged: (v) => setState(() => _agree = v ?? false),
              title: const Text("I have read the instructions. I understand that each section is timed separately and finished sections can't be reopened.", style: TextStyle(fontSize: 13, height: 1.45)),
            ),
          ]);
        },
      ),
    );
  }

  Widget _row(BuildContext context, String n, String name, String q, String m, String t, {bool header = false, bool bold = false}) {
    final st = header
        ? TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: context.palette.muted)
        : TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w800 : FontWeight.w600);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(children: [
        SizedBox(width: 20, child: Text(n, style: st)),
        Expanded(child: Text(name, style: st)),
        SizedBox(width: 32, child: Text(q, textAlign: TextAlign.center, style: st)),
        SizedBox(width: 44, child: Text(m, textAlign: TextAlign.center, style: st)),
        SizedBox(width: 52, child: Text(t, textAlign: TextAlign.right, style: st)),
      ]),
    );
  }
}
