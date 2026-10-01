import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/widgets/ui.dart';
import '../application/auth_controller.dart';

class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key});

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  final _ctrls = List.generate(6, (_) => TextEditingController());
  final _nodes = List.generate(6, (_) => FocusNode());
  Timer? _timer;
  int _left = 30;
  bool _busy = false;
  String? _error;
  bool _ok = false;

  String get _code => _ctrls.map((c) => c.text).join();

  @override
  void initState() {
    super.initState();
    _startTimer();
    WidgetsBinding.instance.addPostFrameCallback((_) => _nodes.first.requestFocus());
  }

  void _startTimer() {
    _timer?.cancel();
    setState(() => _left = 30);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return;
      setState(() => _left = _left > 0 ? _left - 1 : 0);
      if (_left == 0) t.cancel();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final c in _ctrls) {
      c.dispose();
    }
    for (final n in _nodes) {
      n.dispose();
    }
    super.dispose();
  }

  void _onChanged(int i, String v) {
    if (v.length > 1) {
      // paste
      final digits = v.replaceAll(RegExp(r'\D'), '');
      for (var k = 0; k < 6; k++) {
        _ctrls[k].text = k < digits.length ? digits[k] : '';
      }
      _nodes[digits.length.clamp(0, 5)].requestFocus();
    } else if (v.isNotEmpty && i < 5) {
      _nodes[i + 1].requestFocus();
    }
    setState(() => _error = null);
    if (_code.length == 6) _verify();
  }

  Future<void> _verify() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await ref.read(authControllerProvider.notifier).verifyOtp(_code);
      setState(() => _ok = true);
      // Router redirect moves to /setup (new) or /welcome (existing).
    } on ApiException catch (e) {
      final left = e.data?['attempts_left'];
      setState(() => _error = left == null ? e.message : '${e.message} $left attempts left.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    for (final c in _ctrls) {
      c.clear();
    }
    try {
      final info = await ref.read(authControllerProvider.notifier).sendOtp(ref.read(authControllerProvider).phone ?? '');
      if (mounted && info.devOtp != null) showSoon(context, 'New code sent · Demo OTP: ${info.devOtp}');
      _startTimer();
      _nodes.first.requestFocus();
    } on ApiException catch (e) {
      if (mounted) showSoon(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final phone = ref.watch(authControllerProvider.select((s) => s.phone)) ?? '';
    final onWhatsApp = ref.watch(authControllerProvider.select((s) => s.otpChannel)) == 'whatsapp';
    final pretty = phone.length == 10 ? '${phone.substring(0, 5)} ${phone.substring(5)}' : phone;
    final border = _error != null ? AppColors.red : (_ok ? AppColors.green : null);

    return SubPage(
      title: '',
      onLeading: () => context.pop(),
      footer: AppButton(_busy ? 'Verifying…' : 'Verify', expand: true, onPressed: _code.length == 6 && !_busy ? _verify : null),
      children: [
        const Text('Enter the code', style: TextStyle(fontSize: 25, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Row(children: [
          Flexible(child: Text(onWhatsApp ? 'Sent on WhatsApp to +91 $pretty' : 'Sent by SMS to +91 $pretty', style: TextStyle(color: p.muted, fontSize: 14.5))),
          TextButton(onPressed: () => context.pop(), child: const Text('Change')),
        ]),
        const SizedBox(height: 14),
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Row(children: [
              for (var i = 0; i < 6; i++) ...[
                if (i > 0) const SizedBox(width: 8),
                Expanded(
                  child: Focus(
                    canRequestFocus: false,
                    skipTraversal: true,
                    onKeyEvent: (node, e) {
                      if (e is KeyDownEvent && e.logicalKey == LogicalKeyboardKey.backspace && _ctrls[i].text.isEmpty && i > 0) {
                        _ctrls[i - 1].clear();
                        _nodes[i - 1].requestFocus();
                        setState(() {});
                        return KeyEventResult.handled;
                      }
                      return KeyEventResult.ignored;
                    },
                    child: TextField(
                      controller: _ctrls[i],
                      focusNode: _nodes[i],
                      textAlign: TextAlign.center,
                      keyboardType: TextInputType.number,
                      autofillHints: i == 0 ? const [AutofillHints.oneTimeCode] : null,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                      onChanged: (v) => _onChanged(i, v),
                      decoration: InputDecoration(
                        filled: true,
                        fillColor: _ok ? AppColors.green.withValues(alpha: 0.08) : p.card,
                        contentPadding: const EdgeInsets.symmetric(vertical: 16),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: border ?? p.line, width: 1.5)),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: border ?? AppColors.primary2, width: 2)),
                      ),
                    ),
                  ),
                ),
              ],
            ]),
          ),
        ),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: const TextStyle(color: AppColors.red, fontWeight: FontWeight.w700))),
        const SizedBox(height: 10),
        Row(children: [
          Text("Didn't get it?", style: TextStyle(color: p.muted)),
          const Spacer(),
          TextButton(onPressed: _left == 0 ? _resend : null, child: Text(_left == 0 ? 'Resend OTP' : 'Resend in 0:${_left.toString().padLeft(2, '0')}')),
        ]),
        const SizedBox(height: 14),
        AppCard(
          color: AppColors.mint,
          bordered: false,
          child: Row(children: [
            Text(onWhatsApp ? '💬' : '📩'),
            const SizedBox(width: 10),
            Expanded(child: Text(onWhatsApp ? 'Open WhatsApp to see your 6-digit code' : 'Waiting to read the SMS automatically…', style: const TextStyle(color: Color(0xFF0D4A32), fontSize: 12.5))),
          ]),
        ),
        SizedBox(height: context.isTablet ? 0 : 8),
      ],
    );
  }
}
