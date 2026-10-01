import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final plannerRepositoryProvider = Provider<PlannerRepository>((ref) => PlannerRepository(ref.watch(apiClientProvider)));

String ymd(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// Tasks for one week (Monday start) → {days: [{date, done, total, minutes, tasks: [...]}]}
final plannerProvider = FutureProvider.autoDispose.family<Json, DateTime>((ref, monday) => ref.watch(plannerRepositoryProvider).week(monday));

class PlannerRepository {
  PlannerRepository(this._api);
  final ApiClient _api;

  Future<Json> week(DateTime monday) async =>
      asJson(await _api.get('/app/study-plan', query: {'from': ymd(monday), 'to': ymd(monday.add(const Duration(days: 6)))}));
  Future<Json> setDone(int id, bool done) async => asJson(await _api.post('/app/study-plan/tasks/$id/toggle', body: {'done': done}));
  Future<Json> add(DateTime date, String title, {String type = 'study', int minutes = 30}) async =>
      asJson(await _api.post('/app/study-plan/tasks', body: {'date': ymd(date), 'title': title, 'type': type, 'minutes': minutes}));
  Future<void> remove(int id) => _api.delete('/app/study-plan/tasks/$id');
}
