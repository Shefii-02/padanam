import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../application/setup_controller.dart';
import '../data/setup_repository.dart';

/// Plan from POST /profile/setup (or GET /profile if the app was reopened).
final _planProvider = FutureProvider<Json>((ref) async {
  final fresh = ref.watch(setupPlanProvider);
  if (fresh != null) return fresh;
  return (await ref.watch(setupRepositoryProvider).profile()).m('plan');
});

class PlanReadyScreen extends ConsumerWidget {
  const PlanReadyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final user = ref.watch(currentUserProvider);
    final draft = ref.watch(setupControllerProvider);
    final plan = ref.watch(_planProvider);

    void start(String? examId) {
      ref.read(authControllerProvider.notifier).clearLanding();
      context.go(R.home);
      if (examId != null) context.push(R.exam(examId));
    }

    return Scaffold(
      backgroundColor: p.bg,
      body: AsyncView<Json>(
        value: plan,
        onRetry: () => ref.invalidate(_planProvider),
        data: (pl) {
          final main = pl.m('main_exam');
          return CustomScrollView(slivers: [
            SliverToBoxAdapter(
              child: Container(
                padding: EdgeInsets.fromLTRB(20, MediaQuery.paddingOf(context).top + 24, 20, 28),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: [Color(0xFF0F7A55), Color(0xFF27B07C)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                  borderRadius: BorderRadius.vertical(bottom: Radius.circular(34)),
                ),
                child: Column(children: [
                  const Text('🎉', style: TextStyle(fontSize: 54)),
                  const SizedBox(height: 6),
                  Text("You're all set, ${user?.firstName ?? 'friend'}!", textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 23, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text('Main target: ${main.s('name')} · ${main.s('post')}', style: const TextStyle(color: Colors.white70)),
                ]),
              ),
            ),
            SliverToBoxAdapter(
              child: PageWidth(
                maxWidth: 620,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Row(children: [
                      _Kpi('${pl.i('days_to_exam')}', 'days to exam'),
                      const SizedBox(width: 8),
                      _Kpi('${pl.i('hours_per_week')}', 'hrs / week'),
                      const SizedBox(width: 8),
                      _Kpi('${pl.i('topics_planned')}', 'topics planned'),
                    ]),
                    const SizedBox(height: 14),
                    AppCard(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
                      child: DividedColumn(children: [
                        ListRow(
                          leading: draft.photo != null
                              ? ClipOval(child: Image.memory(draft.photo!, width: 40, height: 40, fit: BoxFit.cover))
                              : IconBox(user?.avatar ?? draft.avatar, tone: Tone.lav, size: 40),
                          title: user?.name ?? draft.name,
                          subtitle: '${user?.json.s('gender') ?? draft.gender ?? ''} · ${user?.age ?? draft.age} years',
                        ),
                        ListRow(
                          leading: const IconBox('📍', tone: Tone.mint, size: 40),
                          title: user?.json.s('district').isNotEmpty == true ? user!.json.s('district') : (draft.district ?? draft.state),
                          subtitle: [draft.town, draft.pincode].where((e) => e.isNotEmpty).join(' · '),
                        ),
                        for (final id in user?.exams ?? draft.exams)
                          ListRow(
                            leading: const IconBox('🎯', tone: Tone.peach, size: 40),
                            title: '${id.toUpperCase()} · ${user?.targetPosts[id] ?? draft.posts[id] ?? ''}',
                            subtitle: 'Target',
                          ),
                        ListRow(
                          leading: const IconBox('⏰', tone: Tone.purple, size: 40),
                          title: '${draft.aim} · ${draft.hours} hrs/day',
                          subtitle: '${draft.level} · ${draft.slot}${draft.reminder ? ' reminder on' : ''}',
                        ),
                      ]),
                    ),
                    const SizedBox(height: 12),
                    AppCard(
                      color: AppColors.peach,
                      bordered: false,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Week 1 plan', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF5A2E00))),
                        const SizedBox(height: 6),
                        for (final w in pl.ls('week1')) Text('•  $w', style: const TextStyle(color: Color(0xFF5A2E00), height: 1.6)),
                      ]),
                    ),
                    const SizedBox(height: 18),
                    AppButton('Start learning ${main.s('name')}', expand: true, onPressed: () => start(main.sn('id'))),
                    TextButton(onPressed: () => start(null), child: const Text('Go to home')),
                  ]),
                ),
              ),
            ),
          ]);
        },
      ),
    );
  }
}

class _Kpi extends StatelessWidget {
  const _Kpi(this.value, this.label);
  final String value, label;

  @override
  Widget build(BuildContext context) => Expanded(
        child: AppCard(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Column(children: [
            Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: context.palette.brand)),
            Text(label, style: TextStyle(fontSize: 11, color: context.palette.muted)),
          ]),
        ),
      );
}
