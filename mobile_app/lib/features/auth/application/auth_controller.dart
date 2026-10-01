import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/local_store.dart';
import '../data/auth_repository.dart';
import '../data/user_model.dart';

enum AuthStatus { unknown, unauthenticated, needsSetup, authenticated }

class AuthState {
  const AuthState({
    required this.status,
    this.user,
    this.onboardingSeen = false,
    this.language = 'ml',
    this.phone,
    this.landing,
    this.otpChannel = 'sms',
  });

  final AuthStatus status;
  final AppUser? user;
  final bool onboardingSeen;
  final String language;

  /// Phone waiting for OTP.
  final String? phone;

  /// One-time screen to show right after login/setup ('/welcome' or '/setup/plan').
  final String? landing;

  /// Where the last OTP went: whatsapp | sms | log
  final String otpChannel;

  AuthState copyWith({AuthStatus? status, AppUser? user, bool? onboardingSeen, String? language, String? phone, String? landing, bool clearLanding = false, String? otpChannel}) =>
      AuthState(
        status: status ?? this.status,
        user: user ?? this.user,
        onboardingSeen: onboardingSeen ?? this.onboardingSeen,
        language: language ?? this.language,
        phone: phone ?? this.phone,
        landing: clearLanding ? null : (landing ?? this.landing),
        otpChannel: otpChannel ?? this.otpChannel,
      );
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(AuthController.new);

/// Current user (throws if not logged in – only use inside authenticated screens).
final currentUserProvider = Provider<AppUser?>((ref) => ref.watch(authControllerProvider).user);

class AuthController extends Notifier<AuthState> {
  LocalStore get _store => ref.read(localStoreProvider);
  AuthRepository get _repo => ref.read(authRepositoryProvider);

  @override
  AuthState build() {
    final store = ref.read(localStoreProvider);
    return AuthState(status: AuthStatus.unknown, onboardingSeen: store.onboardingSeen, language: store.language);
  }

  /// Splash: validates the saved JWT with /auth/me.
  Future<void> bootstrap() async {
    if (_store.token == null) {
      state = state.copyWith(status: AuthStatus.unauthenticated);
      return;
    }
    try {
      final user = await _repo.me();
      state = state.copyWith(status: user.profileCompleted ? AuthStatus.authenticated : AuthStatus.needsSetup, user: user);
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await _store.setToken(null);
        state = state.copyWith(status: AuthStatus.unauthenticated);
      } else {
        rethrow; // network error – splash shows retry
      }
    }
  }

  Future<void> finishOnboarding() async {
    await _store.setOnboardingSeen();
    state = state.copyWith(onboardingSeen: true);
  }

  Future<void> setLanguage(String code) async {
    await _store.setLanguage(code);
    state = state.copyWith(language: code);
  }

  Future<OtpInfo> sendOtp(String phone) async {
    final info = await _repo.sendOtp(phone);
    state = state.copyWith(phone: phone, otpChannel: info.channel);
    return info;
  }

  /// Returns true for a new user.
  Future<bool> verifyOtp(String otp) async {
    final r = await _repo.verifyOtp(state.phone ?? '', otp, state.language);
    await _store.setToken(r.token);
    state = state.copyWith(
      status: r.isNewUser ? AuthStatus.needsSetup : AuthStatus.authenticated,
      user: r.user,
      landing: r.isNewUser ? null : '/welcome',
    );
    return r.isNewUser;
  }

  Future<void> googleLogin() async {
    final r = await _repo.google('demo-id-token');
    await _store.setToken(r.token);
    state = state.copyWith(status: r.isNewUser ? AuthStatus.needsSetup : AuthStatus.authenticated, user: r.user, landing: r.isNewUser ? null : '/welcome');
  }

  /// Called by setup after POST /profile/setup.
  void completeSetup(AppUser user) =>
      state = state.copyWith(status: AuthStatus.authenticated, user: user, landing: '/setup/plan');

  void updateUser(AppUser user) => state = state.copyWith(user: user);

  void clearLanding() => state = state.copyWith(clearLanding: true);

  Future<void> logout() async {
    try {
      await _repo.logout();
    } catch (_) {}
    await expire();
  }

  Future<void> expire() async {
    await _store.setToken(null);
    state = AuthState(status: AuthStatus.unauthenticated, onboardingSeen: true, language: state.language);
  }
}

/// Wires the Dio 401 handler to the auth controller.
final authUnauthorizedOverride = unauthorizedHandlerProvider.overrideWith(
  (ref) => () => ref.read(authControllerProvider.notifier).expire(),
);
