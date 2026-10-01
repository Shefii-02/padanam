import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/html_text.dart';
import '../../../core/widgets/ui.dart';
import '../data/learning_repository.dart';

final articleProvider = FutureProvider.autoDispose.family<Json, String>((ref, slug) => ref.watch(learningRepositoryProvider).article(slug));

/// Article / current affairs (deep link padanam.app/a/{slug}).
class ArticleScreen extends ConsumerWidget {
  const ArticleScreen({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return AsyncView<Json>(
      value: ref.watch(articleProvider(slug)),
      onRetry: () => ref.invalidate(articleProvider(slug)),
      loading: const Scaffold(body: LoadingView()),
      data: (a) => SubPage(
        title: a.s('category', 'Article'),
        actions: [
          IconButton(tooltip: 'Share', icon: Icon(Icons.share_rounded, color: p.brand), onPressed: () => SharePlus.instance.share(ShareParams(text: '${a.s('title')}\n${a.s('share_url')}'))),
        ],
        children: [
          if (a.sn('cover_url') != null) ClipRRect(borderRadius: BorderRadius.circular(16), child: Image.network(a.s('cover_url'), fit: BoxFit.cover)),
          const SizedBox(height: 12),
          Text(a.s('title'), style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800, height: 1.3)),
          const SizedBox(height: 6),
          Text([a.s('published_at').split('T').first, if (a.i('reading_min') > 0) '${a.i('reading_min')} min read'].join(' · '), style: TextStyle(fontSize: 12.5, color: p.muted)),
          const SizedBox(height: 14),
          HtmlText(a.s('body')),
          if (a.b('locked')) ...[
            const SizedBox(height: 16),
            const EmptyNote('This is a premium article. Join a course to read the full article.'),
          ],
        ],
      ),
    );
  }
}
