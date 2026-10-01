// Shared building blocks for all inner (exam-category) pages.
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/responsive.dart';
import '../theme/app_theme.dart';

const double kPageMaxWidth = 720;

/// Limits content width on tablets / web and centres it.
class PageWidth extends StatelessWidget {
  const PageWidth({super.key, required this.child, this.maxWidth = kPageMaxWidth});
  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) => Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(constraints: BoxConstraints(maxWidth: maxWidth), child: child),
      );
}

/// Scaffold used by every inner page: back button, title + subtitle, actions, footer.
class SubPage extends StatelessWidget {
  const SubPage({
    super.key,
    required this.title,
    this.subtitle,
    this.actions = const [],
    this.children,
    this.body,
    this.footer,
    this.leadingIcon = Icons.arrow_back_rounded,
    this.onLeading,
    this.floatingActionButton,
  }) : assert(children != null || body != null);

  final String title;
  final String? subtitle;
  final List<Widget> actions;
  final List<Widget>? children;
  final Widget? body;
  final Widget? footer;
  final IconData leadingIcon;
  final VoidCallback? onLeading;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final content = body ??
        ListView(
          padding: EdgeInsets.fromLTRB(
              context.hPad, 4, context.hPad, floatingActionButton != null ? 96 : 24),
          children: children!,
        );
    return Scaffold(
      backgroundColor: p.bg,
      // With a footer, the footer itself lifts above the keyboard (see FooterBar).
      resizeToAvoidBottomInset: footer == null,
      appBar: AppBar(
        backgroundColor: p.bg,
        surfaceTintColor: Colors.transparent,
        scrolledUnderElevation: 0,
        elevation: 0,
        leading: IconButton(
          tooltip: 'Back',
          icon: Icon(leadingIcon, color: p.brand),
          onPressed: onLeading ?? () => context.canPop() ? context.pop() : context.go('/home'),
        ),
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: p.ink)),
            if (subtitle != null)
              Text(subtitle!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: p.muted)),
          ],
        ),
        actions: [...actions, const SizedBox(width: 6)],
      ),
      floatingActionButton: floatingActionButton,
      body: SafeArea(top: false, bottom: footer == null, child: PageWidth(child: content)),
      bottomNavigationBar: footer == null ? null : FooterBar(child: footer!),
    );
  }
}

class FooterBar extends StatelessWidget {
  const FooterBar({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Container(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      decoration: BoxDecoration(color: p.card, border: Border(top: BorderSide(color: p.line))),
      child: SafeArea(
        top: false,
        child: Align(
          alignment: Alignment.topCenter,
          heightFactor: 1,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: kPageMaxWidth),
            child: Padding(padding: const EdgeInsets.fromLTRB(14, 10, 14, 12), child: child),
          ),
        ),
      ),
    );
  }
}

class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.color,
    this.radius = 20,
    this.bordered = true,
    this.onTap,
    this.margin,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final Color? color;
  final double radius;
  final bool bordered;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final r = BorderRadius.circular(radius);
    return Container(
      margin: margin,
      decoration: BoxDecoration(
        color: color ?? p.card,
        borderRadius: r,
        border: bordered ? Border.all(color: p.line) : null,
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          onTap: onTap,
          borderRadius: r,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

// ---------------- Tags / tones ----------------
enum Tone { lav, peach, mint, red, purple, grey }

extension ToneX on Tone {
  Color get bg => switch (this) {
        Tone.lav => AppColors.lavender,
        Tone.peach => AppColors.peach,
        Tone.mint => AppColors.mint,
        Tone.red => const Color(0xFFFCE4E7),
        Tone.purple => const Color(0xFFEFE4FC),
        Tone.grey => const Color(0xFFE8ECF8),
      };
  Color get fg => switch (this) {
        Tone.lav => AppColors.primary,
        Tone.peach => AppColors.peachInk,
        Tone.mint => AppColors.green,
        Tone.red => AppColors.red,
        Tone.purple => const Color(0xFF6A2BC2),
        Tone.grey => const Color(0xFF5E6583),
      };
}

class Tag extends StatelessWidget {
  const Tag(this.text, {super.key, this.tone = Tone.lav});
  final String text;
  final Tone tone;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(8)),
        child: Text(text, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: tone.fg)),
      );
}

class Pill extends StatelessWidget {
  const Pill(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
          color: p.card, borderRadius: BorderRadius.circular(20), border: Border.all(color: p.line)),
      child: Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}

/// Rounded square holding an emoji (placeholder for illustrations).
class IconBox extends StatelessWidget {
  const IconBox(this.emoji, {super.key, this.color, this.tone, this.size = 42, this.radius = 13});
  final String emoji;
  final Color? color;
  final Tone? tone;
  final double size, radius;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: color ?? tone?.bg ?? context.palette.sheet,
          borderRadius: BorderRadius.circular(radius),
        ),
        child: Text(emoji, style: TextStyle(fontSize: size * 0.47)),
      );
}

// ---------------- Selectors ----------------
/// Horizontal chips. Pass [selected] to control it, or leave null for local state.
class ChipBar extends StatefulWidget {
  const ChipBar({super.key, required this.items, this.selected, this.initial = 0, this.onChanged});
  final List<String> items;
  final int? selected;
  final int initial;
  final ValueChanged<int>? onChanged;

  @override
  State<ChipBar> createState() => _ChipBarState();
}

class _ChipBarState extends State<ChipBar> {
  late int _i = widget.initial;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final current = widget.selected ?? _i;
    return SizedBox(
      height: 38 * context.textScale,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: widget.items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final on = i == current;
          return Semantics(
            selected: on,
            button: true,
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                customBorder: const StadiumBorder(),
                onTap: () {
                  if (widget.selected == null) setState(() => _i = i);
                  widget.onChanged?.call(i);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: on ? AppColors.primary : p.card,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: on ? AppColors.primary : p.line),
                  ),
                  child: Text(widget.items[i],
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: on ? Colors.white : p.muted)),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Segmented tabs inside a lavender track.
class SegmentTabs extends StatefulWidget {
  const SegmentTabs({super.key, required this.items, this.selected, this.initial = 0, this.onChanged});
  final List<String> items;
  final int? selected;
  final int initial;
  final ValueChanged<int>? onChanged;

  @override
  State<SegmentTabs> createState() => _SegmentTabsState();
}

class _SegmentTabsState extends State<SegmentTabs> {
  late int _i = widget.initial;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final current = widget.selected ?? _i;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          for (var i = 0; i < widget.items.length; i++)
            Expanded(
              child: Semantics(
                selected: i == current,
                button: true,
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: () {
                    if (widget.selected == null) setState(() => _i = i);
                    widget.onChanged?.call(i);
                  },
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 4),
                    decoration: BoxDecoration(
                      color: i == current ? p.card : Colors.transparent,
                      borderRadius: BorderRadius.circular(10),
                      boxShadow: i == current
                          ? const [BoxShadow(color: Color(0x1F141A33), blurRadius: 3, offset: Offset(0, 1))]
                          : null,
                    ),
                    child: Text(widget.items[i],
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: i == current ? p.ink : p.muted)),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Small two/three-option toggle (e.g. മലയാളം / English).
class LangToggle extends StatelessWidget {
  const LangToggle({super.key, required this.options, required this.selected, required this.onChanged});
  final List<String> options;
  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: p.sheet, borderRadius: BorderRadius.circular(12)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < options.length; i++)
            GestureDetector(
              onTap: () => onChanged(i),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: i == selected ? AppColors.primary : Colors.transparent,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Text(options[i],
                    style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: i == selected ? Colors.white : p.muted)),
              ),
            ),
        ],
      ),
    );
  }
}

// ---------------- Progress ----------------
class ProgressBar extends StatelessWidget {
  const ProgressBar(this.value, {super.key, this.color = AppColors.green, this.height = 7, this.track});
  final double value;
  final Color color;
  final double height;
  final Color? track;

  @override
  Widget build(BuildContext context) => ClipRRect(
        borderRadius: BorderRadius.circular(height),
        child: LinearProgressIndicator(
          value: value.clamp(0.0, 1.0),
          minHeight: height,
          color: color,
          backgroundColor: track ?? context.palette.sheet,
        ),
      );
}

Color ringColorFor(double v) =>
    v >= 0.6 ? AppColors.green : (v >= 0.35 ? AppColors.saffron : AppColors.red);

class ProgressRing extends StatelessWidget {
  const ProgressRing({
    super.key,
    required this.value,
    this.size = 44,
    this.stroke = 5,
    this.color,
    this.track,
    this.child,
  });

  final double value, size, stroke;
  final Color? color, track;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final v = value.clamp(0.0, 1.0);
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _RingPainter(v, color ?? ringColorFor(v), track ?? context.palette.sheet, stroke),
        child: Center(
          child: child ??
              Text('${(v * 100).round()}%',
                  style: TextStyle(fontSize: size * 0.25, fontWeight: FontWeight.w800)),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter(this.value, this.color, this.track, this.stroke);
  final double value, stroke;
  final Color color, track;

  @override
  void paint(Canvas canvas, Size size) {
    final r = (size.shortestSide - stroke) / 2;
    final c = size.center(Offset.zero);
    final base = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..color = track;
    canvas.drawCircle(c, r, base);
    final arc = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round
      ..color = color;
    canvas.drawArc(Rect.fromCircle(center: c, radius: r), -math.pi / 2, 2 * math.pi * value, false, arc);
  }

  @override
  bool shouldRepaint(_RingPainter old) =>
      old.value != value || old.color != color || old.track != track || old.stroke != stroke;
}

// ---------------- Layout helpers ----------------
class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.action, this.onAction, this.trailing});
  final String title;
  final String? action;
  final VoidCallback? onAction;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 22, bottom: 10),
      child: Row(
        children: [
          Expanded(
              child: Text(title, style: const TextStyle(fontSize: 16.5, fontWeight: FontWeight.w800))),
          if (trailing != null)
            Text(trailing!, style: TextStyle(fontSize: 12, color: context.palette.muted)),
          if (action != null)
            TextButton(
              onPressed: onAction ?? () {},
              style: TextButton.styleFrom(
                foregroundColor: AppColors.primary2,
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 8),
              ),
              child: Text(action!, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            ),
        ],
      ),
    );
  }
}

/// Column with hairline dividers between children.
class DividedColumn extends StatelessWidget {
  const DividedColumn({super.key, required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final line = context.palette.line;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < children.length; i++) ...[
          if (i > 0) Divider(height: 1, thickness: 1, color: line),
          children[i],
        ],
      ],
    );
  }
}

class ListRow extends StatelessWidget {
  const ListRow({
    super.key,
    this.leading,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.padding = const EdgeInsets.symmetric(vertical: 12),
  });

  final Widget? leading;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final row = Padding(
      padding: padding,
      child: Row(
        children: [
          if (leading != null) ...[leading!, const SizedBox(width: 12)],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, height: 1.3)),
                if (subtitle != null)
                  Text(subtitle!, style: TextStyle(fontSize: 12, color: context.palette.muted)),
              ],
            ),
          ),
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
    return onTap == null ? row : InkWell(onTap: onTap, child: row);
  }
}

class KeyValueRow extends StatelessWidget {
  const KeyValueRow(this.label, this.value, {super.key});
  final String label, value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(fontSize: 13.5, color: context.palette.muted)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(value,
                  textAlign: TextAlign.right,
                  style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );
}

// ---------------- Buttons ----------------
class AppButton extends StatelessWidget {
  const AppButton(
    this.label, {
    super.key,
    this.onPressed,
    this.color = AppColors.primary,
    this.foreground = Colors.white,
    this.small = false,
    this.outlined = false,
    this.expand = false,
    this.emoji,
  });

  final String label;
  final VoidCallback? onPressed;
  final Color color, foreground;
  final bool small, outlined, expand;
  final String? emoji;

  @override
  Widget build(BuildContext context) {
    final pad = small
        ? const EdgeInsets.symmetric(horizontal: 12, vertical: 7)
        : const EdgeInsets.symmetric(horizontal: 16, vertical: 13);
    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(small ? 11 : 14));
    final text = TextStyle(fontSize: small ? 12.5 : 14, fontWeight: FontWeight.w700);
    final min = Size(0, small ? 34 : 46);
    final child = emoji == null
        ? Text(label, maxLines: 1, overflow: TextOverflow.ellipsis)
        : Row(mainAxisSize: MainAxisSize.min, children: [
            Text(emoji!),
            const SizedBox(width: 6),
            Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis)),
          ]);
    final action = onPressed; // null = disabled

    final Widget b = outlined
        ? OutlinedButton(
            onPressed: action,
            style: OutlinedButton.styleFrom(
              foregroundColor: color,
              side: BorderSide(color: color, width: 1.5),
              padding: pad,
              minimumSize: min,
              shape: shape,
              textStyle: text,
              visualDensity: small ? VisualDensity.compact : null,
            ),
            child: child,
          )
        : FilledButton(
            onPressed: action,
            style: FilledButton.styleFrom(
              backgroundColor: color,
              foregroundColor: foreground,
              padding: pad,
              minimumSize: min,
              shape: shape,
              textStyle: text,
              visualDensity: small ? VisualDensity.compact : null,
            ),
            child: child,
          );
    return expand ? SizedBox(width: double.infinity, child: b) : b;
  }
}

// ---------------- Misc ----------------
class BlinkingDot extends StatefulWidget {
  const BlinkingDot({super.key, this.color = AppColors.red, this.size = 7});
  final Color color;
  final double size;

  @override
  State<BlinkingDot> createState() => _BlinkingDotState();
}

class _BlinkingDotState extends State<BlinkingDot> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
  late final Animation<double> _a = Tween(begin: 0.25, end: 1.0).animate(_c);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (context.reduceMotion) {
      _c.stop();
      _c.value = 1;
    } else if (!_c.isAnimating) {
      _c.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
        opacity: _a,
        child: Container(
          width: widget.size,
          height: widget.size,
          decoration: BoxDecoration(color: widget.color, shape: BoxShape.circle),
        ),
      );
}

class EmptyNote extends StatelessWidget {
  const EmptyNote(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 40),
        child: Text(text,
            textAlign: TextAlign.center,
            style: TextStyle(color: context.palette.muted, fontSize: 13.5)),
      );
}

void showSoon(BuildContext context, String what) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(behavior: SnackBarBehavior.floating, content: Text(what)));
}
