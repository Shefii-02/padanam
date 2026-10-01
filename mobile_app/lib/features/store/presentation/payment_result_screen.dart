import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/ui.dart';
import '../../learning/data/learning_repository.dart';
import '../application/payment_service.dart';
import '../data/store_repository.dart';

/// /paid/{orderNo}: opened after Razorpay, after PhonePe (deep link), or from a WhatsApp payment link.
class PaymentResultScreen extends ConsumerStatefulWidget {
  const PaymentResultScreen({super.key, required this.orderNo});
  final String orderNo;

  @override
  ConsumerState<PaymentResultScreen> createState() => _PaymentResultScreenState();
}

class _PaymentResultScreenState extends ConsumerState<PaymentResultScreen> {
  PayResult? _r;
  bool _checking = true;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    setState(() => _checking = true);
    try {
      final r = await ref.read(paymentServiceProvider).check(widget.orderNo);
      if (r.paid) {
        ref.invalidate(myCoursesProvider);
        ref.invalidate(myOrdersProvider);
      }
      if (mounted) setState(() => _r = r);
    } catch (_) {
      if (mounted) setState(() => _r = PayResult('pending', orderNo: widget.orderNo));
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final r = _r;
    final paid = r?.paid ?? false;
    final d = r?.data ?? const <String, dynamic>{};
    return Scaffold(
      backgroundColor: p.bg,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: _checking && r == null
                  ? const Column(mainAxisSize: MainAxisSize.min, children: [
                      CircularProgressIndicator(),
                      SizedBox(height: 16),
                      Text('Confirming your payment…', style: TextStyle(fontWeight: FontWeight.w700)),
                    ])
                  : Column(mainAxisSize: MainAxisSize.min, children: [
                      CircleAvatar(
                        radius: 48,
                        backgroundColor: paid ? AppColors.mint : AppColors.peach,
                        child: Icon(paid ? Icons.check_rounded : Icons.hourglass_top_rounded, size: 48, color: paid ? AppColors.green : AppColors.peachInk),
                      ),
                      const SizedBox(height: 16),
                      Text(paid ? 'Course unlocked!' : (r?.status == 'failed' ? 'Payment failed' : 'Waiting for payment'),
                          style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      Text(
                        paid
                            ? 'You paid ${d.s('total')} for ${d.s('course')} (${d.s('batch')}).'
                            : r?.status == 'failed'
                                ? 'No money was taken. You can try again.'
                                : 'If money was taken, your course unlocks automatically within a few minutes. We will notify you.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: p.muted, height: 1.5),
                      ),
                      const SizedBox(height: 14),
                      Text('Order ${widget.orderNo}', style: TextStyle(fontSize: 12.5, color: p.muted)),
                      if (paid) ...[
                        const SizedBox(height: 6),
                        const Text('🧾 GST invoice is sent to your WhatsApp', style: TextStyle(fontSize: 13, color: AppColors.green, fontWeight: FontWeight.w700)),
                      ],
                      const SizedBox(height: 24),
                      if (paid) ...[
                        AppButton('Start learning', expand: true, onPressed: () => context.go(R.learn(d.i('course_id'), title: d.s('course')))),
                        if (d.sn('invoice_url') != null) ...[
                          const SizedBox(height: 10),
                          AppButton('Download invoice', expand: true, outlined: true, onPressed: () => launchUrl(Uri.parse(d.s('invoice_url')), mode: LaunchMode.externalApplication)),
                        ],
                      ] else ...[
                        AppButton(_checking ? 'Checking…' : 'Check again', expand: true, onPressed: _checking ? null : _check),
                        const SizedBox(height: 10),
                        AppButton('Go to home', expand: true, outlined: true, onPressed: () => context.go(R.home)),
                      ],
                    ]),
            ),
          ),
        ),
      ),
    );
  }
}
