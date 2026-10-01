import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/html_text.dart';
import '../../../core/widgets/ui.dart';
import '../../learning/presentation/widgets/content_tile.dart';
import '../../learning/presentation/widgets/youtube_box.dart';
import '../data/store_repository.dart';
import 'courses_tab_screen.dart';

/// Course page in the store: intro video, what you get, demo lessons, batch choice → checkout.
class CourseDetailScreen extends ConsumerStatefulWidget {
  const CourseDetailScreen({super.key, required this.slug});
  final String slug;

  @override
  ConsumerState<CourseDetailScreen> createState() => _CourseDetailScreenState();
}

class _CourseDetailScreenState extends ConsumerState<CourseDetailScreen> {
  int? _batchId;

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(courseDetailProvider(widget.slug));
    return AsyncView<Json>(
      value: detail,
      onRetry: () => ref.invalidate(courseDetailProvider(widget.slug)),
      loading: const Scaffold(body: LoadingView()),
      data: (d) {
        final c = d.m('course');
        final batches = d.l('batches').where((b) => b.b('is_purchasable') || b.b('is_free')).toList();
        final all = d.l('batches');
        _batchId ??= (batches.isNotEmpty ? batches : all).firstOrNull?.i('id');
        final chosen = all.where((b) => b.i('id') == _batchId).firstOrNull;
        final enrolled = d.b('is_enrolled') || d.b('is_staff');
        final p = context.palette;
        return SubPage(
          title: c.s('title'),
          subtitle: [c.s('category'), c.s('language')].where((x) => x.isNotEmpty).join(' · '),
          actions: [
            IconButton(tooltip: 'Share', icon: Icon(Icons.share_rounded, color: p.brand), onPressed: () => SharePlus.instance.share(ShareParams(text: '${c.s('title')} on Padanam\n${c.s('share_url')}'))),
          ],
          footer: enrolled
              ? AppButton('Go to course', expand: true, onPressed: () => context.push(R.learn(c.i('id'), title: c.s('title'))))
              : Row(children: [
                  Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(chosen?.s('name') ?? '', style: TextStyle(fontSize: 12, color: p.muted)),
                    Text(chosen?.s('price_text', '—') ?? '—', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                  ]),
                  const SizedBox(width: 14),
                  Expanded(
                    child: AppButton(
                      chosen?.b('is_free') == true ? 'Join for free' : 'Buy now',
                      expand: true,
                      onPressed: chosen == null || !(chosen.b('is_purchasable')) ? null : () => context.push(R.checkout(chosen.i('id'))),
                    ),
                  ),
                ]),
          children: [
            if (c.sn('intro_youtube_id') != null)
              ClipRRect(borderRadius: BorderRadius.circular(18), child: YoutubeBox(videoId: c.s('intro_youtube_id')))
            else
              AspectRatio(aspectRatio: 16 / 9, child: CourseThumb(url: c.sn('thumbnail_url'), radius: 18, title: c.s('title'))),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: [
              if (c.n('rating') > 0) Tag('★ ${c.s('rating')}'),
              if (c.i('students_count') > 0) Tag('${c.i('students_count')} students', tone: Tone.mint),
              for (final e in {'videos': 'classes', 'live': 'live', 'tests': 'tests', 'notes': 'notes'}.entries)
                if (c.m('counts').i(e.key) > 0) Tag('${c.m('counts').i(e.key)} ${e.value}', tone: Tone.peach),
            ]),
            if (c.s('short_description').isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(c.s('short_description'), style: TextStyle(fontSize: 14, color: p.muted, height: 1.5)),
            ],
            if (c.ls('what_you_get').isNotEmpty) ...[
              const SizedBox(height: 16),
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('What you get', style: TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 8),
                  for (final w in c.ls('what_you_get'))
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Icon(Icons.check_circle_rounded, color: AppColors.green, size: 19),
                        const SizedBox(width: 8),
                        Expanded(child: Text(w, style: const TextStyle(fontSize: 14))),
                      ]),
                    ),
                ]),
              ),
            ],
            if (!enrolled && all.isNotEmpty) ...[
              const SectionTitle('Choose your batch'),
              for (final b in all) _BatchOption(b: b, selected: b.i('id') == _batchId, onTap: () => setState(() => _batchId = b.i('id'))),
            ],
            if (c.l('teachers').isNotEmpty) ...[
              const SectionTitle('Teachers'),
              Wrap(spacing: 10, runSpacing: 10, children: [
                for (final t in c.l('teachers'))
                  Chip(avatar: CircleAvatar(child: Text(t.s('avatar', t.s('name').characters.take(1).toString()))), label: Text(t.s('name'))),
              ]),
            ],
            if (d.l('demo').isNotEmpty) ...[
              const SectionTitle('Free demo'),
              for (final item in d.l('demo'))
                ContentTile(item: item, onTap: () {
                  ref.read(storeRepositoryProvider).track('demo_video', {'course_id': c.i('id'), 'content_id': item.i('id')});
                  context.push(R.content(item.i('id')));
                }),
            ],
            if (c.s('description').isNotEmpty) ...[
              const SectionTitle('About this course'),
              HtmlText(c.s('description')),
            ],
            const SizedBox(height: 16),
          ],
        );
      },
    );
  }
}

class _BatchOption extends StatelessWidget {
  const _BatchOption({required this.b, required this.selected, required this.onTap});
  final Json b;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final open = b.b('is_purchasable');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: selected ? AppColors.lavender : p.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: selected ? AppColors.primary2 : p.line, width: selected ? 2 : 1)),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: open ? onTap : null,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Icon(selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: AppColors.primary2),
              const SizedBox(width: 10),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(b.s('name'), style: const TextStyle(fontWeight: FontWeight.w800)),
                  Text([if (b.sn('starts_at') != null) 'Starts ${b.s('starts_at')}', b.s('validity_text')].join(' · '), style: TextStyle(fontSize: 12.5, color: p.muted)),
                  if (!open)
                    const Padding(padding: EdgeInsets.only(top: 4), child: Tag('Admissions closed', tone: Tone.grey))
                  else if (b['seats_left'] != null && b.i('seats_left') <= 20)
                    Padding(padding: const EdgeInsets.only(top: 4), child: Tag('${b.i('seats_left')} seats left', tone: Tone.peach)),
                ]),
              ),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text(b.s('price_text'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                if (b.sn('mrp_text') != null) Text(b.s('mrp_text'), style: TextStyle(fontSize: 12, color: p.muted, decoration: TextDecoration.lineThrough)),
              ]),
            ]),
          ),
        ),
      ),
    );
  }
}
