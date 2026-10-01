import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final testRepositoryProvider = Provider<TestRepository>((ref) => TestRepository(ref.watch(apiClientProvider)));

/// Real Laravel test engine (api/v1/app/...). Attempt ids are ULID strings.
class TestRepository {
  TestRepository(this._api);
  final ApiClient _api;

  /// status: new | done | all · exam = category slug (null = all)
  Future<Json> list(String exam, String status) async =>
      asJson(await _api.get('/app/test-series', query: {'category': exam == 'all' ? null : exam, 'status': status}));
  Future<Json> listKind(String exam, String kind) async =>
      asJson(await _api.get('/app/test-series', query: {'category': exam == 'all' ? null : exam, 'kind': kind}));
  Future<Json> instructions(int id) async => asJson(await _api.get('/app/tests/$id/instructions'));

  /// Starts (or resumes) → the question paper.
  Future<Json> start(int id, String language) async => asJson(await _api.post('/app/tests/$id/start', body: {'language': language}));

  /// answers: [{id: test_question_id, selected: [option ids], marked, visited, time_spent}]
  Future<Json> sync(String attemptId, List<Json> answers, {String? language}) async =>
      asJson(await _api.post('/app/attempts/$attemptId/sync', body: {'answers': answers, if (language != null) 'language': language}));
  Future<Json> nextSection(String attemptId, List<Json> answers) async => asJson(await _api.post('/app/attempts/$attemptId/next-section', body: {'answers': answers}));
  Future<Json> submit(String attemptId, List<Json> answers) async => asJson(await _api.post('/app/attempts/$attemptId/submit', body: {'answers': answers}));

  Future<Json> result(String attemptId) async => asJson(await _api.get('/app/attempts/$attemptId/summary'));
  Future<Json> analysis(String attemptId) async => asJson(await _api.get('/app/attempts/$attemptId/analysis'));
  Future<Json> solutions(String attemptId) async => asJson(await _api.get('/app/attempts/$attemptId/review'));
  Future<Json> dashboard() async => asJson(await _api.get('/app/performance'));
  Future<Json> leaderboard(int testId) async => asJson(await _api.get('/app/tests/$testId/leaderboard'));
  Future<Paged> history({int page = 1}) => _api.page('/app/tests/history', query: {'page': page});

  /// reason: wrong_answer | wrong_question | translation | typo | image | other
  Future<void> report(int questionId, String reason, {String? note}) =>
      _api.post('/app/question-reports', body: {'question_id': questionId, 'reason': reason, if (note != null && note.isNotEmpty) 'note': note});

  /// OMR exam: upload the photo of the filled sheet.
  Future<Json> uploadOmr(int testId, String imagePath) async => asJson(await _api.upload('/app/tests/$testId/omr', files: {'photo': imagePath}));
}

final testListProvider = FutureProvider.family<Json, (String, String)>((ref, a) => ref.watch(testRepositoryProvider).list(a.$1, a.$2));
final instructionsProvider = FutureProvider.autoDispose.family<Json, int>((ref, id) => ref.watch(testRepositoryProvider).instructions(id));
final resultProvider = FutureProvider.autoDispose.family<Json, String>((ref, id) => ref.watch(testRepositoryProvider).result(id));
final analysisProvider = FutureProvider.autoDispose.family<Json, String>((ref, id) => ref.watch(testRepositoryProvider).analysis(id));
final solutionsProvider = FutureProvider.autoDispose.family<Json, String>((ref, id) => ref.watch(testRepositoryProvider).solutions(id));
final dashboardProvider = FutureProvider.autoDispose<Json>((ref) => ref.watch(testRepositoryProvider).dashboard());

/// Question language during tests / solutions: en, ml, hi.
final testLanguageProvider = NotifierProvider<TestLanguage, String>(TestLanguage.new);

class TestLanguage extends Notifier<String> {
  @override
  String build() => 'en';
  void set(String v) => state = v;
}

const reportReasons = <(String, String)>[
  ('wrong_answer', 'The answer key is wrong'),
  ('wrong_question', 'The question is wrong or incomplete'),
  ('translation', 'Translation problem'),
  ('typo', 'Spelling / typing mistake'),
  ('image', 'Image missing or unclear'),
  ('other', 'Something else'),
];
