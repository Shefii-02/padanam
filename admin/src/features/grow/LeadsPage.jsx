import { useState } from 'react';
import { useLeadsQuery, useLeadQuery, useSaveLeadMutation, useLeadActivityMutation, useBulkLeadsMutation } from './api';
import { useCourseOptionsQuery, useStaffOptionsQuery } from '../courses/api';
import { download } from '../../app/api';
import { Badge, Button, Can, Card, Modal, PageHead, ago, fmtDate, useMe } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { Field, SchemaForm } from '../../components/Form';

const SOURCES = ['demo_video', 'launch_offer', 'course_page', 'free_test', 'app_install', 'whatsapp', 'website', 'manual'];

function LeadModal({ id, onClose }) {
  const { data: l } = useLeadQuery(id);
  const [save] = useSaveLeadMutation();
  const [log] = useLeadActivityMutation();
  const { data: staff = [] } = useStaffOptionsQuery(false);
  const [note, setNote] = useState('');
  if (!l) return null;
  const wa = (type) => { log({ id, type, note: note || undefined }); setNote(''); };
  return (
    <Modal wide title={`${l.name || 'Lead'} · ${l.phone}`} onClose={onClose}>
      <div className="grid g2">
        <div className="col">
          <div className="row wrap">
            <a className="btn primary" href={`https://wa.me/91${l.phone}`} target="_blank" rel="noreferrer" onClick={() => wa('whatsapp')}>💬 WhatsApp</a>
            <a className="btn" href={`tel:+91${l.phone}`} onClick={() => wa('call')}>📞 Call</a>
            <a className="btn" href={`/admissions?phone=${l.phone}&name=${encodeURIComponent(l.name || '')}`}>💳 Payment link</a>
          </div>
          <Field f={{ name: 'status', label: 'Status', type: 'select', required: true, options: ['new', 'contacted', 'converted', 'lost'].map((x) => ({ value: x, label: x })) }} value={l.status} onChange={(status) => save({ id, status })} />
          <Field f={{ name: 'assigned_to', label: 'Assigned to', type: 'select', options: staff.map((s) => ({ value: s.id, label: s.name })) }} value={l.assigned_to || ''} onChange={(v) => save({ id, assigned_to: v || null })} />
          <div className="small muted">Source: {l.source} · interest score {l.score} · {l.course?.title || l.category?.name || ''} · added {fmtDate(l.created_at)}</div>
        </div>
        <div className="col">
          <div className="row"><input className="input grow" placeholder="Add a note…" value={note} onChange={(e) => setNote(e.target.value)} aria-label="Note" /><Button onClick={() => note && wa('note')}>Add</Button></div>
          <div style={{ maxHeight: 280, overflow: 'auto' }}>
            {(l.activities || []).slice().reverse().map((a) => <div key={a.id} className="small" style={{ padding: '6px 0', borderBottom: '1px solid var(--line)' }}><b>{a.type}</b> {a.note} <span className="muted">· {a.author?.name} · {ago(a.created_at)}</span></div>)}
          </div>
        </div>
      </div>
    </Modal>
  );
}

export default function LeadsPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useLeadsQuery(f);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [save] = useSaveLeadMutation();
  const [bulk] = useBulkLeadsMutation();
  const [open, setOpen] = useState(null);
  const [adding, setAdding] = useState(false);
  const [sel, setSel] = useState([]);
  const me = useMe();
  const sm = data?.meta?.summary || {};

  return (
    <>
      <PageHead title="Leads" sub="People who watched demos, tapped offers or opened course pages but haven't bought yet. Highest interest first.">
        <Can perm="leads.export"><Button onClick={() => download('admin/leads/export', { ...f, page: undefined })}>⬇️ Export</Button></Can>
        <Can perm="leads.manage"><Button variant="primary" onClick={() => setAdding(true)}>+ Lead</Button></Can>
      </PageHead>
      <div className="chips" style={{ marginBottom: 12 }}>
        {['new', 'contacted', 'converted', 'lost'].map((s) => <button key={s} className={`chip ${f.status === s ? 'on' : ''}`} onClick={() => setF({ ...f, status: f.status === s ? '' : s, page: 1 })}>{s} · {sm[s] || 0}</button>)}
        <button className={`chip ${f.assigned_to === 'me' ? 'on' : ''}`} onClick={() => setF({ ...f, assigned_to: f.assigned_to === 'me' ? '' : 'me', page: 1 })}>Assigned to me</button>
      </div>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} placeholder="Name or phone" filters={[
            { name: 'source', label: 'Source', options: SOURCES.map((x) => ({ value: x, label: x.replace('_', ' ') })) },
            { name: 'course_id', label: 'Course', options: courses.map((c) => ({ value: c.id, label: c.title })) },
            { name: 'min_score', label: 'Interest', options: [{ value: 20, label: 'Warm (20+)' }, { value: 50, label: 'Hot (50+)' }] },
          ]} />
          {sel.length > 0 && <div className="row card" style={{ marginBottom: 12, background: 'var(--lav)', border: 0 }}>
            <b>{sel.length} selected</b>
            <Button size="sm" onClick={() => { bulk({ ids: sel, assigned_to: me.id }); setSel([]); }}>Assign to me</Button>
            <Button size="sm" onClick={() => { bulk({ ids: sel, status: 'contacted' }); setSel([]); }}>Mark contacted</Button>
            <Button size="sm" onClick={() => { bulk({ ids: sel, status: 'lost' }); setSel([]); }}>Mark lost</Button>
          </div>}
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
          columns={[
            { key: 's', label: '', render: (l) => <input type="checkbox" aria-label="Select" checked={sel.includes(l.id)} onChange={() => setSel((x) => (x.includes(l.id) ? x.filter((y) => y !== l.id) : [...x, l.id]))} /> },
            { key: 'name', label: 'Lead', render: (l) => <div style={{ cursor: 'pointer' }} onClick={() => setOpen(l.id)}><div className="b">{l.name || 'Unknown'}</div><div className="small muted">{l.phone} · {l.district || '—'}</div></div> },
            { key: 'source', label: 'Source', render: (l) => l.source.replace('_', ' ') },
            { key: 'course', label: 'Interested in', render: (l) => l.course || l.category || '—' },
            { key: 'score', label: 'Interest', render: (l) => <Badge tone={l.score >= 50 ? 'red' : l.score >= 20 ? 'orange' : 'grey'}>{l.score}</Badge> },
            { key: 'assigned_to', label: 'Owner' },
            { key: 'last', label: 'Last contact', render: (l) => ago(l.last_contacted_at) },
            { key: 'status', label: 'Status', render: (l) => <Badge>{l.status}</Badge> },
          ]} />
      </Card>
      {open && <LeadModal id={open} onClose={() => setOpen(null)} />}
      {adding && <Modal title="New lead" onClose={() => setAdding(false)}>
        <SchemaForm initial={{ source: 'manual' }} onCancel={() => setAdding(false)} fields={[
          { name: 'phone', label: 'Phone', required: true }, { name: 'name', label: 'Name' }, { name: 'district', label: 'District' },
          { name: 'interested_course_id', label: 'Course', type: 'select', options: courses.map((c) => ({ value: c.id, label: c.title })) },
          { name: 'source', label: 'Source', type: 'select', options: ['manual', 'whatsapp', 'website'].map((x) => ({ value: x, label: x })) }, { name: 'notes', label: 'Notes', cols: 2 },
        ]} onSubmit={async (v) => { await save({ ...v, interested_course_id: v.interested_course_id || null }).unwrap(); setAdding(false); }} />
      </Modal>}
    </>
  );
}
