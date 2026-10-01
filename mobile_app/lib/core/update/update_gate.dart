import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../router/nav_key.dart';
import '../theme/app_theme.dart';
import '../widgets/ui.dart';
import 'update_controller.dart';

/// Wraps the whole app (MaterialApp.router builder):
///  • force update → blocking full screen
///  • optional update → dismissible card at the bottom
///  • background download finished → "Restart to update" card
class UpdateGate extends ConsumerWidget {
  const UpdateGate({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(updateControllerProvider);
    final info = s.info;
    return Stack(children: [
      child,
      if (info != null && info.available && info.force) Positioned.fill(child: _ForceUpdate(info: info)),
      if (s.readyToInstall)
        _BottomCard(
          emoji: '✅',
          title: 'Update downloaded',
          body: 'Restart Padanam to finish installing ${info?.latestVersion ?? ''}.',
          primary: 'Restart',
          onPrimary: () => ref.read(updateControllerProvider.notifier).startUpdate(),
        )
      else if (s.showPrompt && !(info?.force ?? false))
        _BottomCard(
          emoji: s.downloading ? '⬇️' : '🚀',
          title: s.downloading ? 'Downloading update…' : '${info!.title} · v${info.latestVersion}',
          body: s.downloading ? 'You can keep studying. We will let you know when it is ready.' : info!.message,
          primary: s.downloading ? null : 'Update',
          onPrimary: () => ref.read(updateControllerProvider.notifier).startUpdate(),
          onLater: () => ref.read(updateControllerProvider.notifier).later(),
          onDetails: s.downloading
              ? null
              : () {
                  final ctx = rootNavigatorKey.currentContext;
                  if (ctx != null) showUpdateSheet(ctx, ref);
                },
        ),
    ]);
  }
}

class _BottomCard extends StatelessWidget {
  const _BottomCard({required this.emoji, required this.title, required this.body, this.primary, this.onPrimary, this.onLater, this.onDetails});
  final String emoji, title, body;
  final String? primary;
  final VoidCallback? onPrimary, onLater, onDetails;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final bottom = MediaQuery.paddingOf(context).bottom + 84;
    return Positioned(
      left: 12,
      right: 12,
      bottom: bottom,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Material(
            elevation: 8,
            color: p.card,
            borderRadius: BorderRadius.circular(18),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
              child: Row(children: [
                Text(emoji, style: const TextStyle(fontSize: 26)),
                const SizedBox(width: 12),
                Expanded(
                  child: GestureDetector(
                    onTap: onDetails,
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                      Text(title, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: p.ink)),
                      Text(body, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: p.muted)),
                    ]),
                  ),
                ),
                if (onLater != null) TextButton(onPressed: onLater, child: const Text('Later')),
                if (primary != null) AppButton(primary!, small: true, onPressed: onPrimary),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

class _ForceUpdate extends ConsumerWidget {
  const _ForceUpdate({required this.info});
  final UpdateInfo info;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return Material(
      color: p.bg,
      child: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Text('🚀', style: TextStyle(fontSize: 64)),
                const SizedBox(height: 12),
                Text('Update required', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: p.ink)),
                const SizedBox(height: 6),
                Text('Version ${info.latestVersion} is needed to keep using Padanam. You have ${info.currentVersion}.',
                    textAlign: TextAlign.center, style: TextStyle(color: p.muted, height: 1.5)),
                const SizedBox(height: 16),
                for (final c in info.changelog)
                  Padding(padding: const EdgeInsets.only(bottom: 6), child: Row(children: [const Text('✓  '), Expanded(child: Text(c, style: TextStyle(color: p.ink)))])),
                const SizedBox(height: 18),
                AppButton('Update now', expand: true, onPressed: () => ref.read(updateControllerProvider.notifier).startUpdate()),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// Details sheet (from the banner or Profile → Check for updates).
Future<void> showUpdateSheet(BuildContext context, WidgetRef ref) async {
  final ctrl = ref.read(updateControllerProvider.notifier);
  await ctrl.check(manual: true);
  if (!context.mounted) return;
  final s = ref.read(updateControllerProvider);
  final info = s.info;
  await showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (ctx) {
      final p = ctx.palette;
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(info == null ? 'Could not check for updates' : (info.available ? 'Version ${info.latestVersion} is available' : "You're on the latest version"),
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text(info == null ? (s.error ?? '') : 'Installed: ${info.currentVersion} (build ${info.currentBuild})', style: TextStyle(color: p.muted)),
            if (info != null && info.available) ...[
              const SizedBox(height: 14),
              for (final c in info.changelog) Padding(padding: const EdgeInsets.only(bottom: 6), child: Text('•  $c')),
              const SizedBox(height: 14),
              AppButton(s.readyToInstall ? 'Restart to update' : 'Update now', expand: true, onPressed: () {
                Navigator.pop(ctx);
                ctrl.startUpdate();
              }),
            ],
          ]),
        ),
      );
    },
  );
}
