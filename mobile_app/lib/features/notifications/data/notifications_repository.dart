import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final notificationsRepositoryProvider = Provider<NotificationsRepository>((ref) => NotificationsRepository(ref.watch(apiClientProvider)));

final inboxProvider = FutureProvider.autoDispose<Paged>((ref) => ref.watch(notificationsRepositoryProvider).inbox());
final notificationPrefsProvider = FutureProvider.autoDispose<List<Json>>((ref) => ref.watch(notificationsRepositoryProvider).preferences());

class NotificationsRepository {
  NotificationsRepository(this._api);
  final ApiClient _api;

  Future<Paged> inbox({int page = 1}) => _api.page('/app/notifications', query: {'page': page});
  Future<void> markAllRead() => _api.post('/app/notifications/read');
  Future<void> opened(int id) => _api.post('/app/notifications/$id/opened');
  Future<List<Json>> preferences() async {
    final d = await _api.get('/app/notification-preferences');
    return [for (final e in (d is List ? d : const [])) if (e is Map) e.cast<String, dynamic>()];
  }

  Future<void> setPreference(String channel, bool enabled) => _api.post('/app/notification-preferences', body: {'channel': channel, 'enabled': enabled});
}
