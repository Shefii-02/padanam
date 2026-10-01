// Smoke test: renders every page with a mocked API and fails on any render crash.
import { describe, it, expect, beforeAll, afterEach, vi } from 'vitest';
import { act } from 'react';
import { render, cleanup } from './render';

// no real socket in tests
vi.mock('socket.io-client', () => ({ io: () => ({ connected: false, on() {}, off() {}, emit() {}, close() {} }) }));

const batch = { id: 1, name: 'Morning', is_default: false, price_text: '₹4,999', mrp_text: '₹9,999', discount_percent: 50, validity_text: '365 days', seat_limit: 100, seats_taken: 3, code: 'LDCM', coupon_policy: 'allow', enrollment_open: true, status: 'active', staff: [{ id: 3, name: 'Suresh' }] };
const course = { id: 1, title: 'LDC 2027', status: 'published', students_count: 3, category: { name: 'PSC' }, course_type: 'live_recorded', batches: [batch], staff: [{ id: 3, name: 'Suresh', role: 'teacher' }], features: { live: true }, feature_labels: { live: 'Live classes' } };
const test = { id: 1, title: 'Mock 1', status: 'draft', mode: 'omr', total_questions: 2, total_marks: 2, duration_min: 60, attempts_count: 0, show_result: 'manual', sections: [{ id: 1, name: 'GK' }], sectional_timing: false };
const q = { id: 5, translations: { en: { text: '<b>Who?</b>' } }, options: [{ is_correct: true, text: { en: 'A' } }], difficulty: 'easy', languages: ['en'], labels: [{ id: 1, name: 'PYQ', color: '#f00' }], default_marks: 1, default_negative: 0.33 };

const FIX = [
  [/admin\/whatsapp\/status/, { share_ready: true, otp_ready: false, auto: { payment_link: true, invoice: true, swap: true } }],
  [/admin\/whatsapp\/settings/, (() => { const acc = (p) => ({ purpose: p, provider: 'generic', enabled: p === 'share', api_url: 'https://x.io/send', has_token: true, token_hint: '••••1234', auth_type: 'bearer', auth_key: 'token', body_format: 'json', text_body: { to: '{{phone}}', message: '{{message}}' }, document_body: null, country_code: '91', message_template: '{{otp}} is your OTP', fallback_sms: true, ready: p === 'share', last_tested_at: '2026-09-28', last_test_ok: true }); return { share: acc('share'), otp: acc('otp'), templates: [{ key: 'payment_link', text: 'Hi {name}', default: 'Hi {name}', variables: ['name', 'link'] }], auto: { invoice: true }, presets: { generic: { label: 'Generic', text_body: {} } }, placeholders: { '{{phone}}': 'Phone', '{{otp}}': 'OTP' } }; })()],
  [/admin\/whatsapp\/messages/, [{ id: 1, account: 'share', to_phone: '98', purpose: 'invoice', type: 'document', body: 'Hi', status: 'failed', error: 'HTTP 401', created_at: '2026-09-29' }], { summary: { today: 1, sent_today: 0, failed_today: 1, by_purpose: { invoice: 1 } } }],
  [/admin\/security\/otp-logs/, [{ id: 1, phone: '98', channel: 'whatsapp', status: 'verified', attempts: 0, created_at: '2026-09-29', user: { id: 1, name: 'Anu' } }], { summary: { sent_24h: 3, verified_24h: 2, success_rate: 66.7, by_channel: { whatsapp: 3 }, top_phones: [{ phone: '98', c: 6 }] } }],
  [/admin\/security\/logins/, [{ id: 1, name: 'Admin', email: 'a@x', platform: 'web', ip: '1.1.1.1', at: '2026-09-29' }]],
  [/admin\/security\/audit/, [{ id: 1, action: 'users.export', user: 'Admin', meta: { a: 1 }, created_at: '2026-09-29' }], { actions: ['users.export'] }],
  [/admin\/marketing\/types/, { types: ['students', 'buyers', 'non_buyers', 'expiring', 'inactive', 'test_takers', 'app_installs', 'leads'], fields: { students: ['name', 'phone'], buyers: ['name'], non_buyers: ['name'], expiring: ['name'], inactive: ['name'], test_takers: ['name'], app_installs: ['name'], leads: ['name'] }, formats: ['standard'], districts: ['Ernakulam'] }],
  [/admin\/marketing\/count/, { count: 1234 }],
  [/admin\/marketing\/exports/, [{ id: 1, type: 'students', format: 'whatsapp', rows: 10, filters: { districts: ['Ernakulam'] }, by: 'Admin', created_at: '2026-09-29' }]],
  [/admin\/swaps\/lookup/, { user: { id: 9, name: 'Anu', phone: '9876543210' }, enrollments: [{ id: 1, status: 'active', course: 'LDC 2027', batch: 'Morning', batch_id: 1, paid: '₹4,999', expires_at: '2027-06-01' }] }],
  [/admin\/swaps/, [{ id: 1, student: { name: 'Anu', phone: '98' }, from: 'LDC – M', to: 'LGS – E', difference: '₹1,000', collected: '₹0', mode: 'payment_link', status: 'pending_payment', payment_link_url: 'https://rzp.io/x', created_at: '2026-09-29' }]],
  [/auth\/me/, { id: 1, name: 'Admin', role: 'admin', permissions: ['*'] }],
  [/admin\/dashboard/, { revenue: { today: '₹0', orders_today: 0, month: '₹0', last_month: '₹0', series_30d: [{ date: '2026-09-01', value: 10 }] }, students: { total: 1, active_today: 1, new_today: 0, active_7d: 1 }, tests_today: 0, open_doubts: 0, signups_30d: [{ date: '2026-09-01', value: 1 }], live_today: [{ id: 1, time: '10:00', title: 'X', batch: 'B', status: 'live' }], top_courses: [{ id: 1, title: 'LDC', students: 3 }], expiring_7d: 2 }],
  [/admin\/categories/, [{ id: 1, name: 'PSC', icon: '🏛️', is_active: true, children: [{ id: 2, name: 'LDC' }], exams: [{ id: 1, name: 'LDC', next_exam_date: '2027-01-01', days_left: 90 }] }]],
  [/admin\/courses\/options/, [{ id: 1, title: 'LDC 2027', batches: [batch] }]],
  [/admin\/courses\/1\/batches/, [batch]],
  [/admin\/courses\/1\/folders/, [{ id: 1, title: 'GK', contents_count: 1, children: [] }]],
  [/admin\/courses\/1\/contents/, [{ id: 1, type: 'video', title: 'Class 1', access: 'premium', meta: { youtube_id: 'abc' } }]],
  [/admin\/courses\/1$/, course],
  [/admin\/courses/, [course], { pagination: { page: 1, last_page: 2, total: 30 } }],
  [/admin\/batches\/1\/students/, [{ id: 1, user: { id: 9, name: 'Anu', phone: '98' }, source: 'purchase', status: 'active' }]],
  [/admin\/staff\/options/, [{ id: 3, name: 'Suresh', role: 'teacher' }]],
  [/admin\/live-classes/, [{ id: 1, title: 'L1', status: 'scheduled', starts_at: '2026-10-01T10:00:00', course: 'LDC', batch: 'M', teacher: { name: 'S' } }]],
  [/question-bank\/folders/, { total: 1, folders: [{ id: 1, name: 'GK', count: 1, children: [] }] }],
  [/question-bank\/labels/, [{ id: 1, name: 'PYQ', color: '#f00', questions_count: 1 }]],
  [/question-bank\/questions\/facets/, { subjects: ['GK'] }],
  [/question-bank\/questions/, [q]],
  [/admin\/tests\/1\/questions/, [{ section: { id: 1, name: 'GK', marks_per_question: 1, negative_per_question: 0.33 }, questions: [{ id: 1, question: q }] }]],
  [/admin\/tests\/1\/results/, [], { summary: { participants: 0 }, question_stats: [] }],
  [/admin\/tests\/1\/omr\/pending/, []],
  [/admin\/tests\/1$/, test],
  [/admin\/tests/, [test]],
  [/admin\/daily-quiz/, [{ id: 1, date: '2026-09-29', status: 'planned', questions: 10 }]],
  [/admin\/study-plans/, [{ id: 1, title: 'Plan', course: { title: 'LDC' }, week: [{ day: 1, items: [{ title: 'x', type: 'video', minutes: 20 }] }] }]],
  [/admin\/articles/, { data: [{ id: 1, title: 'A', access: 'free', status: 'draft' }], current_page: 1, last_page: 1, total: 1 }],
  [/admin\/doubts/, [{ id: 1, student: { name: 'A' }, text: 'Why?', created_at: '2026-09-29' }]],
  [/admin\/students\/summary/, { total: 1, new_this_week: 1, active_7d: 1, inactive_14d: 0, never_purchased: 1 }],
  [/admin\/students\/1$/, { user: { id: 1, name: 'Anu', phone: '98', status: 'active', interests: [] }, stats: { tests_taken: 1, avg_score_percent: 50 }, enrollments: [], attempts: [], orders: [] }],
  [/admin\/students/, [{ id: 1, name: 'Anu', phone: '98', initials: 'A', status: 'active', interests: ['PSC'], avg_score_percent: null }]],
  [/admin\/payments/, [{ id: 1, order_no: 'P1', name: 'A', total: '₹1', status: 'paid', gateway: 'razorpay' }], { summary: { collected: '₹1', by_gateway: { razorpay: '₹1' } } }],
  [/admin\/coupons/, [{ id: 1, code: 'X', value_text: '20%', audience: 'all', batches: [], required_batches: [], used_count: 0, total_discount_given: '₹0', state: 'active' }]],
  [/revenue-share\/ledger/, []],
  [/revenue-share\/payouts/, []],
  [/admin\/revenue-share/, { enabled: false, percent: 10, partner_name: 'Partner', total_share_text: '₹0', paid_out_text: '₹0', balance_text: '₹0', this_month_share: '₹0' }],
  [/notification-channels/, [{ key: 'announcement', name: 'Announcements' }]],
  [/campaigns\/preview/, { users: 5, with_app: 3 }],
  [/admin\/campaigns/, { data: [], current_page: 1, last_page: 1, total: 0 }],
  [/notification-templates/, []],
  [/admin\/leads/, [{ id: 1, name: 'L', phone: '9', source: 'demo_video', score: 60, status: 'new' }], { summary: { new: 1 } }],
  [/reports\/revenue/, { total: '₹0', refunds: '₹0', net: '₹0', rows: [{ label: 'LDC', revenue: 10, orders: 1 }] }],
  [/reports\/activity/, { total: 1, active: 1, inactive: 0, paid_inactive: 0, daily: [], by_district: { Ernakulam: 1 } }],
  [/reports\/performance/, []],
  [/admin\/roles/, [{ id: 1, name: 'admin', label: 'Admin', permissions: ['*'], users_count: 1, is_system: true }]],
  [/admin\/permissions/, [{ module: 'courses', label: 'Courses', actions: [{ name: 'courses.view', label: 'View' }] }]],
  [/admin\/staff/, [{ id: 3, name: 'Suresh', email: 's@x', roles: ['teacher'], status: 'active' }]],
  [/admin\/settings/, { 'invoice.seller': { name: 'Padanam' } }],
  [/admin\/app-versions/, [{ platform: 'android', latest_version: '1.0.0', latest_build: 1, min_supported_build: 1 }]],
  [/admin\/chat\/rooms/, { data: [{ id: 1, name: 'LDC group', type: 'batch_group', join_mode: 'open', members_count: 3 }], current_page: 1, last_page: 1, total: 1 }],
  [/app\/chat\/rooms/, [{ id: 1, name: 'LDC group', unread: 2, members_count: 3 }]],
  [/admin\/chat\/reports/, { data: [], current_page: 1, last_page: 1, total: 0 }],
  [/admin\/chat\/policies/, [{ from_role: 'student', to_role: 'teacher', rule: 'shared_batch' }]],
];

beforeAll(() => {
  localStorage.setItem('pdn_token', 't');
  localStorage.setItem('pdn_user', JSON.stringify({ id: 1, name: 'Admin', role: 'admin', permissions: ['*'] }));
  global.fetch = vi.fn(async (req) => {
    const url = typeof req === 'string' ? req : req.url;
    const path = url.split('/api/v1/')[1]?.split('?')[0] || '';
    const hit = FIX.find(([re]) => re.test(path));
    const body = { success: true, message: 'OK', data: hit ? hit[1] : [], meta: hit?.[2] || {} };
    return new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } });
  });
  global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };
});
afterEach(cleanup);

const EXPECT = { '/whatsapp': 'Share API', '/security': 'OTP logs', '/marketing': '1,234', '/admissions?tab=swap&phone=9876543210': 'LDC 2027', '/admissions?tab=swaps': 'LGS – E', '/': 'Top courses', '/courses/1': 'Class 1', '/tests/1': 'Who?', '/question-bank': 'PYQ', '/chat': 'LDC group', '/students/1': 'Anu', '/daily': 'Daily quiz', '/roles': 'Suresh', '/revenue-share': 'Partner' };
const ROUTES = ['/', '/categories', '/courses', '/courses/1', '/live', '/question-bank', '/tests', '/tests/1', '/daily', '/articles', '/doubts',
  '/students', '/students/1', '/admissions', '/chat', '/leads', '/payments', '/coupons', '/revenue-share', '/notifications', '/reports', '/roles', '/settings',
  '/whatsapp', '/security', '/marketing', '/admissions?tab=swap&phone=9876543210', '/admissions?tab=swaps', '/admissions?tab=history', '/admissions?tab=manual'];

describe('every page renders', () => {
  for (const r of ROUTES) {
    it(r, async () => {
      const errors = [];
      const spy = vi.spyOn(console, 'error').mockImplementation((...a) => errors.push(a.map(String).join(' ')));
      const { container, settle } = await render(r);
      await settle();
      if (EXPECT[r]) expect(container.textContent).toContain(EXPECT[r]);
      const tabCount = container.querySelectorAll('[role=tab]').length;
      for (let i = 0; i < tabCount; i++) {
        const tabs = container.querySelectorAll('[role=tab]');
        if (!tabs[i]) break;
        await act(async () => { tabs[i].click(); });
        await settle();
      }
      spy.mockRestore();
      const fatal = errors.filter((e) => /Error:|The above error|Cannot read|is not a function|undefined/.test(e) && !/act\(|not wrapped/.test(e));
      expect(fatal, fatal.join('\n')).toEqual([]);
      expect(container.textContent.length).toBeGreaterThan(20);
    });
  }
});
