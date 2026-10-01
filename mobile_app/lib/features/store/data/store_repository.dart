import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final storeRepositoryProvider = Provider<StoreRepository>((ref) => StoreRepository(ref.watch(apiClientProvider)));

/// Store list filters → cached per filter.
final storeCoursesProvider = FutureProvider.family<Paged, ({String? category, String? search, String? pricing})>(
  (ref, f) => ref.watch(storeRepositoryProvider).courses(category: f.category, search: f.search, pricing: f.pricing),
);

/// Course page: {course, batches, demo, tabs, is_enrolled, is_staff, my_batches}
final courseDetailProvider = FutureProvider.family<Json, String>((ref, slug) => ref.watch(storeRepositoryProvider).course(slug));

/// My orders (paid / refunded) with invoices.
final myOrdersProvider = FutureProvider<Paged>((ref) => ref.watch(storeRepositoryProvider).orders());

class StoreRepository {
  StoreRepository(this._api);
  final ApiClient _api;

  Future<Paged> courses({String? category, String? search, String? pricing, int page = 1}) =>
      _api.page('/public/courses', query: {'category': category, 'search': search, 'pricing': pricing, 'page': page});

  Future<Json> course(String slugOrId) async => asJson(await _api.get('/public/courses/$slugOrId'));

  /// Price breakdown + coupon check + available gateways.
  Future<Json> quote(int batchId, {String? coupon}) async =>
      asJson(await _api.post('/app/checkout/quote', body: {'batch_id': batchId, if (coupon != null && coupon.isNotEmpty) 'coupon': coupon}));

  /// Creates the order → {order_no, status, gateway, key/order_id/amount (razorpay) | redirect_url (phonepe)}
  Future<Json> checkout(int batchId, String gateway, {String? coupon}) async => asJson(await _api.post('/app/checkout', body: {
        'batch_id': batchId,
        'gateway': gateway,
        if (coupon != null && coupon.isNotEmpty) 'coupon': coupon,
      }));

  Future<Json> verifyRazorpay(String orderNo, {required String orderId, required String paymentId, required String signature}) async =>
      asJson(await _api.post('/app/orders/$orderNo/verify', body: {
        'razorpay_order_id': orderId,
        'razorpay_payment_id': paymentId,
        'razorpay_signature': signature,
      }));

  /// Asks the server to check with the gateway (PhonePe return, or app killed during payment).
  Future<Json> orderStatus(String orderNo) async => asJson(await _api.get('/app/orders/$orderNo/status'));

  Future<Paged> orders({int page = 1}) => _api.page('/app/orders', query: {'page': page});

  /// Lead tracking: demo watched, offer tapped, course viewed (feeds the admin Leads page).
  Future<void> track(String event, Json data) async {
    try {
      await _api.post('/app/events', body: {'event': event, 'properties': data});
    } catch (_) {}
  }
}
