import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/data/auth_repository.dart';
import '../router/app_router.dart';
import '../router/routes.dart';

/// Android channel ids – must match notification_channels.key on the server.
const _channels = <(String, String, Importance)>[
  ('class_alert', 'Class alerts', Importance.max),
  ('live_class', 'Live classes', Importance.high),
  ('course_update', 'New classes & notes', Importance.high),
  ('test_result', 'Results', Importance.defaultImportance),
  ('reminder', 'Reminders', Importance.defaultImportance),
  ('announcement', 'Announcements', Importance.defaultImportance),
  ('offer', 'Offers', Importance.low),
  ('payment', 'Payments', Importance.high),
  ('chat', 'Chat messages', Importance.high),
];

final _local = FlutterLocalNotificationsPlugin();
bool get _supported => !kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);

Future<void> _initLocal({void Function(NotificationResponse)? onTap}) async {
  await _local.initialize(
    const InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(requestAlertPermission: false, requestSoundPermission: false, requestBadgePermission: false),
    ),
    onDidReceiveNotificationResponse: onTap,
  );
  final android = _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
  for (final (id, name, imp) in _channels) {
    await android?.createNotificationChannel(AndroidNotificationChannel(
      id, name,
      importance: imp,
      playSound: true,
      enableVibration: true,
      audioAttributesUsage: id == 'class_alert' ? AudioAttributesUsage.alarm : AudioAttributesUsage.notification,
    ));
  }
}

/// Full-screen "ringing" notification for a class starting in 5 minutes (shown even when the app is closed).
Future<void> showClassAlert(Map<String, dynamic> data) async {
  final id = int.tryParse('${data['live_class_id']}') ?? 0;
  await _local.show(
    900000 + id,
    '${data['title'] ?? 'Live class'}',
    '${data['body'] ?? 'Your live class starts in 5 minutes'}',
    NotificationDetails(
      android: AndroidNotificationDetails(
        'class_alert', 'Class alerts',
        importance: Importance.max,
        priority: Priority.max,
        category: AndroidNotificationCategory.alarm,
        fullScreenIntent: true,
        ongoing: true,
        autoCancel: true,
        timeoutAfter: 5 * 60 * 1000,
        audioAttributesUsage: AudioAttributesUsage.alarm,
        additionalFlags: Int32List.fromList([4]), // FLAG_INSISTENT – keeps ringing until opened
        actions: const [
          AndroidNotificationAction('join', 'Join class', showsUserInterface: true),
          AndroidNotificationAction('dismiss', 'Dismiss', cancelNotification: true),
        ],
      ),
      iOS: const DarwinNotificationDetails(interruptionLevel: InterruptionLevel.timeSensitive, presentSound: true),
    ),
    payload: jsonEncode({'route': R.classAlert(id), 'join': R.live(id), ...data}),
  );
}

/// Runs in a separate isolate when the app is in the background / killed.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage m) async {
  await Firebase.initializeApp();
  if (m.data['channel'] == 'class_alert') {
    await _initLocal();
    await showClassAlert(m.data);
  }
}

final pushServiceProvider = Provider<PushService>((ref) => PushService(ref));

/// FCM token registration, foreground notifications and tap → screen.
class PushService {
  PushService(this._ref);
  final Ref _ref;
  bool _ready = false;
  final _subs = <StreamSubscription>[];

  Future<void> init() async {
    if (!_supported || _ready) return;
    try {
      await Firebase.initializeApp();
    } catch (e) {
      if (kDebugMode) debugPrint('Firebase not configured: $e');
      return;
    }
    _ready = true;
    FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);
    await _initLocal(onTap: (r) => _openPayload(r.payload, action: r.actionId));

    final fm = FirebaseMessaging.instance;
    await fm.requestPermission(alert: true, sound: true, badge: true);
    await _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.requestNotificationsPermission();
    await _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.requestFullScreenIntentPermission();
    await fm.setForegroundNotificationPresentationOptions(alert: true, sound: true, badge: true);

    _subs
      ..add(FirebaseMessaging.onMessage.listen(_foreground))
      ..add(FirebaseMessaging.onMessageOpenedApp.listen((m) => _open(m.data)))
      ..add(fm.onTokenRefresh.listen(_register));

    final initial = await fm.getInitialMessage();
    if (initial != null) _open(initial.data);
    final launch = await _local.getNotificationAppLaunchDetails();
    if (launch?.didNotificationLaunchApp == true) _openPayload(launch!.notificationResponse?.payload, action: launch.notificationResponse?.actionId);
  }

  /// Call after login (and on app start when logged in).
  Future<void> registerToken() async {
    if (!_ready) return;
    try {
      _register(await FirebaseMessaging.instance.getToken());
    } catch (_) {}
  }

  Future<String?> token() async => _ready ? FirebaseMessaging.instance.getToken() : null;

  void _register(String? token) {
    if (token == null || _ref.read(authControllerProvider).status != AuthStatus.authenticated) return;
    _ref.read(authRepositoryProvider).registerDevice(token).ignore();
  }

  void _foreground(RemoteMessage m) {
    final d = m.data;
    if (d['channel'] == 'class_alert') {
      _go(R.classAlert(int.tryParse('${d['live_class_id']}') ?? 0), extra: d);
      return;
    }
    // Android does not show FCM notifications while the app is open – show them ourselves.
    final n = m.notification;
    if (n == null || defaultTargetPlatform != TargetPlatform.android) return;
    final ch = _channels.firstWhere((c) => c.$1 == d['channel'], orElse: () => _channels[5]);
    _local.show(
      m.hashCode,
      n.title,
      n.body,
      NotificationDetails(android: AndroidNotificationDetails(ch.$1, ch.$2, importance: ch.$3, priority: Priority.high)),
      payload: jsonEncode(d),
    );
  }

  void _openPayload(String? payload, {String? action}) {
    if (payload == null) return;
    try {
      final d = (jsonDecode(payload) as Map).cast<String, dynamic>();
      if (action == 'join' && d['join'] != null) {
        _go('${d['join']}');
      } else if (action != 'dismiss') {
        _open(d);
      }
    } catch (_) {}
  }

  void _open(Map<String, dynamic> d) {
    if (d['channel'] == 'class_alert' || d['type'] == 'class_alert') {
      _go(R.classAlert(int.tryParse('${d['live_class_id']}') ?? 0), extra: d);
      return;
    }
    final link = d['route'] ?? d['deep_link'];
    if (link is String && link.startsWith('/')) _go(link);
  }

  void _go(String route, {Object? extra}) {
    // wait until the router is ready (cold start from a notification)
    Future<void>.delayed(const Duration(milliseconds: 400), () => _ref.read(routerProvider).push(route, extra: extra));
  }

  void dispose() {
    for (final s in _subs) {
      s.cancel();
    }
  }
}
