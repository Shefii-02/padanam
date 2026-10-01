import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';

class _Tab {
  const _Tab(this.icon, this.selected, this.label);
  final IconData icon, selected;
  final String label;
}

const _tabs = [
  _Tab(Icons.home_outlined, Icons.home_rounded, 'Home'),
  _Tab(Icons.school_outlined, Icons.school_rounded, 'Courses'),
  _Tab(Icons.assignment_outlined, Icons.assignment_rounded, 'Tests'),
  _Tab(Icons.chat_bubble_outline_rounded, Icons.chat_bubble_rounded, 'Chat'),
  _Tab(Icons.person_outline_rounded, Icons.person_rounded, 'Profile'),
];

/// Adaptive navigation:
///   < 840 px  → bottom bar (phones)
///   ≥ 840 px  → navigation rail (tablets, web, desktop); extended with labels ≥ 1200 px
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.shell});
  final StatefulNavigationShell shell;

  void _go(int i) => shell.goBranch(i, initialLocation: i == shell.currentIndex);

  @override
  Widget build(BuildContext context) {
    final w = MediaQuery.sizeOf(context).width;
    final p = context.palette;

    if (w >= 840) {
      final extended = w >= 1200;
      return Scaffold(
        backgroundColor: p.bg,
        body: Row(children: [
          NavigationRail(
            backgroundColor: p.card,
            extended: extended,
            minExtendedWidth: 220,
            selectedIndex: shell.currentIndex,
            onDestinationSelected: _go,
            labelType: extended ? NavigationRailLabelType.none : NavigationRailLabelType.all,
            indicatorColor: AppColors.primary,
            selectedIconTheme: const IconThemeData(color: Colors.white),
            leading: Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const CircleAvatar(radius: 20, backgroundColor: AppColors.primary, child: Text('പ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800))),
                if (extended) ...[
                  const SizedBox(width: 10),
                  Text('Padanam', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: p.brand)),
                ],
              ]),
            ),
            destinations: [
              for (final t in _tabs)
                NavigationRailDestination(icon: Icon(t.icon), selectedIcon: Icon(t.selected), label: Text(t.label, style: const TextStyle(fontWeight: FontWeight.w700))),
            ],
          ),
          VerticalDivider(width: 1, color: p.line),
          Expanded(child: shell),
        ]),
      );
    }

    return Scaffold(
      backgroundColor: p.bg,
      body: shell,
      bottomNavigationBar: NavigationBar(
        backgroundColor: p.card,
        indicatorColor: AppColors.primary,
        height: 68,
        selectedIndex: shell.currentIndex,
        onDestinationSelected: _go,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: [
          for (final t in _tabs)
            NavigationDestination(icon: Icon(t.icon, color: p.muted), selectedIcon: Icon(t.selected, color: Colors.white), label: t.label),
        ],
      ),
    );
  }
}
