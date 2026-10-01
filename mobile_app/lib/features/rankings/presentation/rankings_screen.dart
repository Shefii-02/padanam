import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../home/data/home_repository.dart';
import '../../home/presentation/widgets/home_sections.dart';
import '../data/rankings_repository.dart';

/// Rankings tab: 30-day leaderboard per exam + my test ranks.
class RankingsScreen extends ConsumerStatefulWidget {
  const RankingsScreen({super.key});

  @override
  ConsumerState<RankingsScreen> createState() => _RankingsScreenState();
}

class _RankingsScreenState extends ConsumerState<RankingsScreen> {
  int _tab = 0;

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
                if (context.canPop()) IconButton(tooltip: 'Back', onPressed: () => context.pop(), icon: Icon(Icons.arrow_back_rounded, color: p.brand)),
                const Text('Rankings', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
              ]),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: context.hPad),
              child: SegmentTabs(items: const ['Leaderboard', 'My tests'], selected: _tab, onChanged: (i) => setState(() => _tab = i)),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: _tab == 0
                  ? AsyncView<Json>(
                      value: ref.watch(homeProvider),
                      onRetry: () => ref.invalidate(homeProvider),
                      data: (h) => RefreshIndicator(
                        onRefresh: () => ref.refresh(homeProvider.future),
                        child: ListView(children: [LeaderboardSection(data: h.m('leaderboard'), showHeader: false)]),
                      ),
                    )
                  : AsyncView<Paged>(
                      value: ref.watch(testHistoryProvider),
                      onRetry: () => ref.invalidate(testHistoryProvider),
                      data: (page) => page.items.isEmpty
                          ? const EmptyView(emoji: '🏆', title: 'No tests yet', subtitle: 'Attempt a mock test to get your all-Kerala rank.')
                          : ListView.separated(
                              padding: EdgeInsets.fromLTRB(context.hPad, 4, context.hPad, 24),
                              itemCount: page.items.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 10),
                              itemBuilder: (_, i) {
                                final t = page.items[i];
                                return AppCard(
                                  onTap: () => context.push(R.result(t.s('attempt'))),
                                  child: Row(children: [
                                    IconBox(t['rank'] == null ? '📝' : '🏅', tone: Tone.lav),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text(t.s('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                                        Text(t['score'] == null ? 'Result not published yet' : 'Score ${t.s('score')} / ${t.s('total_marks')}', style: TextStyle(fontSize: 12.5, color: p.muted)),
                                      ]),
                                    ),
                                    if (t['rank'] != null) Tag('Rank ${t.i('rank')}', tone: Tone.mint),
                                  ]),
                                );
                              },
                            ),
                    ),
            ),
          ]),
        ),
      ),
    );
  }
}
