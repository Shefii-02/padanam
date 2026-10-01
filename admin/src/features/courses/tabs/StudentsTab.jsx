import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useBatchStudentsQuery, useRemoveEnrollmentMutation, useExtendEnrollmentMutation, useMoveEnrollmentMutation } from '../api';
import { Badge, Button, Can, Card, Modal, ago, fmtDate } from '../../../components/ui';
import DataTable from '../../../components/DataTable';
import Filters from '../../../components/Filters';
import { SchemaForm } from '../../../components/Form';

export default function StudentsTab({ course }) {
  const batches = course.batches || [];
  const [batchId, setBatchId] = useState(batches.find((b) => !b.is_default)?.id || batches[0]?.id);
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useBatchStudentsQuery({ batchId, ...f }, { skip: !batchId });
  const [remove] = useRemoveEnrollmentMutation();
  const [extend] = useExtendEnrollmentMutation();
  const [move] = useMoveEnrollmentMutation();
  const [action, setAction] = useState(null);

  return (
    <Card flush>
      <div style={{ padding: '14px 16px 0' }}>
        <div className="chips" style={{ marginBottom: 12 }}>
          {batches.map((b) => <button key={b.id} className={`chip ${b.id === batchId ? 'on' : ''}`} onClick={() => { setBatchId(b.id); setF({ page: 1 }); }}>{b.name} · {b.seats_taken}</button>)}
        </div>
        <Filters value={f} onChange={setF} placeholder="Name or phone" filters={[
          { name: 'status', label: 'Status', options: ['active', 'expired', 'revoked'].map((x) => ({ value: x, label: x })) },
          { name: 'source', label: 'Joined by', options: ['purchase', 'manual', 'free', 'coupon_100'].map((x) => ({ value: x, label: x })) },
        ]}>
          <Can perm="enrollments.add_manual"><Link className="btn" to={`/admissions?batch=${batchId}`}>+ Add student</Link></Can>
        </Filters>
      </div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
        columns={[
          { key: 'user', label: 'Student', render: (e) => <Link to={`/students/${e.user?.id}`}><b>{e.user?.name || 'Unnamed'}</b></Link> },
          { key: 'phone', label: 'Phone', render: (e) => e.user?.phone },
          { key: 'source', label: 'Joined by', render: (e) => <span>{e.source}{e.paid ? ` · ${e.paid}` : ''}</span> },
          { key: 'expires_at', label: 'Access until', render: (e) => (e.expires_at ? fmtDate(e.expires_at) : 'Lifetime') },
          { key: 'seen', label: 'Last seen', render: (e) => ago(e.last_seen_at) },
          { key: 'status', label: 'Status', render: (e) => <Badge>{e.status}</Badge> },
          { key: 'x', label: '', className: 'right nowrap', render: (e) => (
            <>
              <Can perm="enrollments.extend"><Button size="sm" onClick={() => setAction({ kind: 'extend', e })}>Extend</Button></Can>
              <Can perm="enrollments.add_manual"><Button size="sm" onClick={() => setAction({ kind: 'move', e })}>Move</Button></Can>
              <Can perm="enrollments.remove">{e.status === 'active' && <Button size="sm" variant="danger" onClick={() => setAction({ kind: 'remove', e })}>Remove</Button>}</Can>
            </>
          ) },
        ]} />
      {action && (
        <Modal title={{ extend: 'Extend access', move: 'Move to another batch', remove: 'Remove access' }[action.kind]} onClose={() => setAction(null)}>
          <SchemaForm onCancel={() => setAction(null)} submitText="Confirm"
            fields={{
              extend: [{ name: 'days', label: 'Add days', type: 'number', min: 1 }, { name: 'until', label: 'Or set an end date', type: 'date' }],
              move: [{ name: 'batch_id', label: 'New batch', type: 'select', required: true, options: batches.filter((b) => b.id !== batchId).map((b) => ({ value: b.id, label: b.name })) }],
              remove: [{ name: 'reason', label: 'Reason (kept in the audit log)', cols: 2 }],
            }[action.kind]}
            onSubmit={async (v) => {
              const id = action.e.id;
              if (action.kind === 'extend') await extend({ id, days: v.days || undefined, until: v.until || undefined }).unwrap();
              if (action.kind === 'move') await move({ id, batch_id: v.batch_id }).unwrap();
              if (action.kind === 'remove') await remove({ id, reason: v.reason }).unwrap();
              setAction(null);
            }} />
        </Modal>
      )}
    </Card>
  );
}
