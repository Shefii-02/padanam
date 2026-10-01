import { useEffect, useState } from 'react';
import { useCampaignsQuery, useChannelsQuery, useAudiencePreviewMutation, useSaveCampaignMutation, useCampaignActionMutation, useTemplatesQuery, useSaveTemplateMutation } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { useCategoriesQuery } from '../catalog/api';
import { Badge, Button, Can, Card, Modal, PageHead, Tabs, fmtDate, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';
import { Field, SchemaForm } from '../../components/Form';

const AUDIENCES = [
  ['all_installs', 'Everyone with the app', true], ['all_users', 'All students', true], ['course', 'Students of courses'], ['batch', 'Students of batches'],
  ['category_interest', 'Interested in exams', true], ['inactive_days', 'Inactive for N days', true], ['expiring', 'Access ending soon', true], ['leads', 'Leads (not bought)', true], ['custom_users', 'Chosen phone numbers'],
];

function Composer() {
  const can = useCan();
  const { data: channels = [] } = useChannelsQuery();
  const { data: courses = [] } = useCourseOptionsQuery();
  const { data: cats = [] } = useCategoriesQuery();
  const [preview, pv] = useAudiencePreviewMutation();
  const [save, s] = useSaveCampaignMutation();
  const [c, setC] = useState({ channel_key: 'announcement', audience: can('notifications.send_all') ? 'all_users' : 'course', title: '', body: '', deep_link: '', f: {} });
  const set = (k) => (v) => setC((x) => ({ ...x, [k]: v }));
  const setF = (k) => (v) => setC((x) => ({ ...x, f: { ...x.f, [k]: v } }));
  const filter = () => ({
    course: { course_ids: c.f.course_ids || [] }, batch: { batch_ids: c.f.batch_ids || [] }, category_interest: { category_ids: c.f.category_ids || [] },
    inactive_days: { days: Number(c.f.days || 7) }, expiring: { days: Number(c.f.days || 7) }, leads: { status: c.f.status || undefined },
    custom_users: { phones: (c.f.phones || '').split(/[\s,]+/).filter(Boolean) },
  }[c.audience] || {});

  useEffect(() => {
    const t = setTimeout(() => preview({ audience: c.audience, audience_filter: filter(), silent: true }), 400);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [c.audience, JSON.stringify(c.f)]);

  const submit = async (sendNow) => {
    await save({ title: c.title, body: c.body, deep_link: c.deep_link || null, image: c.image || null, channel_key: c.channel_key, audience: c.audience, audience_filter: filter(), scheduled_at: sendNow ? null : c.scheduled_at || null, send_now: sendNow }).unwrap();
    setC((x) => ({ ...x, title: '', body: '' }));
  };
  const batches = courses.flatMap((x) => x.batches.map((b) => ({ value: b.id, label: `${x.title} – ${b.name}` })));

  return (
    <div className="grid" style={{ gridTemplateColumns: '1fr auto', alignItems: 'start' }}>
      <Card title="Compose a push notification">
        <div className="grid g2">
          <Field f={{ name: 'audience', label: 'Send to', type: 'select', required: true, options: AUDIENCES.filter(([, , all]) => !all || can('notifications.send_all')).map(([value, label]) => ({ value, label })) }} value={c.audience} onChange={(v) => setC((x) => ({ ...x, audience: v, f: {} }))} />
          <Field f={{ name: 'channel_key', label: 'Type', type: 'select', required: true, options: channels.filter((x) => x.key !== 'class_alert').map((x) => ({ value: x.key, label: x.name })) }} value={c.channel_key} onChange={set('channel_key')} />
          {c.audience === 'course' && <Field f={{ name: 'course_ids', label: 'Courses', type: 'multiselect', cols: 2, options: courses.map((x) => ({ value: x.id, label: x.title })) }} value={c.f.course_ids} onChange={setF('course_ids')} />}
          {c.audience === 'batch' && <Field f={{ name: 'batch_ids', label: 'Batches', type: 'multiselect', cols: 2, options: batches }} value={c.f.batch_ids} onChange={setF('batch_ids')} />}
          {c.audience === 'category_interest' && <Field f={{ name: 'category_ids', label: 'Exams', type: 'multiselect', cols: 2, options: cats.map((x) => ({ value: x.id, label: x.name })) }} value={c.f.category_ids} onChange={setF('category_ids')} />}
          {['inactive_days', 'expiring'].includes(c.audience) && <Field f={{ name: 'days', label: c.audience === 'expiring' ? 'Access ends within (days)' : 'Not opened the app for (days)', type: 'number', min: 1 }} value={c.f.days ?? 7} onChange={setF('days')} />}
          {c.audience === 'leads' && <Field f={{ name: 'status', label: 'Lead status', type: 'select', options: ['new', 'contacted', 'lost'].map((x) => ({ value: x, label: x })) }} value={c.f.status} onChange={setF('status')} />}
          {c.audience === 'custom_users' && <Field f={{ name: 'phones', label: 'Phone numbers (comma or new line)', type: 'textarea', cols: 2 }} value={c.f.phones} onChange={setF('phones')} />}
          <Field f={{ name: 'title', label: 'Title', required: true, cols: 2, placeholder: 'Onam offer – 40% off all courses 🌼' }} value={c.title} onChange={set('title')} />
          <Field f={{ name: 'body', label: 'Message', type: 'textarea', cols: 2, required: true }} value={c.body} onChange={set('body')} />
          <Field f={{ name: 'deep_link', label: 'Opens (optional)', placeholder: '/course/12 or /daily-quiz' }} value={c.deep_link} onChange={set('deep_link')} />
          <Field f={{ name: 'image', label: 'Image URL (optional)' }} value={c.image} onChange={set('image')} />
          <Field f={{ name: 'scheduled_at', label: 'Schedule for later (optional)', type: 'datetime' }} value={c.scheduled_at} onChange={set('scheduled_at')} />
        </div>
        <div className="row between" style={{ marginTop: 14 }}>
          <span className="small">{pv.data ? <>👥 <b>{pv.data.users}</b> people · 📱 {pv.data.with_app} with the app</> : '…'}</span>
          <span className="row">
            <Button disabled={!c.title || !c.body || !c.scheduled_at} loading={s.isLoading} onClick={() => submit(false)}>Schedule</Button>
            <Button variant="primary" disabled={!c.title || !c.body} loading={s.isLoading} onClick={() => submit(true)}>Send now</Button>
          </span>
        </div>
      </Card>
      <div className="phone" aria-label="Preview">
        <div className="small" style={{ color: '#666', textAlign: 'center', marginBottom: 12 }}>9:41</div>
        <div className="push">
          <div className="row small" style={{ color: '#666' }}><b style={{ color: '#1B2A7A' }}>പ Padanam</b> · now</div>
          <div className="b" style={{ marginTop: 4 }}>{c.title || 'Title'}</div>
          <div className="small">{c.body || 'Your message appears here.'}</div>
          {c.image && <img src={c.image} alt="" style={{ width: '100%', borderRadius: 8, marginTop: 6 }} />}
        </div>
      </div>
    </div>
  );
}

function History() {
  const [page, setPage] = useState(1);
  const { data, isFetching } = useCampaignsQuery({ page });
  const [act] = useCampaignActionMutation();
  return (
    <Card flush>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={page} onPage={setPage}
        columns={[
          { key: 'title', label: 'Notification', render: (x) => <div><div className="b">{x.title}</div><div className="small muted">{x.body}</div></div> },
          { key: 'audience', label: 'To', render: (x) => x.audience.replace('_', ' ') },
          { key: 'when', label: 'When', render: (x) => fmtDate(x.scheduled_at || x.created_at, true) },
          { key: 'stats', label: 'Sent / opened', render: (x) => `${x.sent} / ${x.opened}${x.failed ? ` (${x.failed} failed)` : ''}` },
          { key: 'status', label: 'Status', render: (x) => <Badge>{x.status}</Badge> },
          { key: 'a', label: '', render: (x) => ['draft', 'scheduled'].includes(x.status) && <><Button size="sm" onClick={() => act({ id: x.id, action: 'send' })}>Send now</Button><Button size="sm" variant="ghost" onClick={() => act({ id: x.id, action: 'cancel' })}>Cancel</Button></> },
        ]} />
    </Card>
  );
}

function Templates() {
  const { data = [] } = useTemplatesQuery();
  const [save] = useSaveTemplateMutation();
  const [edit, setEdit] = useState(null);
  return (
    <Card title="Automatic messages" actions={<span className="small muted">Use {'{variables}'} shown in each template</span>}>
      <table className="t"><tbody>{data.map((t) => (
        <tr key={t.id} className="click" onClick={() => setEdit(t)}><td className="kbd">{t.key}</td><td><div className="b">{t.title}</div><div className="small muted">{t.body}</div></td><td><Badge>{t.channel_key}</Badge></td></tr>
      ))}</tbody></table>
      {edit && <Modal title={edit.key} onClose={() => setEdit(null)}>
        <SchemaForm initial={edit} onCancel={() => setEdit(null)} fields={[{ name: 'title', label: 'Title', required: true, cols: 2 }, { name: 'body', label: 'Message', type: 'textarea', required: true, cols: 2 }, { name: 'deep_link', label: 'Opens', cols: 2 }]}
          onSubmit={async (v) => { await save({ id: edit.id, title: v.title, body: v.body, deep_link: v.deep_link }).unwrap(); setEdit(null); }} />
      </Modal>}
    </Card>
  );
}

export default function NotificationsPage() {
  const [tab, setTab] = useState('compose');
  const can = useCan();
  return (
    <>
      <PageHead title="Notifications" sub="Push campaigns. Class alerts, payment receipts and new-content alerts are sent automatically." />
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'compose', label: '✍️ Compose' }, { key: 'history', label: '📜 Sent & scheduled' }, can('notifications.manage_templates') && { key: 'templates', label: '🧩 Automatic messages' }]} />
      {tab === 'compose' && <Composer />}
      {tab === 'history' && <History />}
      {tab === 'templates' && <Can perm="notifications.manage_templates"><Templates /></Can>}
    </>
  );
}
