import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../application/payment_service.dart';
import '../data/store_repository.dart';

/// Checkout: coupon, price breakup (GST included), gateway choice → pay.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key, required this.batchId, this.coupon});
  final int batchId;
  final String? coupon;

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  late final _code = TextEditingController(text: widget.coupon ?? '');
  Json? _quote;
  Object? _error;
  String? _applied;
  String _gateway = 'razorpay';
  bool _busy = false, _paying = false;

  @override
  void initState() {
    super.initState();
    _load(widget.coupon);
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _load(String? coupon) async {
    setState(() => _busy = true);
    try {
      final q = await ref.read(storeRepositoryProvider).quote(widget.batchId, coupon: coupon);
      setState(() {
        _quote = q;
        _error = null;
        _applied = q.m('coupon').isEmpty ? null : q.m('coupon').s('code');
        final gws = q.ls('gateways');
        if (gws.isNotEmpty && !gws.contains(_gateway)) _gateway = gws.first;
      });
      if (coupon != null && coupon.isNotEmpty && q.sn('coupon_error') != null && mounted) showSoon(context, q.s('coupon_error'));
    } catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pay() async {
    setState(() => _paying = true);
    try {
      final r = await ref.read(paymentServiceProvider).buy(batchId: widget.batchId, gateway: _quote!.i('total_paise') == 0 ? 'razorpay' : _gateway, coupon: _applied);
      if (!mounted) return;
      if (r.status == 'cancelled') {
        showSoon(context, 'Payment cancelled');
      } else if (r.status == 'failed') {
        showSoon(context, r.message ?? 'Payment failed. No money was taken – please try again.');
      } else {
        context.pushReplacement(R.paid(r.orderNo ?? ''));
      }
    } catch (e) {
      if (mounted) showSoon(context, e is ApiException ? e.message : 'Could not start the payment');
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    if (_quote == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: _error != null ? ErrorView(error: _error!, onRetry: () => _load(null)) : const LoadingView(),
      );
    }
    final q = _quote!;
    final free = q.i('total_paise') == 0;
    final gws = q.ls('gateways');
    return SubPage(
      title: 'Checkout',
      footer: AppButton(
        _paying ? 'Opening payment…' : (free ? 'Join now – free' : 'Pay ${q.s('total')} securely'),
        expand: true,
        onPressed: _paying || _busy || (!free && gws.isEmpty) ? null : _pay,
      ),
      children: [
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Coupon', style: TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(
                child: TextField(
                  controller: _code,
                  textCapitalization: TextCapitalization.characters,
                  enabled: _applied == null,
                  decoration: const InputDecoration(hintText: 'Enter code'),
                  onSubmitted: (v) => _load(v.trim().toUpperCase()),
                ),
              ),
              const SizedBox(width: 8),
              _applied == null
                  ? AppButton('Apply', small: true, onPressed: _busy ? null : () => _load(_code.text.trim().toUpperCase()))
                  : AppButton('Remove', small: true, outlined: true, onPressed: () {
                      _code.clear();
                      _load(null);
                    }),
            ]),
            if (_applied != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Row(children: [
                  const Icon(Icons.check_circle_rounded, color: AppColors.green, size: 18),
                  const SizedBox(width: 6),
                  Expanded(child: Text('$_applied applied – you save ${q.s('discount')}', style: const TextStyle(color: AppColors.green, fontWeight: FontWeight.w700))),
                ]),
              ),
            if (_applied == null)
              for (final o in q.l('offers'))
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Material(
                    color: AppColors.peach,
                    borderRadius: BorderRadius.circular(12),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: () {
                        _code.text = o.s('code');
                        ref.read(storeRepositoryProvider).track('launch_offer', {'code': o.s('code'), 'batch_id': widget.batchId});
                        _load(o.s('code'));
                      },
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(children: [
                          const Text('🏷️', style: TextStyle(fontSize: 18)),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(o.s('code'), style: const TextStyle(fontWeight: FontWeight.w800)),
                              Text('${o.s('title')} · save ${o.s('saves')}', style: TextStyle(fontSize: 12, color: p.muted)),
                            ]),
                          ),
                          const Text('Use', style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.peachInk)),
                        ]),
                      ),
                    ),
                  ),
                ),
          ]),
        ),
        const SizedBox(height: 12),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Text('Price details', style: TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            if (q.s('mrp') != q.s('price') && q.s('mrp') != '₹0') KeyValueRow('Original price', q.s('mrp')),
            KeyValueRow('Course price', q.s('price')),
            if (_applied != null) KeyValueRow('Coupon $_applied', '− ${q.s('discount')}'),
            const Divider(height: 18),
            Row(children: [
              const Expanded(child: Text('To pay', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16))),
              Text(q.s('total'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
            ]),
            const SizedBox(height: 4),
            Text('Access: ${q.s('validity')} · includes GST · invoice on WhatsApp', style: TextStyle(fontSize: 12, color: p.muted)),
          ]),
        ),
        if (!free) ...[
          const SectionTitle('Pay with'),
          if (gws.isEmpty) const EmptyNote('Online payment is not set up yet. Please contact the Padanam office.'),
          for (final g in gws)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: RadioListTile<String>(
                value: g,
                groupValue: _gateway,
                onChanged: (v) => setState(() => _gateway = v!),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: _gateway == g ? AppColors.primary2 : p.line)),
                tileColor: p.card,
                title: Text(g == 'razorpay' ? 'UPI, cards, net banking' : 'PhonePe', style: const TextStyle(fontWeight: FontWeight.w800)),
                subtitle: Text(g == 'razorpay' ? 'GPay, PhonePe, Paytm and all UPI apps · Razorpay' : 'Pay in the PhonePe app'),
              ),
            ),
        ],
        const SizedBox(height: 8),
        Center(child: Text('🔒 Payments are secured by the payment gateway. We never see your card or UPI PIN.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: p.muted))),
      ],
    );
  }
}
