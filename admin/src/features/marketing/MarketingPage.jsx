import { useState } from 'react';
import { useDispatch } from 'react-redux';
import { useMarketingTypesQuery, useAudienceCountQuery, useExportHistoryQuery } from './api';
import { useCategoriesQuery } from '../catalog/api';
import { useCourseOptionsQuery } from '../courses/api';
import { useTestsQuery } from '../tests/api';
import { api, download } from '../../app/api';
import { toast } from '../uiSlice';
import { Badge, Button, Card, PageHead, Spinner, Tabs, fmtDate, useDebounced } from '../../components/ui';
import DataTable from '../../components/DataTable';
import { Field } from '../../components/Form';

const TABS = [
  ['students', '👥 All students', 'Everyone who signed up in the app.'],
  ['buyers', '💳 Paid students', 'Bought at least one course – use for upsell and renewals.'],
  ['non_buyers', '🆓 Never bought', 'Signed up but never paid – best for offers and coupons.'],
  ['expiring', '⏳ Access ending', 'Course access ends soon – renewal campaigns.'],
  ['inactive', '😴 Inactive', 'Haven’t opened the app for a while – comeback campaigns.'],
  ['test_takers', '📝 Test takers', 'Students who wrote tests – toppers, weak students, free-test takers.'],
  ['app_installs', '📱 App users', 'Have the app on a phone (Android / iOS).'],
  ['leads', '🎯 Leads', 'Interested people who haven’t bought (demo views, offers, website).'],
];

const FORMATS = [
  { value: 'standard', label: 'Standard CSV (choose columns)' },
  { value: 'whatsapp', label: 'WhatsApp broadcast list (Name, +91 phone)' },
  { value: 'google_ads', label: 'Google Ads customer match' },
  { value: 'meta_ads', label: 'Facebook / Instagram custom audience' },
];

const EMPTY = {};
const SOURCES = ['demo_video', 'launch_offer', 'course_page', 'app_install', 'free_test', 'whatsapp', 'manual', 'website'];

export default function MarketingPage() {
  const { data: meta } = useMarketingTypesQuery();
  const { data: cats = [] } = useCategoriesQuery();
  const { data: courses = [] } = useCourseOptionsQuery();
  const { data: tests } = useTestsQuery({ per_page: 100, status: 'published' });
  const [tab, setTab] = useState('students');
  const [filters, setFilters] = useState({});
  const [fields, setFields] = useState({});
  const [format, setFormat] = useState('standard');
  const [busy, setBusy] = useState(false);
  const d = useDispatch();

  const f = filters[tab] || EMPTY;
  const setF = (k) => (v) => setFilters((all) => ({ ...all, [tab]: { ...(all[tab] || {}), [k]: v } }));
  const debounced = useDebounced(f, 500);
  const { data: count, isFetching: counting } = useAudienceCountQuery({ type: tab, ...debounced }, { skip: tab === 'history' });
  const { data: history, isFetching: hLoading } = useExportHistoryQuery({}, { skip: tab !== 'history' });

  if (!meta) return <Spinner />;
  const allFields = meta.fields[tab] || [];
  const chosen = fields[tab] || allFields.slice(0, 6);
  const catOpts = cats.map((c) => ({ value: c.id, label: c.name }));
  const courseOpts = courses.map((c) => ({ value: c.id, label: c.title }));
  const batchOpts = courses.flatMap((c) => c.batches.map((b) => ({ value: b.id, label: `${c.title} – ${b.name}` })));
  const districtOpts = meta.districts.map((x) => ({ value: x, label: x }));

  const common = [
    { name: 'category_ids', label: 'Interested in exams', type: 'multiselect', options: catOpts, cols: 2 },
    { name: 'districts', label: 'Districts', type: 'multiselect', options: districtOpts, cols: 2 },
    { name: 'gender', label: 'Gender', type: 'select', options: ['male', 'female', 'other'].map((x) => ({ value: x, label: x })) },
    { name: 'joined_from', label: 'Joined from', type: 'date' }, { name: 'joined_to', label: 'Joined to', type: 'date' },
  ];
  const activeDays = { name: 'active_days', label: 'Opened the app in the last', type: 'select', empty: 'Any time', options: [[1, 'day'], [7, '7 days'], [30, '30 days'], [90, '90 days']].map(([value, label]) => ({ value, label })) };
  const perTab = {
    students: [activeDays, { name: 'paid', label: 'Purchase', type: 'select', options: [{ value: '1', label: 'Paid at least once' }, { value: '0', label: 'Never paid' }] },
      { name: 'profile_complete', label: 'Profile', type: 'select', options: [{ value: '1', label: 'Completed' }, { value: '0', label: 'Not completed' }] }, ...common],
    buyers: [
      { name: 'course_ids', label: 'Bought these courses', type: 'multiselect', options: courseOpts, cols: 2 },
      { name: 'batch_ids', label: 'Or these batches', type: 'multiselect', options: batchOpts, cols: 2 },
      { name: 'not_course_ids', label: 'But do NOT have (upsell)', type: 'multiselect', options: courseOpts, cols: 2 },
      { name: 'enrollment_status', label: 'Access now', type: 'select', options: [{ value: 'active', label: 'Still active' }, { value: 'expired', label: 'Expired' }] },
      { name: 'from', label: 'Paid from', type: 'date' }, { name: 'to', label: 'Paid to', type: 'date' }, ...common],
    non_buyers: [activeDays, { name: 'viewed_course_ids', label: 'Showed interest in courses', type: 'multiselect', options: courseOpts, cols: 2 }, ...common],
    expiring: [{ name: 'within_days', label: 'Access ends within (days)', type: 'number', min: 1, placeholder: '15' }, { name: 'course_ids', label: 'Courses', type: 'multiselect', options: courseOpts, cols: 2 }, ...common],
    inactive: [{ name: 'days', label: 'Not opened the app for (days)', type: 'number', min: 1, placeholder: '14' },
      { name: 'paid_only', label: 'Who', type: 'select', options: [{ value: '1', label: 'Paid students only' }] },
      { name: 'course_ids', label: 'Of courses', type: 'multiselect', options: courseOpts, cols: 2 }, ...common],
    test_takers: [
      { name: 'test_ids', label: 'Wrote these tests', type: 'multiselect', options: (tests?.items || []).map((t) => ({ value: t.id, label: t.title })), cols: 2 },
      { name: 'course_ids', label: 'Tests of courses', type: 'multiselect', options: courseOpts, cols: 2 },
      { name: 'min_percent', label: 'Average score from %', type: 'number', min: 0 }, { name: 'max_percent', label: 'to %', type: 'number', max: 100 },
      { name: 'from', label: 'Test date from', type: 'date' }, { name: 'to', label: 'to', type: 'date' }, ...common],
    app_installs: [
      { name: 'platform', label: 'Platform', type: 'select', options: [{ value: 'android', label: 'Android' }, { value: 'ios', label: 'iPhone' }] },
      { name: 'active_days', label: 'App used in the last (days)', type: 'number', min: 1 },
      { name: 'no_purchase', label: 'Purchase', type: 'select', options: [{ value: '1', label: 'Never bought' }] }, ...common],
    leads: [
      { name: 'status', label: 'Status', type: 'multiselect', options: ['new', 'contacted', 'lost', 'converted'].map((x) => ({ value: x, label: x })), hint: 'Default: all except converted', cols: 2 },
      { name: 'source', label: 'Source', type: 'multiselect', options: SOURCES.map((x) => ({ value: x, label: x.replace('_', ' ') })), cols: 2 },
      { name: 'min_score', label: 'Interest score at least', type: 'number', min: 0 },
      { name: 'course_ids', label: 'Interested in courses', type: 'multiselect', options: courseOpts, cols: 2 },
      { name: 'category_ids', label: 'Interested in exams', type: 'multiselect', options: catOpts, cols: 2 },
      { name: 'districts', label: 'Districts', type: 'multiselect', options: districtOpts, cols: 2 },
      { name: 'from', label: 'Added from', type: 'date' }, { name: 'to', label: 'to', type: 'date' }],
  };

  const doExport = async () => {
    setBusy(true);
    try {
      await download('admin/marketing/export', { type: tab, format, fields: format === 'standard' ? chosen : [], ...f });
      d(api.util.invalidateTags(['Export']));
    } catch { d(toast('Export failed', 'error')); }
    setBusy(false);
  };

  return (
    <>
      <PageHead title="Marketing data" sub="Download audiences for WhatsApp broadcasts, SMS, calls and Google / Meta ads. One number per row, blocked users excluded. Every export is logged." />
      <Tabs value={tab} onChange={setTab} tabs={[...TABS.map(([key, label]) => ({ key, label })), { key: 'history', label: '🕘 Export history' }]} />
      {tab === 'history' ? (
        <Card flush>
          <DataTable loading={hLoading} rows={history?.items}
            columns={[
              { key: 'created_at', label: 'When', render: (e) => fmtDate(e.created_at, true) },
              { key: 'type', label: 'Audience', render: (e) => TABS.find((t) => t[0] === e.type)?.[1] || e.type },
              { key: 'format', label: 'Format' }, { key: 'rows', label: 'Rows' },
              { key: 'filters', label: 'Filters', render: (e) => <span className="small muted">{Object.entries(e.filters || {}).map(([k, v]) => `${k}: ${[].concat(v).join('/')}`).join(' · ') || 'none'}</span> },
              { key: 'by', label: 'By' },
            ]} />
        </Card>
      ) : (
        <div className="grid" style={{ gridTemplateColumns: 'minmax(0,1fr) 320px', alignItems: 'start' }}>
          <Card title="Filters" actions={<Button size="sm" variant="ghost" onClick={() => setFilters({ ...filters, [tab]: {} })}>Clear</Button>}>
            <p className="small muted" style={{ marginTop: 0 }}>{TABS.find((t) => t[0] === tab)[2]}</p>
            <div className="grid g2">
              {perTab[tab].map((x) => <Field key={x.name} f={x} value={f[x.name]} onChange={setF(x.name)} />)}
            </div>
          </Card>
          <div className="col" style={{ position: 'sticky', top: 80 }}>
            <Card>
              <div className="stat"><span className="l">People matching</span><div className="v">{counting ? '…' : (count?.count ?? '—').toLocaleString('en-IN')}</div></div>
            </Card>
            <Card title="Download">
              <Field f={{ name: 'format', label: 'Format', type: 'select', required: true, options: FORMATS }} value={format} onChange={setFormat} />
              {format === 'standard' && <div style={{ marginTop: 10 }}><Field f={{ name: 'fields', label: 'Columns', type: 'multiselect', options: allFields.map((x) => ({ value: x, label: x.replace('_', ' ') })) }}
                value={chosen} onChange={(v) => setFields({ ...fields, [tab]: v })} /></div>}
              {format !== 'standard' && <p className="small muted">{format === 'whatsapp' ? 'Import into WhatsApp Business broadcast lists or your WhatsApp API tool.' : 'Upload this file directly as a customer list in the ads manager.'}</p>}
              <Button variant="primary" loading={busy} disabled={!count?.count} onClick={doExport} style={{ marginTop: 12, width: '100%', justifyContent: 'center' }}>⬇️ Download CSV</Button>
              <p className="small muted">Only message people who agreed to hear from Padanam. <Badge tone="grey">logged</Badge></p>
            </Card>
          </div>
        </div>
      )}
    </>
  );
}
