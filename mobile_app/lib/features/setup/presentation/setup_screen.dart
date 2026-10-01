import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../application/setup_controller.dart';
import '../data/setup_repository.dart';
import 'steps/goal_steps.dart';
import 'steps/plan_steps.dart';
import 'steps/profile_steps.dart';

/// New-user profile setup: 9 steps, one topic per screen.
class SetupScreen extends ConsumerStatefulWidget {
  const SetupScreen({super.key});

  @override
  ConsumerState<SetupScreen> createState() => _SetupScreenState();
}

class _SetupScreenState extends ConsumerState<SetupScreen> {
  bool _busy = false;

  Future<void> _submit() async {
    setState(() => _busy = true);
    try {
      await ref.read(setupControllerProvider.notifier).submit(); // router moves to /setup/plan
    } on ApiException catch (e) {
      if (mounted) showSoon(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final opts = ref.watch(setupOptionsProvider);
    final d = ref.watch(setupControllerProvider);
    final ctrl = ref.read(setupControllerProvider.notifier);
    const total = SetupController.totalSteps;
    final last = d.step == total - 1;

    return PopScope(
      canPop: d.step == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) ctrl.back();
      },
      child: Scaffold(
        backgroundColor: p.bg,
        appBar: AppBar(
          backgroundColor: p.bg,
          surfaceTintColor: Colors.transparent,
          leading: IconButton(
            tooltip: d.step == 0 ? 'Log out' : 'Back',
            icon: Icon(d.step == 0 ? Icons.logout_rounded : Icons.arrow_back_rounded, color: p.brand),
            onPressed: d.step == 0 ? () => ref.read(authControllerProvider.notifier).logout() : ctrl.back,
          ),
          title: Text('Step ${d.step + 1} of $total', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: p.muted)),
          centerTitle: false,
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(10),
            child: PageWidth(
              child: Padding(
                padding: EdgeInsets.fromLTRB(context.hPad, 0, context.hPad, 6),
                child: Row(children: [
                  for (var i = 0; i < total; i++) ...[
                    if (i > 0) const SizedBox(width: 5),
                    Expanded(
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 300),
                        height: 5,
                        decoration: BoxDecoration(
                          color: i < d.step ? AppColors.green : (i == d.step ? AppColors.primary2 : p.line),
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                    ),
                  ],
                ]),
              ),
            ),
          ),
        ),
        body: AsyncView<Json>(
          value: opts,
          onRetry: () => ref.invalidate(setupOptionsProvider),
          data: (o) => PageWidth(
            maxWidth: 620,
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 220),
              child: ListView(
                key: ValueKey(d.step),
                padding: EdgeInsets.fromLTRB(context.hPad, 12, context.hPad, 24),
                children: switch (d.step) {
                  0 => avatarStep(context, ref, o),
                  1 => nameStep(context, ref, o),
                  2 => ageStep(context, ref, o),
                  3 => placeStep(context, ref, o),
                  4 => qualificationStep(context, ref, o),
                  5 => examsStep(context, ref, o),
                  6 => postsStep(context, ref, o),
                  7 => aimStep(context, ref, o),
                  _ => timeStep(context, ref, o),
                },
              ),
            ),
          ),
        ),
        bottomNavigationBar: FooterBar(
          child: AppButton(
            last ? (_busy ? 'Building your plan…' : 'Create my plan') : 'Continue',
            expand: true,
            onPressed: !d.canContinue(d.step) || _busy ? null : (last ? _submit : ctrl.next),
          ),
        ),
      ),
    );
  }
}

/// Shared heading for every step.
List<Widget> stepHeader(BuildContext context, String title, [String? lead]) => [
      Text(title, style: const TextStyle(fontSize: 25, fontWeight: FontWeight.w800, height: 1.25)),
      if (lead != null) ...[
        const SizedBox(height: 6),
        Text(lead, style: TextStyle(fontSize: 14.5, color: context.palette.muted, height: 1.5)),
      ],
      const SizedBox(height: 18),
    ];

/// Selectable tile used across steps.
class ChoiceTile extends StatelessWidget {
  const ChoiceTile({super.key, required this.title, this.subtitle, this.emoji, required this.selected, this.onTap, this.badge, this.disabled = false});
  final String title;
  final String? subtitle, emoji, badge;
  final bool selected, disabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Opacity(
      opacity: disabled ? 0.45 : 1,
      child: InkWell(
        onTap: disabled ? null : onTap,
        borderRadius: BorderRadius.circular(18),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: selected ? AppColors.primary2.withValues(alpha: 0.07) : p.card,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: selected ? AppColors.primary2 : p.line, width: 1.5),
          ),
          child: Row(children: [
            if (emoji != null) ...[IconBox(emoji!, size: 44), const SizedBox(width: 12)],
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (badge != null) Padding(padding: const EdgeInsets.only(bottom: 4), child: Tag(badge!, tone: Tone.mint)),
                Text(title, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700)),
                if (subtitle != null) Text(subtitle!, style: TextStyle(fontSize: 12, color: p.muted)),
              ]),
            ),
            Icon(selected ? Icons.check_circle_rounded : Icons.circle_outlined, color: selected ? AppColors.primary2 : p.line),
          ]),
        ),
      ),
    );
  }
}

/// Row of equal buttons (hours, slots, gender…).
class Segmented extends StatelessWidget {
  const Segmented({super.key, required this.items, required this.selected, required this.onTap});
  final List<String> items;
  final int selected;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Row(children: [
      for (var i = 0; i < items.length; i++) ...[
        if (i > 0) const SizedBox(width: 8),
        Expanded(
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: () => onTap(i),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: i == selected ? AppColors.primary2.withValues(alpha: 0.08) : p.card,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: i == selected ? AppColors.primary2 : p.line, width: 1.5),
              ),
              child: Text(items[i],
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: i == selected ? AppColors.primary2 : p.ink)),
            ),
          ),
        ),
      ],
    ]);
  }
}

Widget fieldLabel(String t, [String? hint]) => Padding(
      padding: const EdgeInsets.only(bottom: 6, top: 4),
      child: Text.rich(TextSpan(children: [
        TextSpan(text: t, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        if (hint != null) TextSpan(text: '  $hint', style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 12)),
      ])),
    );

InputDecoration inputDeco(BuildContext context, {String? hint, Widget? prefix}) => InputDecoration(
      hintText: hint,
      prefixIcon: prefix,
      filled: true,
      fillColor: context.palette.card,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    );
