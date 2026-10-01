import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../../core/config/env.dart';
import '../../../core/storage/local_store.dart';
import '../../../core/utils/json.dart';
import '../../auth/application/auth_controller.dart';
import '../data/chat_repository.dart';

/// One Socket.IO connection for the whole app (chat + live class events), same JWT as the API.
final chatSocketProvider = Provider<ChatSocket>((ref) {
  final loggedIn = ref.watch(authControllerProvider.select((s) => s.status == AuthStatus.authenticated));
  final s = ChatSocket(ref);
  if (loggedIn) s.connect();
  ref.onDispose(s.dispose);
  return s;
});

/// Server → app events, one stream: (event name, payload).
typedef ChatEvent = ({String name, Json data});

class ChatSocket {
  ChatSocket(this._ref);
  final Ref _ref;
  io.Socket? _s;
  final _events = StreamController<ChatEvent>.broadcast();
  final connected = ValueNotifier<bool>(false);

  Stream<ChatEvent> get events => _events.stream;
  Stream<Json> on(String name) => events.where((e) => e.name == name).map((e) => e.data);

  static const _names = [
    'message:new', 'message:edited', 'message:deleted', 'message:read', 'typing', 'poll:updated',
    'room:added', 'room:removed', 'room:updated', 'member:updated', 'member:left', 'join:requested', 'live:started', 'live:ended',
  ];

  void connect() {
    final token = _ref.read(localStoreProvider).token;
    if (token == null || _s != null) return;
    _s = io.io(
      Env.realtimeUrl,
      io.OptionBuilder().setTransports(['websocket']).setAuth({'token': token}).enableReconnection().disableAutoConnect().build(),
    );
    _s!
      ..onConnect((_) => connected.value = true)
      ..onDisconnect((_) => connected.value = false)
      ..onConnectError((e) {
        connected.value = false;
        if (kDebugMode) debugPrint('chat socket: $e');
      });
    for (final n in _names) {
      _s!.on(n, (d) {
        _events.add((name: n, data: asJson(d)));
        // keep the chats list fresh (unread counts, last message)
        if (n == 'message:new' || n.startsWith('room:')) _ref.invalidate(chatRoomsProvider);
      });
    }
    _s!.connect();
  }

  /// Sends and waits for the server ack → the saved message.
  Future<Json> send(int roomId, {String type = 'text', String? body, Json? meta, int? replyTo}) {
    final c = Completer<Json>();
    final clientId = '${DateTime.now().microsecondsSinceEpoch}';
    _s?.emitWithAck('message:send', {
      'room_id': roomId, 'client_id': clientId, 'type': type,
      if (body != null) 'body': body, if (meta != null) 'meta': meta, if (replyTo != null) 'reply_to_id': replyTo,
    }, ack: (res) {
      final r = asJson(res);
      r.b('ok') ? c.complete(r.m('message')) : c.completeError(r.s('message', 'Could not send'));
    });
    if (_s == null || !connected.value) {
      Future<void>.delayed(const Duration(seconds: 8), () {
        if (!c.isCompleted) c.completeError('No connection. Message not sent.');
      });
    }
    return c.future.timeout(const Duration(seconds: 15), onTimeout: () => throw 'Message not sent. Check your internet.');
  }

  void read(int roomId, int messageId) => _s?.emit('message:read', {'room_id': roomId, 'message_id': messageId});
  void typing(int roomId, bool on) => _s?.emit('typing', {'room_id': roomId, 'typing': on});
  void delete(int messageId) => _s?.emit('message:delete', {'id': messageId});
  void subscribe(int roomId) => _s?.emit('room:subscribe', {'room_id': roomId});

  void dispose() {
    _s?.dispose();
    _s = null;
    _events.close();
    connected.dispose();
  }
}
