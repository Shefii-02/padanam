import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../tests/data/test_repository.dart';

/// My submitted tests with score and rank.
final testHistoryProvider = FutureProvider.autoDispose<Paged>((ref) => ref.watch(testRepositoryProvider).history());
