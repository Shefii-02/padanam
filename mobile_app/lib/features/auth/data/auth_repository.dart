import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/env.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/utils/json.dart';
import 'user_model.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(apiClientProvider)));

class OtpInfo {
  OtpInfo(this.json);
  final Json json;
  int get resendIn => json.i('resend_in', 30);

  /// whatsapp | sms | log (local dev)
  String get channel => json.s('channel', 'sms');
  bool get onWhatsApp => channel == 'whatsapp';

  /// Only local builds: the server uses a fixed demo OTP (config app.demo_otp).
  String? get devOtp => kDebugMode && channel == 'log' ? '123456' : null;
}

class LoginResult {
  LoginResult(this.json);
  final Json json;
  String get token => json.s('token', json.s('access_token'));
  bool get isNewUser => json.b('is_new_user') || !AppUser(json.m('user')).profileCompleted;
  AppUser get user => AppUser(json.m('user'));
}

/// Real Laravel endpoints (api/v1/auth/*).
class AuthRepository {
  AuthRepository(this._api);
  final ApiClient _api;

  /// Before login: tells if the OTP will come on WhatsApp or SMS.
  Future<String> otpChannel() async => asJson(await _api.get('/auth/otp/channel')).s('channel', 'sms');

  Future<OtpInfo> sendOtp(String phone) async => OtpInfo(asJson(await _api.post('/auth/otp/send', body: {'phone': phone})));

  Future<LoginResult> verifyOtp(String phone, String otp, String language, {String? fcmToken}) async => LoginResult(asJson(await _api.post('/auth/otp/verify', body: {
        'phone': phone,
        'otp': otp,
        'language': language,
        'platform': Env.platform,
        'app_build': Env.build,
        'device_name': Env.deviceName,
        if (fcmToken != null) 'fcm_token': fcmToken,
      })));

  Future<LoginResult> google(String idToken) async => throw ApiException('Google sign-in is not available yet. Please use your mobile number.');

  Future<AppUser> me() async => AppUser(asJson(await _api.get('/auth/me')));

  /// Registers / refreshes the FCM token for push notifications and class alerts.
  Future<void> registerDevice(String? fcmToken) => _api.post('/auth/device', body: {
        'platform': Env.platform,
        'fcm_token': fcmToken,
        'app_build': Env.build,
        'device_name': Env.deviceName,
      });

  Future<void> logout({String? fcmToken}) => _api.post('/auth/logout', body: {if (fcmToken != null) 'fcm_token': fcmToken});
}
