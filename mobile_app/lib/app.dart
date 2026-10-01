import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/links/deep_links.dart';
import 'core/push/push_service.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'core/update/update_gate.dart';
import 'features/auth/application/auth_controller.dart';
import 'features/chat/application/chat_socket.dart';

class PadanamApp extends ConsumerStatefulWidget {
  const PadanamApp({super.key});

  @override
  ConsumerState<PadanamApp> createState() => _PadanamAppState();
}

class _PadanamAppState extends ConsumerState<PadanamApp> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await ref.read(pushServiceProvider).init();
      await ref.read(deepLinksProvider).init();
    });
  }

  @override
  Widget build(BuildContext context) {
    // after login: register the FCM token and open the chat socket
    ref.listen(authControllerProvider.select((s) => s.status), (prev, next) {
      if (next == AuthStatus.authenticated && prev != AuthStatus.authenticated) {
        ref.read(pushServiceProvider).registerToken();
        ref.read(chatSocketProvider);
      }
    });
    return MaterialApp.router(
      title: 'Padanam',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.system,
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => UpdateGate(child: child ?? const SizedBox()),
    );
  }
}
