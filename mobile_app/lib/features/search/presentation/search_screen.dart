import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';

/// Searches courses and articles together.
final searchProvider = FutureProvider.autoDispose.family<(List<Json>, List<Json>), String>((ref, q) async {
  final api = ref.watch(apiClientProvider);
  final r = await Future.wait([
    api.page('/public/courses', query: {'search': q}),
    api.page('/public/articles', query: {'search': q}),
  ]);
  return (r[0].items, r[1].items);
});

class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: TextField(
          autofocus: true,
          textInputAction: TextInputAction.search,
          decoration: const InputDecoration(hintText: 'Search courses, articles…', border: InputBorder.none, filled: false),
          onSubmitted: (v) => setState(() => _q = v.trim()),
        ),
      ),
      body: _q.length < 2
          ? const EmptyView(emoji: '🔎', title: 'Search Padanam', subtitle: 'Try “LDC”, “KTET”, “Renaissance”…')
          : AsyncView<(List<Json>, List<Json>)>(
              value: ref.watch(searchProvider(_q)),
              onRetry: () => ref.invalidate(searchProvider(_q)),
              data: (r) {
                final (courses, articles) = r;
                if (courses.isEmpty && articles.isEmpty) return EmptyView(emoji: '🤷', title: 'Nothing found for “$_q”');
                return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 24), children: [
                  if (courses.isNotEmpty) const SectionTitle('Courses'),
                  for (final c in courses)
                    ListRow(
                      leading: const IconBox('🎓', tone: Tone.lav),
                      title: c.s('title'),
                      subtitle: [c.s('category'), c.s('price_text')].where((x) => x.isNotEmpty).join(' · '),
                      onTap: () => context.push(R.course(c.s('slug', '${c.i('id')}'))),
                    ),
                  if (articles.isNotEmpty) const SectionTitle('Articles'),
                  for (final a in articles)
                    ListRow(
                      leading: const IconBox('📰', tone: Tone.peach),
                      title: a.s('title'),
                      subtitle: a.s('published_at').split('T').first,
                      onTap: () => context.push(R.article(a.s('slug'))),
                      trailing: Icon(Icons.chevron_right_rounded, color: p.muted),
                    ),
                ]);
              },
            ),
    );
  }
}
