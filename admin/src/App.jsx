import { lazy, Suspense, useEffect } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import Layout from './components/Layout';
import LoginPage from './features/auth/LoginPage';
import { useMeQuery } from './features/auth/api';
import { hasPerm, setUser } from './features/authSlice';
import { Empty, Spinner } from './components/ui';

const P = (f) => lazy(f);
const pages = {
  Dashboard: P(() => import('./features/dashboard/DashboardPage')),
  Categories: P(() => import('./features/catalog/CategoriesPage')),
  Courses: P(() => import('./features/courses/CoursesPage')),
  CourseBuilder: P(() => import('./features/courses/CourseBuilder')),
  Live: P(() => import('./features/live/LivePage')),
  QBank: P(() => import('./features/qbank/QuestionBankPage')),
  Tests: P(() => import('./features/tests/TestsPage')),
  TestBuilder: P(() => import('./features/tests/TestBuilder')),
  Daily: P(() => import('./features/daily/DailyPage')),
  Articles: P(() => import('./features/articles/ArticlesPage')),
  Doubts: P(() => import('./features/articles/DoubtsPage')),
  Students: P(() => import('./features/students/StudentsPage')),
  StudentDetail: P(() => import('./features/students/StudentDetail')),
  Admissions: P(() => import('./features/students/AdmissionsPage')),
  Chat: P(() => import('./features/chat/ChatPage')),
  Leads: P(() => import('./features/grow/LeadsPage')),
  Payments: P(() => import('./features/money/PaymentsPage')),
  Coupons: P(() => import('./features/money/CouponsPage')),
  Revenue: P(() => import('./features/money/RevenueSharePage')),
  Notifications: P(() => import('./features/grow/NotificationsPage')),
  Reports: P(() => import('./features/grow/ReportsPage')),
  Roles: P(() => import('./features/admin/RolesPage')),
  Settings: P(() => import('./features/admin/SettingsPage')),
  Marketing: P(() => import('./features/marketing/MarketingPage')),
  WhatsApp: P(() => import('./features/whatsapp/WhatsAppPage')),
  Security: P(() => import('./features/security/SecurityPage')),
};

/** [path, page, permission] */
const ROUTES = [
  ['/', 'Dashboard', 'dashboard.view'],
  ['/categories', 'Categories', 'categories.view'],
  ['/courses', 'Courses', 'courses.view'],
  ['/courses/:id', 'CourseBuilder', 'courses.view'],
  ['/live', 'Live', 'live_classes.view'],
  ['/question-bank', 'QBank', 'question_bank.view'],
  ['/tests', 'Tests', 'tests.view'],
  ['/tests/:id', 'TestBuilder', 'tests.view'],
  ['/daily', 'Daily', ['daily_quiz.manage', 'study_plans.manage']],
  ['/articles', 'Articles', 'articles.view'],
  ['/doubts', 'Doubts', 'doubts.view'],
  ['/students', 'Students', 'users.view'],
  ['/students/:id', 'StudentDetail', 'users.view'],
  ['/admissions', 'Admissions', ['enrollments.add_manual', 'payments.create_link', 'enrollments.swap']],
  ['/chat', 'Chat', ['chat.manage_groups', 'chat.moderate', 'chat.use']],
  ['/leads', 'Leads', 'leads.view'],
  ['/payments', 'Payments', 'payments.view'],
  ['/coupons', 'Coupons', 'coupons.view'],
  ['/revenue-share', 'Revenue', 'revenue_share.view'],
  ['/notifications', 'Notifications', 'notifications.send'],
  ['/reports', 'Reports', 'reports.view'],
  ['/roles', 'Roles', 'roles.view'],
  ['/settings', 'Settings', ['settings.manage', 'app_versions.manage']],
  ['/marketing', 'Marketing', 'marketing.export'],
  ['/whatsapp', 'WhatsApp', ['whatsapp.manage', 'whatsapp.view', 'whatsapp.send']],
  ['/security', 'Security', 'security.view'],
];

function RequireAuth({ children }) {
  const token = useSelector((s) => s.auth.token);
  const loc = useLocation();
  const d = useDispatch();
  // refresh permissions/profile on every load so role changes apply without re-login
  const { data } = useMeQuery(undefined, { skip: !token });
  useEffect(() => { if (data) d(setUser(data.user || data)); }, [data, d]);
  if (!token) return <Navigate to="/login" replace state={{ from: loc }} />;
  return children;
}

function Guard({ perm, children }) {
  const me = useSelector((s) => s.auth.user);
  if (!hasPerm(me, perm)) {
    return <Empty icon="🔒" title="You don't have access to this page">Ask an admin to give your role this permission.</Empty>;
  }
  return children;
}

export default function App() {
  const token = useSelector((s) => s.auth.token);
  return (
    <Suspense fallback={<Spinner />}>
      <Routes>
        <Route path="/login" element={token ? <Navigate to="/" replace /> : <LoginPage />} />
        <Route element={<RequireAuth><Layout /></RequireAuth>}>
          {ROUTES.map(([path, page, perm]) => {
            const C = pages[page];
            return <Route key={path} path={path} element={<Guard perm={perm}><Suspense fallback={<Spinner />}><C /></Suspense></Guard>} />;
          })}
          <Route path="*" element={<Empty icon="🧭" title="Page not found" />} />
        </Route>
      </Routes>
    </Suspense>
  );
}
