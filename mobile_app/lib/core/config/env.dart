import 'package:flutter/foundation.dart';

/// Build-time config. Override with:
///   flutter run --dart-define=API_BASE_URL=https://api.padanam.app/api/v1 --dart-define=REALTIME_URL=https://rt.padanam.app
class Env {
  Env._();

  static const _override = String.fromEnvironment('API_BASE_URL');
  static const _rt = String.fromEnvironment('REALTIME_URL');

  /// Build number (keep in sync with pubspec version +N). Used for update checks and device registration.
  static const build = int.fromEnvironment('APP_BUILD', defaultValue: 10);

  /// Public web host used in share links and deep links (padanam.app/c/{slug}).
  static const linkHost = String.fromEnvironment('LINK_HOST', defaultValue: 'https://padanam.app');

  static String get baseUrl {
    if (_override.isNotEmpty) return _override;
    if (kIsWeb) return 'http://localhost:8000/api/v1';
    // Android emulator reaches the host machine on 10.0.2.2
    if (defaultTargetPlatform == TargetPlatform.android) return 'http://10.0.2.2:8000/api/v1';
    return 'http://127.0.0.1:8000/api/v1';
  }

  /// Socket.IO chat server.
  static String get realtimeUrl {
    if (_rt.isNotEmpty) return _rt;
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) return 'http://10.0.2.2:4000';
    return 'http://127.0.0.1:4000';
  }

  static const connectTimeout = Duration(seconds: 12);
  static const receiveTimeout = Duration(seconds: 20);

  static String get platform {
    if (kIsWeb) return 'web';
    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'android',
      TargetPlatform.iOS => 'ios',
      TargetPlatform.macOS => 'macos',
      TargetPlatform.windows => 'windows',
      TargetPlatform.linux => 'linux',
      _ => 'android',
    };
  }

  static String get deviceName => '${platform[0].toUpperCase()}${platform.substring(1)} app';
}
