import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/update/update_controller.dart';
import '../../../core/update/update_gate.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/data/user_model.dart';
import '../data/profile_repository.dart';

/// Tab: profile, settings, app update, logout.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    final data = ref.watch(profileProvider);
    final update = ref.watch(updateControllerProvider);
    final lang = ref.watch(authControllerProvider.select((s) => s.language));
    return Scaffold(
      backgroundColor: p.bg,
      appBar: AppBar(
        backgroundColor: p.bg,
        surfaceTintColor: Colors.transparent,
        automaticallyImplyLeading: false,
        title: const Text('Profile', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [IconButton(tooltip: 'Notifications', onPressed: () => context.push(R.notifications), icon: Icon(Icons.notifications_none_rounded, color: p.brand))],
      ),
      body: AsyncView<Json>(
        value: data,
        onRetry: () => ref.invalidate(profileProvider),
        data: (d) {
          final u = d.m('user');
          final user = AppUser(u);
          return RefreshIndicator(
            onRefresh: () => ref.refresh(profileProvider.future),
            child: PageWidth(
              child: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
                AppCard(
                  child: Row(children: [
                    CircleAvatar(radius: 32, backgroundColor: AppColors.lavender, child: Text(user.avatar, style: const TextStyle(fontSize: 30))),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(user.name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                        Text('+91 ${user.phone}', style: TextStyle(color: p.muted)),
                        Text([u.s('district'), if (user.age != null) '${user.age} years'].where((e) => e.isNotEmpty).join(' · '), style: TextStyle(color: p.muted, fontSize: 12.5)),
                      ]),
                    ),
                    IconButton(tooltip: 'Edit name', onPressed: () => _editName(context, ref, user.name), icon: Icon(Icons.edit_outlined, color: p.brand)),
                  ]),
                ),
                const SizedBox(height: 12),
                Row(children: [
                  for (final (i, s) in d.l('stats').indexed) ...[
                    if (i > 0) const SizedBox(width: 10),
                    Expanded(
                      child: AppCard(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Column(children: [
                          Text(s.s('value'), style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: p.brand)),
                          Text(s.s('label'), style: TextStyle(fontSize: 11.5, color: p.muted)),
                        ]),
                      ),
                    ),
                  ],
                ]),
                const SectionTitle('My exams'),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final e in user.exams) ActionChip(label: Text('${e.toUpperCase()} · ${user.targetPosts[e] ?? ''}'), onPressed: () => context.push(R.exam(e))),
                  ActionChip(avatar: const Icon(Icons.add, size: 18), label: const Text('Add exam'), onPressed: () => context.push(R.exams)),
                ]),
                const SectionTitle('Shortcuts'),
                AppCard(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
                  child: DividedColumn(children: [
                    for (final m in d.l('menu'))
                      ListRow(onTap: () => context.open(m.s('route', R.home)), leading: IconBox(m.s('icon')), title: m.s('title'), trailing: Icon(Icons.chevron_right_rounded, color: p.muted)),
                  ]),
                ),
                const SectionTitle('Settings'),
                AppCard(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
                  child: DividedColumn(children: [
                    ListRow(
                      leading: const IconBox('🅰️'),
                      title: 'App language',
                      subtitle: switch (lang) { 'en' => 'English', 'both' => 'Both', _ => 'മലയാളം' },
                      trailing: DropdownButton<String>(
                        value: lang,
                        underline: const SizedBox(),
                        items: const [
                          DropdownMenuItem(value: 'ml', child: Text('മലയാളം')),
                          DropdownMenuItem(value: 'en', child: Text('English')),
                          DropdownMenuItem(value: 'both', child: Text('Both')),
                        ],
                        onChanged: (v) async {
                          if (v == null) return;
                          await ref.read(authControllerProvider.notifier).setLanguage(v);
                          ref.read(profileRepositoryProvider).update({'language': v}).ignore();
                        },
                      ),
                    ),
                    ListRow(
                      leading: const IconBox('⏰'),
                      title: 'Daily reminder',
                      subtitle: u.s('study_slot', 'Evening'),
                      trailing: Switch(
                        value: u.b('reminder', true),
                        onChanged: (v) async {
                          await runAction(context, () => ref.read(profileRepositoryProvider).update({'reminder': v}));
                          ref.invalidate(profileProvider);
                        },
                      ),
                    ),
                    ListRow(
                      onTap: () => showUpdateSheet(context, ref),
                      leading: const IconBox('⬆️'),
                      title: update.checking ? 'Checking for updates…' : 'Check for updates',
                      subtitle: update.info == null
                          ? 'Tap to check'
                          : (update.info!.available ? 'Version ${update.info!.latestVersion} available' : 'You have the latest version (${update.info!.currentVersion})'),
                      trailing: update.info?.available == true ? const Tag('NEW', tone: Tone.red) : Icon(Icons.chevron_right_rounded, color: p.muted),
                    ),
                    ListRow(onTap: () => showSoon(context, 'Help: help@padanam.app'), leading: const IconBox('💬'), title: 'Help & support', trailing: Icon(Icons.chevron_right_rounded, color: p.muted)),
                  ]),
                ),
                const SizedBox(height: 18),
                AppButton('Log out', outlined: true, color: AppColors.red, expand: true, onPressed: () async {
                  final ok = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Log out?'),
                      content: const Text('You can log in again with your mobile number.'),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Log out')),
                      ],
                    ),
                  );
                  if (ok == true) await ref.read(authControllerProvider.notifier).logout();
                }),
              ]),
            ),
          );
        },
      ),
    );
  }

  Future<void> _editName(BuildContext context, WidgetRef ref, String current) async {
    final c = TextEditingController(text: current);
    final name = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit name'),
        content: TextField(controller: c, autofocus: true, textCapitalization: TextCapitalization.words),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: const Text('Save')),
        ],
      ),
    );
    c.dispose();
    if (name == null || name.length < 2 || !context.mounted) return;
    final u = await runAction(context, () => ref.read(profileRepositoryProvider).update({'name': name}), success: 'Name updated');
    if (u != null) {
      ref.read(authControllerProvider.notifier).updateUser(AppUser(u));
      ref.invalidate(profileProvider);
    }
  }
}
