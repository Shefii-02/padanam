import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/responsive.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../application/chat_socket.dart';
import '../data/chat_repository.dart';

String chatTime(String iso) {
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return '';
  final now = DateTime.now();
  if (d.year == now.year && d.month == now.month && d.day == now.day) {
    final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
    return '$h:${d.minute.toString().padLeft(2, '0')} ${d.hour < 12 ? 'am' : 'pm'}';
  }
  if (now.difference(d).inDays < 7) return const ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][d.weekday - 1];
  return '${d.day}/${d.month}';
}

class ChatAvatar extends StatelessWidget {
  const ChatAvatar({super.key, required this.text, this.size = 46});
  final String text;
  final double size;

  @override
  Widget build(BuildContext context) {
    final isEmoji = text.runes.length <= 2 && text.runes.any((r) => r > 0x2000);
    return CircleAvatar(
      radius: size / 2,
      backgroundColor: AppColors.lavender,
      child: Text(isEmoji ? text : text.characters.take(2).toString().toUpperCase(),
          style: TextStyle(fontSize: isEmoji ? size * .45 : size * .32, fontWeight: FontWeight.w800, color: AppColors.primary)),
    );
  }
}

/// Chat tab: batch groups, broadcast channels, teachers and support.
class ChatsScreen extends ConsumerStatefulWidget {
  const ChatsScreen({super.key});

  @override
  ConsumerState<ChatsScreen> createState() => _ChatsScreenState();
}

class _ChatsScreenState extends ConsumerState<ChatsScreen> {
  int _filter = 0;

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    final socket = ref.watch(chatSocketProvider);
    return Scaffold(
      backgroundColor: p.bg,
      floatingActionButton: FloatingActionButton(tooltip: 'New chat', onPressed: () => context.push(R.newChat), child: const Icon(Icons.chat_rounded)),
      body: SafeArea(
        bottom: false,
        child: PageWidth(
          child: Column(children: [
            Padding(
              padding: EdgeInsets.fromLTRB(context.hPad, 12, context.hPad, 8),
              child: Row(children: [
                const Expanded(child: Text('Chats', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
                ValueListenableBuilder<bool>(
                  valueListenable: socket.connected,
                  builder: (_, on, __) => on ? const SizedBox.shrink() : const Tag('Connecting…', tone: Tone.grey),
                ),
              ]),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: context.hPad),
              child: ChipBar(items: const ['All', 'Groups', 'Teachers & support'], selected: _filter, onChanged: (i) => setState(() => _filter = i)),
            ),
            const SizedBox(height: 6),
            Expanded(
              child: AsyncView<List<Json>>(
                value: ref.watch(chatRoomsProvider),
                onRetry: () => ref.invalidate(chatRoomsProvider),
                data: (rooms) {
                  final list = rooms.where((r) => switch (_filter) { 1 => r.s('type') != 'direct', 2 => r.s('type') == 'direct', _ => true }).toList();
                  if (list.isEmpty) {
                    return const EmptyView(emoji: '💬', title: 'No chats yet', subtitle: 'Join a course to get its batch group, or message a teacher.');
                  }
                  return RefreshIndicator(
                    onRefresh: () => ref.refresh(chatRoomsProvider.future),
                    child: ListView.separated(
                      padding: const EdgeInsets.only(bottom: 96),
                      itemCount: list.length,
                      separatorBuilder: (_, __) => Divider(height: 1, indent: 76, color: p.line),
                      itemBuilder: (_, i) {
                        final r = list[i];
                        final last = r.m('last_message');
                        return ListTile(
                          contentPadding: EdgeInsets.symmetric(horizontal: context.hPad, vertical: 4),
                          leading: ChatAvatar(text: r.s('avatar', r.s('name'))),
                          title: Row(children: [
                            Expanded(child: Text(r.s('name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800))),
                            if (last.isNotEmpty) Text(chatTime(last.s('at')), style: TextStyle(fontSize: 11.5, color: r.i('unread') > 0 ? AppColors.primary2 : p.muted)),
                          ]),
                          subtitle: Row(children: [
                            Expanded(
                              child: Text(
                                last.isEmpty ? (r.s('type') == 'broadcast' ? 'Announcements' : 'Say hello 👋') : '${last.s('by').isNotEmpty && r.s('type') != 'direct' ? '${last.s('by')}: ' : ''}${last.s('text')}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (r.i('unread') > 0)
                              Container(
                                margin: const EdgeInsets.only(left: 8),
                                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                decoration: BoxDecoration(color: AppColors.primary2, borderRadius: BorderRadius.circular(12)),
                                child: Text('${r.i('unread')}', style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w800)),
                              ),
                          ]),
                          onTap: () => context.push(R.chatRoom(r.i('id'))),
                        );
                      },
                    ),
                  );
                },
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

/// New chat: only teachers of my batches and support (rules set in Admin → Chat rules).
class NewChatScreen extends ConsumerStatefulWidget {
  const NewChatScreen({super.key});

  @override
  ConsumerState<NewChatScreen> createState() => _NewChatScreenState();
}

class _NewChatScreenState extends ConsumerState<NewChatScreen> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    return SubPage(
      title: 'New message',
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            decoration: const InputDecoration(hintText: 'Search teachers or support', prefixIcon: Icon(Icons.search_rounded)),
            onSubmitted: (v) => setState(() => _q = v.trim()),
          ),
        ),
        Expanded(
          child: AsyncView<List<Json>>(
            value: ref.watch(chatContactsProvider(_q)),
            onRetry: () => ref.invalidate(chatContactsProvider(_q)),
            data: (list) => list.isEmpty
                ? const EmptyView(emoji: '🙋', title: 'Nobody to message yet', subtitle: 'You can message the teachers of your batches and Padanam support.')
                : ListView(children: [
                    for (final u in list)
                      ListTile(
                        leading: ChatAvatar(text: u.s('avatar', u.s('name')), size: 42),
                        title: Text(u.s('name'), style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text(u.s('role').replaceAll('_', ' '), style: TextStyle(color: p.muted)),
                        onTap: () async {
                          final id = await runAction(context, () => ref.read(chatRepositoryProvider).direct(u.i('id')));
                          if (id != null && context.mounted) {
                            ref.invalidate(chatRoomsProvider);
                            context.pushReplacement(R.chatRoom(id));
                          }
                        },
                      ),
                  ]),
          ),
        ),
      ]),
    );
  }
}

/// Deep link padanam.app/j/{code}: group preview → join / request.
class JoinGroupScreen extends ConsumerWidget {
  const JoinGroupScreen({super.key, required this.code});
  final String code;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return SubPage(
      title: 'Group invite',
      body: AsyncView<Json>(
        value: ref.watch(invitePreviewProvider(code)),
        onRetry: () => ref.invalidate(invitePreviewProvider(code)),
        data: (g) => ListView(padding: const EdgeInsets.all(24), children: [
          Center(child: ChatAvatar(text: g.s('avatar', g.s('name')), size: 88)),
          const SizedBox(height: 14),
          Text(g.s('name'), textAlign: TextAlign.center, style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800)),
          Text('Group · ${g.i('members_count')} members', textAlign: TextAlign.center, style: TextStyle(color: p.muted)),
          if (g.s('description').isNotEmpty) ...[const SizedBox(height: 14), AppCard(child: Text(g.s('description'), style: const TextStyle(height: 1.5)))],
          const SizedBox(height: 12),
          if (g.s('join_mode') == 'approval') const EmptyNote('An admin approves each request. You get a notification when you are in.'),
          if (g.b('requires_enrollment')) const EmptyNote('Only students of this batch can join.'),
          const SizedBox(height: 20),
          AppButton(g.s('join_mode') == 'approval' ? 'Request to join' : 'Join group', expand: true, onPressed: () async {
            final r = await runAction(context, () => ref.read(chatRepositoryProvider).join(code));
            if (r == null || !context.mounted) return;
            ref.invalidate(chatRoomsProvider);
            if (r.s('status') == 'requested') {
              showSoon(context, 'Request sent. An admin will approve it.');
              context.go(R.chat);
            } else {
              context.pushReplacement(R.chatRoom(r.i('room_id')));
            }
          }),
        ]),
      ),
    );
  }
}
