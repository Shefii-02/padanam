import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/routes.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/responsive.dart';
import '../../../../core/utils/format.dart';
import '../../../../core/utils/json.dart';
import '../../../../core/widgets/ui.dart';
import '../../../exams/data/exam_repository.dart';

/// Section heading with page padding.
class HomeHeader extends StatelessWidget {
  const HomeHeader(this.title, {super.key, this.subtitle, this.action, this.onAction});
  final String title;
  final String? subtitle, action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(context.hPad, 24, context.hPad - 8, 12),
      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            if (subtitle != null) Text(subtitle!, style: TextStyle(fontSize: 12.5, color: context.palette.muted)),
          ]),
        ),
        if (action != null)
          TextButton(onPressed: onAction, child: Text(action!, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5))),
      ]),
    );
  }
}

// ---------------- Announcements ----------------
class AnnouncementCarousel extends StatefulWidget {
  const AnnouncementCarousel({super.key, required this.items});
  final List<Json> items;

  @override
  State<AnnouncementCarousel> createState() => _AnnouncementCarouselState();
}

class _AnnouncementCarouselState extends State<AnnouncementCarousel> {
  PageController? _pc;
  double? _fraction;
  Timer? _timer;
  int _i = 0;
  DateTime _touch = DateTime(2000);

  int get _perView => (_fraction ?? 1) < 1 ? 2 : 1;
  int get _pages => (widget.items.length - _perView + 1).clamp(1, widget.items.length.clamp(1, 99));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final f = context.isTablet ? 0.5 : 1.0;
    if (f != _fraction) {
      final old = _pc;
      _fraction = f;
      _i = _i.clamp(0, _pages - 1);
      _pc = PageController(viewportFraction: f, initialPage: _i);
      if (old != null) WidgetsBinding.instance.addPostFrameCallback((_) => old.dispose());
    }
    _timer?.cancel();
    if (!context.reduceMotion && _pages > 1) {
      _timer = Timer.periodic(const Duration(seconds: 4), (_) {
        final c = _pc;
        if (!mounted || c == null || !c.hasClients || DateTime.now().difference(_touch).inSeconds < 5) return;
        c.animateToPage(_i >= _pages - 1 ? 0 : _i + 1, duration: const Duration(milliseconds: 450), curve: Curves.easeOutCubic);
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pc?.dispose();
    super.dispose();
  }

  static (Color?, Gradient?, Color, Color, Color) _theme(String t) => switch (t) {
        'indigo' => (null, const LinearGradient(colors: [AppColors.primary, Color(0xFF3B4FD8)]), Colors.white, const Color(0xFFFFD28A), const Color(0x29FFFFFF)),
        'peach' => (AppColors.peach, null, const Color(0xFF5A2E00), const Color(0xFFC25E00), Colors.white),
        'mint' => (AppColors.mint, null, const Color(0xFF0D4A32), AppColors.green, Colors.white),
        _ => (AppColors.lavender, null, const Color(0xFF15205F), AppColors.primary2, Colors.white),
      };

  @override
  Widget build(BuildContext context) {
    if (widget.items.isEmpty) return const SizedBox.shrink();
    final p = context.palette;
    final tablet = _perView == 2;
    return Column(children: [
      SizedBox(
        height: (context.isSmallPhone ? 184.0 : 168.0) * context.textScale,
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: tablet ? context.hPad - 6 : 0),
          child: Listener(
            onPointerDown: (_) => _touch = DateTime.now(),
            child: PageView.builder(
              controller: _pc,
              padEnds: false,
              itemCount: widget.items.length,
              onPageChanged: (i) => setState(() => _i = i.clamp(0, _pages - 1)),
              itemBuilder: (_, i) {
                final a = widget.items[i];
                final (color, gradient, fg, cta, iconBg) = _theme(a.s('theme'));
                return Padding(
                  padding: EdgeInsets.symmetric(horizontal: tablet ? 6 : context.hPad),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(22),
                    onTap: () => context.open(a.s('route', R.home)),
                    child: Ink(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(color: color, gradient: gradient, borderRadius: BorderRadius.circular(22)),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Container(width: 46, height: 46, alignment: Alignment.center, decoration: BoxDecoration(color: iconBg, shape: BoxShape.circle), child: Text(a.s('emoji'), style: const TextStyle(fontSize: 22))),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(a.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 16.5, fontWeight: FontWeight.w800, color: fg, height: 1.3)),
                            const SizedBox(height: 6),
                            Expanded(child: Text(a.s('body'), maxLines: 3, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 13.5, color: fg.withValues(alpha: 0.85), height: 1.45))),
                            Text(a.s('cta'), style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: cta)),
                          ]),
                        ),
                      ]),
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ),
      Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          for (var k = 0; k < _pages; k++)
            GestureDetector(
              onTap: () => _pc?.animateToPage(k, duration: const Duration(milliseconds: 400), curve: Curves.easeOut),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 250),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: k == _i ? 22 : 7,
                height: 7,
                decoration: BoxDecoration(color: k == _i ? AppColors.primary2 : p.line, borderRadius: BorderRadius.circular(4)),
              ),
            ),
        ]),
      ),
    ]);
  }
}

// ---------------- Exam categories ----------------
class CategoriesSheet extends ConsumerWidget {
  const CategoriesSheet({super.key, required this.categories, required this.more});
  final List<Json> categories;
  final int more;

  void _open(BuildContext context, WidgetRef ref, Json c) {
    final id = c.sn('exam_id');
    if (id == null) {
      showSoon(context, '${c.s('name')} is coming soon');
      return;
    }
    ref.read(currentExamProvider.notifier).set(id);
    context.push(R.exam(id));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final featured = categories.where((c) => c.b('featured')).toList();
    final others = categories.where((c) => !c.b('featured')).toList();
    return Container(
      margin: EdgeInsets.symmetric(horizontal: context.isTablet ? context.hPad : 0),
      padding: EdgeInsets.fromLTRB(context.hPad, 12, context.hPad, 20),
      decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(32)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Center(child: Container(width: 54, height: 5, decoration: BoxDecoration(color: const Color(0xFFB9C1DE), borderRadius: BorderRadius.circular(3)))),
        const SizedBox(height: 8),
        Row(children: [
          const Expanded(child: Text('Choose your exam', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800))),
          TextButton(onPressed: () => context.push(R.exams), child: const Text('View all', style: TextStyle(fontWeight: FontWeight.w700))),
        ]),
        const SizedBox(height: 6),
        Row(children: [
          for (var i = 0; i < featured.length; i++) ...[
            if (i > 0) const SizedBox(width: 12),
            Expanded(
              child: Material(
                color: p.card,
                borderRadius: BorderRadius.circular(22),
                child: InkWell(
                  borderRadius: BorderRadius.circular(22),
                  onTap: () => _open(context, ref, featured[i]),
                  child: Container(
                    constraints: const BoxConstraints(minHeight: 104),
                    padding: const EdgeInsets.all(16),
                    child: Row(children: [
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                          Text(featured[i].s('name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                          const SizedBox(height: 3),
                          Text(featured[i].s('subtitle'), maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, color: p.muted)),
                        ]),
                      ),
                      Text(featured[i].s('emoji'), style: TextStyle(fontSize: context.isSmallPhone ? 32 : 40)),
                    ]),
                  ),
                ),
              ),
            ),
          ],
        ]),
        const SizedBox(height: 12),
        LayoutBuilder(builder: (context, box) {
          return GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            itemCount: others.length + 1,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: gridColumns(box.maxWidth),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              mainAxisExtent: 96 * context.textScale,
            ),
            itemBuilder: (_, i) {
              if (i == others.length) {
                return InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: () => context.push(R.exams),
                  child: Container(
                    decoration: BoxDecoration(borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFFAEB7D9), width: 1.5)),
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Text('+$more', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.primary2)),
                      const SizedBox(height: 6),
                      const Text('More', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
                    ]),
                  ),
                );
              }
              final c = others[i];
              return Material(
                color: p.card,
                borderRadius: BorderRadius.circular(18),
                child: InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: () => _open(context, ref, c),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(c.s('emoji'), style: const TextStyle(fontSize: 30)),
                    const SizedBox(height: 8),
                    Text(c.s('name'), textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
                  ]),
                ),
              );
            },
          );
        }),
      ]),
    );
  }
}

// ---------------- Continue learning ----------------
class ContinueCard extends StatelessWidget {
  const ContinueCard({super.key, required this.lesson});
  final Json lesson;

  @override
  Widget build(BuildContext context) {
    if (lesson.isEmpty) return const SizedBox.shrink();
    final p = context.palette;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const HomeHeader('Continue learning'),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: context.hPad),
        child: AppCard(
          onTap: () => context.push(R.content(lesson.i('content_id', lesson.i('id')))),
          child: Row(children: [
            Container(
              width: 56,
              height: 56,
              alignment: Alignment.center,
              decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), gradient: const LinearGradient(colors: [Color(0xFFFFB14A), Color(0xFFF2701B)])),
              child: Text(lesson.s('emoji'), style: const TextStyle(fontSize: 26)),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(lesson.s('title'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700)),
                Text('${lesson.s('course')} · ${lesson.i('minutes_left')} min left', style: TextStyle(fontSize: 12, color: p.muted)),
                const SizedBox(height: 8),
                ProgressBar(lesson.n('progress'), height: 6),
              ]),
            ),
            const SizedBox(width: 12),
            const CircleAvatar(backgroundColor: AppColors.primary, child: Icon(Icons.play_arrow_rounded, color: Colors.white)),
          ]),
        ),
      ),
    ]);
  }
}

// ---------------- New courses ----------------
class NewCourses extends ConsumerWidget {
  const NewCourses({super.key, required this.courses});
  final List<Json> courses;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final w = context.isTablet ? 270.0 : 240.0;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      HomeHeader('Newly launched courses', subtitle: 'Fresh batches, starting this month', action: 'See all', onAction: () => context.go('${R.courses}?tab=explore')),
      SizedBox(
        height: 118 + 150 * context.textScale,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: EdgeInsets.symmetric(horizontal: context.hPad),
          itemCount: courses.length,
          separatorBuilder: (_, __) => const SizedBox(width: 12),
          itemBuilder: (_, i) {
            final c = courses[i];
            final colors = [for (final h in c.ls('colors')) hex(h)];
            final off = c.i('mrp') == 0 ? 0 : ((1 - c.i('price') / c.i('mrp')) * 100).round();
            void open() => context.push(R.course(c.s('slug', '${c.i('id')}')));

            return Container(
              width: w,
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(color: p.card, borderRadius: BorderRadius.circular(22), border: Border.all(color: p.line)),
              child: InkWell(
                onTap: open,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Container(
                    height: 118,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(gradient: LinearGradient(colors: colors.length > 1 ? colors : [AppColors.primary, AppColors.primary2])),
                    child: Stack(children: [
                      if (c.b('is_new'))
                        Positioned(
                          top: 0,
                          left: 0,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
                            child: const Text('NEW', style: TextStyle(color: AppColors.red, fontWeight: FontWeight.w800, fontSize: 11)),
                          ),
                        ),
                      Positioned(top: 0, right: 0, child: Text(c.s('emoji'), style: const TextStyle(fontSize: 38))),
                      Align(alignment: Alignment.bottomLeft, child: Text(c.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800, height: 1.25))),
                    ]),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${c.s('exam')}  ·  ⭐ ${c.s('rating')}  ·  ${c.i('lessons')} lessons', style: TextStyle(fontSize: 12, color: p.muted)),
                        const Spacer(),
                        Row(children: [
                          Text(inr(c.i('price')), style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800)),
                          const SizedBox(width: 8),
                          Flexible(child: Text(inr(c.i('mrp')), overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 13, color: p.muted, decoration: TextDecoration.lineThrough))),
                          const Spacer(),
                          Tag('$off% off', tone: Tone.mint),
                        ]),
                        const SizedBox(height: 10),
                        AppButton('Enroll now', small: true, expand: true, onPressed: open),
                      ]),
                    ),
                  ),
                ]),
              ),
            );
          },
        ),
      ),
    ]);
  }
}

// ---------------- Leaderboard ----------------
class LeaderboardSection extends StatefulWidget {
  const LeaderboardSection({super.key, required this.data, this.showHeader = true});
  final Json data;
  final bool showHeader;

  @override
  State<LeaderboardSection> createState() => _LeaderboardSectionState();
}

class _LeaderboardSectionState extends State<LeaderboardSection> {
  int _cat = 0;

  @override
  Widget build(BuildContext context) {
    final cats = widget.data.ls('categories');
    if (cats.isEmpty) return const SizedBox.shrink();
    final cat = cats[_cat.clamp(0, cats.length - 1)];
    final entries = widget.data.m('entries').l(cat);
    final me = widget.data.m('my_ranks').m(cat);
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (widget.showHeader)
        HomeHeader('Leaderboard', subtitle: "Based on this week's mock tests", action: 'Full ranking', onAction: () => context.push(R.rankings))
      else
        const SizedBox(height: 8),
      Padding(padding: EdgeInsets.symmetric(horizontal: context.hPad), child: ChipBar(items: cats, selected: _cat, onChanged: (i) => setState(() => _cat = i))),
      const SizedBox(height: 14),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: context.hPad),
        child: LeaderboardBoard(entries: entries, me: me, category: cat),
      ),
    ]);
  }
}

class LeaderboardBoard extends StatelessWidget {
  const LeaderboardBoard({super.key, required this.entries, required this.me, required this.category});
  final List<Json> entries;
  final Json me;
  final String category;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    Widget spot(Json e, int place) {
      final first = place == 1;
      final ring = place == 1 ? AppColors.gold : (place == 2 ? const Color(0xFFC9D1E8) : const Color(0xFFD9905A));
      return Column(mainAxisSize: MainAxisSize.min, children: [
        if (first) const Text('👑', style: TextStyle(fontSize: 24, height: 1.1)),
        Container(
          width: first ? 74 : 58,
          height: first ? 74 : 58,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle, border: Border.all(color: ring, width: first ? 4 : 3)),
          child: Text(initialsOf(e.s('name')), style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: first ? 22 : 17)),
        ),
        const SizedBox(height: 8),
        Text(e.s('name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700)),
        Text('${groupIndian(e.i('points'))} pts', style: const TextStyle(color: Colors.white70, fontSize: 12)),
        const SizedBox(height: 8),
        Container(
          height: first ? 78 : (place == 2 ? 58 : 44),
          width: double.infinity,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: Colors.white.withValues(alpha: first ? 0.22 : 0.14), borderRadius: const BorderRadius.vertical(top: Radius.circular(14), bottom: Radius.circular(4))),
          child: Text('$place', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
        ),
      ]);
    }

    final ranked = me.i('rank') > 0;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 20, 14, 14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        gradient: const LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [AppColors.primary, Color(0xFF2B3EB8)]),
      ),
      child: Column(children: [
        if (entries.length >= 3)
          Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Expanded(flex: 10, child: spot(entries[1], 2)),
            const SizedBox(width: 8),
            Expanded(flex: 12, child: spot(entries[0], 1)),
            const SizedBox(width: 8),
            Expanded(flex: 10, child: spot(entries[2], 3)),
          ]),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(color: p.card, borderRadius: BorderRadius.circular(18)),
          child: DividedColumn(children: [
            for (var i = 3; i < entries.length; i++)
              ListRow(
                padding: const EdgeInsets.symmetric(vertical: 10),
                leading: Row(mainAxisSize: MainAxisSize.min, children: [
                  SizedBox(width: 24, child: Text('${i + 1}', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, color: p.muted))),
                  const SizedBox(width: 8),
                  CircleAvatar(radius: 18, backgroundColor: p.sheet, child: Text(initialsOf(entries[i].s('name')), style: TextStyle(color: p.brand, fontWeight: FontWeight.w800, fontSize: 13))),
                ]),
                title: entries[i].s('name'),
                subtitle: entries[i].s('district'),
                trailing: Text(groupIndian(entries[i].i('points')), style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
          ]),
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(color: AppColors.peach, borderRadius: BorderRadius.circular(16)),
          child: Row(children: [
            const CircleAvatar(radius: 19, backgroundColor: AppColors.saffron, child: Text('You', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 11))),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(ranked ? 'You · Rank #${groupIndian(me.i('rank'))}' : 'Not ranked yet', style: const TextStyle(color: Color(0xFF5A2E00), fontWeight: FontWeight.w800)),
                Text(ranked ? '${groupIndian(me.i('points'))} pts in $category' : 'Take a $category mock test to get ranked', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xCC5A2E00), fontSize: 12)),
              ]),
            ),
            if (ranked && me.i('change') != 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10)),
                child: Text('${me.i('change') > 0 ? '▲' : '▼'} ${me.i('change').abs()}', style: TextStyle(fontWeight: FontWeight.w800, color: me.i('change') > 0 ? AppColors.green : AppColors.red)),
              )
            else if (!ranked)
              AppButton('Start', small: true, color: AppColors.saffron, onPressed: () => context.go(R.tests)),
          ]),
        ),
      ]),
    );
  }
}

// ---------------- Achievers ----------------
class Achievers extends StatelessWidget {
  const Achievers({super.key, required this.stats, required this.items});
  final List<Json> stats, items;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const HomeHeader('Our achievers', subtitle: 'Students who cleared with Padanam'),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: context.hPad),
        child: Row(children: [
          for (var i = 0; i < stats.length; i++) ...[
            if (i > 0) const SizedBox(width: 10),
            Expanded(
              child: AppCard(
                radius: 18,
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
                child: Column(children: [
                  FittedBox(child: Text(stats[i].s('value'), style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: p.brand))),
                  Text(stats[i].s('label'), textAlign: TextAlign.center, style: TextStyle(fontSize: 11.5, color: p.muted)),
                ]),
              ),
            ),
          ],
        ]),
      ),
      const SizedBox(height: 12),
      SizedBox(
        height: 232 * context.textScale,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: EdgeInsets.symmetric(horizontal: context.hPad),
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(width: 12),
          itemBuilder: (_, i) {
            final a = items[i];
            final colors = [for (final h in a.ls('colors')) hex(h)];
            return Container(
              width: context.isTablet ? 280 : 260,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(borderRadius: BorderRadius.circular(22), gradient: LinearGradient(colors: colors.length > 1 ? colors : [AppColors.primary, AppColors.primary2])),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  CircleAvatar(radius: 29, backgroundColor: Colors.white, child: Text(initialsOf(a.s('name')), style: const TextStyle(color: Color(0xFF222222), fontWeight: FontWeight.w800, fontSize: 18))),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(a.s('name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 15.5, fontWeight: FontWeight.w800)),
                      Text(a.s('district'), style: const TextStyle(color: Colors.white70, fontSize: 12)),
                    ]),
                  ),
                ]),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(14)),
                  child: Row(children: [
                    Expanded(child: Text('${a.s('exam')}\n${a.s('post')}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 12.5, height: 1.3))),
                    Text(a.s('result'), style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
                  ]),
                ),
                const SizedBox(height: 12),
                Expanded(child: Text('“${a.s('quote')}”', maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 13, height: 1.45))),
              ]),
            );
          },
        ),
      ),
    ]);
  }
}

// ---------------- Upcoming events ----------------
class EventsSection extends StatefulWidget {
  const EventsSection({super.key, required this.events});
  final List<Json> events;

  @override
  State<EventsSection> createState() => _EventsSectionState();
}

class _EventsSectionState extends State<EventsSection> {
  final _reminded = <int>{};
  Timer? _tick;

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  static (Tone, String) _kind(String k) => switch (k) {
        'mockTest' => (Tone.lav, 'Mock test'),
        'webinar' => (Tone.peach, 'Webinar'),
        _ => (Tone.mint, 'Live class'),
      };

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const HomeHeader('Upcoming events', subtitle: 'Live classes, mock tests and webinars'),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: context.hPad),
        child: LayoutBuilder(builder: (context, box) {
          final cols = box.maxWidth >= 640 ? 2 : 1;
          final w = (box.maxWidth - 10 * (cols - 1)) / cols;
          return Wrap(spacing: 10, runSpacing: 10, children: [
            for (final e in widget.events)
              SizedBox(
                width: w,
                child: e.b('is_live_now') ? _live(context, e) : _card(context, e, p),
              ),
          ]);
        }),
      ),
    ]);
  }

  Widget _live(BuildContext context, Json e) => InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => context.open(e.s('route', R.live(1))),
        child: Ink(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(20), gradient: const LinearGradient(colors: [AppColors.red, Color(0xFFFF6A3D)])),
          child: Row(children: [
            Container(
              width: 58,
              height: 64,
              decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(14)),
              child: const Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Text('NOW', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                Icon(Icons.sensors_rounded, color: Colors.white),
              ]),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(8)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    const BlinkingDot(),
                    const SizedBox(width: 6),
                    Text('LIVE · ${compact(e.i('viewers'))} watching', style: const TextStyle(color: AppColors.red, fontSize: 11, fontWeight: FontWeight.w800)),
                  ]),
                ),
                const SizedBox(height: 4),
                Text(e.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                Text(e.s('subtitle'), style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ]),
            ),
            AppButton('Join', small: true, color: Colors.white, foreground: AppColors.red, onPressed: () => context.open(e.s('route', R.live(1)))),
          ]),
        ),
      );

  Widget _card(BuildContext context, Json e, AppPalette p) {
    final start = DateTime.tryParse(e.s('start'))?.toLocal() ?? DateTime.now();
    final (tone, label) = _kind(e.s('kind'));
    final id = e.i('id');
    final on = _reminded.contains(id);
    return AppCard(
      padding: const EdgeInsets.all(12),
      onTap: () => context.open(e.s('route', R.tests)),
      child: Row(children: [
        Container(
          width: 58,
          height: 64,
          decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(14)),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(monthAbbr(start), style: TextStyle(color: tone.fg, fontSize: 11, fontWeight: FontWeight.w700)),
            Text(start.day.toString().padLeft(2, '0'), style: TextStyle(color: tone.fg, fontSize: 22, fontWeight: FontWeight.w800)),
          ]),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Tag(label, tone: tone),
            const SizedBox(height: 4),
            Text(e.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
            Text(e.b('show_countdown') ? 'Starts in ${countdownTo(start)}' : e.s('subtitle'), style: TextStyle(fontSize: 12, color: p.muted)),
          ]),
        ),
        AppButton(on ? 'Reminder set' : 'Remind me', small: true, outlined: !on, color: AppColors.primary2, onPressed: () {
          setState(() => on ? _reminded.remove(id) : _reminded.add(id));
          showSoon(context, on ? 'Reminder removed' : 'Reminder set for "${e.s('title')}"');
        }),
      ]),
    );
  }
}

// ---------------- Study tools & promo ----------------
class StudyTools extends StatelessWidget {
  const StudyTools({super.key, required this.tools});
  final List<Json> tools;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const HomeHeader('Study tools'),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: context.hPad),
        child: LayoutBuilder(builder: (context, box) {
          return GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            itemCount: tools.length,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: gridColumns(box.maxWidth, tablet: 8), crossAxisSpacing: 10, mainAxisSpacing: 14, mainAxisExtent: 98 * context.textScale),
            itemBuilder: (_, i) => InkWell(
              borderRadius: BorderRadius.circular(18),
              onTap: () => context.open(tools[i].s('route', R.home)),
              child: Column(children: [
                IconBox(tools[i].s('emoji'), color: hex(tools[i].s('tint')), size: 58, radius: 18),
                const SizedBox(height: 8),
                Text(tools[i].s('label'), textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, height: 1.25)),
              ]),
            ),
          );
        }),
      ),
    ]);
  }
}

class PromoCard extends StatelessWidget {
  const PromoCard({super.key, required this.promo});
  final Json promo;

  @override
  Widget build(BuildContext context) {
    if (promo.isEmpty) return const SizedBox.shrink();
    final p = context.palette;
    return Container(
      margin: EdgeInsets.fromLTRB(context.hPad, 24, context.hPad, 0),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(promo.s('title'), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text(promo.s('subtitle'), style: TextStyle(fontSize: 12.5, color: p.muted, height: 1.4)),
            const SizedBox(height: 10),
            AppButton(promo.s('cta'), onPressed: () => context.open(promo.s('route', R.courses))),
          ]),
        ),
        const SizedBox(width: 12),
        Text(promo.s('emoji', '🎟️'), style: const TextStyle(fontSize: 44)),
      ]),
    );
  }
}
