import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Fixed brand colours (same in light and dark).
class AppColors {
  AppColors._();
  static const primary = Color(0xFF1B2A7A); // deep indigo
  static const primary2 = Color(0xFF2F45C4);
  static const saffron = Color(0xFFF28C1B);
  static const green = Color(0xFF1F9D6B);
  static const red = Color(0xFFE5263A);
  static const gold = Color(0xFFE8A317);
  static const peach = Color(0xFFFFF1E0);
  static const peachInk = Color(0xFFB35A00);
  static const mint = Color(0xFFE2F5EC);
  static const lavender = Color(0xFFE3E8FA);
}

/// Surface colours that change between light and dark mode.
@immutable
class AppPalette extends ThemeExtension<AppPalette> {
  final Color bg, sheet, card, ink, muted, line, brand;

  const AppPalette({
    required this.bg,
    required this.sheet,
    required this.card,
    required this.ink,
    required this.muted,
    required this.line,
    required this.brand,
  });

  static const light = AppPalette(
    bg: Color(0xFFF6F7FC),
    sheet: Color(0xFFE8ECF8),
    card: Colors.white,
    ink: Color(0xFF141A33),
    muted: Color(0xFF5E6583),
    line: Color(0xFFDDE2F2),
    brand: AppColors.primary,
  );

  static const dark = AppPalette(
    bg: Color(0xFF0E1226),
    sheet: Color(0xFF1A2044),
    card: Color(0xFF20274F),
    ink: Color(0xFFEEF0FA),
    muted: Color(0xFFA5ABCB),
    line: Color(0xFF2E3665),
    brand: Color(0xFF8FA2FF),
  );

  @override
  AppPalette copyWith({
    Color? bg,
    Color? sheet,
    Color? card,
    Color? ink,
    Color? muted,
    Color? line,
    Color? brand,
  }) =>
      AppPalette(
        bg: bg ?? this.bg,
        sheet: sheet ?? this.sheet,
        card: card ?? this.card,
        ink: ink ?? this.ink,
        muted: muted ?? this.muted,
        line: line ?? this.line,
        brand: brand ?? this.brand,
      );

  @override
  AppPalette lerp(ThemeExtension<AppPalette>? other, double t) {
    if (other is! AppPalette) return this;
    return AppPalette(
      bg: Color.lerp(bg, other.bg, t)!,
      sheet: Color.lerp(sheet, other.sheet, t)!,
      card: Color.lerp(card, other.card, t)!,
      ink: Color.lerp(ink, other.ink, t)!,
      muted: Color.lerp(muted, other.muted, t)!,
      line: Color.lerp(line, other.line, t)!,
      brand: Color.lerp(brand, other.brand, t)!,
    );
  }
}

extension PaletteX on BuildContext {
  AppPalette get palette => Theme.of(this).extension<AppPalette>()!;
}

class AppTheme {
  AppTheme._();

  static ThemeData get light => _build(Brightness.light, AppPalette.light);
  static ThemeData get dark => _build(Brightness.dark, AppPalette.dark);

  static ThemeData _build(Brightness b, AppPalette p) {
    final base = ThemeData(
      useMaterial3: true,
      brightness: b,
      colorScheme: ColorScheme.fromSeed(seedColor: AppColors.primary, brightness: b),
      scaffoldBackgroundColor: p.bg,
    );
    return base.copyWith(
      textTheme: GoogleFonts.manropeTextTheme(base.textTheme)
          .apply(bodyColor: p.ink, displayColor: p.ink),
      extensions: <ThemeExtension<dynamic>>[p],
    );
  }
}

/// '#1B2A7A' → Color. Falls back to brand indigo.
Color hex(String? value, [Color fallback = AppColors.primary]) {
  if (value == null) return fallback;
  var h = value.replaceAll('#', '').trim();
  if (h.length == 6) h = 'FF$h';
  final v = int.tryParse(h, radix: 16);
  return v == null ? fallback : Color(v);
}
