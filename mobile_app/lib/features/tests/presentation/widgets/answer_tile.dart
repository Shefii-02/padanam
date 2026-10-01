import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';
import '../../../../core/widgets/ui.dart';

enum AnswerState { idle, selected, right, wrong }

/// One multiple-choice option (A/B/C/D).
class AnswerTile extends StatelessWidget {
  const AnswerTile({super.key, required this.letter, required this.text, required this.state, this.onTap});
  final String letter, text;
  final AnswerState state;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final (Color border, Color keyBg, Color keyFg, Color bg) = switch (state) {
      AnswerState.idle => (p.line, p.sheet, p.ink, p.card),
      AnswerState.selected => (AppColors.primary2, AppColors.primary2, Colors.white, p.card),
      AnswerState.right => (AppColors.green, AppColors.green, Colors.white, AppColors.green.withValues(alpha: 0.08)),
      AnswerState.wrong => (AppColors.red, AppColors.red, Colors.white, AppColors.red.withValues(alpha: 0.08)),
    };
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Semantics(
        button: true,
        selected: state != AnswerState.idle,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: bg,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: border, width: 1.5),
            ),
            child: Row(children: [
              Container(
                width: 30,
                height: 30,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: keyBg, borderRadius: BorderRadius.circular(10)),
                child: Text(letter, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: keyFg)),
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(text, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600))),
            ]),
          ),
        ),
      ),
    );
  }
}

class TimerBadge extends StatelessWidget {
  const TimerBadge(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: AppColors.peach, borderRadius: BorderRadius.circular(10)),
        child: Text('⏱ $text',
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: AppColors.peachInk)),
      );
}

/// Card used by mock-test and previous-paper lists.
class TestCard extends StatelessWidget {
  const TestCard({super.key, required this.tags, required this.title, required this.meta, required this.bottom, this.trailingTop});
  final List<Widget> tags;
  final String title;
  final List<String> meta;
  final List<Widget> bottom;
  final Widget? trailingTop;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return AppCard(
      radius: 18,
      margin: const EdgeInsets.only(bottom: 10),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Wrap(spacing: 6, runSpacing: 6, children: tags)),
          if (trailingTop != null) trailingTop!,
        ]),
        const SizedBox(height: 8),
        Text(title, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, height: 1.35)),
        const SizedBox(height: 4),
        Wrap(spacing: 10, runSpacing: 2, children: [
          for (final m in meta) Text(m, style: TextStyle(fontSize: 12, color: p.muted)),
        ]),
        const SizedBox(height: 12),
        Row(children: bottom),
      ]),
    );
  }
}
