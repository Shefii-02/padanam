import 'dart:async';

import 'package:flutter/material.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';

/// YouTube (unlisted) player. Reports position every 15 s through [onProgress] (seconds, duration).
class YoutubeBox extends StatefulWidget {
  const YoutubeBox({super.key, required this.videoId, this.startAt = 0, this.autoPlay = false, this.live = false, this.onProgress});
  final String videoId;
  final int startAt;
  final bool autoPlay, live;
  final void Function(int position, int duration)? onProgress;

  @override
  State<YoutubeBox> createState() => _YoutubeBoxState();
}

class _YoutubeBoxState extends State<YoutubeBox> {
  late final YoutubePlayerController _c;
  Timer? _t;

  @override
  void initState() {
    super.initState();
    _c = YoutubePlayerController.fromVideoId(
      videoId: widget.videoId,
      autoPlay: widget.autoPlay,
      startSeconds: widget.startAt > 5 ? widget.startAt.toDouble() : null,
      params: const YoutubePlayerParams(showFullscreenButton: true, strictRelatedVideos: true, showVideoAnnotations: false),
    );
    if (widget.onProgress != null && !widget.live) {
      _t = Timer.periodic(const Duration(seconds: 15), (_) => _report());
    }
  }

  Future<void> _report() async {
    try {
      final pos = await _c.currentTime;
      final dur = await _c.duration;
      if (dur > 0) widget.onProgress?.call(pos.round(), dur.round());
    } catch (_) {}
  }

  @override
  void dispose() {
    _t?.cancel();
    _report();
    _c.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => YoutubePlayer(controller: _c, aspectRatio: 16 / 9);
}
