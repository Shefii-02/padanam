import { useState } from 'react';
import { usePaymentsQuery, usePaymentQuery, useRefreshPaymentMutation, useRefundMutation, useInvoiceLinksMutation, useLazyLinkWhatsappQuery } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { useWaStatusQuery, useSendLinkWaMutation, useSendInvoiceWaMutation } from '../whatsapp/api';
import { download } from '../../app/api';
import { Badge, Button, Can, Card, Modal, PageHead, Stat, fmtDate } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm } from '../../components/Form';

function OrderModal({ id, onClose }) {
  const { data: o } = usePaymentQuery(id);
  const [refresh, r] = useRefreshPaymentMutation();
  const [refund] = useRefundMutation();
  const [invoice] = useInvoiceLinksMutation();
  const [waLink] = useLazyLinkWhatsappQuery();
  const [refunding, setRefunding] = useState(false);
  const { data: wa } = useWaStatusQuery();
  const [sendLink, sl] = useSendLinkWaMutation();
  const [sendInv, si] = useSendInvoiceWaMutation();
  if (!o) return null;
  const openInvoice = async (share) => { const i = await invoice(o.id).unwrap(); window.open(share ? i.whatsapp_url : i.url, '_blank', 'noopener'); };

  return (
    <Modal title={`Order ${o.order_no}`} onClose={onClose}>
      <table className="t"><tbody>
        {[['Student', `${o.name} · ${o.phone}`], ['Course', `${o.course} – ${o.batch}`], ['Price', o.amount], ['Discount', `${o.discount}${o.coupon ? ` (${o.coupon})` : ''}`], ['Paid', o.total],
          ['Gateway', `${o.gateway} · ${o.channel}${o.manual_mode ? ` · ${o.manual_mode}` : ''}`], ['Reference', o.manual_reference || o.payments?.[0]?.gateway_payment_id], ['Status', <Badge key="s">{o.status}</Badge>],
          ['Created', fmtDate(o.created_at, true)], ['Paid at', fmtDate(o.paid_at, true)], ['By', o.created_by]].map(([k, v]) => <tr key={k}><td className="muted">{k}</td><td>{v || '—'}</td></tr>)}
      </tbody></table>
      {o.refunds?.length > 0 && <p className="small">Refunds: {o.refunds.map((x) => `₹${x.amount / 100} (${x.status})`).join(', ')}</p>}
      <div className="row wrap" style={{ marginTop: 12 }}>
        {['pending', 'created'].includes(o.status) && <Button loading={r.isLoading} onClick={() => refresh(o.id)}>🔄 Check payment now</Button>}
        {o.payment_link_url && o.status === 'pending' && (wa?.share_ready
          ? <Can perm="payments.create_link"><Button loading={sl.isLoading} onClick={() => sendLink(o.id)}>🟢 Send link (WhatsApp API)</Button></Can>
          : <Button onClick={async () => window.open((await waLink(o.id).unwrap()).whatsapp_url, '_blank')}>💬 Resend link</Button>)}
        {o.status === 'paid' && o.total_paise > 0 && <>
          <Button onClick={() => openInvoice(false)}>🧾 Invoice PDF</Button>
          {wa?.share_ready
            ? <Can perm="invoices.send"><Button loading={si.isLoading} onClick={() => sendInv(o.id)}>🟢 Send invoice PDF (WhatsApp API)</Button></Can>
            : <Button onClick={() => openInvoice(true)}>💬 Invoice on WhatsApp</Button>}
        </>}
        {o.status === 'paid' && o.total_paise > 0 && <Can perm="payments.refund"><Button variant="danger" onClick={() => setRefunding(true)}>Refund</Button></Can>}
      </div>
      {refunding && (
        <div className="card" style={{ marginTop: 12 }}>
          <SchemaForm initial={{ amount: o.total_paise / 100, revoke_access: true }} submitText="Refund" onCancel={() => setRefunding(false)}
            fields={[{ name: 'amount', label: 'Amount ₹', type: 'number', required: true }, { name: 'reason', label: 'Reason', required: true }, { name: 'revoke_access', label: 'Remove course access', type: 'toggle', cols: 2 }]}
            onSubmit={async (v) => { await refund({ id: o.id, ...v }).unwrap(); setRefunding(false); }} />
        </div>
      )}
    </Modal>
  );
}

export default function PaymentsPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = usePaymentsQuery(f);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [open, setOpen] = useState(null);
  const sm = data?.meta?.summary || {};

  return (
    <>
      <PageHead title="Payments" sub="App purchases, WhatsApp payment links and manual admissions.">
        <Can perm="payments.export"><Button onClick={() => download('admin/payments-export', { ...f, page: undefined })}>⬇️ Export</Button></Can>
      </PageHead>
      <div className="grid g4" style={{ marginBottom: 14 }}>
        <Stat label="Collected" value={sm.collected} hint={`${sm.orders_paid || 0} paid orders`} />
        <Stat label="Pending links" value={sm.pending_links} />
        <Stat label="Refunded" value={sm.refunded} />
        <Stat label="By gateway" value={<span className="small">{Object.entries(sm.by_gateway || {}).map(([k, v]) => `${k} ${v}`).join(' · ') || '—'}</span>} />
      </div>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} placeholder="Order no, name, phone" filters={[
            { name: 'status', label: 'Status', options: ['paid', 'pending', 'created', 'failed', 'refunded', 'expired'].map((x) => ({ value: x, label: x })) },
            { name: 'gateway', label: 'Gateway', options: ['razorpay', 'phonepe', 'manual', 'free'].map((x) => ({ value: x, label: x })) },
            { name: 'channel', label: 'Channel', options: [['app', 'App'], ['payment_link', 'Payment link'], ['admin', 'Manual']].map(([value, label]) => ({ value, label })) },
            { name: 'course_id', label: 'Course', options: courses.map((c) => ({ value: c.id, label: c.title })) },
          ]}>
            <input type="date" className="input" style={{ width: 'auto' }} value={f.from || ''} onChange={(e) => setF({ ...f, from: e.target.value, page: 1 })} aria-label="From" />
            <input type="date" className="input" style={{ width: 'auto' }} value={f.to || ''} onChange={(e) => setF({ ...f, to: e.target.value, page: 1 })} aria-label="To" />
          </Filters>
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={(o) => setOpen(o.id)}
          columns={[
            { key: 'order_no', label: 'Order', render: (o) => <div><div className="b">{o.order_no}</div><div className="small muted">{fmtDate(o.created_at, true)}</div></div> },
            { key: 'name', label: 'Student', render: (o) => <div>{o.name}<div className="small muted">{o.phone}</div></div> },
            { key: 'course', label: 'Course', render: (o) => <div>{o.course}<div className="small muted">{o.batch}</div></div> },
            { key: 'total', label: 'Paid', render: (o) => <div className="b">{o.total}{o.coupon && <div className="small muted">🏷️ {o.coupon}</div>}</div> },
            { key: 'gateway', label: 'Via', render: (o) => `${o.gateway}${o.channel === 'payment_link' ? ' · link' : ''}` },
            { key: 'status', label: 'Status', render: (o) => <Badge>{o.status}</Badge> },
          ]} />
      </Card>
      {open && <OrderModal id={open} onClose={() => setOpen(null)} />}
    </>
  );
}
