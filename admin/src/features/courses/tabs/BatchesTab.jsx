import { useState } from 'react';
import { useBatchesQuery, useSaveBatchMutation, useCloneBatchMutation, useBatchEnrollmentMutation, useDeleteBatchMutation, useStaffOptionsQuery } from '../api';
import { Badge, Button, Can, Card, Confirm, Modal, Spinner, Toggle, fmtDate } from '../../../components/ui';
import { SchemaForm, useFormErrors } from '../../../components/Form';

const VALIDITY = [{ value: 'days', label: 'Number of days' }, { value: 'fixed_date', label: 'Until a date' }, { value: 'lifetime', label: 'Lifetime' }];

export default function BatchesTab({ course }) {
  const { data: batches, isLoading } = useBatchesQuery(course.id);
  const { data: staff = [] } = useStaffOptionsQuery(true);
  const [save, s] = useSaveBatchMutation();
  const [clone] = useCloneBatchMutation();
  const [toggle] = useBatchEnrollmentMutation();
  const [del] = useDeleteBatchMutation();
  const [edit, setEdit] = useState(null);
  const [cloning, setCloning] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [errors, run] = useFormErrors();
  if (isLoading) return <Spinner />;

  return (
    <>
      <div className="row" style={{ justifyContent: 'flex-end', marginBottom: 12 }}>
        <Can perm="batches.create"><Button variant="primary" onClick={() => setEdit({ validity_type: 'days', validity_days: 365, coupon_policy: 'allow', enrollment_open: true })}>+ Batch</Button></Can>
      </div>
      <div className="grid g3">
        {batches.map((b) => (
          <Card key={b.id} title={<span>{b.name} {b.is_default && <Badge tone="grey">default</Badge>}</span>}>
            <div className="row" style={{ alignItems: 'baseline' }}><span style={{ fontSize: 22, fontWeight: 800 }}>{b.price_text}</span>{b.mrp_text && <s className="muted">{b.mrp_text}</s>}{b.discount_percent > 0 && <Badge tone="green">{b.discount_percent}% off</Badge>}</div>
            <div className="small muted" style={{ margin: '6px 0 10px' }}>
              {b.validity_text} · {b.seat_limit ? `${b.seats_taken}/${b.seat_limit} seats` : `${b.seats_taken} students`}
              {b.starts_at && ` · starts ${fmtDate(b.starts_at)}`} · code <span className="kbd">{b.code}</span>
            </div>
            <div className="small" style={{ marginBottom: 10 }}>👩‍🏫 {(b.staff || []).map((x) => x.name).join(', ') || 'No teachers'} · 🏷️ coupons: {b.coupon_policy}</div>
            <div className="row between">
              <span className="row small"><Toggle on={b.enrollment_open} onChange={(open) => toggle({ id: b.id, open })} label="Enrollment open" />{b.enrollment_open ? 'Admissions open' : 'Closed'}</span>
              <Badge>{b.status}</Badge>
            </div>
            <div className="row" style={{ marginTop: 12 }}>
              <Can perm="batches.edit"><Button size="sm" onClick={() => setEdit({ ...b, staff_ids: (b.staff || []).map((x) => x.id) })}>Edit</Button>
                <Button size="sm" onClick={() => setCloning(b)}>Clone</Button></Can>
              <Can perm="batches.delete"><Button size="sm" variant="danger" onClick={() => setConfirm(b.id)}>Delete</Button></Can>
            </div>
          </Card>
        ))}
      </div>

      {edit && (
        <Modal title={edit.id ? `Edit ${edit.name}` : 'New batch'} onClose={() => setEdit(null)} wide>
          <SchemaForm errors={errors} loading={s.isLoading} initial={edit} onCancel={() => setEdit(null)}
            fields={[
              { name: 'name', label: 'Batch name', required: true, placeholder: 'Morning batch – Jan 2027' },
              { name: 'status', label: 'Status', type: 'select', options: ['active', 'closed', 'archived'].map((x) => ({ value: x, label: x })), required: true },
              { name: 'price', label: 'Price (₹)', type: 'number', min: 0, required: true, hint: '0 = free batch' },
              { name: 'mrp', label: 'MRP (₹)', type: 'number', min: 0 },
              { name: 'starts_at', label: 'Starts on', type: 'date' },
              { name: 'ends_at', label: 'Admissions end on', type: 'date' },
              { name: 'validity_type', label: 'Access validity', type: 'select', options: VALIDITY, required: true },
              { name: 'validity_days', label: 'Days of access', type: 'number', show: (v) => v.validity_type === 'days' },
              { name: 'valid_until', label: 'Access until', type: 'date', show: (v) => v.validity_type === 'fixed_date' },
              { name: 'seat_limit', label: 'Seat limit', type: 'number', min: 1, hint: 'Leave empty for unlimited' },
              { name: 'coupon_policy', label: 'Coupons', type: 'select', options: [{ value: 'allow', label: 'Allow all coupons' }, { value: 'deny', label: 'No coupons' }, { value: 'custom', label: 'Only coupons linked to this batch' }] },
              { name: 'enrollment_open', label: 'Admissions open', type: 'toggle' },
              { name: 'staff_ids', label: 'Teachers of this batch', type: 'multiselect', cols: 2, options: staff.map((u) => ({ value: u.id, label: u.name })) },
            ]}
            onSubmit={async (v) => {
              const { staff_ids = [], staff: _s, coupon_ids: _c, ...rest } = v;
              await run(save({ ...rest, course_id: course.id, staff: staff_ids.map((id) => ({ user_id: id, role: 'teacher' })), seat_limit: v.seat_limit || null }));
              setEdit(null);
            }} />
        </Modal>
      )}
      {cloning && (
        <Modal title={`Clone ${cloning.name}`} onClose={() => setCloning(null)}>
          <p className="small muted" style={{ marginTop: 0 }}>Copies price, validity, teachers, coupons and the upcoming live-class schedule (shifted to the new start date).</p>
          <SchemaForm initial={{ name: `${cloning.name} (copy)` }} onCancel={() => setCloning(null)} submitText="Clone batch"
            fields={[{ name: 'name', label: 'New batch name', required: true }, { name: 'starts_at', label: 'New start date', type: 'date' }]}
            onSubmit={async (v) => { await clone({ id: cloning.id, ...v }).unwrap(); setCloning(null); }} />
        </Modal>
      )}
      {confirm && <Confirm text="Delete this batch? Batches with students can't be deleted." onYes={() => del(confirm)} onClose={() => setConfirm(null)} />}
    </>
  );
}
