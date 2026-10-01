import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final profileProvider = FutureProvider<Json>((ref) async => asJson(await ref.watch(apiClientProvider).get('/app/profile-page')));

final profileRepositoryProvider = Provider<ProfileRepository>((ref) => ProfileRepository(ref.watch(apiClientProvider)));

class ProfileRepository {
  ProfileRepository(this._api);
  final ApiClient _api;
  Future<Json> update(Json body) async => asJson(await _api.patch('/app/profile', body: body));
}
