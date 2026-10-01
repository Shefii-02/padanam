import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_exception.dart';
import '../theme/app_theme.dart';
import 'ui.dart';

/// Standard loading / error / data switch for every API-backed screen.
class AsyncView<T> extends StatelessWidget {
  const AsyncView({super.key, required this.value, required this.data, this.onRetry, this.loading});

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final VoidCallback? onRetry;
  final Widget? loading;

  @override
  Widget build(BuildContext context) {
    return value.when(
      skipLoadingOnRefresh: true,
      skipLoadingOnReload: true,
      data: data,
      loading: () => loading ?? const LoadingView(),
      error: (e, _) => ErrorView(error: e, onRetry: onRetry),
    );
  }
}

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});

  @override
  Widget build(BuildContext context) =>
      const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator(strokeWidth: 3)));
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});
  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final msg = error is ApiException ? (error as ApiException).message : 'Something went wrong.';
    final offline = error is ApiException && (error as ApiException).isNetwork;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(offline ? '📡' : '⚠️', style: const TextStyle(fontSize: 44)),
          const SizedBox(height: 12),
          Text(offline ? "You're offline" : 'Could not load', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text(msg, textAlign: TextAlign.center, style: TextStyle(color: p.muted, fontSize: 13.5, height: 1.5)),
          if (onRetry != null) ...[
            const SizedBox(height: 16),
            AppButton('Try again', onPressed: onRetry),
          ],
        ]),
      ),
    );
  }
}

/// Runs an API action with a snackbar on failure. Returns the result or null.
Future<T?> runAction<T>(BuildContext context, Future<T> Function() action, {String? success}) async {
  try {
    final r = await action();
    if (success != null && context.mounted) showSoon(context, success);
    return r;
  } catch (e) {
    if (context.mounted) showSoon(context, e is ApiException ? e.message : 'Something went wrong');
    return null;
  }
}

/// Centered empty state for lists.
class EmptyView extends StatelessWidget {
  const EmptyView({super.key, required this.emoji, required this.title, this.subtitle, this.action});
  final String emoji, title;
  final String? subtitle;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(emoji, style: const TextStyle(fontSize: 44)),
          const SizedBox(height: 12),
          Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          if (subtitle != null) ...[
            const SizedBox(height: 6),
            Text(subtitle!, textAlign: TextAlign.center, style: TextStyle(color: p.muted, fontSize: 13.5, height: 1.5)),
          ],
          if (action != null) ...[const SizedBox(height: 16), action!],
        ]),
      ),
    );
  }
}
