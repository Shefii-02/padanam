import 'package:flutter/widgets.dart';

class Breakpoints {
  Breakpoints._();
  static const double tablet = 700;
  static const double desktop = 1100;

  /// Content never grows wider than this (tablets / web / desktop).
  static const double maxContent = 960;
}

extension ResponsiveX on BuildContext {
  double get screenWidth => MediaQuery.sizeOf(this).width;
  bool get isSmallPhone => screenWidth < 360;
  bool get isTablet => screenWidth >= Breakpoints.tablet;
  bool get isDesktop => screenWidth >= Breakpoints.desktop;

  /// Horizontal page padding.
  double get hPad => isTablet ? 24 : 16;

  /// System text scale, clamped so fixed-height rows never overflow.
  double get textScale => MediaQuery.textScalerOf(this).scale(1).clamp(1.0, 1.6);

  bool get reduceMotion => MediaQuery.disableAnimationsOf(this);
}

/// Grid columns for a given available width.
int gridColumns(double width, {int phone = 4, int tablet = 6, int desktop = 8}) {
  if (width >= 900) return desktop;
  if (width >= 600) return tablet;
  return phone;
}
