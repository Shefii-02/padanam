import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

final doubtsRepositoryProvider = Provider<DoubtsRepository>((ref) => DoubtsRepository(ref.watch(apiClientProvider)));

/// My doubts (waiting + answered).
final myDoubtsProvider = FutureProvider.autoDispose<Paged>((ref) => ref.watch(doubtsRepositoryProvider).mine());

/// Answered doubts of a course – students learn from each other's questions.
final courseDoubtsProvider = FutureProvider.autoDispose.family<Paged, int>((ref, id) => ref.watch(doubtsRepositoryProvider).course(id));

class DoubtsRepository {
  DoubtsRepository(this._api);
  final ApiClient _api;

  Future<Paged> mine({int page = 1}) => _api.page('/app/doubts', query: {'page': page});
  Future<Paged> course(int courseId, {int page = 1}) => _api.page('/app/courses/$courseId/doubts', query: {'page': page});

  Future<Json> ask({int? courseId, String? subject, required String text, String? imagePath}) async {
    final fields = {if (courseId != null && courseId > 0) 'course_id': courseId, if (subject != null && subject.isNotEmpty) 'subject': subject, 'text': text};
    if (imagePath != null) return asJson(await _api.upload('/app/doubts', files: {'image': imagePath}, fields: fields));
    return asJson(await _api.post('/app/doubts', body: fields));
  }
}
