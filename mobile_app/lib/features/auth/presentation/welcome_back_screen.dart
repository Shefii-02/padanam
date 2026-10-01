import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/ui.dart';
import '../application/auth_controller.dart';

class WelcomeBackScreen extends ConsumerWidget {
  const WelcomeBackScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final user = ref.watch(currentUserProvider);
    void goHome([String? then]) {
      ref.read(authControllerProvider.notifier).clearLanding();
      context.go(R.home);
      if (then != null) context.open(then);
    }

    return Scaffold(
      backgroundColor: p.bg,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: ListView(shrinkWrap: true, padding: const EdgeInsets.all(24), children: [
              Center(
                child: CircleAvatar(radius: 48, backgroundColor: AppColors.lavender, child: Text(user?.initials ?? 'ME', style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: AppColors.primary))),
              ),
              const SizedBox(height: 22),
              Text('Welcome back, ${user?.firstName ?? 'friend'} 👋', textAlign: TextAlign.center, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text('Pick up where you left off', textAlign: TextAlign.center, style: TextStyle(color: p.muted)),
              const SizedBox(height: 22),
              AppCard(
                onTap: () => goHome(R.courses),
                child: const ListRow(leading: IconBox('🎓', tone: Tone.peach, size: 48), title: 'My courses', subtitle: 'Continue your classes and notes', trailing: Icon(Icons.play_arrow_rounded), padding: EdgeInsets.zero),
              ),
              const SizedBox(height: 10),
              AppCard(
                onTap: () => goHome(R.tests),
                child: const ListRow(leading: IconBox('📝', tone: Tone.lav, size: 48), title: 'Mock tests', subtitle: 'Attempt a test and see your rank', trailing: Icon(Icons.chevron_right_rounded), padding: EdgeInsets.zero),
              ),
              const SizedBox(height: 24),
              AppButton('Go to home', expand: true, onPressed: () => goHome()),
            ]),
          ),
        ),
      ),
    );
  }
}
