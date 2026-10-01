import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';
import '../../tests/data/test_repository.dart';

final practiceRepositoryProvider = Provider<PracticeRepository>((ref) => PracticeRepository(ref.watch(apiClientProvider), ref.watch(testRepositoryProvider)));

class PracticeRepository {
  PracticeRepository(this._api, this._tests);
  final ApiClient _api;
  final TestRepository _tests;

  /// {quizzes: [{id, test_id, title, category, questions, duration_min, done, score, attempt}], streak, week: [{date, done}]}
  Future<Json> dailyQuiz() async => asJson(await _api.get('/app/daily-quiz'));

  /// Previous year papers = tests of kind "pyq".
  Future<Json> pyq(String exam) => _tests.listKind(exam, 'pyq');
}

final dailyQuizProvider = FutureProvider.autoDispose<Json>((ref) => ref.watch(practiceRepositoryProvider).dailyQuiz());
final pyqProvider = FutureProvider.autoDispose.family<Json, String>((ref, exam) => ref.watch(practiceRepositoryProvider).pyq(exam));
