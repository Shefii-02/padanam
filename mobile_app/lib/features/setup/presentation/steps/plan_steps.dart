import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/json.dart';
import '../../../../core/widgets/ui.dart';
import '../../application/setup_controller.dart';
import '../setup_screen.dart';

SetupController _c(WidgetRef ref) => ref.read(setupControllerProvider.notifier);

Widget _q(String t) => Padding(padding: const EdgeInsets.only(top: 18, bottom: 10), child: Text(t, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800)));

Widget _tiles(BuildContext context, List<Json> items, String selected, ValueChanged<String> onTap) {
  final p = context.palette;
  return GridView.count(
    crossAxisCount: 2,
    shrinkWrap: true,
    physics: const NeverScrollableScrollPhysics(),
    mainAxisSpacing: 8,
    crossAxisSpacing: 8,
    childAspectRatio: 2.1,
    children: [
      for (final t in items)
        InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => onTap(t.s('value')),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: selected == t.s('value') ? AppColors.primary2.withValues(alpha: 0.07) : p.card,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: selected == t.s('value') ? AppColors.primary2 : p.line, width: 1.5),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
              Text('${t.s('emoji')}  ${t.s('value')}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
              const SizedBox(height: 2),
              Text(t.s('subtitle'), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11.5, color: p.muted)),
            ]),
          ),
        ),
    ],
  );
}

// ---------------- Step 8: aim ----------------
List<Widget> aimStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final attempts = o.ls('attempts');
  return [
    ...stepHeader(context, 'Set your aim'),
    _q('Where are you now?'),
    _tiles(context, o.l('levels'), d.level, (v) => _c(ref).update((x) => x.copyWith(level: v))),
    _q('Your goal'),
    _tiles(context, o.l('aims'), d.aim, (v) => _c(ref).update((x) => x.copyWith(aim: v))),
    _q('Which attempt is this?'),
    Segmented(
      items: [for (final a in attempts) a.split(' ').first],
      selected: attempts.indexOf(d.attempt),
      onTap: (i) => _c(ref).update((x) => x.copyWith(attempt: attempts[i])),
    ),
  ];
}

// ---------------- Step 9: study time ----------------
const _dayLetters = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

List<Widget> timeStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final p = context.palette;
  final hours = [for (final h in (o['hours'] as List? ?? const [1, 2, 3, 5])) (h as num).toInt()];
  final slots = o.l('slots');
  final slotTime = {for (final s in slots) s.s('value'): s.s('time')};

  return [
    ...stepHeader(context, 'When can you study?'),
    _q('Time per day'),
    Segmented(
      items: [for (final h in hours) h >= 5 ? '$h+ hrs' : (h == 1 ? '1 hr' : '$h hrs')],
      selected: hours.indexOf(d.hours),
      onTap: (i) => _c(ref).update((x) => x.copyWith(hours: hours[i])),
    ),
    _q('Study days'),
    Row(children: [
      for (var i = 0; i < 7; i++) ...[
        if (i > 0) const SizedBox(width: 6),
        Expanded(
          child: AspectRatio(
            aspectRatio: 1,
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => _c(ref).toggleDay(i),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: d.days[i] ? AppColors.primary2 : p.card,
                  border: Border.all(color: d.days[i] ? AppColors.primary2 : p.line, width: 1.5),
                ),
                child: Text(_dayLetters[i], style: TextStyle(fontWeight: FontWeight.w800, color: d.days[i] ? Colors.white : p.ink)),
              ),
            ),
          ),
        ),
      ],
    ]),
    _q('Preferred time'),
    Segmented(
      items: [for (final s in slots) '${s.s('emoji')} ${s.s('value')}'],
      selected: slots.indexWhere((s) => s.s('value') == d.slot),
      onTap: (i) => _c(ref).update((x) => x.copyWith(slot: slots[i].s('value'))),
    ),
    const SizedBox(height: 18),
    AppCard(
      child: Row(children: [
        const IconBox('⏰'),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Daily reminder', style: TextStyle(fontWeight: FontWeight.w700)),
            Text(d.reminder ? '${d.slot}, ${slotTime[d.slot] ?? ''}' : 'Off', style: TextStyle(fontSize: 12, color: p.muted)),
          ]),
        ),
        Switch(value: d.reminder, onChanged: (v) => _c(ref).update((x) => x.copyWith(reminder: v))),
      ]),
    ),
    const SizedBox(height: 12),
    Text('${d.hours * d.studyDays} hours a week · ${d.studyDays} study days', style: TextStyle(fontSize: 12.5, color: p.muted, fontWeight: FontWeight.w600)),
  ];
}
