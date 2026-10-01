import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/json.dart';
import '../../../../core/widgets/ui.dart';
import '../../application/setup_controller.dart';
import '../setup_screen.dart';

SetupController _c(WidgetRef ref) => ref.read(setupControllerProvider.notifier);

Json? _qual(Json o, String? id) {
  for (final q in o.l('qualifications')) {
    if (q.s('id') == id) return q;
  }
  return null;
}

// ---------------- Step 5: qualification ----------------
List<Widget> qualificationStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  return [
    ...stepHeader(context, 'Your highest qualification', "We'll show exams you're eligible for."),
    for (final q in o.l('qualifications'))
      Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: ChoiceTile(
          emoji: q.s('emoji'),
          title: q.s('name'),
          subtitle: q.s('detail'),
          selected: d.qualification == q.s('id'),
          onTap: () {
            final blocked = q.ls('not_eligible');
            final exams = d.exams.where((e) => !blocked.contains(e)).toList();
            final posts = Map<String, String>.of(d.posts)..removeWhere((k, _) => !exams.contains(k));
            _c(ref).update((x) => x.copyWith(qualification: q.s('id'), exams: exams, posts: posts));
          },
        ),
      ),
  ];
}

// ---------------- Step 6: exam categories ----------------
List<Widget> examsStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final p = context.palette;
  final q = _qual(o, d.qualification);
  final rec = q?.ls('recommended') ?? const <String>[];
  final no = q?.ls('not_eligible') ?? const <String>[];
  final max = o.i('max_exams', 3);
  final exams = [...o.l('exams')]..sort((a, b) {
      int score(Json e) => rec.contains(e.s('id')) ? 0 : (no.contains(e.s('id')) ? 2 : 1);
      return score(a).compareTo(score(b));
    });

  return [
    ...stepHeader(context, 'Which exams are you preparing for?'),
    Row(children: [
      Text('Pick up to $max', style: TextStyle(color: p.muted, fontWeight: FontWeight.w600)),
      const Spacer(),
      Text('${d.exams.length} / $max selected', style: const TextStyle(fontWeight: FontWeight.w800)),
    ]),
    const SizedBox(height: 12),
    LayoutBuilder(builder: (context, box) {
      final cols = box.maxWidth >= 520 ? 3 : 2;
      return GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: exams.length,
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: cols, mainAxisSpacing: 10, crossAxisSpacing: 10, mainAxisExtent: 128),
        itemBuilder: (_, i) {
          final e = exams[i];
          final id = e.s('id');
          final on = d.exams.contains(id);
          final disabled = no.contains(id);
          return Opacity(
            opacity: disabled ? 0.45 : 1,
            child: InkWell(
              borderRadius: BorderRadius.circular(20),
              onTap: disabled
                  ? () => showSoon(context, 'Needs a higher qualification')
                  : () {
                      if (!on && d.exams.length >= max) {
                        showSoon(context, 'You can pick up to $max exams');
                        return;
                      }
                      _c(ref).toggleExam(id, max);
                    },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 160),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: on ? AppColors.primary2.withValues(alpha: 0.07) : p.card,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: on ? AppColors.primary2 : p.line, width: 1.5),
                ),
                child: Stack(children: [
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(e.s('emoji'), style: const TextStyle(fontSize: 28)),
                    const SizedBox(height: 6),
                    Text(e.s('name'), style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
                    Text(disabled ? 'Needs higher qualification' : e.s('detail'),
                        maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11.5, color: p.muted, height: 1.3)),
                  ]),
                  Positioned(
                    top: 0,
                    right: 0,
                    child: Icon(on ? Icons.check_circle_rounded : Icons.circle_outlined, size: 22, color: on ? AppColors.primary2 : p.line),
                  ),
                  if (rec.contains(id) && !disabled) const Positioned(top: 0, right: 26, child: Tag('Recommended', tone: Tone.mint)),
                ]),
              ),
            ),
          );
        },
      );
    }),
  ];
}

// ---------------- Step 7: target post per exam ----------------
List<Widget> postsStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final p = context.palette;
  final byId = {for (final e in o.l('exams')) e.s('id'): e};
  return [
    ...stepHeader(context, "What's your target?", 'Choose one for each exam. You can add more later.'),
    for (final id in d.exams)
      if (byId[id] != null)
        AppCard(
          margin: const EdgeInsets.only(bottom: 12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text('${byId[id]!.s('emoji')}  ${byId[id]!.s('name')}', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
              const Spacer(),
              Text(byId[id]!.s('label', 'Post'), style: TextStyle(fontSize: 12, color: p.muted)),
            ]),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final post in byId[id]!.ls('posts'))
                ChoiceChip(
                  label: Text(post),
                  selected: d.posts[id] == post,
                  selectedColor: AppColors.primary2,
                  labelStyle: TextStyle(fontWeight: FontWeight.w700, color: d.posts[id] == post ? Colors.white : p.ink),
                  onSelected: (_) => _c(ref).setPost(id, post),
                ),
            ]),
            if (id == 'psc')
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text('LDC and LGS share most of the syllabus, so you can add the other later.', style: TextStyle(fontSize: 11.5, color: p.muted)),
              ),
          ]),
        ),
  ];
}
