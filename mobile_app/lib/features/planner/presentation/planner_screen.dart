import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/format.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/planner_repository.dart';

/// Study plan: tasks from your course's weekly plan + your own tasks. Tick them off.
class PlannerScreen extends ConsumerStatefulWidget {
  const PlannerScreen({super.key});

  @override
  ConsumerState<PlannerScreen> createState() => _PlannerScreenState();
}

class _PlannerScreenState extends ConsumerState<PlannerScreen> {
  late DateTime _monday = _mondayOf(DateTime.now());
  late DateTime _day = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);

  static DateTime _mondayOf(DateTime d) => DateTime(d.year, d.month, d.day).subtract(Duration(days: d.weekday - 1));

  void _shift(int weeks) => setState(() {
        _monday = _monday.add(Duration(days: 7 * weeks));
        _day = _monday;
      });

  static const _icons = {'video': '🎬', 'notes': '📝', 'test': '🧪', 'quiz': '⚡', 'revise': '🔁', 'study': '📖'};

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final data = ref.watch(plannerProvider(_monday));
    return SubPage(
      title: 'Study plan',
      subtitle: '${_monday.day} ${monthAbbr(_monday)} – ${_monday.add(const Duration(days: 6)).day} ${monthAbbr(_monday.add(const Duration(days: 6)))}',
      actions: [
        IconButton(tooltip: 'Previous week', onPressed: () => _shift(-1), icon: Icon(Icons.chevron_left_rounded, color: p.brand)),
        IconButton(tooltip: 'Next week', onPressed: () => _shift(1), icon: Icon(Icons.chevron_right_rounded, color: p.brand)),
      ],
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _add(context), icon: const Icon(Icons.add_rounded), label: const Text('Add task')),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(plannerProvider(_monday)),
        data: (d) {
          final byDate = {for (final day in d.l('days')) day.s('date'): day};
          final today = byDate[ymd(_day)] ?? const <String, dynamic>{};
          final tasks = today.l('tasks');
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 96), children: [
            Row(children: [
              for (var k = 0; k < 7; k++)
                Expanded(child: () {
                  final day = _monday.add(Duration(days: k));
                  final info = byDate[ymd(day)] ?? const <String, dynamic>{};
                  final on = ymd(day) == ymd(_day);
                  final complete = info.i('total') > 0 && info.i('done') == info.i('total');
                  return Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: Material(
                      color: on ? AppColors.primary : p.card,
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () => setState(() => _day = day),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          child: Column(children: [
                            Text(const ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][k], style: TextStyle(fontSize: 11, color: on ? Colors.white70 : p.muted, fontWeight: FontWeight.w700)),
                            Text('${day.day}', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: on ? Colors.white : p.ink)),
                            const SizedBox(height: 4),
                            Icon(complete ? Icons.check_circle_rounded : Icons.circle, size: complete ? 14 : 6,
                                color: complete ? AppColors.green : (info.i('total') > 0 ? AppColors.saffron : Colors.transparent)),
                          ]),
                        ),
                      ),
                    ),
                  );
                }()),
            ]),
            const SizedBox(height: 16),
            if (tasks.isNotEmpty) ...[
              ProgressBar(today.i('done') / today.i('total', 1)),
              const SizedBox(height: 6),
              Text('${today.i('done')} of ${today.i('total')} done · ${today.i('minutes')} min planned', style: TextStyle(fontSize: 12.5, color: p.muted)),
              const SizedBox(height: 12),
            ],
            if (tasks.isEmpty) const EmptyNote('Nothing planned for this day. Add a task, or join a course with a study plan.'),
            for (final t in tasks)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  child: Row(children: [
                    Checkbox(
                      value: t.b('done'),
                      onChanged: (v) async {
                        await runAction(context, () => ref.read(plannerRepositoryProvider).setDone(t.i('id'), v ?? false));
                        ref.invalidate(plannerProvider(_monday));
                      },
                    ),
                    Text(_icons[t.s('type')] ?? '📖', style: const TextStyle(fontSize: 18)),
                    const SizedBox(width: 8),
                    Expanded(
                      child: InkWell(
                        onTap: t['content_id'] == null ? null : () => context.push(R.content(t.i('content_id'))),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(t.s('title'), style: TextStyle(fontWeight: FontWeight.w700, decoration: t.b('done') ? TextDecoration.lineThrough : null)),
                            Text('${t.i('minutes')} min${t.b('personal') ? ' · my task' : ''}', style: TextStyle(fontSize: 12, color: p.muted)),
                          ]),
                        ),
                      ),
                    ),
                    if (t.b('personal'))
                      IconButton(
                        tooltip: 'Delete task',
                        icon: Icon(Icons.delete_outline_rounded, color: p.muted),
                        onPressed: () async {
                          await runAction(context, () => ref.read(plannerRepositoryProvider).remove(t.i('id')));
                          ref.invalidate(plannerProvider(_monday));
                        },
                      ),
                  ]),
                ),
              ),
          ]);
        },
      ),
    );
  }

  Future<void> _add(BuildContext context) async {
    final title = TextEditingController();
    var minutes = 30;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Padding(
          padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(ctx).bottom + 16),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Task for ${_day.day} ${monthAbbr(_day)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            TextField(controller: title, autofocus: true, decoration: const InputDecoration(labelText: 'What will you study?', hintText: 'Revise Kerala Renaissance notes')),
            const SizedBox(height: 12),
            Text('Time: $minutes min'),
            Slider(value: minutes.toDouble(), min: 10, max: 180, divisions: 17, label: '$minutes min', onChanged: (v) => set(() => minutes = v.round())),
            AppButton('Add task', expand: true, onPressed: () => Navigator.pop(ctx, true)),
          ]),
        ),
      ),
    );
    if (ok == true && title.text.trim().isNotEmpty && context.mounted) {
      await runAction(context, () => ref.read(plannerRepositoryProvider).add(_day, title.text.trim(), minutes: minutes), success: 'Task added');
      ref.invalidate(plannerProvider(_monday));
    }
  }
}
