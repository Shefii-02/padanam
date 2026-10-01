import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Overridden in main() after SharedPreferences.getInstance().
final sharedPrefsProvider = Provider<SharedPreferences>((ref) => throw UnimplementedError('sharedPrefsProvider not overridden'));

final localStoreProvider = Provider<LocalStore>((ref) => LocalStore(ref.watch(sharedPrefsProvider)));

/// Token + small preferences. SharedPreferences works on mobile, web and desktop.
class LocalStore {
  LocalStore(this._p);
  final SharedPreferences _p;

  static const _kToken = 'auth_token';
  static const _kOnboarding = 'onboarding_seen';
  static const _kLang = 'language';
  static const _kSkipBuild = 'update_skip_build';

  String? get token => _p.getString(_kToken);
  Future<void> setToken(String? v) => v == null ? _p.remove(_kToken) : _p.setString(_kToken, v);

  bool get onboardingSeen => _p.getBool(_kOnboarding) ?? false;
  Future<void> setOnboardingSeen() => _p.setBool(_kOnboarding, true);

  String get language => _p.getString(_kLang) ?? 'ml';
  Future<void> setLanguage(String v) => _p.setString(_kLang, v);

  int get skippedUpdateBuild => _p.getInt(_kSkipBuild) ?? 0;
  Future<void> skipUpdate(int build) => _p.setInt(_kSkipBuild, build);
}
