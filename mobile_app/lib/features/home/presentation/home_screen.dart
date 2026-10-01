import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/format.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/home_repository.dart';
import 'widgets/home_sections.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final home = ref.watch(homeProvider);
    return Scaffold(
      backgroundColor: p.bg,
      body: SafeArea(
        bottom: false,
        child: AsyncView<Json>(
          value: home,
          onRetry: () => ref.invalidate(homeProvider),
          data: (h) => RefreshIndicator(
            onRefresh: () => ref.refresh(homeProvider.future),
            child: PageWidth(
              maxWidth: 960,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverAppBar(
                    pinned: true,
                    automaticallyImplyLeading: false,
                    backgroundColor: p.bg,
                    surfaceTintColor: Colors.transparent,
                    toolbarHeight: 64,
                    titleSpacing: context.hPad,
                    title: Row(mainAxisSize: MainAxisSize.min, children: [
                      const CircleAvatar(radius: 20, backgroundColor: AppColors.primary, child: Text('പ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800))),
                      const SizedBox(width: 8),
                      Text('Padanam', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: p.brand)),
                    ]),
                    actions: [
                      IconButton(tooltip: 'Search', onPressed: () => context.push(R.search), icon: Icon(Icons.search_rounded, color: p.brand, size: 27)),
                      IconButton(
                        tooltip: 'Notifications',
                        onPressed: () => context.push(R.notifications),
                        icon: Badge(
                          isLabelVisible: h.i('unread_notifications') > 0,
                          label: Text('${h.i('unread_notifications')}'),
                          backgroundColor: AppColors.red,
                          child: Icon(Icons.notifications_rounded, color: p.brand, size: 27),
                        ),
                      ),
                      Padding(
                        padding: EdgeInsets.only(right: context.hPad - 4, left: 4),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: () => context.go(R.profile),
                          child: CircleAvatar(radius: 21, backgroundColor: p.sheet, child: Text(h.s('initials'), style: TextStyle(color: p.brand, fontWeight: FontWeight.w700))),
                        ),
                      ),
                    ],
                  ),
                  SliverList.list(children: [
                    Padding(
                      padding: EdgeInsets.fromLTRB(context.hPad, 4, context.hPad, 14),
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('${greetingFor(DateTime.now())}, ${h.s('greeting_name')} 👋',
                                maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                            Text(h.s('target'), style: TextStyle(fontSize: 13, color: p.muted)),
                          ]),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
                          decoration: BoxDecoration(color: AppColors.peach, borderRadius: BorderRadius.circular(20)),
                          child: Text('🔥 ${h.i('streak_days')}-day streak', style: const TextStyle(color: AppColors.peachInk, fontWeight: FontWeight.w700, fontSize: 12)),
                        ),
                      ]),
                    ),
                    AnnouncementCarousel(items: h.l('announcements')),
                    const SizedBox(height: 14),
                    CategoriesSheet(categories: h.l('categories'), more: h.i('more_categories_count')),
                    ContinueCard(lesson: h.m('continue_lesson')),
                    NewCourses(courses: h.l('new_courses')),
                    LeaderboardSection(data: h.m('leaderboard')),
                    Achievers(stats: h.l('achiever_stats'), items: h.l('achievers')),
                    EventsSection(events: h.l('events')),
                    StudyTools(tools: h.l('study_tools')),
                    PromoCard(promo: h.m('promo')),
                    const SizedBox(height: 28),
                  ]),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
