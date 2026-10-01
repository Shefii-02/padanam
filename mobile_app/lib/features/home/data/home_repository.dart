import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

/// GET /app/home – one call for the whole home page.
final homeProvider = FutureProvider<Json>((ref) async => asJson(await ref.watch(apiClientProvider).get('/app/home')));
