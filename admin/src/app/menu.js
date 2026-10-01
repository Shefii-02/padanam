/** Sidebar – each item is shown only if the user has its permission. */
export const MENU = [
  { group: 'Overview', items: [
    { to: '/', icon: '🏠', label: 'Dashboard', perm: 'dashboard.view', end: true },
  ] },
  { group: 'Learning', items: [
    { to: '/categories', icon: '🗂️', label: 'Categories', perm: 'categories.view' },
    { to: '/courses', icon: '🎓', label: 'Courses', perm: 'courses.view' },
    { to: '/live', icon: '🔴', label: 'Live classes', perm: 'live_classes.view' },
    { to: '/question-bank', icon: '❓', label: 'Question bank', perm: 'question_bank.view' },
    { to: '/tests', icon: '📝', label: 'Tests & OMR', perm: 'tests.view' },
    { to: '/daily', icon: '📅', label: 'Daily quiz & plans', perm: ['daily_quiz.manage', 'study_plans.manage'] },
    { to: '/articles', icon: '📰', label: 'Articles', perm: 'articles.view' },
    { to: '/doubts', icon: '🙋', label: 'Doubts', perm: 'doubts.view' },
  ] },
  { group: 'People', items: [
    { to: '/students', icon: '👥', label: 'Students', perm: 'users.view' },
    { to: '/admissions', icon: '➕', label: 'Admissions & swap', perm: ['enrollments.add_manual', 'payments.create_link', 'enrollments.swap'] },
    { to: '/chat', icon: '💬', label: 'Chat & groups', perm: ['chat.manage_groups', 'chat.moderate'] },
    { to: '/leads', icon: '🎯', label: 'Leads', perm: 'leads.view' },
  ] },
  { group: 'Money', items: [
    { to: '/payments', icon: '💳', label: 'Payments', perm: 'payments.view' },
    { to: '/coupons', icon: '🏷️', label: 'Coupons', perm: 'coupons.view' },
    { to: '/revenue-share', icon: '🤝', label: 'Revenue share', perm: 'revenue_share.view' },
  ] },
  { group: 'Grow', items: [
    { to: '/notifications', icon: '🔔', label: 'Notifications', perm: 'notifications.send' },
    { to: '/marketing', icon: '📤', label: 'Marketing data', perm: 'marketing.export' },
    { to: '/reports', icon: '📊', label: 'Reports', perm: 'reports.view' },
  ] },
  { group: 'Admin', items: [
    { to: '/roles', icon: '🛡️', label: 'Roles & staff', perm: 'roles.view' },
    { to: '/whatsapp', icon: '🟢', label: 'WhatsApp', perm: ['whatsapp.manage', 'whatsapp.view', 'whatsapp.send'] },
    { to: '/security', icon: '🔐', label: 'Security & logs', perm: 'security.view' },
    { to: '/settings', icon: '⚙️', label: 'Settings', perm: ['settings.manage', 'app_versions.manage'] },
  ] },
];
