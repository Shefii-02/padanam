import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/local_store.dart';
import '../../../core/utils/json.dart';
import '../../auth/application/auth_controller.dart';
import '../data/store_repository.dart';

final paymentServiceProvider = Provider<PaymentService>((ref) {
  final s = PaymentService(ref);
  ref.onDispose(s.dispose);
  return s;
});

/// Result shown on the payment screen.
class PayResult {
  PayResult(this.status, {this.orderNo, this.data = const {}, this.message});
  final String status; // paid | pending | failed | cancelled
  final String? orderNo;
  final Json data;
  final String? message;
  bool get paid => status == 'paid';
}

/// Runs a purchase end to end:
///   /checkout → Razorpay sheet (or PhonePe page) → /verify or /status polling.
/// Webhooks confirm the payment on the server too, so a closed app never loses a paid order.
class PaymentService {
  PaymentService(this._ref);
  final Ref _ref;
  Razorpay? _rz;

  StoreRepository get _repo => _ref.read(storeRepositoryProvider);

  static const _kPending = 'pending_order_no';

  /// Order started but not confirmed yet (app was closed during payment) – checked on start.
  String? get pendingOrder => _ref.read(sharedPrefsProvider).getString(_kPending);
  Future<void> _setPending(String? v) async {
    final p = _ref.read(sharedPrefsProvider);
    v == null ? await p.remove(_kPending) : await p.setString(_kPending, v);
  }

  Future<PayResult> buy({required int batchId, required String gateway, String? coupon}) async {
    final order = await _repo.checkout(batchId, gateway, coupon: coupon);
    final orderNo = order.s('order_no');
    if (order.s('status') == 'paid') return PayResult('paid', orderNo: orderNo, data: await _repo.orderStatus(orderNo));
    await _setPending(orderNo);

    if (order.s('gateway') == 'phonepe') {
      final url = order.sn('redirect_url');
      if (url == null) throw ApiException('PhonePe is not available right now. Try UPI / cards.');
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      // PhonePe returns to padanam.app/paid/{order} (deep link) – the payment screen polls status.
      return PayResult('pending', orderNo: orderNo);
    }
    return _razorpay(order);
  }

  Future<PayResult> _razorpay(Json order) {
    final done = Completer<PayResult>();
    final orderNo = order.s('order_no');
    final user = _ref.read(authControllerProvider).user;
    _rz?.clear();
    _rz = Razorpay()
      ..on(Razorpay.EVENT_PAYMENT_SUCCESS, (PaymentSuccessResponse r) async {
        try {
          final data = await _repo.verifyRazorpay(orderNo, orderId: r.orderId ?? order.s('order_id'), paymentId: r.paymentId ?? '', signature: r.signature ?? '');
          await _setPending(null);
          if (!done.isCompleted) done.complete(PayResult(data.s('status', 'paid'), orderNo: orderNo, data: data));
        } catch (e) {
          // signature check failed on our side – the webhook will still confirm a real payment
          if (!done.isCompleted) done.complete(PayResult('pending', orderNo: orderNo, message: '$e'));
        }
      })
      ..on(Razorpay.EVENT_PAYMENT_ERROR, (PaymentFailureResponse r) {
        final cancelled = r.code == Razorpay.PAYMENT_CANCELLED;
        if (!done.isCompleted) done.complete(PayResult(cancelled ? 'cancelled' : 'failed', orderNo: orderNo, message: r.message));
      })
      ..on(Razorpay.EVENT_EXTERNAL_WALLET, (ExternalWalletResponse r) {
        if (!done.isCompleted) done.complete(PayResult('pending', orderNo: orderNo));
      });

    _rz!.open({
      'key': order.s('key'),
      'order_id': order.s('order_id'),
      'amount': order.i('amount'),
      'currency': 'INR',
      'name': 'Padanam',
      'description': order.s('description', 'Course purchase'),
      'prefill': {'contact': user?.phone ?? '', 'name': user?.name ?? ''},
      'theme': {'color': '#1B2A7A'},
      'retry': {'enabled': true, 'max_count': 2},
    });
    return done.future;
  }

  /// Poll after returning from PhonePe / reopening the app.
  Future<PayResult> check(String orderNo, {int tries = 6}) async {
    for (var i = 0; i < tries; i++) {
      final d = await _repo.orderStatus(orderNo);
      if (d.s('status') == 'paid') {
        await _setPending(null);
        return PayResult('paid', orderNo: orderNo, data: d);
      }
      if (d.s('status') == 'failed' || d.s('status') == 'expired') {
        await _setPending(null);
        return PayResult('failed', orderNo: orderNo, data: d);
      }
      await Future<void>.delayed(Duration(seconds: 2 + i));
    }
    return PayResult('pending', orderNo: orderNo);
  }

  void dispose() {
    try {
      _rz?.clear();
    } catch (e) {
      if (kDebugMode) debugPrint('$e');
    }
  }
}
