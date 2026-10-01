import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/otp_screen.dart';
import '../../features/auth/presentation/welcome_back_screen.dart';
import '../../features/community/presentation/doubts_screen.dart';
import '../../features/exams/presentation/exam_hub_screen.dart';
import '../../features/exams/presentation/exams_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/learn/presentation/current_affairs_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/live/presentation/live_screens.dart';
import '../../features/onboarding/presentation/language_screen.dart';
import '../../features/onboarding/presentation/onboarding_screen.dart';
import '../../features/planner/presentation/planner_screen.dart';
import '../../features/practice/presentation/daily_quiz_screen.dart';
import '../../features/practice/presentation/previous_papers_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/rankings/presentation/rankings_screen.dart';
import '../../features/search/presentation/search_screen.dart';
import '../../features/setup/presentation/plan_ready_screen.dart';
import '../../features/setup/presentation/setup_screen.dart';
import '../../features/shell/presentation/main_shell.dart';
import '../../features/splash/presentation/splash_screen.dart';
import '../../features/store/presentation/checkout_screen.dart';
import '../../features/store/presentation/course_detail_screen.dart';
import '../../features/store/presentation/courses_tab_screen.dart';
import '../../features/store/presentation/orders_screen.dart';
import '../../features/store/presentation/payment_result_screen.dart';
import '../../features/learning/presentation/article_screen.dart';
import '../../features/learning/presentation/content_screen.dart';
import '../../features/learning/presentation/course_home_screen.dart';
import '../../features/chat/presentation/chat_room_screen.dart';
import '../../features/chat/presentation/chats_screen.dart';
import '../../features/live/presentation/class_alert_screen.dart';
import '../../features/teacher/presentation/teacher_screens.dart';
import '../../features/tests/presentation/analysis_screen.dart';
import '../../features/tests/presentation/attempt_screen.dart';
import '../../features/tests/presentation/dashboard_screen.dart';
import '../../features/tests/presentation/instructions_screen.dart';
import '../../features/tests/presentation/omr_upload_screen.dart';
import '../../features/tests/presentation/result_screen.dart';
import '../../features/tests/presentation/solutions_screen.dart';
import '../../features/tests/presentation/test_list_screen.dart';
import 'nav_key.dart';
import 'routes.dart';

const _publicRoutes = {R.splash, R.onboarding, R.language, R.login, R.otp};

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.listen(authControllerProvider, (_, __) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: R.splash,
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final loc = state.matchedLocation;
      switch (auth.status) {
        case AuthStatus.unknown:
          return loc == R.splash ? null : R.splash;
        case AuthStatus.unauthenticated:
          if (loc == R.splash) return auth.onboardingSeen ? R.login : R.onboarding;
          return _publicRoutes.contains(loc) ? null : R.login;
        case AuthStatus.needsSetup:
          return loc == R.setup ? null : R.setup;
        case AuthStatus.authenticated:
          if (auth.landing != null && (_publicRoutes.contains(loc) || loc == R.setup)) return auth.landing;
          if (_publicRoutes.contains(loc) || loc == R.setup) return R.home;
          return null;
      }
    },
    routes: [
      GoRoute(path: R.splash, builder: (_, __) => const SplashScreen()),
      GoRoute(path: R.onboarding, builder: (_, __) => const OnboardingScreen()),
      GoRoute(path: R.language, builder: (_, __) => const LanguageScreen()),
      GoRoute(path: R.login, builder: (_, __) => const LoginScreen()),
      GoRoute(path: R.otp, builder: (_, __) => const OtpScreen()),
      GoRoute(path: R.welcome, builder: (_, __) => const WelcomeBackScreen()),
      GoRoute(path: R.setup, builder: (_, __) => const SetupScreen()),
      GoRoute(path: R.plan, builder: (_, __) => const PlanReadyScreen()),

      // ---- tabs (bottom bar on phones, side rail on tablet / desktop / web) ----
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => MainShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: R.home, builder: (_, __) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: R.courses, builder: (_, s) => CoursesTabScreen(initialTab: s.uri.queryParameters['tab'] == 'explore' ? 1 : 0))]),
          StatefulShellBranch(routes: [GoRoute(path: R.tests, builder: (_, __) => const TestListScreen())]),
          StatefulShellBranch(routes: [
            GoRoute(path: R.chat, builder: (_, __) => const ChatsScreen(), routes: [
              GoRoute(path: 'new', parentNavigatorKey: rootNavigatorKey, builder: (_, __) => const NewChatScreen()),
              GoRoute(path: 'room/:id', parentNavigatorKey: rootNavigatorKey, builder: (_, s) => ChatRoomScreen(roomId: int.parse(s.pathParameters['id']!))),
            ]),
          ]),
          StatefulShellBranch(routes: [GoRoute(path: R.profile, builder: (_, __) => const ProfileScreen())]),
        ],
      ),

      // ---- full-screen pages ----
      GoRoute(path: '/exam/:id', builder: (_, s) => ExamHubScreen(examId: s.pathParameters['id']!)),
      GoRoute(path: R.currentAffairs, builder: (_, s) => CurrentAffairsScreen(initialType: int.tryParse(s.uri.queryParameters['type'] ?? '') ?? 0)),
      GoRoute(path: R.liveList, builder: (_, __) => const LiveListScreen()),
      GoRoute(path: '/live/:id', builder: (_, s) => LiveClassScreen(liveId: int.tryParse(s.pathParameters['id']!) ?? 0)),
      GoRoute(path: R.quiz, builder: (_, __) => const DailyQuizScreen()),
      GoRoute(path: R.pyq, builder: (_, __) => const PreviousPapersScreen()),
      GoRoute(path: R.doubts, builder: (_, s) => DoubtsScreen(courseId: int.tryParse(s.uri.queryParameters['course'] ?? '')), routes: [
        GoRoute(path: 'ask', builder: (_, s) => AskDoubtScreen(courseId: int.tryParse(s.uri.queryParameters['course'] ?? ''), context: s.uri.queryParameters['about'])),
      ]),
      GoRoute(path: R.exams, builder: (_, __) => const ExamsScreen()),
      GoRoute(path: R.rankings, builder: (_, __) => const RankingsScreen()),
      GoRoute(path: '/j/:code', builder: (_, s) => JoinGroupScreen(code: s.pathParameters['code']!)),
      GoRoute(
        path: '/alert/:id',
        builder: (_, s) => ClassAlertScreen(liveId: int.tryParse(s.pathParameters['id']!) ?? 0, data: s.extra is Map ? (s.extra as Map).cast<String, dynamic>() : null),
      ),
      GoRoute(path: R.teacher, builder: (_, __) => const TeacherHomeScreen(), routes: [
        GoRoute(path: 'doubts', builder: (_, __) => const TeacherDoubtsScreen()),
        GoRoute(
          path: 'live/:id',
          builder: (_, s) => GoLiveScreen(liveId: int.parse(s.pathParameters['id']!), info: s.extra is Map ? (s.extra as Map).cast<String, dynamic>() : null),
        ),
      ]),

      // ---- store, payments, learning ----
      GoRoute(path: '/course/:slug', builder: (_, s) => CourseDetailScreen(slug: s.pathParameters['slug']!)),
      GoRoute(path: '/c/:slug', redirect: (_, s) => R.course(s.pathParameters['slug']!)),
      GoRoute(path: '/checkout/:batch', builder: (_, s) => CheckoutScreen(batchId: int.parse(s.pathParameters['batch']!), coupon: s.uri.queryParameters['coupon'])),
      GoRoute(path: '/paid/:order', builder: (_, s) => PaymentResultScreen(orderNo: s.pathParameters['order']!)),
      GoRoute(path: R.orders, builder: (_, __) => const OrdersScreen()),
      GoRoute(
        path: '/learn/:course',
        builder: (_, s) => CourseHomeScreen(
          courseId: int.parse(s.pathParameters['course']!),
          title: s.uri.queryParameters['title'],
          tab: s.uri.queryParameters['tab'],
          folderId: int.tryParse(s.uri.queryParameters['folder'] ?? ''),
        ),
      ),
      GoRoute(path: '/content/:id', builder: (_, s) => ContentScreen(contentId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/article/:slug', builder: (_, s) => ArticleScreen(slug: s.pathParameters['slug']!)),
      GoRoute(path: '/a/:slug', redirect: (_, s) => R.article(s.pathParameters['slug']!)),
      GoRoute(path: R.planner, builder: (_, __) => const PlannerScreen()),
      GoRoute(path: R.notifications, builder: (_, __) => const NotificationsScreen(), routes: [
        GoRoute(path: 'settings', builder: (_, __) => const NotificationSettingsScreen()),
      ]),
      GoRoute(path: R.search, builder: (_, __) => const SearchScreen()),
      GoRoute(path: R.performance, builder: (_, __) => const DashboardScreen()),
      GoRoute(path: '/test/:id/instructions', builder: (_, s) => InstructionsScreen(testId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/test/:id/attempt', builder: (_, s) => AttemptScreen(testId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/test/:id/omr', builder: (_, s) => OmrUploadScreen(testId: int.parse(s.pathParameters['id']!), title: s.uri.queryParameters['title'])),
      GoRoute(path: '/result/:aid', builder: (_, s) => ResultScreen(attemptId: s.pathParameters['aid']!), routes: [
        GoRoute(path: 'analysis', builder: (_, s) => AnalysisScreen(attemptId: s.pathParameters['aid']!)),
        GoRoute(path: 'solutions', builder: (_, s) => SolutionsScreen(attemptId: s.pathParameters['aid']!)),
      ]),
    ],
    errorBuilder: (context, state) => Scaffold(
      body: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Text('🧭', style: TextStyle(fontSize: 48)),
        const SizedBox(height: 8),
        Text('Page not found: ${state.uri.path}'),
        TextButton(onPressed: () => context.go(R.home), child: const Text('Go home')),
      ])),
    ),
  );
});
