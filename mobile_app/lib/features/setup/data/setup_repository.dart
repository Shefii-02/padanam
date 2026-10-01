import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final setupRepositoryProvider = Provider<SetupRepository>((ref) => SetupRepository(ref.watch(apiClientProvider)));

/// GET /public/setup/options – avatars, districts, qualifications, exams, levels…
final setupOptionsProvider = FutureProvider<Json>((ref) => ref.watch(setupRepositoryProvider).options());

class SetupRepository {
  SetupRepository(this._api);
  final ApiClient _api;

  Future<Json> options() async => asJson(await _api.get('/public/setup/options'));

  /// POST /app/profile/setup → { user, plan }
  Future<Json> submit(Json body) async => asJson(await _api.post('/app/profile/setup', body: body));

  Future<Json> profile() async => asJson(await _api.get('/app/profile'));
}
