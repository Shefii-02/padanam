import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final teacherRepositoryProvider = Provider<TeacherRepository>((ref) => TeacherRepository(ref.watch(apiClientProvider)));

/// My upcoming / live classes (teacher = me).
final myClassesProvider = FutureProvider.autoDispose<Paged>((ref) => ref.watch(teacherRepositoryProvider).classes());

/// Doubts from my courses. status: open | answered
final teacherDoubtsProvider = FutureProvider.autoDispose.family<Paged, String>((ref, status) => ref.watch(teacherRepositoryProvider).doubts(status));

/// Teacher mode uses the same staff endpoints as the admin panel (permissions + course access checked there).
class TeacherRepository {
  TeacherRepository(this._api);
  final ApiClient _api;

  Future<Paged> classes() => _api.page('/admin/live-classes', query: {'mine': 1, 'when': 'upcoming'});
  Future<Json> goLive(int id, String url) async => asJson(await _api.post('/app/live-classes/$id/go-live', body: {'url': url}));
  Future<Json> end(int id) async => asJson(await _api.post('/app/live-classes/$id/end'));
  Future<Json> attendance(int id) async => asJson(await _api.get('/admin/live-classes/$id/attendance'));

  Future<Paged> doubts(String status) => _api.page('/admin/doubts', query: {'status': status});
  Future<Json> answer(int id, String text) async => asJson(await _api.post('/admin/doubts/$id/answer', body: {'answer': text}));
}
