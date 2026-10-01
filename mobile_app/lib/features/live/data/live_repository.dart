import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final liveRepositoryProvider = Provider<LiveRepository>((ref) => LiveRepository(ref.watch(apiClientProvider)));

/// Upcoming + live classes of my batches (and the ones I teach).
final upcomingLiveProvider = FutureProvider.autoDispose<List<Json>>((ref) => ref.watch(liveRepositoryProvider).upcoming());

/// Join → {id, title, status, starts_at, youtube_id, stream_url, recording_content_id, chat_room_id}
final liveJoinProvider = FutureProvider.autoDispose.family<Json, int>((ref, id) => ref.watch(liveRepositoryProvider).join(id));

class LiveRepository {
  LiveRepository(this._api);
  final ApiClient _api;

  Future<List<Json>> upcoming() async {
    final d = await _api.get('/app/live-classes');
    return [for (final e in (d is List ? d : const [])) if (e is Map) e.cast<String, dynamic>()];
  }

  Future<Json> join(int id) async => asJson(await _api.get('/app/live-classes/$id/join'));

  // ---- teacher ----
  Future<Json> goLive(int id, String url) async => asJson(await _api.post('/app/live-classes/$id/go-live', body: {'url': url}));
  Future<Json> end(int id) async => asJson(await _api.post('/app/live-classes/$id/end'));
}
