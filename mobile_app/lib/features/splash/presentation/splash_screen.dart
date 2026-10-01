import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/update/update_controller.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../data/app_repository.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _anim = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..forward();
  String? _error;

  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    setState(() => _error = null);
    ref.read(appConfigProvider); // warm onboarding content
    ref.read(updateControllerProvider.notifier).check();
    try {
      await Future.wait([
        Future<void>.delayed(const Duration(milliseconds: 1800)),
        ref.read(authControllerProvider.notifier).bootstrap(),
      ]);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  void dispose() {
    _anim.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final fade = CurvedAnimation(parent: _anim, curve: Curves.easeOut);
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: RadialGradient(center: Alignment(0, -0.4), radius: 1.2, colors: [Color(0xFF3148D6), AppColors.primary, Color(0xFF101742)]),
        ),
        child: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(30),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                ScaleTransition(
                  scale: Tween(begin: 0.5, end: 1.0).animate(CurvedAnimation(parent: _anim, curve: Curves.elasticOut)),
                  child: Container(
                    width: 112,
                    height: 112,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(34), boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 30, offset: Offset(0, 14))]),
                    child: const Text('പ', style: TextStyle(fontSize: 56, fontWeight: FontWeight.w800, color: AppColors.primary)),
                  ),
                ),
                const SizedBox(height: 22),
                FadeTransition(
                  opacity: fade,
                  child: const Column(children: [
                    Text('Padanam', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800)),
                    SizedBox(height: 4),
                    Text('സർക്കാർ ജോലി സ്വപ്നത്തിലേക്ക്', style: TextStyle(color: Colors.white, fontSize: 15)),
                    Text('Your path to a government job', style: TextStyle(color: Colors.white70, fontSize: 13.5)),
                  ]),
                ),
                const SizedBox(height: 40),
                if (_error == null)
                  const SizedBox(width: 120, child: LinearProgressIndicator(minHeight: 4, color: AppColors.saffron, backgroundColor: Colors.white24))
                else ...[
                  Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, height: 1.5)),
                  const SizedBox(height: 14),
                  AppButton('Try again', color: Colors.white, foreground: AppColors.primary, onPressed: _start),
                ],
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
