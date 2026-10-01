import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../../splash/data/app_repository.dart';

class LanguageScreen extends ConsumerWidget {
  const LanguageScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final lang = ref.watch(authControllerProvider.select((s) => s.language));
    final cfg = ref.watch(appConfigProvider);
    return SubPage(
      title: '',
      onLeading: () => context.go(R.onboarding),
      footer: AppButton('Continue', expand: true, onPressed: () async {
        await ref.read(authControllerProvider.notifier).finishOnboarding();
        if (context.mounted) context.go(R.login);
      }),
      body: AsyncView(
        value: cfg,
        onRetry: () => ref.invalidate(appConfigProvider),
        data: (c) => ListView(padding: EdgeInsets.fromLTRB(context.hPad, 4, context.hPad, 24), children: [
          const Text('Choose your language', style: TextStyle(fontSize: 25, fontWeight: FontWeight.w800)),
          Text('ഭാഷ തിരഞ്ഞെടുക്കുക', style: TextStyle(fontSize: 19, color: p.muted, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text('You can change this any time in settings.', style: TextStyle(color: p.muted)),
          const SizedBox(height: 20),
          for (final l in c.l('languages'))
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _LangCard(
                badge: '${l['badge']}',
                title: '${l['label']}',
                subtitle: '${l['subtitle']}',
                selected: lang == l['code'],
                onTap: () => ref.read(authControllerProvider.notifier).setLanguage('${l['code']}'),
              ),
            ),
        ]),
      ),
    );
  }
}

class _LangCard extends StatelessWidget {
  const _LangCard({required this.badge, required this.title, required this.subtitle, required this.selected, required this.onTap});
  final String badge, title, subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary2.withValues(alpha: 0.06) : p.card,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: selected ? AppColors.primary2 : p.line, width: 1.5),
        ),
        child: Row(children: [
          IconBox(badge, tone: Tone.lav, size: 52, radius: 16),
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
              Text(subtitle, style: TextStyle(fontSize: 12.5, color: p.muted)),
            ]),
          ),
          Icon(selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: selected ? AppColors.primary2 : p.line),
        ]),
      ),
    );
  }
}
