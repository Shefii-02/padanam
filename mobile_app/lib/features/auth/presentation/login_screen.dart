import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/ui.dart';
import '../application/auth_controller.dart';

final _mobile = RegExp(r'^[6-9]\d{9}$');

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _phone = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  bool get _valid => _mobile.hasMatch(_phone.text);

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final info = await ref.read(authControllerProvider.notifier).sendOtp(_phone.text);
      if (!mounted) return;
      if (info.devOtp != null) showSoon(context, 'Demo OTP: ${info.devOtp}');
      context.push(R.otp, extra: info.resendIn);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return Scaffold(
      backgroundColor: p.bg,
      body: LayoutBuilder(builder: (context, box) {
        final wide = box.maxWidth >= 840;
        final form = _form(context);
        if (wide) {
          return Row(children: [
            Expanded(child: _hero(fullHeight: true)),
            Expanded(child: Center(child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 440), child: SingleChildScrollView(padding: const EdgeInsets.all(32), child: form)))),
          ]);
        }
        return SingleChildScrollView(
          child: Column(children: [
            _hero(),
            Padding(padding: const EdgeInsets.fromLTRB(20, 20, 20, 24), child: form),
          ]),
        );
      }),
    );
  }

  Widget _hero({bool fullHeight = false}) {
    return Container(
      height: fullHeight ? double.infinity : 250,
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(24, MediaQuery.paddingOf(context).top + 16, 24, 26),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF3B4FD8)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: fullHeight ? null : const BorderRadius.vertical(bottom: Radius.circular(36)),
      ),
      child: Stack(children: [
        const Positioned(right: -10, top: 10, child: Opacity(opacity: 0.14, child: Text('🎓', style: TextStyle(fontSize: 120)))),
        Column(mainAxisAlignment: fullHeight ? MainAxisAlignment.center : MainAxisAlignment.end, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            width: 52,
            height: 52,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
            child: const Text('പ', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: AppColors.primary)),
          ),
          const SizedBox(height: 14),
          const Text('Welcome to Padanam', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          const Text('Log in or sign up with your mobile number', style: TextStyle(color: Colors.white70, fontSize: 14)),
        ]),
      ]),
    );
  }

  Widget _form(BuildContext context) {
    final p = context.palette;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const Text('Mobile number', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
      const SizedBox(height: 6),
      TextField(
        controller: _phone,
        keyboardType: TextInputType.phone,
        autofillHints: const [AutofillHints.telephoneNumberNational],
        maxLength: 10,
        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, letterSpacing: 1),
        onChanged: (_) => setState(() => _error = _phone.text.length == 10 && !_valid ? 'Enter a valid Indian mobile number' : null),
        onSubmitted: (_) => _valid && !_busy ? _send() : null,
        decoration: InputDecoration(
          counterText: '',
          hintText: '98765 43210',
          prefixIcon: const Padding(padding: EdgeInsets.symmetric(horizontal: 12), child: Text('🇮🇳 +91', style: TextStyle(fontWeight: FontWeight.w800))),
          prefixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
          filled: true,
          fillColor: p.card,
          errorText: _error,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
      const SizedBox(height: 14),
      AppButton(_busy ? 'Sending…' : 'Send OTP', expand: true, onPressed: _valid && !_busy ? _send : null),
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 18),
        child: Row(children: [Expanded(child: Divider(color: p.line)), Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text('or', style: TextStyle(color: p.muted))), Expanded(child: Divider(color: p.line))]),
      ),
      OutlinedButton.icon(
        onPressed: _busy ? null : () => ref.read(authControllerProvider.notifier).googleLogin().catchError((Object e) {
          if (mounted) showSoon(context, '$e');
        }),
        icon: const Text('G', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF4285F4), fontSize: 18)),
        label: const Text('Continue with Google', style: TextStyle(fontWeight: FontWeight.w700)),
        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
      ),
      const SizedBox(height: 22),
      Text('By continuing you agree to our Terms and Privacy Policy.\nWe will never share your number.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: p.muted, height: 1.5)),
    ]);
  }
}
