import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../../auth/application/auth_controller.dart';
import '../application/chat_socket.dart';
import '../data/chat_repository.dart';
import 'chats_screen.dart';

/// One chat room: history (paged), live messages over the socket, photos & files, typing, delete, report.
class ChatRoomScreen extends ConsumerStatefulWidget {
  const ChatRoomScreen({super.key, required this.roomId});
  final int roomId;

  @override
  ConsumerState<ChatRoomScreen> createState() => _ChatRoomScreenState();
}

class _ChatRoomScreenState extends ConsumerState<ChatRoomScreen> {
  final _text = TextEditingController();
  final _scroll = ScrollController();
  final List<Json> _msgs = []; // newest first (list is reversed)
  final _subs = <StreamSubscription>[];
  bool _loading = true, _more = true, _sending = false;
  String? _typing;
  Timer? _typingOff, _typingSent;

  ChatSocket get _socket => ref.read(chatSocketProvider);
  int get _me => ref.read(authControllerProvider).user?.id ?? 0;

  @override
  void initState() {
    super.initState();
    _load();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 200) _load(older: true);
    });
    final s = ref.read(chatSocketProvider);
    s.subscribe(widget.roomId);
    _subs
      ..add(s.on('message:new').listen((d) {
        final m = d.m('message').isEmpty ? d : d.m('message');
        if (d.i('room_id', m.i('room_id')) != widget.roomId) return;
        if (_msgs.any((x) => x.i('id') == m.i('id'))) return;
        setState(() => _msgs.insert(0, m));
        _markRead();
      }))
      ..add(s.on('message:deleted').listen((d) {
        setState(() {
          for (final m in _msgs) {
            if (m.i('id') == d.i('id')) {
              m['deleted'] = true;
              m['body'] = null;
            }
          }
        });
      }))
      ..add(s.on('typing').listen((d) {
        if (d.i('room_id') != widget.roomId || d.i('user_id') == _me) return;
        setState(() => _typing = d.b('typing') ? d.s('name', 'Someone') : null);
        _typingOff?.cancel();
        _typingOff = Timer(const Duration(seconds: 5), () => mounted ? setState(() => _typing = null) : null);
      }));
  }

  Future<void> _load({bool older = false}) async {
    if (older && (!_more || _loading)) return;
    setState(() => _loading = true);
    try {
      final list = await ref.read(chatRepositoryProvider).messages(widget.roomId, beforeId: older && _msgs.isNotEmpty ? _msgs.last.i('id') : null);
      setState(() {
        _msgs.addAll(list.where((m) => !_msgs.any((x) => x.i('id') == m.i('id'))));
        _more = list.length >= 50;
      });
      if (!older) _markRead();
    } catch (e) {
      if (mounted) showSoon(context, '$e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _markRead() {
    if (_msgs.isNotEmpty) _socket.read(widget.roomId, _msgs.first.i('id'));
    ref.invalidate(chatRoomsProvider);
  }

  Future<void> _send({String type = 'text', String? body, Json? meta}) async {
    setState(() => _sending = true);
    try {
      final m = await _socket.send(widget.roomId, type: type, body: body, meta: meta);
      if (!_msgs.any((x) => x.i('id') == m.i('id'))) setState(() => _msgs.insert(0, m));
      if (type == 'text') _text.clear();
    } catch (e) {
      if (mounted) showSoon(context, '$e');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _attach() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.photo_camera_rounded), title: const Text('Camera'), onTap: () => Navigator.pop(context, 'camera')),
          ListTile(leading: const Icon(Icons.photo_rounded), title: const Text('Photo'), onTap: () => Navigator.pop(context, 'gallery')),
          ListTile(leading: const Icon(Icons.picture_as_pdf_rounded), title: const Text('PDF / document'), onTap: () => Navigator.pop(context, 'file')),
        ]),
      ),
    );
    String? path;
    if (choice == 'camera' || choice == 'gallery') {
      path = (await ImagePicker().pickImage(source: choice == 'camera' ? ImageSource.camera : ImageSource.gallery, imageQuality: 75, maxWidth: 1800))?.path;
    } else if (choice == 'file') {
      path = (await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: ['pdf', 'doc', 'docx']))?.files.single.path;
    }
    if (path == null || !mounted) return;
    setState(() => _sending = true);
    final up = await runAction(context, () => ref.read(chatRepositoryProvider).upload(widget.roomId, path!));
    if (mounted) setState(() => _sending = false);
    if (up != null) await _send(type: up.s('type', 'file'), meta: up.m('meta'));
  }

  void _onTyping(String v) {
    if (_typingSent?.isActive ?? false) return;
    _socket.typing(widget.roomId, v.isNotEmpty);
    _typingSent = Timer(const Duration(seconds: 3), () {});
  }

  Future<void> _msgMenu(Json m, Json room) async {
    final mine = m.m('user').i('id') == _me;
    final canDelete = mine || ['owner', 'admin', 'moderator'].contains(room.s('my_role'));
    final a = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          if (m.s('type') == 'text') ListTile(leading: const Icon(Icons.copy_rounded), title: const Text('Copy'), onTap: () => Navigator.pop(context, 'copy')),
          if (canDelete) ListTile(leading: const Icon(Icons.delete_outline_rounded), title: const Text('Delete'), onTap: () => Navigator.pop(context, 'delete')),
          if (!mine) ListTile(leading: const Icon(Icons.flag_outlined), title: const Text('Report'), onTap: () => Navigator.pop(context, 'report')),
        ]),
      ),
    );
    if (!mounted) return;
    switch (a) {
      case 'copy':
        await SharePlus.instance.share(ShareParams(text: m.s('body')));
      case 'delete':
        _socket.delete(m.i('id'));
      case 'report':
        await runAction(context, () => ref.read(chatRepositoryProvider).report(m.i('id'), 'abuse'), success: 'Reported to the moderators');
    }
  }

  @override
  void dispose() {
    for (final s in _subs) {
      s.cancel();
    }
    _typingOff?.cancel();
    _typingSent?.cancel();
    _text.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final room = ref.watch(chatRoomProvider(widget.roomId));
    return AsyncView<Json>(
      value: room,
      onRetry: () => ref.invalidate(chatRoomProvider(widget.roomId)),
      loading: const Scaffold(body: LoadingView()),
      data: (r) => Scaffold(
        backgroundColor: p.bg,
        appBar: AppBar(
          titleSpacing: 0,
          title: Row(children: [
            ChatAvatar(text: r.s('avatar', r.s('name')), size: 38),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(r.s('name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                Text(_typing != null ? '$_typing is typing…' : (r.s('type') == 'direct' ? 'Direct chat' : '${r.i('members_count')} members'),
                    style: TextStyle(fontSize: 12, color: _typing != null ? AppColors.green : p.muted)),
              ]),
            ),
          ]),
          actions: [
            PopupMenuButton<String>(
              onSelected: (v) async {
                if (v == 'invite') await SharePlus.instance.share(ShareParams(text: 'Join ${r.s('name')} on Padanam: ${r.s('invite_url')}'));
                if (v == 'mute') await runAction(context, () => ref.read(chatRepositoryProvider).prefs(widget.roomId, notifications: false), success: 'Notifications muted');
                if (v == 'unmute') await runAction(context, () => ref.read(chatRepositoryProvider).prefs(widget.roomId, notifications: true), success: 'Notifications on');
                if (v == 'leave' && context.mounted) {
                  var left = false;
                  await runAction(context, () async {
                    await ref.read(chatRepositoryProvider).leave(widget.roomId);
                    left = true;
                  }, success: 'You left the group');
                  if (left && context.mounted) Navigator.of(context).pop();
                }
              },
              itemBuilder: (_) => [
                if (r.sn('invite_url') != null) const PopupMenuItem(value: 'invite', child: Text('Share invite link')),
                const PopupMenuItem(value: 'mute', child: Text('Mute notifications')),
                const PopupMenuItem(value: 'unmute', child: Text('Unmute')),
                if (r.s('type') != 'batch_group' && r.s('type') != 'direct') const PopupMenuItem(value: 'leave', child: Text('Leave group')),
              ],
            ),
          ],
        ),
        body: Column(children: [
          Expanded(
            child: _msgs.isEmpty && !_loading
                ? const EmptyView(emoji: '👋', title: 'No messages yet')
                : ListView.builder(
                    controller: _scroll,
                    reverse: true,
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
                    itemCount: _msgs.length + (_loading ? 1 : 0),
                    itemBuilder: (_, i) {
                      if (i >= _msgs.length) return const Padding(padding: EdgeInsets.all(12), child: Center(child: CircularProgressIndicator(strokeWidth: 2)));
                      final m = _msgs[i];
                      return _Bubble(m: m, mine: m.m('user').i('id') == _me, showName: r.s('type') != 'direct', onLongPress: () => _msgMenu(m, r));
                    },
                  ),
          ),
          if (r.b('can_send'))
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(6, 6, 8, 8),
                decoration: BoxDecoration(color: p.card, border: Border(top: BorderSide(color: p.line))),
                child: Row(children: [
                  if (r.m('settings').b('send_media', true) || r.s('my_role') != 'member')
                    IconButton(tooltip: 'Attach', onPressed: _sending ? null : _attach, icon: Icon(Icons.attach_file_rounded, color: p.muted)),
                  Expanded(
                    child: TextField(
                      controller: _text,
                      minLines: 1,
                      maxLines: 5,
                      onChanged: _onTyping,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(hintText: 'Message', contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 10)),
                    ),
                  ),
                  const SizedBox(width: 6),
                  IconButton.filled(
                    tooltip: 'Send',
                    onPressed: _sending ? null : () => _text.text.trim().isEmpty ? null : _send(body: _text.text.trim()),
                    icon: _sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_rounded),
                  ),
                ]),
              ),
            )
          else
            SafeArea(
              top: false,
              child: Padding(padding: const EdgeInsets.all(14), child: Text('Only admins can send messages here.', textAlign: TextAlign.center, style: TextStyle(color: p.muted))),
            ),
        ]),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.m, required this.mine, required this.showName, required this.onLongPress});
  final Json m;
  final bool mine, showName;
  final VoidCallback onLongPress;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    if (m.s('type') == 'system') {
      return Center(
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 6),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(color: p.card, borderRadius: BorderRadius.circular(10)),
          child: Text(m.s('body'), style: TextStyle(fontSize: 12, color: p.muted)),
        ),
      );
    }
    final meta = m.m('meta');
    Widget content;
    if (m.b('deleted')) {
      content = Text('Message deleted', style: TextStyle(fontStyle: FontStyle.italic, color: p.muted));
    } else if (m.s('type') == 'image') {
      content = ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.network(meta.s('url'), width: 220, fit: BoxFit.cover));
    } else if (m.s('type') == 'file' || m.s('type') == 'audio') {
      content = InkWell(
        onTap: () => launchUrl(Uri.parse(meta.s('url')), mode: LaunchMode.externalApplication),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(m.s('type') == 'audio' ? Icons.mic_rounded : Icons.description_rounded, color: AppColors.red),
          const SizedBox(width: 8),
          Flexible(child: Text(meta.s('name', 'File'), style: const TextStyle(fontWeight: FontWeight.w700))),
        ]),
      );
    } else {
      content = SelectableText(m.s('body'), style: const TextStyle(fontSize: 14.5, height: 1.4));
    }
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: GestureDetector(
        onLongPress: m.b('deleted') ? null : onLongPress,
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 3),
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
          constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .78),
          decoration: BoxDecoration(
            color: mine ? AppColors.lavender : p.card,
            border: mine ? null : Border.all(color: p.line),
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(16),
              topRight: const Radius.circular(16),
              bottomLeft: Radius.circular(mine ? 16 : 4),
              bottomRight: Radius.circular(mine ? 4 : 16),
            ),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            if (showName && !mine) Text(m.m('user').s('name'), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary2)),
            content,
            const SizedBox(height: 2),
            Align(alignment: Alignment.centerRight, child: Text('${chatTime(m.s('created_at'))}${m.b('edited') ? ' · edited' : ''}', style: TextStyle(fontSize: 10.5, color: p.muted))),
          ]),
        ),
      ),
    );
  }
}
