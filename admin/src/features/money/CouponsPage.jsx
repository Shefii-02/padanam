import { useState } from 'react';
import { useCouponsQuery, useSaveCouponMutation, useDeleteCouponMutation } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead, fmtDate } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm, useFormErrors } from '../../components/Form';

export default function CouponsPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useCouponsQuery(f);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [save, s] = useSaveCouponMutation();
  const [del] = useDeleteCouponMutation();
  const [edit, setEdit] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [errors, run] = useFormErrors();
  const batches = courses.flatMap((c) => c.batches.map((b) => ({ value: b.id, label: `${c.title} – ${b.name}` })));

  return (
    <>
      <PageHead title="Coupons" sub="Old-student offers, launch discounts, batch-only codes.">
        <Can perm="coupons.manage"><Button variant="primary" onClick={() => setEdit({ type: 'percent', audience: 'all', per_user_limit: 1, is_active: true })}>+ Coupon</Button></Can>
      </PageHead>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}><Filters value={f} onChange={setF} filters={[{ name: 'state', label: 'State', options: [{ value: 'active', label: 'Active' }, { value: 'expired', label: 'Expired' }] }]} /></div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
          onRow={(c) => setEdit({ ...c, batch_ids: c.batches.map((b) => b.id), required_batch_ids: c.required_batches.map((b) => b.id) })}
          columns={[
            { key: 'code', label: 'Code', render: (c) => <div><span className="kbd b">{c.code}</span><div className="small muted">{c.title}</div></div> },
            { key: 'value_text', label: 'Discount', render: (c) => <b>{c.value_text}</b> },
            { key: 'audience', label: 'Who', render: (c) => <div>{c.audience.replace('_', ' ')}{c.required_batches.length > 0 && <div className="small muted">must own: {c.required_batches.map((b) => b.name).join(', ')}</div>}</div> },
            { key: 'batches', label: 'Works on', render: (c) => (c.batches.length ? c.batches.map((b) => b.name).join(', ') : 'All batches') },
            { key: 'used', label: 'Used', render: (c) => `${c.used_count}${c.total_limit ? ` / ${c.total_limit}` : ''} · ${c.total_discount_given}` },
            { key: 'ends_at', label: 'Ends', render: (c) => fmtDate(c.ends_at) },
            { key: 'state', label: 'State', render: (c) => <Badge tone={c.state === 'active' ? 'green' : 'grey'}>{c.state.replace('_', ' ')}</Badge> },
          ]} />
      </Card>
      {edit && (
        <Modal wide title={edit.id ? `Edit ${edit.code}` : 'New coupon'} onClose={() => setEdit(null)}
          footer={edit.id && <Can perm="coupons.manage"><Button variant="danger" onClick={() => setConfirm(edit.id)}>Delete / disable</Button></Can>}>
          <SchemaForm errors={errors} loading={s.isLoading} initial={edit} onCancel={() => setEdit(null)}
            fields={[
              { name: 'code', label: 'Code', required: true, placeholder: 'ONAM25' },
              { name: 'title', label: 'Title (shown to students)', required: true },
              { name: 'type', label: 'Type', type: 'select', required: true, options: [{ value: 'percent', label: '% off' }, { value: 'flat', label: '₹ off' }] },
              { name: 'value', label: 'Value (% or ₹)', type: 'number', required: true, min: 1 },
              { name: 'max_discount', label: 'Max discount ₹', type: 'number', show: (v) => v.type === 'percent' },
              { name: 'min_amount', label: 'Minimum price ₹', type: 'number' },
              { name: 'starts_at', label: 'Starts', type: 'datetime' }, { name: 'ends_at', label: 'Ends', type: 'datetime' },
              { name: 'total_limit', label: 'Total uses', type: 'number', hint: 'Empty = unlimited' }, { name: 'per_user_limit', label: 'Uses per student', type: 'number', min: 1 },
              { name: 'audience', label: 'Who can use it', type: 'select', required: true, options: [['all', 'Everyone'], ['new_users', 'First-time buyers'], ['existing_students', 'Existing students'], ['specific_users', 'Chosen students']].map(([value, label]) => ({ value, label })) },
              { name: 'show_in_app', label: 'Show as an offer on checkout', type: 'toggle' },
              { name: 'batch_ids', label: 'Works only on these batches (empty = all that allow coupons)', type: 'multiselect', cols: 2, options: batches },
              { name: 'required_batch_ids', label: 'Old-student offer: buyer must already own one of these batches', type: 'multiselect', cols: 2, options: batches },
              { name: 'is_active', label: 'Active', type: 'toggle' },
            ]}
            onSubmit={async (v) => {
              const keys = ['code', 'title', 'type', 'value', 'max_discount', 'min_amount', 'starts_at', 'ends_at', 'total_limit', 'per_user_limit', 'audience', 'show_in_app', 'batch_ids', 'required_batch_ids', 'is_active'];
              await run(save({ id: edit.id, ...Object.fromEntries(keys.map((k) => [k, v[k] === '' ? null : v[k]])) }));
              setEdit(null);
            }} />
        </Modal>
      )}
      {confirm && <Confirm text="Coupons that were already used are disabled instead of deleted." onYes={() => { del(confirm); setEdit(null); }} onClose={() => setConfirm(null)} />}
    </>
  );
}
