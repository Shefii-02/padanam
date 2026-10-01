import 'package:dio/dio.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.status, this.data});
  final String message;
  final int? status;
  final Map<String, dynamic>? data;

  bool get isUnauthorized => status == 401;
  bool get isNetwork => status == null;

  factory ApiException.fromDio(DioException e) {
    final res = e.response;
    final body = res?.data;
    if (body is Map && body['message'] != null) {
      return ApiException('${body['message']}', status: res?.statusCode, data: body.cast<String, dynamic>());
    }
    return switch (e.type) {
      DioExceptionType.connectionTimeout ||
      DioExceptionType.receiveTimeout ||
      DioExceptionType.sendTimeout => ApiException('The server is taking too long. Please try again.'),
      DioExceptionType.connectionError => ApiException("Can't reach the server. Check your internet or the API URL."),
      _ => ApiException(res != null ? 'Something went wrong (${res.statusCode}).' : 'Something went wrong.', status: res?.statusCode),
    };
  }

  @override
  String toString() => message;
}
