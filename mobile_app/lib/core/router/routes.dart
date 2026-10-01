import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

/// All app paths in one place.
class R {
  R._();
  // start
  static const splash = '/splash';
  static const onboarding = '/onboarding';
  static const language = '/language';
  static const login = '/login';
  static const otp = '/otp';
  static const welcome = '/welcome';
  static const setup = '/setup';
  static const plan = '/setup/plan';
  // tabs
  static const home = '/home';
  static const courses = '/courses';
  static const exams = '/exams';
  static const tests = '/tests';
  static const rankings = '/rankings';
  static const profile = '/profile';
  // screens
  static String exam(String id) => '/exam/$id';
  static const currentAffairs = '/current-affairs';
  static String live(int id) => '/live/$id';
  static const quiz = '/quiz';
  static const pyq = '/pyq';
  static const doubts = '/doubts';
  static const planner = '/planner';
  static const notifications = '/notifications';
  static const search = '/search';
  static const performance = '/performance';
  static String instructions(int testId) => '/test/$testId/instructions';
  static String attempt(int testId) => '/test/$testId/attempt';
  static String result(String attemptId) => '/result/$attemptId';
  static String analysis(String attemptId) => '/result/$attemptId/analysis';
  static String solutions(String attemptId) => '/result/$attemptId/solutions';

  // store & learning
  static String course(String slugOrId) => '/course/$slugOrId';
  static String checkout(int batchId, {String? coupon}) => '/checkout/$batchId${coupon == null ? '' : '?coupon=$coupon'}';
  static String paid(String orderNo) => '/paid/$orderNo';
  static const orders = '/orders';
  static String learn(int courseId, {String? title, String? tab, int? folderId}) => Uri(
        path: '/learn/$courseId',
        queryParameters: {if (title != null) 'title': title, if (tab != null) 'tab': tab, if (folderId != null) 'folder': '$folderId'},
      ).toString();
  static String content(int id) => '/content/$id';
  static String article(String slug) => '/article/$slug';
  static String courseDoubts(int courseId) => '/doubts?course=$courseId';
  static String askDoubt({int? courseId, int? contentId, String? title}) => Uri(
        path: '/doubts/ask',
        queryParameters: {if (courseId != null && courseId > 0) 'course': '$courseId', if (title != null) 'about': title},
      ).toString();
  static const chat = '/chat';
  static const liveList = '/live';
  static String chatRoom(int id) => '/chat/room/$id';
  static const notificationSettings = '/notifications/settings';
  static const teacher = '/teacher';
  static const teacherDoubts = '/teacher/doubts';
  static String goLive(int id) => '/teacher/live/$id';
  static const newChat = '/chat/new';
  static String joinGroup(String code) => '/j/$code';
  static String classAlert(int liveId) => '/alert/$liveId';
  static String omr(int testId, {String? title}) => Uri(path: '/test/$testId/omr', queryParameters: {if (title != null) 'title': title}).toString();

  static const tabs = [home, courses, tests, chat, profile];
}

extension NavX on BuildContext {
  /// Opens any route coming from the API. Tabs are switched with go(), other screens are pushed.
  void open(String route) {
    if (route.startsWith('http')) {
      launchUrl(Uri.parse(route), mode: LaunchMode.externalApplication);
      return;
    }
    final path = Uri.parse(route).path;
    if (R.tabs.contains(path)) {
      go(route);
    } else {
      push(route);
    }
  }
}
