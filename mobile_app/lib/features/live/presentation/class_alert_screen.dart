import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../learning/data/learning_repository.dart';
import 'live_screens.dart';

/// Full-screen "class starting" ring (opened from the alert notification or while the app is open).
class ClassAlertScreen extends ConsumerStatefulWidget {
  const ClassAlertScreen({super.key, required this.liveId, this.data});
  final int liveId;
  final Json? data;

  @override
  ConsumerState<ClassAlertScreen> createState() => _ClassAlertScreenState();
}

class _ClassAlertScreenState extends ConsumerState<ClassAlertScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..repeat(reverse: true);
  Timer? _buzz;

  Json get d => widget.data ?? const <String, dynamic>{};

  @override
  void initState() {
    super.initState();
    _buzz = Timer.periodic(const Duration(seconds: 2), (_) => HapticFeedback.heavyImpact());
    Future<void>.delayed(const Duration(seconds: 30), () => _buzz?.cancel());
  }

  @override
  void dispose() {
    _buzz?.cancel();
    _pulse.dispose();
    super.dispose();
  }

  void _close() => context.canPop() ? context.pop() : context.go(R.home);

  @override
  Widget build(BuildContext context) {
    final start = d.sn('starts_at');
    return Scaffold(
      backgroundColor: AppColors.primary,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 40, 24, 24),
          child: Column(children: [
            const Text('LIVE CLASS STARTING', style: TextStyle(color: Color(0xFFD6DCF7), fontWeight: FontWeight.w800, letterSpacing: 1.2)),
            const Spacer(),
            ScaleTransition(
              scale: Tween(begin: .92, end: 1.06).animate(CurvedAnimation(parent: _pulse, curve: Curves.easeInOut)),
              child: Container(
                width: 150,
                height: 150,
                decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: .12)),
                child: const Icon(Icons.notifications_active_rounded, color: Colors.white, size: 64),
              ),
            ),
            const SizedBox(height: 24),
            Text(d.s('title', 'Your live class'), textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, height: 1.25)),
            const SizedBox(height: 8),
            Text(
              [if (d.s('teacher').isNotEmpty) d.s('teacher'), if (d.s('batch').isNotEmpty) d.s('batch'), if (start != null) liveWhen(start)].join(' · '),
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFFD6DCF7)),
            ),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: AppColors.primary, minimumSize: const Size.fromHeight(56), shape: const StadiumBorder()),
                onPressed: () => context.pushReplacement(R.live(widget.liveId)),
                icon: const Icon(Icons.sensors_rounded),
                label: const Text('Join class', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              ),
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white54), minimumSize: const Size.fromHeight(48), shape: const StadiumBorder()),
                  onPressed: () {
                    showSoon(context, "We'll remind you in 2 minutes");
                    final router = GoRouter.of(context);
                    final id = widget.liveId;
                    final data = widget.data;
                    Timer(const Duration(minutes: 2), () => router.push(R.classAlert(id), extra: data));
                    _close();
                  },
                  child: const Text('Remind in 2 min'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white54), minimumSize: const Size.fromHeight(48), shape: const StadiumBorder()),
                  onPressed: _close,
                  child: const Text('Dismiss'),
                ),
              ),
            ]),
            if (d['course_id'] != null)
              TextButton(
                onPressed: () async {
                  await runAction(context, () => ref.read(learningRepositoryProvider).classAlerts(d.i('course_id'), false), success: 'Class alerts turned off for this course');
                  if (mounted) _close();
                },
                child: const Text('Turn off alerts for this course', style: TextStyle(color: Color(0xFFD6DCF7))),
              ),
          ]),
        ),
      ),
    );
  }
}
