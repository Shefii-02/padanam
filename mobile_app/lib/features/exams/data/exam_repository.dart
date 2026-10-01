import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';
import '../../auth/application/auth_controller.dart';

final examRepositoryProvider = Provider<ExamRepository>((ref) => ExamRepository(ref.watch(apiClientProvider)));

class ExamRepository {
  ExamRepository(this._api);
  final ApiClient _api;

  /// [{id: slug, name, emoji, color, courses, mine, exams: [...]}]
  Future<List<Json>> list() async {
    final d = await _api.get('/app/exams');
    return [for (final e in (d is List ? d : const [])) if (e is Map) e.cast<String, dynamic>()];
  }

  /// {id, name, emoji, exams, courses, free_tests, articles}
  Future<Json> hub(String slug) async => asJson(await _api.get('/app/exams/$slug'));
}

final examsListProvider = FutureProvider<List<Json>>((ref) => ref.watch(examRepositoryProvider).list());
final examHubProvider = FutureProvider.family<Json, String>((ref, slug) => ref.watch(examRepositoryProvider).hub(slug));

/// The exam category the student is working on (tests, PYQ). Category slug.
final currentExamProvider = NotifierProvider<CurrentExam, String>(CurrentExam.new);

class CurrentExam extends Notifier<String> {
  @override
  String build() => ref.watch(currentUserProvider)?.primaryExam ?? 'all';
  void set(String id) => state = id;
}
