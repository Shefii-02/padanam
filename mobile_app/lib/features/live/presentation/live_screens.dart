import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/format.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../learning/presentation/widgets/youtube_box.dart';
import '../data/live_repository.dart';

String liveWhen(String iso) {
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return '';
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final day = DateTime(d.year, d.month, d.day);
  final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
  final t = '$h:${d.minute.toString().padLeft(2, '0')} ${d.hour < 12 ? 'AM' : 'PM'}';
  if (day == today) return 'Today · $t';
  if (day == today.add(const Duration(days: 1))) return 'Tomorrow · $t';
  return '${d.day} ${monthAbbr(d)} · $t';
}

/// All upcoming live classes of my batches.
class LiveListScreen extends ConsumerWidget {
  const LiveListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return SubPage(
      title: 'Live classes',
      body: AsyncView<List<Json>>(
        value: ref.watch(upcomingLiveProvider),
        onRetry: () => ref.invalidate(upcomingLiveProvider),
        data: (list) => list.isEmpty
            ? const EmptyView(emoji: '📺', title: 'No live classes scheduled', subtitle: 'You get a ring 5 minutes before every class.')
            : RefreshIndicator(
                onRefresh: () => ref.refresh(upcomingLiveProvider.future),
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    final l = list[i];
                    final live = l.s('status') == 'live';
                    return AppCard(
                      color: live ? AppColors.primary : null,
                      onTap: () => context.push(R.live(l.i('id'))),
                      child: Row(children: [
                        Icon(live ? Icons.sensors_rounded : Icons.event_rounded, color: live ? Colors.white : AppColors.primary2, size: 28),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(l.s('title'), style: TextStyle(fontWeight: FontWeight.w800, color: live ? Colors.white : p.ink)),
                            Text([l.s('course'), if (l.m('teacher').s('name').isNotEmpty) l.m('teacher').s('name')].join(' · '),
                                style: TextStyle(fontSize: 12.5, color: live ? Colors.white70 : p.muted)),
                            Text(live ? 'LIVE NOW' : liveWhen(l.s('starts_at')), style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: live ? Colors.white : AppColors.primary2)),
                          ]),
                        ),
                        if (live) const Tag('JOIN', tone: Tone.red),
                      ]),
                    );
                  },
                ),
              ),
      ),
    );
  }
}

/// One live class: countdown → live YouTube → recording.
class LiveClassScreen extends ConsumerStatefulWidget {
  const LiveClassScreen({super.key, required this.liveId});
  final int liveId;

  @override
  ConsumerState<LiveClassScreen> createState() => _LiveClassScreenState();
}

class _LiveClassScreenState extends ConsumerState<LiveClassScreen> {
  Timer? _t;

  @override
  void initState() {
    super.initState();
    // refresh every 20 s while waiting, so the video appears when the teacher goes live
    _t = Timer.periodic(const Duration(seconds: 20), (_) {
      final st = ref.read(liveJoinProvider(widget.liveId)).value?.s('status');
      if (st == 'scheduled') ref.invalidate(liveJoinProvider(widget.liveId));
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _t?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return AsyncView<Json>(
      value: ref.watch(liveJoinProvider(widget.liveId)),
      onRetry: () => ref.invalidate(liveJoinProvider(widget.liveId)),
      loading: const Scaffold(body: LoadingView()),
      data: (l) {
        final st = l.s('status');
        final start = DateTime.tryParse(l.s('starts_at'))?.toLocal();
        final left = start?.difference(DateTime.now());
        return SubPage(
          title: l.s('title'),
          subtitle: st == 'live' ? 'LIVE' : (st == 'ended' ? 'Recording' : liveWhen(l.s('starts_at'))),
          children: [
            if ((st == 'live' || st == 'ended') && l.sn('youtube_id') != null)
              ClipRRect(borderRadius: BorderRadius.circular(16), child: YoutubeBox(videoId: l.s('youtube_id'), autoPlay: true, live: st == 'live'))
            else
              AspectRatio(
                aspectRatio: 16 / 9,
                child: Container(
                  decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(16)),
                  alignment: Alignment.center,
                  padding: const EdgeInsets.all(16),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(Icons.schedule_rounded, color: Colors.white, size: 40),
                    const SizedBox(height: 8),
                    Text(
                      st == 'cancelled'
                          ? 'This class was cancelled'
                          : (left != null && left.inSeconds > 0 ? 'Starts in ${countdownTo(start!)}' : 'Waiting for the teacher to start…'),
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ]),
                ),
              ),
            const SizedBox(height: 14),
            Text(l.s('title'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(st == 'live' ? 'Your attendance is marked.' : 'You get a full-screen ring 5 minutes before the class.', style: TextStyle(color: p.muted)),
            const SizedBox(height: 16),
            if (l['chat_room_id'] != null)
              AppButton('Open batch chat', outlined: true, expand: true, emoji: '💬', onPressed: () => context.push(R.chatRoom(l.i('chat_room_id')))),
            if (st == 'ended' && l['recording_content_id'] != null) ...[
              const SizedBox(height: 10),
              AppButton('Watch recording in the course', expand: true, onPressed: () => context.push(R.content(l.i('recording_content_id')))),
            ],
          ],
        );
      },
    );
  }
}
