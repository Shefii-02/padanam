import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

/// GET /public/app/config – onboarding slides, languages.
final appConfigProvider = FutureProvider<Json>((ref) async => asJson(await ref.watch(apiClientProvider).get('/public/app/config')));
