import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../learning/data/learning_repository.dart';
import '../data/store_repository.dart';

/// Tab "Courses": my courses (continue / renew) + the store.
class CoursesTabScreen extends ConsumerStatefulWidget {
  const CoursesTabScreen({super.key, this.initialTab = 0});
  final int initialTab;

  @override
  ConsumerState<CoursesTabScreen> createState() => _CoursesTabScreenState();
}

class _CoursesTabScreenState extends ConsumerState<CoursesTabScreen> {
  late int _tab = widget.initialTab;
  String? _pricing;
  String _search = '';
  final _q = TextEditingController();

  @override
  void dispose() {
    _q.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Scaffold(
      backgroundColor: p.bg,
      body: SafeArea(
        bottom: false,
        child: PageWidth(
          child: Column(children: [
            Padding(
              padding: EdgeInsets.fromLTRB(context.hPad, 12, context.hPad, 8),
              child: Row(children: [
                const Expanded(child: Text('Courses', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
                IconButton(tooltip: 'Orders & invoices', onPressed: () => context.push(R.orders), icon: Icon(Icons.receipt_long_rounded, color: p.brand)),
              ]),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: context.hPad),
              child: SegmentTabs(items: const ['My courses', 'Explore'], selected: _tab, onChanged: (i) => setState(() => _tab = i)),
            ),
            const SizedBox(height: 8),
            Expanded(child: _tab == 0 ? _MyCourses(onExplore: () => setState(() => _tab = 1)) : _store(context)),
          ]),
        ),
      ),
    );
  }

  Widget _store(BuildContext context) {
    final f = (category: null as String?, search: _search.isEmpty ? null : _search, pricing: _pricing);
    final list = ref.watch(storeCoursesProvider(f));
    return Column(children: [
      Padding(
        padding: EdgeInsets.fromLTRB(context.hPad, 4, context.hPad, 8),
        child: TextField(
          controller: _q,
          textInputAction: TextInputAction.search,
          onSubmitted: (v) => setState(() => _search = v.trim()),
          decoration: InputDecoration(
            hintText: 'Search courses',
            prefixIcon: const Icon(Icons.search_rounded),
            suffixIcon: _search.isEmpty ? null : IconButton(tooltip: 'Clear', icon: const Icon(Icons.close_rounded), onPressed: () => setState(() => _search = _q.text = '')),
          ),
        ),
      ),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: context.hPad),
        child: ChipBar(
          items: const ['All', 'Paid', 'Free'],
          selected: _pricing == null ? 0 : (_pricing == 'paid' ? 1 : 2),
          onChanged: (i) => setState(() => _pricing = [null, 'paid', 'free'][i]),
        ),
      ),
      Expanded(
        child: AsyncView(
          value: list,
          onRetry: () => ref.invalidate(storeCoursesProvider(f)),
          data: (page) => page.items.isEmpty
              ? const EmptyView(emoji: '🔎', title: 'No courses found')
              : RefreshIndicator(
                  onRefresh: () => ref.refresh(storeCoursesProvider(f).future),
                  child: ListView.separated(
                    padding: EdgeInsets.fromLTRB(context.hPad, 10, context.hPad, 24),
                    itemCount: page.items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (_, i) => CourseCard(course: page.items[i]),
                  ),
                ),
        ),
      ),
    ]);
  }
}

class _MyCourses extends ConsumerWidget {
  const _MyCourses({required this.onExplore});
  final VoidCallback onExplore;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return AsyncView<List<Json>>(
      value: ref.watch(myCoursesProvider),
      onRetry: () => ref.invalidate(myCoursesProvider),
      data: (all) {
        if (all.isEmpty) {
          return EmptyView(emoji: '🎓', title: 'No courses yet', subtitle: 'Join a course to see classes, notes and tests here.', action: AppButton('Explore courses', onPressed: onExplore));
        }
        final active = all.where((c) => c.s('status') == 'active').toList();
        final expired = all.where((c) => c.s('status') != 'active').toList();
        final ending = active.where((c) => c['days_left'] != null && c.i('days_left') <= 30 && c.s('role') == 'student').toList();
        return RefreshIndicator(
          onRefresh: () => ref.refresh(myCoursesProvider.future),
          child: ListView(padding: EdgeInsets.fromLTRB(context.hPad, 8, context.hPad, 24), children: [
            for (final c in ending)
              AppCard(
                margin: const EdgeInsets.only(bottom: 12),
                color: AppColors.peach,
                bordered: false,
                onTap: () => context.push(R.course(c.s('slug', '${c.i('course_id')}'))),
                child: Row(children: [
                  const Text('⏳', style: TextStyle(fontSize: 22)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${c.s('title')} ends in ${c.i('days_left')} days', style: const TextStyle(fontWeight: FontWeight.w800)),
                      const Text('Renew now to keep your classes, notes and results', style: TextStyle(fontSize: 12.5, color: AppColors.peachInk)),
                    ]),
                  ),
                  const Text('Renew', style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.peachInk)),
                ]),
              ),
            for (final c in active) _MyCourseTile(c),
            if (expired.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text('Expired', style: TextStyle(fontWeight: FontWeight.w800, color: p.muted)),
              const SizedBox(height: 8),
              for (final c in expired) Opacity(opacity: .7, child: _MyCourseTile(c)),
            ],
          ]),
        );
      },
    );
  }
}

class _MyCourseTile extends StatelessWidget {
  const _MyCourseTile(this.c);
  final Json c;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final teaching = c.s('role') != 'student';
    final active = c.s('status') == 'active';
    return AppCard(
      margin: const EdgeInsets.only(bottom: 12),
      onTap: () => active ? context.push(R.learn(c.i('course_id'), title: c.s('title'))) : context.push(R.course(c.s('slug', '${c.i('course_id')}'))),
      child: Row(children: [
        CourseThumb(url: c.sn('thumbnail_url'), size: 64),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(c.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 2),
            Text(
              teaching ? 'You teach this course' : [if (c.s('batch').isNotEmpty) c.s('batch'), if (c['days_left'] != null) '${c.i('days_left')} days left' else 'Lifetime'].join(' · '),
              style: TextStyle(fontSize: 12.5, color: p.muted),
            ),
            if (!teaching && active) ...[
              const SizedBox(height: 8),
              ProgressBar(c.i('progress') / 100, height: 6),
              const SizedBox(height: 4),
              Text('${c.i('progress')}% done', style: TextStyle(fontSize: 11.5, color: p.muted)),
            ],
            if (!active) const Padding(padding: EdgeInsets.only(top: 6), child: Tag('Expired · tap to renew', tone: Tone.red)),
          ]),
        ),
        if (teaching) const Tag('Teacher', tone: Tone.mint),
      ]),
    );
  }
}

/// Store card used in lists and the exam hub.
class CourseCard extends StatelessWidget {
  const CourseCard({super.key, required this.course});
  final Json course;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final c = course;
    return AppCard(
      padding: EdgeInsets.zero,
      onTap: () => context.push(R.course(c.s('slug', '${c.i('id')}'))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        AspectRatio(aspectRatio: 16 / 7, child: CourseThumb(url: c.sn('thumbnail_url'), radius: 0, title: c.s('title'))),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              if (c.s('category').isNotEmpty) Tag(c.s('category')),
              if (c.b('is_enrolled')) ...[const SizedBox(width: 6), const Tag('Enrolled', tone: Tone.mint)],
              if (c.b('is_free')) ...[const SizedBox(width: 6), const Tag('Free', tone: Tone.mint)],
            ]),
            const SizedBox(height: 8),
            Text(c.s('title'), style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800)),
            if (c.s('short_description').isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(c.s('short_description'), maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, color: p.muted)),
            ],
            const SizedBox(height: 10),
            Row(children: [
              Text(c.s('price_text', '—'), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
              if (c.sn('mrp_text') != null) ...[
                const SizedBox(width: 6),
                Text(c.s('mrp_text'), style: TextStyle(color: p.muted, decoration: TextDecoration.lineThrough)),
              ],
              if (c.i('discount_percent') > 0) ...[const SizedBox(width: 6), Tag('${c.i('discount_percent')}% off', tone: Tone.mint)],
              const Spacer(),
              if (c.i('students_count') > 0) Text('${c.i('students_count')} students', style: TextStyle(fontSize: 12, color: p.muted)),
            ]),
          ]),
        ),
      ]),
    );
  }
}

/// Thumbnail with a branded placeholder.
class CourseThumb extends StatelessWidget {
  const CourseThumb({super.key, this.url, this.size, this.radius = 14, this.title});
  final String? url;
  final double? size, radius;
  final String? title;

  @override
  Widget build(BuildContext context) {
    final ph = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius ?? 0),
        gradient: const LinearGradient(colors: [AppColors.primary, AppColors.primary2], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Text(title == null ? 'പ' : title!.characters.take(1).toString(), style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: (size ?? 60) * .38)),
    );
    if (url == null || url!.isEmpty) return ph;
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius ?? 0),
      child: Image.network(url!, width: size, height: size, fit: BoxFit.cover, errorBuilder: (_, __, ___) => ph),
    );
  }
}
