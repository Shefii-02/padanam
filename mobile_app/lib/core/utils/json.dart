typedef Json = Map<String, dynamic>;

/// Null-safe readers for API maps. Dummy APIs change often, so never crash on a missing key.
extension JsonX on Json {
  String s(String k, [String d = '']) {
    final v = this[k];
    return v == null ? d : '$v';
  }

  String? sn(String k) => this[k]?.toString();

  int i(String k, [int d = 0]) {
    final v = this[k];
    if (v is num) return v.toInt();
    return int.tryParse('$v') ?? d;
  }

  double n(String k, [double d = 0]) {
    final v = this[k];
    if (v is num) return v.toDouble();
    return double.tryParse('$v') ?? d;
  }

  bool b(String k, [bool d = false]) {
    final v = this[k];
    if (v is bool) return v;
    if (v is num) return v != 0;
    return d;
  }

  Json m(String k) {
    final v = this[k];
    return v is Map ? v.cast<String, dynamic>() : <String, dynamic>{};
  }

  List<Json> l(String k) {
    final v = this[k];
    if (v is! List) return const [];
    return [for (final e in v) if (e is Map) e.cast<String, dynamic>()];
  }

  List<String> ls(String k) {
    final v = this[k];
    return v is List ? [for (final e in v) '$e'] : const [];
  }

  List<double> ln(String k) {
    final v = this[k];
    return v is List ? [for (final e in v) e is num ? e.toDouble() : double.tryParse('$e') ?? 0] : const [];
  }
}

Json asJson(dynamic v) => v is Map ? v.cast<String, dynamic>() : <String, dynamic>{};

/// Picks a language from {"en": "...", "ml": "...", "hi": "..."} with English fallback.
String tr(dynamic v, String lang) {
  if (v is String) return v;
  if (v is Map) return (v[lang] ?? v['en'] ?? (v.isEmpty ? '' : v.values.first) ?? '').toString();
  return '';
}
