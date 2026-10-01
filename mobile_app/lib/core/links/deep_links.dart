import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../router/app_router.dart';

/// padanam.app/c/{slug} course · /a/{slug} article · /j/{code} group invite · /paid/{order} payment link return.
/// Android: App Links (assetlinks.json on padanam.app). iOS: Universal Links (apple-app-site-association).
final deepLinksProvider = Provider<DeepLinks>((ref) {
  final d = DeepLinks(ref);
  ref.onDispose(d.dispose);
  return d;
});

class DeepLinks {
  DeepLinks(this._ref);
  final Ref _ref;
  StreamSubscription<Uri>? _sub;
  static const _paths = ['c', 'a', 'j', 'paid', 'course', 'article', 'live', 'test'];

  Future<void> init() async {
    if (kIsWeb) return; // on web the browser URL already goes through go_router
    final links = AppLinks();
    try {
      final first = await links.getInitialLink();
      if (first != null) _open(first, delay: true);
    } catch (_) {}
    _sub = links.uriLinkStream.listen(_open);
  }

  void _open(Uri uri, {bool delay = false}) {
    final seg = uri.pathSegments;
    if (seg.isEmpty || !_paths.contains(seg.first)) return;
    final route = uri.path + (uri.hasQuery ? '?${uri.query}' : '');
    // after login the router redirect sends the user on; before login they land on /login first
    Future<void>.delayed(Duration(milliseconds: delay ? 600 : 0), () => _ref.read(routerProvider).push(route));
  }

  void dispose() => _sub?.cancel();
}
