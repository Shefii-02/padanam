import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useOtpLogsQuery, useLoginsQuery, useAuditQuery } from './api';
import { useWaStatusQuery } from '../whatsapp/api';
import { download } from '../../app/api';
import { Badge, Button, Card, Modal, PageHead, Stat, Tabs, fmtDate, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';

const TONE = { sent: 'orange', verified: 'green', failed: 'red', wrong_code: 'red', expired: 'grey', rate_limited: 'purple', blocked: 'red' };

function DateRange({ f, setF }) {
  return <>
    <input type="date" className="input" style={{ width: 'auto' }} value={f.from || ''} onChange={(e) => setF({ ...f, from: e.target.value, page: 1 })} aria-label="From" />
    <input type="date" className="input" style={{ width: 'auto' }} value={f.to || ''} onChange={(e) => setF({ ...f, to: e.target.value, page: 1 })} aria-label="To" />
  </>;
}

function OtpLogs() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useOtpLogsQuery(f, { pollingInterval: 15000 });
  const { data: wa } = useWaStatusQuery();
  const can = useCan();
  const sm = data?.meta?.summary || {};
  return (
    <>
      <div className="grid g4" style={{ marginBottom: 14 }}>
        <Stat label="OTPs sent (24 h)" value={sm.sent_24h} hint={Object.entries(sm.by_channel || {}).map(([k, n]) => `${k} ${n}`).join(' · ')} />
        <Stat label="Logged in (24 h)" value={sm.verified_24h} hint={sm.success_rate != null ? `${sm.success_rate}% success` : ''} />
        <Stat label="Delivery failed" value={sm.failed_24h} hint="Check the WhatsApp / SMS provider" />
        <Stat label="Blocked / too many" value={sm.blocked_24h} />
      </div>
      <Card className="row wrap" >
        <span>OTP delivery now: <b>{wa?.otp_ready ? '🟢 WhatsApp' : '📩 SMS'}</b></span>
        {can('whatsapp.manage') && <Link to="/whatsapp" className="btn sm">Change</Link>}
        {sm.top_phones?.length > 0 && <span className="small">⚠️ Many requests (24 h): {sm.top_phones.map((p) => `${p.phone} ×${p.c}`).join(', ')}</span>}
      </Card>
      <Card flush className="" >
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} placeholder="Phone number" filters={[
            { name: 'status', label: 'Status', options: Object.keys(TONE).map((x) => ({ value: x, label: x.replace('_', ' ') })) },
            { name: 'channel', label: 'Channel', options: ['whatsapp', 'sms', 'log'].map((x) => ({ value: x, label: x })) },
          ]}>
            <DateRange f={f} setF={setF} />
            <Button onClick={() => download('admin/security/otp-logs/export', { ...f, page: undefined })}>⬇️ CSV</Button>
          </Filters>
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
          columns={[
            { key: 'created_at', label: 'Time', render: (l) => <span className="nowrap">{fmtDate(l.created_at, true)}</span> },
            { key: 'phone', label: 'Phone', render: (l) => <div><b>{l.phone}</b>{l.user && <div className="small"><Link to={`/students/${l.user.id}`}>{l.user.name || 'student'}</Link></div>}</div> },
            { key: 'channel', label: 'Sent by', render: (l) => ({ whatsapp: '🟢 WhatsApp', sms: '📩 SMS', log: '🧪 log (dev)' }[l.channel]) },
            { key: 'status', label: 'Result', render: (l) => <Badge tone={TONE[l.status]}>{l.status.replace('_', ' ')}</Badge> },
            { key: 'attempts', label: 'Wrong tries' },
            { key: 'device', label: 'Device', render: (l) => <span className="small">{l.platform || '—'} · {l.ip}</span> },
            { key: 'error', label: 'Note', render: (l) => <span className="small muted">{l.error || (l.verified_at ? `verified ${fmtDate(l.verified_at, true)}` : '')}</span> },
          ]} />
      </Card>
    </>
  );
}

function Logins() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useLoginsQuery(f);
  return (
    <Card flush>
      <div style={{ padding: '14px 16px 0' }}>
        <Filters value={f} onChange={setF} placeholder="Name, phone or email" filters={[
          { name: 'platform', label: 'Platform', options: ['android', 'ios', 'web'].map((x) => ({ value: x, label: x })) },
          { name: 'staff', label: 'Who', options: [{ value: '1', label: 'Staff & teachers only' }] },
        ]}><DateRange f={f} setF={setF} /></Filters>
      </div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
        columns={[
          { key: 'at', label: 'Time', render: (l) => fmtDate(l.at, true) },
          { key: 'name', label: 'User', render: (l) => <div><b>{l.name || '—'}</b><div className="small muted">{l.email || l.phone}</div></div> },
          { key: 'platform', label: 'Platform' },
          { key: 'ip', label: 'IP' },
          { key: 'user_agent', label: 'Device', render: (l) => <span className="small muted">{(l.user_agent || '').slice(0, 60)}</span> },
        ]} />
    </Card>
  );
}

function Audit() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useAuditQuery(f);
  const [open, setOpen] = useState(null);
  return (
    <Card flush>
      <div style={{ padding: '14px 16px 0' }}>
        <Filters value={f} onChange={setF} placeholder="Action or person" filters={[
          { name: 'action', label: 'Action', options: [...new Set((data?.meta?.actions || []).map((a) => a.split('.')[0]))].map((x) => ({ value: x, label: x })) },
        ]}><DateRange f={f} setF={setF} /></Filters>
      </div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={setOpen}
        columns={[
          { key: 'created_at', label: 'Time', render: (a) => fmtDate(a.created_at, true) },
          { key: 'user', label: 'By', render: (a) => a.user || 'system' },
          { key: 'action', label: 'Action', render: (a) => <span className="kbd">{a.action}</span> },
          { key: 'subject', label: 'On' },
          { key: 'ip', label: 'IP' },
        ]} />
      {open && <Modal title={open.action} onClose={() => setOpen(null)}><pre className="small" style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(open.meta, null, 2) || 'No details'}</pre></Modal>}
    </Card>
  );
}

export default function SecurityPage() {
  const [tab, setTab] = useState('otp');
  return (
    <>
      <PageHead title="Security & logs" sub="Login OTPs, sign-ins and every sensitive action (exports, refunds, permission changes)." />
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'otp', label: '🔐 OTP logs' }, { key: 'logins', label: '🚪 Login activity' }, { key: 'audit', label: '🧾 Audit log' }]} />
      {tab === 'otp' && <OtpLogs />}
      {tab === 'logins' && <Logins />}
      {tab === 'audit' && <Audit />}
    </>
  );
}
