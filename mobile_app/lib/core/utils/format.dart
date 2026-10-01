/// 120000 -> 1,20,000 (Indian digit grouping)
String groupIndian(int v) {
  final s = v.abs().toString();
  if (s.length <= 3) return v < 0 ? '-$s' : s;
  final last3 = s.substring(s.length - 3);
  var rest = s.substring(0, s.length - 3);
  final parts = <String>[];
  while (rest.length > 2) {
    parts.insert(0, rest.substring(rest.length - 2));
    rest = rest.substring(0, rest.length - 2);
  }
  if (rest.isNotEmpty) parts.insert(0, rest);
  return '${v < 0 ? '-' : ''}${parts.join(',')},$last3';
}

String inr(int v) => '\u20B9${groupIndian(v)}';

/// 2300 -> 2.3k
String compact(int v) {
  if (v < 1000) return '$v';
  final k = (v / 1000).toStringAsFixed(1);
  return '${k.endsWith('.0') ? k.substring(0, k.length - 2) : k}k';
}

String initialsOf(String name) => name
    .trim()
    .split(RegExp(r'\s+'))
    .take(2)
    .map((w) => w.isEmpty ? '' : w[0].toUpperCase())
    .join();

const _months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
String monthAbbr(DateTime d) => _months[d.month - 1];

String greetingFor(DateTime now) {
  if (now.hour < 12) return 'Good morning';
  if (now.hour < 17) return 'Good afternoon';
  return 'Good evening';
}

String countdownTo(DateTime target) {
  final d = target.difference(DateTime.now());
  if (d.isNegative) return 'Started';
  final days = d.inDays, h = d.inHours % 24, m = d.inMinutes % 60;
  return days > 0 ? '${days}d ${h}h' : '${h}h ${m}m';
}

String mmss(int seconds) {
  final s = seconds < 0 ? 0 : seconds;
  return '${(s ~/ 60).toString().padLeft(2, '0')}:${(s % 60).toString().padLeft(2, '0')}';
}

/// Formats 29.5 → "29.5", 30.0 → "30".
String num1(num v) => v == v.roundToDouble() ? v.round().toString() : v.toStringAsFixed(1);
