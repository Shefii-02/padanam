import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final learningRepositoryProvider = Provider<LearningRepository>((ref) => LearningRepository(ref.watch(apiClientProvider)));

/// Enrolled + teaching courses.
final myCoursesProvider = FutureProvider<List<Json>>((ref) => ref.watch(learningRepositoryProvider).myCourses());

/// One screen of a course: tab + folder → {tabs, folder, breadcrumbs, folders, items}
final browseProvider = FutureProvider.autoDispose.family<Json, ({int courseId, String? tab, int? folderId})>(
  (ref, a) => ref.watch(learningRepositoryProvider).browse(a.courseId, tab: a.tab, folderId: a.folderId),
);

/// Opens one content item (access checked on the server) → item + payload.
final contentProvider = FutureProvider.autoDispose.family<Json, int>((ref, id) => ref.watch(learningRepositoryProvider).open(id));

class LearningRepository {
  LearningRepository(this._api);
  final ApiClient _api;

  Future<List<Json>> myCourses() async {
    final d = await _api.get('/app/my-courses');
    return [for (final e in (d is List ? d : const [])) if (e is Map) e.cast<String, dynamic>()];
  }

  Future<Json> browse(int courseId, {String? tab, int? folderId}) async =>
      asJson(await _api.get('/app/courses/$courseId/browse', query: {'tab': tab, 'folder_id': folderId}));

  Future<Json> open(int contentId) async => asJson(await _api.get('/app/contents/$contentId'));

  /// progress 0–100; position in seconds (videos). ≥ 90 marks it done.
  Future<Json> progress(int contentId, int progress, {int position = 0}) async =>
      asJson(await _api.post('/app/contents/$contentId/progress', body: {'progress': progress.clamp(0, 100), 'position': position}));

  Future<bool> classAlerts(int courseId, bool enabled) async => asJson(await _api.post('/app/courses/$courseId/class-alerts', body: {'enabled': enabled})).b('enabled');

  Future<Json> article(String slug) async => asJson(await _api.get('/public/articles/$slug'));
}
