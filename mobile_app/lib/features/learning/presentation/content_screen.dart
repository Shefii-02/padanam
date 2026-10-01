import 'dart:async';

import 'package:chewie/chewie.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:pdfrx/pdfrx.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/html_text.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../data/learning_repository.dart';
import 'widgets/youtube_box.dart';

/// Opens any content item. Tests, articles and live classes hand over to their own screens.
class ContentScreen extends ConsumerWidget {
  const ContentScreen({super.key, required this.contentId});
  final int contentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final v = ref.watch(contentProvider(contentId));
    return v.when(
      loading: () => const Scaffold(body: LoadingView()),
      error: (e, _) => Scaffold(appBar: AppBar(), body: _Locked(error: e, onRetry: () => ref.invalidate(contentProvider(contentId)))),
      data: (c) {
        final pay = c.m('payload');
        switch (c.s('type')) {
          case 'test':
          case 'quiz':
            return _Handover(route: R.instructions(pay.i('test_id')));
          case 'article':
            return _Handover(route: R.article(pay.s('slug')));
          case 'live':
            return _Handover(route: R.live(pay.i('live_class_id')));
          case 'video':
            return _VideoPage(item: c);
          case 'pdf':
            return _PdfPage(item: c);
          case 'note':
            return _NotePage(item: c);
          default:
            return _LinkPage(item: c);
        }
      },
    );
  }
}

/// Replaces this route with the right screen (keeps back navigation clean).
class _Handover extends StatefulWidget {
  const _Handover({required this.route});
  final String route;

  @override
  State<_Handover> createState() => _HandoverState();
}

class _HandoverState extends State<_Handover> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.pushReplacement(widget.route);
    });
  }

  @override
  Widget build(BuildContext context) => const Scaffold(body: LoadingView());
}

class _Locked extends StatelessWidget {
  const _Locked({required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final e = error;
    if (e is ApiException && e.data?['locked'] == true) {
      return EmptyView(
        emoji: '🔒',
        title: 'This lesson is locked',
        subtitle: e.message,
        action: e.data?['course_id'] == null ? null : AppButton('See course & batches', onPressed: () => context.pushReplacement(R.course('${e.data!['course_id']}'))),
      );
    }
    return ErrorView(error: error, onRetry: onRetry);
  }
}

// ---------------- video ----------------
class _VideoPage extends ConsumerStatefulWidget {
  const _VideoPage({required this.item});
  final Json item;

  @override
  ConsumerState<_VideoPage> createState() => _VideoPageState();
}

class _VideoPageState extends ConsumerState<_VideoPage> {
  VideoPlayerController? _vp;
  ChewieController? _chewie;
  Timer? _t;
  int _lastSaved = -1;

  Json get _pay => widget.item.m('payload');
  bool get _isYoutube => _pay.s('kind') == 'youtube';

  @override
  void initState() {
    super.initState();
    if (!_isYoutube && _pay.sn('url') != null) {
      _vp = VideoPlayerController.networkUrl(Uri.parse(_pay.s('url')));
      _vp!.initialize().then((_) {
        if (!mounted) return;
        setState(() {
          _chewie = ChewieController(
            videoPlayerController: _vp!,
            autoPlay: true,
            startAt: Duration(seconds: widget.item.i('last_position')),
            playbackSpeeds: const [0.75, 1, 1.25, 1.5, 1.75, 2],
            allowedScreenSleep: false,
          );
        });
      });
      _t = Timer.periodic(const Duration(seconds: 15), (_) {
        final v = _vp?.value;
        if (v != null && v.isInitialized && v.duration.inSeconds > 0) _save(v.position.inSeconds, v.duration.inSeconds);
      });
    }
  }

  void _save(int pos, int dur) {
    final pct = (pos / dur * 100).round();
    if (pct == _lastSaved) return;
    _lastSaved = pct;
    ref.read(learningRepositoryProvider).progress(widget.item.i('id'), pct, position: pos).ignore();
  }

  @override
  void dispose() {
    _t?.cancel();
    final v = _vp?.value;
    if (v != null && v.isInitialized && v.duration.inSeconds > 0) _save(v.position.inSeconds, v.duration.inSeconds);
    _chewie?.dispose();
    _vp?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final item = widget.item;
    final player = _isYoutube
        ? YoutubeBox(videoId: _pay.s('youtube_id'), startAt: item.i('last_position'), autoPlay: true, onProgress: _save)
        : AspectRatio(aspectRatio: 16 / 9, child: _chewie == null ? const ColoredBox(color: Colors.black, child: Center(child: CircularProgressIndicator())) : Chewie(controller: _chewie!));
    return SubPage(
      title: item.s('title'),
      children: [
        ClipRRect(borderRadius: BorderRadius.circular(16), child: player),
        const SizedBox(height: 14),
        Text(item.s('title'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
        if (item.s('description').isNotEmpty) ...[
          const SizedBox(height: 6),
          HtmlText(item.s('description'), fontSize: 14),
        ],
        const SizedBox(height: 14),
        Row(children: [
          Expanded(
            child: AppButton(item.b('completed') ? 'Completed ✓' : 'Mark as done', outlined: true, onPressed: item.b('completed')
                ? null
                : () async {
                    await runAction(context, () => ref.read(learningRepositoryProvider).progress(item.i('id'), 100), success: 'Marked as done');
                  }),
          ),
          const SizedBox(width: 10),
          Expanded(child: AppButton('Ask a doubt', outlined: true, onPressed: () => context.push(R.askDoubt(courseId: item.i('course_id'), contentId: item.i('id'), title: item.s('title'))))),
        ]),
        const SizedBox(height: 10),
        Text('Videos are for your personal study only. Screen recording is blocked.', style: TextStyle(fontSize: 12, color: p.muted)),
      ],
    );
  }
}

// ---------------- PDF ----------------
class _PdfPage extends ConsumerStatefulWidget {
  const _PdfPage({required this.item});
  final Json item;

  @override
  ConsumerState<_PdfPage> createState() => _PdfPageState();
}

class _PdfPageState extends ConsumerState<_PdfPage> {
  @override
  void initState() {
    super.initState();
    ref.read(learningRepositoryProvider).progress(widget.item.i('id'), 100).ignore();
  }

  @override
  Widget build(BuildContext context) {
    final pay = widget.item.m('payload');
    final p = context.palette;
    return SubPage(
      title: widget.item.s('title'),
      actions: [
        if (pay.b('downloadable'))
          IconButton(
            tooltip: 'Download',
            icon: Icon(Icons.download_rounded, color: p.brand),
            onPressed: () => launchUrl(Uri.parse(pay.s('url')), mode: LaunchMode.externalApplication),
          ),
      ],
      body: PdfViewer.uri(Uri.parse(pay.s('url'))),
    );
  }
}

// ---------------- note ----------------
class _NotePage extends ConsumerStatefulWidget {
  const _NotePage({required this.item});
  final Json item;

  @override
  ConsumerState<_NotePage> createState() => _NotePageState();
}

class _NotePageState extends ConsumerState<_NotePage> {
  late String _lang = ref.read(currentLangProvider);

  @override
  void initState() {
    super.initState();
    ref.read(learningRepositoryProvider).progress(widget.item.i('id'), 100).ignore();
  }

  @override
  Widget build(BuildContext context) {
    final pay = widget.item.m('payload');
    final body = pay['body'];
    final langs = body is Map ? [for (final k in body.keys) '$k'] : const <String>[];
    final p = context.palette;
    return SubPage(
      title: widget.item.s('title'),
      actions: [
        if (langs.length > 1)
          Padding(
            padding: const EdgeInsets.only(right: 4),
            child: LangToggle(
              options: [for (final l in langs) l == 'ml' ? 'മല' : l.toUpperCase()],
              selected: langs.contains(_lang) ? langs.indexOf(_lang) : 0,
              onChanged: (i) => setState(() => _lang = langs[i]),
            ),
          ),
      ],
      children: [
        HtmlText(tr(body, _lang), fontSize: 15.5),
        if (tr(pay['tip'], _lang).isNotEmpty) ...[
          const SizedBox(height: 14),
          AppCard(color: AppColors.peach, bordered: false, child: Text('💡 ${tr(pay['tip'], _lang)}', style: const TextStyle(height: 1.5))),
        ],
        if (pay['one_liners'] is List && (pay['one_liners'] as List).isNotEmpty) ...[
          const SectionTitle('Quick revision'),
          for (final l in pay['one_liners'] as List)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('•  ', style: TextStyle(color: p.muted)),
                Expanded(child: Text(tr(l, _lang), style: const TextStyle(height: 1.5))),
              ]),
            ),
        ],
      ],
    );
  }
}

// ---------------- link ----------------
class _LinkPage extends StatelessWidget {
  const _LinkPage({required this.item});
  final Json item;

  @override
  Widget build(BuildContext context) {
    final url = item.m('payload').s('url');
    return SubPage(
      title: item.s('title'),
      children: [
        if (item.s('description').isNotEmpty) HtmlText(item.s('description')),
        const SizedBox(height: 16),
        AppButton('Open link', expand: true, onPressed: url.isEmpty ? null : () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication)),
        const SizedBox(height: 8),
        Text(url, style: TextStyle(fontSize: 12, color: context.palette.muted)),
      ],
    );
  }
}

/// Preferred content language (ml / en) from the auth state.
final currentLangProvider = Provider<String>((ref) {
  final l = ref.watch(authControllerProvider).language;
  return l == 'both' ? 'ml' : l;
});
