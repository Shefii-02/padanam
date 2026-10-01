import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/format.dart';
import '../../../../core/utils/json.dart';
import '../../../../core/widgets/ui.dart';

const contentEmoji = {'video': '🎬', 'live': '🔴', 'pdf': '📄', 'note': '📝', 'article': '📰', 'test': '🧪', 'quiz': '⚡', 'link': '🔗'};

/// One row in a course folder: video / pdf / note / test / live / link.
class ContentTile extends StatelessWidget {
  const ContentTile({super.key, required this.item, required this.onTap});
  final Json item;
  final VoidCallback onTap;

  String _sub() {
    final m = item.m('meta');
    return switch (item.s('type')) {
      'video' => m.i('duration_sec') > 0 ? '${(m.i('duration_sec') / 60).round()} min${item.i('progress') > 0 && !item.b('completed') ? ' · ${item.i('progress')}% watched' : ''}' : 'Video',
      'live' => m.s('status') == 'live' ? 'LIVE now' : (m.sn('starts_at') != null ? _when(m.s('starts_at')) : 'Live class'),
      'test' || 'quiz' => '${m.i('questions')} Qs · ${m.i('duration_min')} min${m.s('mode') == 'omr' ? ' · OMR' : ''}',
      'pdf' => m.i('size_bytes') > 0 ? 'PDF · ${(m.i('size_bytes') / 1048576).toStringAsFixed(1)} MB' : 'PDF',
      'article' => '${m.i('reading_min', 3)} min read',
      'note' => 'Note',
      _ => 'Link',
    };
  }

  static String _when(String iso) {
    final d = DateTime.tryParse(iso)?.toLocal();
    if (d == null) return 'Live class';
    final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
    return '${d.day} ${monthAbbr(d)} · $h:${d.minute.toString().padLeft(2, '0')} ${d.hour < 12 ? 'AM' : 'PM'}';
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final locked = item.b('locked');
    final done = item.b('completed');
    final live = item.s('type') == 'live' && item.m('meta').s('status') == 'live';
    return Semantics(
      button: true,
      label: '${item.s('title')}${locked ? ', locked' : ''}${done ? ', completed' : ''}',
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
          child: Row(children: [
            Container(
              width: 44,
              height: 44,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: done ? AppColors.mint : (live ? const Color(0xFFFDE7EA) : AppColors.lavender), borderRadius: BorderRadius.circular(13)),
              child: done
                  ? const Icon(Icons.check_rounded, color: AppColors.green)
                  : locked
                      ? Icon(Icons.lock_rounded, color: p.muted, size: 20)
                      : Text(contentEmoji[item.s('type')] ?? '📦', style: const TextStyle(fontSize: 20)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Opacity(
                opacity: locked ? .6 : 1,
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(item.s('title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
                  const SizedBox(height: 2),
                  Text(locked ? (item.s('lock_reason', 'Locked')) : _sub(), maxLines: 1, overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12.5, color: live ? AppColors.red : p.muted, fontWeight: live ? FontWeight.w800 : FontWeight.w500)),
                ]),
              ),
            ),
            if (item.s('access') == 'free' || item.s('access') == 'demo') const Tag('Free', tone: Tone.mint),
          ]),
        ),
      ),
    );
  }
}
