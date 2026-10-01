import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:in_app_update/in_app_update.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/env.dart';
import '../network/api_client.dart';
import '../storage/local_store.dart';
import '../utils/json.dart';

class UpdateInfo {
  UpdateInfo(this.json, this.currentVersion, this.currentBuild);
  final Json json;
  final String currentVersion;
  final int currentBuild;

  /// Laravel /public/app-version → update: none | flexible | immediate
  bool get available => json.s('update', 'none') != 'none';
  bool get force => json.s('update') == 'immediate';
  String get latestVersion => json.s('latest_version');
  int get latestBuild => json.i('latest_build');
  String get title => json.s('title', 'Update available');
  String get message => json.s('notes');
  List<String> get changelog => [for (final l in json.s('notes').split('\n')) if (l.trim().isNotEmpty) l.trim()];
  String get storeUrl => json.s('store_url');
}

class UpdateState {
  const UpdateState({this.info, this.checking = false, this.downloading = false, this.readyToInstall = false, this.dismissed = false, this.error});
  final UpdateInfo? info;
  final bool checking, downloading, readyToInstall, dismissed;
  final String? error;

  bool get showPrompt => info != null && info!.available && !dismissed;

  UpdateState copyWith({UpdateInfo? info, bool? checking, bool? downloading, bool? readyToInstall, bool? dismissed, String? error}) => UpdateState(
        info: info ?? this.info,
        checking: checking ?? this.checking,
        downloading: downloading ?? this.downloading,
        readyToInstall: readyToInstall ?? this.readyToInstall,
        dismissed: dismissed ?? this.dismissed,
        error: error,
      );
}

final updateControllerProvider = NotifierProvider<UpdateController, UpdateState>(UpdateController.new);

/// In-app update:
///  • Android (Play Store build): Google Play in-app updates. Flexible = downloads in the
///    background, then "Restart to update". Force = immediate full-screen update.
///  • Every platform: /app/version from our API → banner / blocking screen → store link.
///  • Re-checks on app resume (max once an hour) and every 6 hours.
class UpdateController extends Notifier<UpdateState> {
  Timer? _timer;
  AppLifecycleListener? _life;
  DateTime _lastCheck = DateTime(2000);

  bool get _isAndroid => !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  @override
  UpdateState build() {
    _timer = Timer.periodic(const Duration(hours: 6), (_) => check());
    _life = AppLifecycleListener(onResume: () {
      if (DateTime.now().difference(_lastCheck) > const Duration(hours: 1)) check();
    });
    ref.onDispose(() {
      _timer?.cancel();
      _life?.dispose();
    });
    return const UpdateState();
  }

  Future<void> check({bool manual = false}) async {
    if (state.checking) return;
    _lastCheck = DateTime.now();
    state = state.copyWith(checking: true, dismissed: manual ? false : state.dismissed);
    try {
      final pkg = await PackageInfo.fromPlatform();
      final build = int.tryParse(pkg.buildNumber) ?? 0;
      final data = asJson(await ref.read(apiClientProvider).get('/public/app-version', query: {'platform': Env.platform, 'build': build, 'version': pkg.version}));
      final info = UpdateInfo(data, pkg.version, build);
      final skipped = ref.read(localStoreProvider).skippedUpdateBuild;
      final hide = !manual && !info.force && skipped >= info.latestBuild;
      state = state.copyWith(info: info, checking: false, dismissed: hide ? true : state.dismissed);
      if (info.available && _isAndroid) await _tryPlayUpdate(info.force);
    } catch (e) {
      state = state.copyWith(checking: false, error: '$e');
    }
  }

  Future<void> _tryPlayUpdate(bool force) async {
    try {
      final play = await InAppUpdate.checkForUpdate();
      if (play.updateAvailability != UpdateAvailability.updateAvailable) return;
      if (force && play.immediateUpdateAllowed) {
        await InAppUpdate.performImmediateUpdate();
      } else if (play.flexibleUpdateAllowed) {
        state = state.copyWith(downloading: true);
        await InAppUpdate.startFlexibleUpdate(); // downloads in background
        state = state.copyWith(downloading: false, readyToInstall: true);
      }
    } catch (_) {
      // Not installed from Play (debug / APK) – the API banner is used instead.
      state = state.copyWith(downloading: false);
    }
  }

  /// "Update" button.
  Future<void> startUpdate() async {
    if (state.readyToInstall && _isAndroid) {
      await InAppUpdate.completeFlexibleUpdate(); // restarts into the new version
      return;
    }
    final url = state.info?.storeUrl;
    if (url == null || url.isEmpty) return;
    await launchUrl(Uri.parse(url), mode: kIsWeb ? LaunchMode.platformDefault : LaunchMode.externalApplication, webOnlyWindowName: kIsWeb ? '_self' : null);
  }

  Future<void> later() async {
    final b = state.info?.latestBuild;
    if (b != null) await ref.read(localStoreProvider).skipUpdate(b);
    state = state.copyWith(dismissed: true);
  }
}
