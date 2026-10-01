import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';

/// Articles & current affairs (free ones for everyone, premium for enrolled students).
final articlesProvider = FutureProvider.autoDispose.family<Paged, String?>(
  (ref, search) => ref.watch(apiClientProvider).page('/public/articles', query: {'search': search}),
);

class CurrentAffairsScreen extends ConsumerStatefulWidget {
  const CurrentAffairsScreen({super.key, this.initialType = 0});
  final int initialType;

  @override
  ConsumerState<CurrentAffairsScreen> createState() => _CurrentAffairsScreenState();
}

class _CurrentAffairsScreenState extends ConsumerState<CurrentAffairsScreen> {
  String? _q;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return SubPage(
      title: 'Current affairs & articles',
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            textInputAction: TextInputAction.search,
            decoration: const InputDecoration(hintText: 'Search articles', prefixIcon: Icon(Icons.search_rounded)),
            onSubmitted: (v) => setState(() => _q = v.trim().isEmpty ? null : v.trim()),
          ),
        ),
        Expanded(
          child: AsyncView<Paged>(
            value: ref.watch(articlesProvider(_q)),
            onRetry: () => ref.invalidate(articlesProvider(_q)),
            data: (page) => page.items.isEmpty
                ? const EmptyView(emoji: '📰', title: 'No articles yet')
                : RefreshIndicator(
                    onRefresh: () => ref.refresh(articlesProvider(_q).future),
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                      itemCount: page.items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (_, i) {
                        final a = page.items[i];
                        return AppCard(
                          onTap: () => context.push(R.article(a.s('slug'))),
                          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            if (a.sn('cover_url') != null) ...[
                              ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(a.s('cover_url'), width: 84, height: 84, fit: BoxFit.cover)),
                              const SizedBox(width: 12),
                            ],
                            Expanded(
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Row(children: [
                                  if (a.s('category').isNotEmpty) Tag(a.s('category')),
                                  if (a.s('access') == 'premium') ...[const SizedBox(width: 6), const Tag('Premium', tone: Tone.purple)],
                                ]),
                                const SizedBox(height: 6),
                                Text(a.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)),
                                const SizedBox(height: 3),
                                Text(a.s('excerpt'), maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, color: p.muted)),
                                const SizedBox(height: 4),
                                Text('${a.s('published_at').split('T').first} · ${a.i('reading_min', 2)} min read', style: TextStyle(fontSize: 11.5, color: p.muted)),
                              ]),
                            ),
                          ]),
                        );
                      },
                    ),
                  ),
          ),
        ),
      ]),
    );
  }
}
