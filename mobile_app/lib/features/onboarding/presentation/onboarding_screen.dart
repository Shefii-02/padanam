import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../splash/data/app_repository.dart';

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _pc = PageController();
  int _i = 0;

  @override
  void dispose() {
    _pc.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final cfg = ref.watch(appConfigProvider);
    return Scaffold(
      backgroundColor: p.bg,
      body: SafeArea(
        child: AsyncView(
          value: cfg,
          onRetry: () => ref.invalidate(appConfigProvider),
          data: (c) {
            final slides = c.l('onboarding');
            return Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 560),
                child: Column(children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(onPressed: () => context.go(R.language), child: const Text('Skip', style: TextStyle(fontWeight: FontWeight.w700))),
                  ),
                  Expanded(
                    child: PageView.builder(
                      controller: _pc,
                      itemCount: slides.length,
                      onPageChanged: (i) => setState(() => _i = i),
                      itemBuilder: (_, i) => _Slide(slide: slides[i]),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
                    child: Row(children: [
                      for (var k = 0; k < slides.length; k++)
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 250),
                          margin: const EdgeInsets.only(right: 6),
                          width: k == _i ? 26 : 8,
                          height: 8,
                          decoration: BoxDecoration(color: k == _i ? AppColors.primary2 : p.line, borderRadius: BorderRadius.circular(4)),
                        ),
                      const Spacer(),
                      FloatingActionButton(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: const CircleBorder(),
                        onPressed: () {
                          if (_i >= slides.length - 1) {
                            context.go(R.language);
                          } else {
                            _pc.nextPage(duration: const Duration(milliseconds: 350), curve: Curves.easeOut);
                          }
                        },
                        child: Icon(_i >= slides.length - 1 ? Icons.check_rounded : Icons.arrow_forward_rounded),
                      ),
                    ]),
                  ),
                ]),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _Slide extends StatelessWidget {
  const _Slide({required this.slide});
  final Json slide;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final floats = slide.l('floats');
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        AspectRatio(
          aspectRatio: 1.05,
          child: Container(
            decoration: BoxDecoration(color: hex(slide.s('color')), borderRadius: BorderRadius.circular(32)),
            child: Stack(children: [
              Center(child: Text(slide.s('emoji'), style: const TextStyle(fontSize: 96))),
              if (floats.isNotEmpty) Positioned(top: 30, right: 16, child: _Float(f: floats[0])),
              if (floats.length > 1) Positioned(bottom: 34, left: 16, child: _Float(f: floats[1])),
            ]),
          ),
        ),
        const SizedBox(height: 26),
        Text(slide.s('title'), style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, height: 1.3, color: p.ink)),
        const SizedBox(height: 8),
        Text(slide.s('body'), style: TextStyle(fontSize: 14.5, height: 1.6, color: p.muted)),
      ]),
    );
  }
}

class _Float extends StatelessWidget {
  const _Float({required this.f});
  final Json f;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: p.card, borderRadius: BorderRadius.circular(16), boxShadow: const [BoxShadow(color: Color(0x33141A33), blurRadius: 20, offset: Offset(0, 8))]),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Text(f.s('emoji'), style: const TextStyle(fontSize: 18)),
        const SizedBox(width: 8),
        Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text(f.s('title'), style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5, color: p.ink)),
          Text(f.s('subtitle'), style: TextStyle(fontSize: 11, color: p.muted)),
        ]),
      ]),
    );
  }
}
