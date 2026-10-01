import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../storage/local_store.dart';
import 'api_exception.dart';

/// Called when the server says the JWT is no longer valid.
/// Set by the auth controller (avoids a provider cycle).
final unauthorizedHandlerProvider = Provider<void Function()>((ref) => () {});

final dioProvider = Provider<Dio>((ref) {
  final store = ref.watch(localStoreProvider);
  final dio = Dio(BaseOptions(
    baseUrl: Env.baseUrl,
    connectTimeout: Env.connectTimeout,
    receiveTimeout: Env.receiveTimeout,
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
  ));

  dio.interceptors.add(InterceptorsWrapper(
    onRequest: (options, handler) {
      final token = store.token;
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
      options.headers['Accept-Language'] = store.language;
      handler.next(options);
    },
    onError: (e, handler) {
      final isAuthCall = e.requestOptions.path.startsWith('/auth/');
      if (e.response?.statusCode == 401 && !isAuthCall) {
        ref.read(unauthorizedHandlerProvider)();
      }
      handler.next(e);
    },
  ));
  if (kDebugMode) {
    dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: false, logPrint: (o) => debugPrint('$o')));
  }
  return dio;
});

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient(ref.watch(dioProvider)));

/// Thin wrapper: unwraps the Laravel envelope { success, message, data }.
class ApiClient {
  ApiClient(this._dio);
  final Dio _dio;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get(path, queryParameters: _clean(query)));
  Future<dynamic> post(String path, {Object? body}) => _send(() => _dio.post(path, data: body));
  Future<dynamic> put(String path, {Object? body}) => _send(() => _dio.put(path, data: body));
  Future<dynamic> patch(String path, {Object? body}) => _send(() => _dio.patch(path, data: body));
  Future<dynamic> delete(String path, {Object? body}) => _send(() => _dio.delete(path, data: body));

  /// Multipart upload: [files] maps field name → local path.
  Future<dynamic> upload(String path, {required Map<String, String> files, Map<String, dynamic>? fields, void Function(int, int)? onProgress}) async {
    final form = FormData.fromMap({
      ...?fields,
      for (final e in files.entries) e.key: await MultipartFile.fromFile(e.value, filename: e.value.split('/').last),
    });
    return _send(() => _dio.post(path, data: form, onSendProgress: onProgress, options: Options(contentType: 'multipart/form-data')));
  }

  /// Paginated list: returns items + meta.pagination (Laravel envelope keeps meta outside data).
  Future<Paged> page(String path, {Map<String, dynamic>? query}) async {
    try {
      final res = await _dio.get(path, queryParameters: _clean(query));
      final body = res.data;
      if (body is Map && body['success'] == false) {
        throw ApiException('${body['message'] ?? 'Request failed'}', status: res.statusCode, data: body.cast<String, dynamic>());
      }
      final data = body is Map ? body['data'] : body;
      final meta = body is Map && body['meta'] is Map ? (body['meta'] as Map).cast<String, dynamic>() : <String, dynamic>{};
      return Paged(
        [for (final e in (data is List ? data : const [])) if (e is Map) e.cast<String, dynamic>()],
        meta,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Map<String, dynamic>? _clean(Map<String, dynamic>? q) =>
      q == null ? null : (Map.of(q)..removeWhere((_, v) => v == null));

  Future<dynamic> _send(Future<Response<dynamic>> Function() call) async {
    try {
      final res = await call();
      final body = res.data;
      if (body is Map) {
        if (body['success'] == false) {
          throw ApiException('${body['message'] ?? 'Request failed'}', status: res.statusCode, data: body.cast<String, dynamic>());
        }
        return body.containsKey('data') ? body['data'] : body;
      }
      return body;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

/// One page of a Laravel paginated list.
class Paged {
  Paged(this.items, this.meta);
  final List<Map<String, dynamic>> items;
  final Map<String, dynamic> meta;
  Map<String, dynamic> get pagination => meta['pagination'] is Map ? (meta['pagination'] as Map).cast<String, dynamic>() : const {};
  int get page => (pagination['page'] as num?)?.toInt() ?? 1;
  int get lastPage => (pagination['last_page'] as num?)?.toInt() ?? 1;
  bool get hasMore => page < lastPage;
}
