import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

/// Groups of coloured bars, e.g. You / Average / Topper × 4 sections.
class GroupedBars extends StatelessWidget {
  const GroupedBars({super.key, required this.groups, required this.values, required this.colors, this.height = 190});
  final List<String> groups;
  final List<List<double>> values; // [group][series]
  final List<Color> colors;
  final double height;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final maxV = math.max(1.0, values.expand((e) => e).fold<double>(0, math.max)) * 1.15;
    return SizedBox(
      height: height + 28,
      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        SizedBox(
          width: 30,
          height: height,
          child: Column(mainAxisAlignment: MainAxisAlignment.spaceBetween, crossAxisAlignment: CrossAxisAlignment.end, children: [
            for (var k = 4; k >= 0; k--) Text('${(maxV * k / 4).round()}', style: TextStyle(fontSize: 9, color: p.muted)),
          ]),
        ),
        const SizedBox(width: 6),
        for (var g = 0; g < groups.length; g++)
          Expanded(
            child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
              SizedBox(
                height: height,
                child: Row(crossAxisAlignment: CrossAxisAlignment.end, mainAxisAlignment: MainAxisAlignment.center, children: [
                  for (var s = 0; s < values[g].length; s++)
                    Tooltip(
                      message: '${values[g][s]}',
                      child: TweenAnimationBuilder<double>(
                        tween: Tween(begin: 0, end: math.max(0, values[g][s]) / maxV),
                        duration: const Duration(milliseconds: 500),
                        builder: (_, v, __) => Container(
                          width: 13,
                          height: height * v,
                          margin: const EdgeInsets.symmetric(horizontal: 1.5),
                          decoration: BoxDecoration(color: colors[s % colors.length], borderRadius: const BorderRadius.vertical(top: Radius.circular(3))),
                        ),
                      ),
                    ),
                ]),
              ),
              const SizedBox(height: 8),
              Text(groups[g], style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700)),
            ]),
          ),
      ]),
    );
  }
}

class LineSeries {
  const LineSeries(this.values, this.color, {this.dashed = false});
  final List<double> values;
  final Color color;
  final bool dashed;
}

/// Minimal line chart with labelled x axis.
class LineChart extends StatelessWidget {
  const LineChart({super.key, required this.series, required this.labels, required this.maxY, this.unit = '', this.height = 170});
  final List<LineSeries> series;
  final List<String> labels;
  final double maxY;
  final String unit;
  final double height;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return SizedBox(
      height: height,
      child: CustomPaint(
        size: Size.infinite,
        painter: _LinePainter(series, labels, maxY, unit, p.line, p.muted, AppColors.primary2),
      ),
    );
  }
}

class _LinePainter extends CustomPainter {
  _LinePainter(this.series, this.labels, this.maxY, this.unit, this.grid, this.text, this.highlight);
  final List<LineSeries> series;
  final List<String> labels;
  final double maxY;
  final String unit;
  final Color grid, text, highlight;

  void _label(Canvas c, String s, Offset at, {TextAlign align = TextAlign.center, Color? color, bool bold = false}) {
    final tp = TextPainter(
      text: TextSpan(text: s, style: TextStyle(fontSize: 9.5, color: color ?? text, fontWeight: bold ? FontWeight.w800 : FontWeight.w600)),
      textDirection: TextDirection.ltr,
    )..layout();
    final dx = align == TextAlign.right ? at.dx - tp.width : at.dx - tp.width / 2;
    tp.paint(c, Offset(dx, at.dy - tp.height / 2));
  }

  @override
  void paint(Canvas canvas, Size size) {
    const left = 34.0, bottom = 20.0, top = 8.0;
    final w = size.width - left - 8;
    final h = size.height - bottom - top;
    final gp = Paint()
      ..color = grid
      ..strokeWidth = 1;
    for (var k = 0; k <= 4; k++) {
      final y = top + h - h * k / 4;
      canvas.drawLine(Offset(left, y), Offset(size.width, y), gp);
      _label(canvas, '${(maxY * k / 4).toStringAsFixed(maxY < 5 ? 1 : 0)}$unit', Offset(left - 5, y), align: TextAlign.right);
    }
    final n = labels.length;
    double x(int i) => left + 6 + (n <= 1 ? 0 : w * i / (n - 1));
    for (var i = 0; i < n; i++) {
      final last = i == n - 1;
      _label(canvas, labels[i], Offset(x(i), size.height - 8), color: last ? highlight : null, bold: last);
    }
    for (final s in series) {
      final paint = Paint()
        ..color = s.color
        ..strokeWidth = 2.5
        ..style = PaintingStyle.stroke
        ..strokeJoin = StrokeJoin.round;
      final pts = [for (var i = 0; i < s.values.length && i < n; i++) Offset(x(i), top + h - h * (s.values[i] / maxY).clamp(0.0, 1.0))];
      if (s.dashed) {
        for (var i = 0; i < pts.length - 1; i++) {
          final a = pts[i], b = pts[i + 1];
          const dash = 5.0;
          final len = (b - a).distance;
          for (var d = 0.0; d < len; d += dash * 2) {
            canvas.drawLine(Offset.lerp(a, b, d / len)!, Offset.lerp(a, b, math.min(1, (d + dash) / len))!, paint);
          }
        }
      } else if (pts.length > 1) {
        final path = Path()..moveTo(pts.first.dx, pts.first.dy);
        for (final pt in pts.skip(1)) {
          path.lineTo(pt.dx, pt.dy);
        }
        canvas.drawPath(path, paint);
      }
      final dot = Paint()..color = s.color;
      for (final pt in pts) {
        canvas.drawCircle(pt, 3.5, dot);
      }
    }
  }

  @override
  bool shouldRepaint(_LinePainter old) => true;
}

/// Coloured legend row.
class ChartLegend extends StatelessWidget {
  const ChartLegend({super.key, required this.items});
  final List<(Color, String)> items;

  @override
  Widget build(BuildContext context) => Wrap(spacing: 14, runSpacing: 6, children: [
        for (final (c, t) in items)
          Row(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 12, height: 12, decoration: BoxDecoration(color: c, borderRadius: BorderRadius.circular(3))),
            const SizedBox(width: 6),
            Text(t, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
          ]),
      ]);
}
