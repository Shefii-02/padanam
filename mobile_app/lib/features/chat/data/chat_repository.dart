import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final chatRepositoryProvider = Provider<ChatRepository>((ref) => ChatRepository(ref.watch(apiClientProvider)));

/// My rooms with unread counts and last message.
final chatRoomsProvider = FutureProvider<List<Json>>((ref) => ref.watch(chatRepositoryProvider).rooms());
final chatRoomProvider = FutureProvider.autoDispose.family<Json, int>((ref, id) => ref.watch(chatRepositoryProvider).room(id));
final chatContactsProvider = FutureProvider.autoDispose.family<List<Json>, String>((ref, q) => ref.watch(chatRepositoryProvider).contacts(q));
final invitePreviewProvider = FutureProvider.autoDispose.family<Json, String>((ref, code) => ref.watch(chatRepositoryProvider).invitePreview(code));

List<Json> _list(dynamic d) => [for (final e in (d is List ? d : const [])) if (e is Map) e.cast<String, dynamic>()];

/// REST side of chat (history, rooms, joining). Live messages go through the socket (ChatSocket).
class ChatRepository {
  ChatRepository(this._api);
  final ApiClient _api;

  Future<List<Json>> rooms() async => _list(await _api.get('/app/chat/rooms'));
  Future<Json> room(int id) async => asJson(await _api.get('/app/chat/rooms/$id'));

  /// Newest first, 50 per page. Pass [beforeId] for older messages.
  Future<List<Json>> messages(int roomId, {int? beforeId}) async => _list(await _api.get('/app/chat/rooms/$roomId/messages', query: {'before_id': beforeId}));
  Future<List<Json>> members(int roomId) async => _list(await _api.get('/app/chat/rooms/$roomId/members'));
  Future<List<Json>> contacts(String search) async => _list(await _api.get('/app/chat/contacts', query: {'search': search.isEmpty ? null : search}));

  /// Opens (or creates) a 1:1 chat → room id.
  Future<int> direct(int userId) async => asJson(await _api.post('/app/chat/direct/$userId')).i('room_id');

  Future<Json> invitePreview(String code) async => asJson(await _api.get('/public/invites/$code'));

  /// → {status: joined | requested | already, room_id}
  Future<Json> join(String code) async => asJson(await _api.post('/app/chat/join/$code'));

  /// Upload a photo / PDF / voice note first, then send its {type, meta} over the socket.
  Future<Json> upload(int roomId, String path) async => asJson(await _api.upload('/app/chat/rooms/$roomId/upload', files: {'file': path}));

  Future<void> leave(int roomId) => _api.post('/app/chat/rooms/$roomId/leave');
  Future<void> prefs(int roomId, {bool? notifications, bool? pinned}) =>
      _api.post('/app/chat/rooms/$roomId/prefs', body: {if (notifications != null) 'notifications': notifications, if (pinned != null) 'pinned': pinned});
  Future<void> report(int messageId, String reason) => _api.post('/app/chat/messages/$messageId/report', body: {'reason': reason});
}
