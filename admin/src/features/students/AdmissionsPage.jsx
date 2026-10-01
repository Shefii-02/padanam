import { useEffect, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { useAdmitMutation, usePaymentLinkMutation, useLazySwapLookupQuery, useSwapQuoteQuery, useDoSwapMutation, useSwapsQuery, useCancelSwapMutation } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { usePaymentsQuery } from '../money/api';
import { useWaStatusQuery } from '../whatsapp/api';
import { Badge, Button, Can, Card, Confirm, Empty, PageHead, Tabs, fmtDate, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { Field, SchemaForm, useFormErrors } from '../../components/Form';

const batchOptions = (courses) => courses.flatMap((c) => c.batches.filter((b) => b.status !== 'archived').map((b) => ({ value: b.id, label: `${c.title} – ${b.name}${b.price_text ? ` · ${b.price_text}` : ''}` })));

/** Result banner with WhatsApp status + wa.me fallback */
function Done({ done, onClose }) {
  if (!done) return null;
  const wa = done.whatsapp;
  return (
    <Card className="row wrap" >
      <span className="grow">✅ {done.text}
        {wa && <div className="small">{wa.status === 'sent' ? '🟢 Sent through the WhatsApp API' : <span style={{ color: 'var(--red)' }}>WhatsApp API failed: {wa.error} – use the button instead.</span>}</div>}
      </span>
      {done.waMe && (!wa || wa.status !== 'sent') && <a className="btn primary" href={done.waMe} target="_blank" rel="noreferrer">💬 Open WhatsApp</a>}
      {done.link && <Button onClick={() => navigator.clipboard.writeText(done.link)}>Copy link</Button>}
      <Button variant="ghost" onClick={onClose}>✕</Button>
    </Card>
  );
}

function LinkTab({ initial, courses, waReady, auto }) {
  const [link, l] = usePaymentLinkMutation();
  const [errors, run] = useFormErrors();
  const [done, setDone] = useState(null);
  return (
    <div className="col">
      <Done done={done} onClose={() => setDone(null)} />
      <Card title="💳 Send a payment link">
        <p className="small muted" style={{ marginTop: 0 }}>The student pays online; the course unlocks automatically, the invoice is created and (if on) sent on WhatsApp.</p>
        <SchemaForm key={JSON.stringify(initial)} errors={errors} loading={l.isLoading} initial={{ ...initial, expires_in_hours: 72, send_whatsapp: waReady && auto?.payment_link !== false }} submitText="Create link"
          fields={[
            { name: 'phone', label: 'Mobile number', required: true, placeholder: '98xxxxxxxx' },
            { name: 'name', label: 'Student name', required: true },
            { name: 'batch_id', label: 'Course & batch', type: 'select', required: true, cols: 2, options: batchOptions(courses) },
            { name: 'coupon', label: 'Coupon code (optional)' },
            { name: 'amount', label: 'Special price ₹ (optional)', type: 'number', min: 1, hint: 'Overrides the batch price' },
            { name: 'expires_in_hours', label: 'Link valid for (hours)', type: 'number', min: 1 },
            { name: 'note', label: 'Note (internal)' },
            { name: 'send_whatsapp', label: waReady ? 'Send the link on WhatsApp now (API)' : 'WhatsApp API not set up – you will get a WhatsApp button instead', type: 'toggle', cols: 2, show: () => waReady },
          ]}
          onSubmit={async (v) => {
            const o = await run(link({ ...v, amount: v.amount || undefined, coupon: v.coupon || undefined, send_whatsapp: waReady && !!v.send_whatsapp }));
            setDone({ text: `Link for ${o.name}: ${o.total}`, whatsapp: o.whatsapp, waMe: o.whatsapp_url, link: o.payment_link_url });
          }} />
      </Card>
    </div>
  );
}

function ManualTab({ initial, courses, waReady }) {
  const [admit, a] = useAdmitMutation();
  const [errors, run] = useFormErrors();
  const [done, setDone] = useState(null);
  return (
    <div className="col">
      <Done done={done} onClose={() => setDone(null)} />
      <Card title="🧾 Manual admission">
        <p className="small muted" style={{ marginTop: 0 }}>For cash, bank transfer, UPI outside the app, cheque or scholarship. Creates the student if new, the order, invoice and access.</p>
        <SchemaForm key={JSON.stringify(initial)} errors={errors} loading={a.isLoading} initial={{ ...initial, mode: 'cash', send_whatsapp: waReady }} submitText="Admit student"
          fields={[
            { name: 'phone', label: 'Mobile number', required: true },
            { name: 'name', label: 'Student name', required: true },
            { name: 'batch_id', label: 'Course & batch', type: 'select', required: true, cols: 2, options: batchOptions(courses) },
            { name: 'mode', label: 'Paid by', type: 'select', required: true, options: [['cash', 'Cash'], ['bank', 'Bank transfer'], ['upi_outside', 'UPI (outside the app)'], ['cheque', 'Cheque'], ['scholarship', 'Scholarship'], ['free', 'Free']].map(([value, label]) => ({ value, label })) },
            { name: 'amount', label: 'Amount received ₹', type: 'number', min: 0 },
            { name: 'reference', label: 'Reference / UTR' },
            { name: 'expires_at', label: 'Access until (optional)', type: 'date', hint: 'Default: batch validity' },
            { name: 'note', label: 'Note', cols: 2 },
            { name: 'send_whatsapp', label: 'Send invoice PDF (or welcome message if free) on WhatsApp', type: 'toggle', cols: 2, show: () => waReady },
          ]}
          onSubmit={async (v) => {
            const o = await run(admit({ ...v, expires_at: v.expires_at || undefined, send_whatsapp: waReady && !!v.send_whatsapp }));
            setDone({ text: `${o.name} admitted to ${o.batch}. They can log in with ${o.phone}.`, whatsapp: o.whatsapp, waMe: o.invoice_whatsapp_url });
          }} />
      </Card>
    </div>
  );
}

function SwapTab({ initialPhone, courses, waReady, auto }) {
  const [phone, setPhone] = useState(initialPhone || '');
  const [lookup, lk] = useLazySwapLookupQuery();
  const [student, setStudent] = useState(null);
  const [enr, setEnr] = useState(null);
  const [toBatch, setToBatch] = useState('');
  const [v, setV] = useState({ mode: 'free', keep_expiry: true, send_whatsapp: waReady && auto?.swap !== false, payment_mode: 'cash' });
  const { data: quote, isFetching: quoting } = useSwapQuoteQuery({ enrollmentId: enr?.id, toBatchId: toBatch }, { skip: !enr || !toBatch });
  const [swap, sw] = useDoSwapMutation();
  const [errors, run] = useFormErrors();
  const [done, setDone] = useState(null);
  const set = (k) => (x) => setV((o) => ({ ...o, [k]: x }));

  const find = async (p = phone) => {
    const r = await lookup(p).unwrap().catch(() => null);
    setStudent(r); setEnr(null); setToBatch(''); setDone(null);
  };
  useEffect(() => { if (initialPhone) find(initialPhone); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [initialPhone]);
  useEffect(() => { if (quote) setV((o) => ({ ...o, mode: quote.difference > 0 ? 'payment_link' : 'free', amount: quote.difference > 0 ? quote.difference / 100 : '' })); }, [quote]);

  const submit = async () => {
    const r = await run(swap({ enrollmentId: enr.id, to_batch_id: Number(toBatch), ...v, amount: v.mode === 'free' ? undefined : v.amount }));
    setDone(r);
    find(student.user.phone);
  };

  return (
    <div className="col">
      {done && (
        <Card className="row wrap" >
          <span className="grow">✅ {done.status === 'pending_payment' ? `Waiting for payment – ${done.to} unlocks after ${done.student?.name} pays.` : `${done.student?.name} moved to ${done.to}.`}</span>
          {done.payment_link_url && <Button onClick={() => navigator.clipboard.writeText(done.payment_link_url)}>Copy payment link</Button>}
          <Button variant="ghost" onClick={() => setDone(null)}>✕</Button>
        </Card>
      )}
      <Card title="🔁 Course swap">
        <p className="small muted" style={{ marginTop: 0 }}>Move a student to another batch of <b>any course</b> – free, with the difference received offline, or with a payment link (the swap finishes after payment).</p>
        <div className="row" style={{ maxWidth: 420 }}>
          <input className="input grow" placeholder="Student mobile number" value={phone} onChange={(e) => setPhone(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && find()} aria-label="Student phone" />
          <Button loading={lk.isFetching} disabled={phone.length < 10} onClick={() => find()}>Find</Button>
        </div>
        {lk.isSuccess && !student && <p className="muted">No student with this number. Use Manual admission instead.</p>}
      </Card>
      {student && (
        <div className="grid g2" style={{ alignItems: 'start' }}>
          <Card title={<span>{student.user.name || 'Student'} · {student.user.phone} <Link className="small" to={`/students/${student.user.id}`}>open</Link></span>}>
            <div className="small b muted" style={{ marginBottom: 6 }}>1. Choose the course to move FROM</div>
            {!student.enrollments.length && <Empty icon="📭" title="No courses" />}
            {student.enrollments.map((e) => (
              <div key={e.id} className={`row node ${enr?.id === e.id ? 'on' : ''}`} style={{ padding: 10, borderRadius: 10, cursor: e.status === 'active' ? 'pointer' : 'default', opacity: e.status === 'active' ? 1 : 0.55, background: enr?.id === e.id ? 'var(--lav)' : undefined }}
                onClick={() => e.status === 'active' && setEnr(e)}>
                <input type="radio" readOnly checked={enr?.id === e.id} disabled={e.status !== 'active'} aria-label={e.course} />
                <div className="grow"><b>{e.course}</b><div className="small muted">{e.batch} · {e.paid ? `paid ${e.paid}` : e.source} · until {e.expires_at ? fmtDate(e.expires_at) : 'lifetime'}</div></div>
                <Badge>{e.status}</Badge>
              </div>
            ))}
          </Card>
          <Card title="2. Move TO">
            {!enr ? <p className="muted">Pick a course on the left.</p> : (
              <div className="col">
                <Field f={{ name: 'to', label: 'New course & batch', type: 'select', required: true, options: batchOptions(courses).filter((b) => b.value !== enr.batch_id) }} value={toBatch} onChange={setToBatch} />
                {quoting && <span className="small muted">Checking price…</span>}
                {quote && <>
                  <div className="card" style={{ background: 'var(--bg)' }}>
                    <div className="row between"><span>Paid for {quote.from.course}</span><b>{quote.from.paid}</b></div>
                    <div className="row between"><span>Price of {quote.to.course}</span><b>{quote.to.price}</b></div>
                    <div className="row between" style={{ borderTop: '1px solid var(--line)', marginTop: 6, paddingTop: 6 }}>
                      <span className="b">{quote.difference > 0 ? 'Student should pay' : quote.difference < 0 ? 'New course is cheaper by' : 'Same price'}</span><b>{quote.difference_text}</b></div>
                    {quote.to.seats_left === 0 && <div className="small" style={{ color: 'var(--saffron)' }}>This batch is full – the swap still goes through (admin override).</div>}
                  </div>
                  <div className="chips">
                    {[['free', 'Free swap'], ['collected', 'Received offline'], ['payment_link', 'Send payment link']].map(([k, l]) => (
                      <button key={k} type="button" className={`chip ${v.mode === k ? 'on' : ''}`} onClick={() => set('mode')(k)}>{l}</button>
                    ))}
                  </div>
                  {v.mode !== 'free' && <Field f={{ name: 'amount', label: v.mode === 'payment_link' ? 'Amount to collect ₹' : 'Amount received ₹', type: 'number', min: 0 }} value={v.amount} onChange={set('amount')} error={errors.amount} />}
                  {v.mode === 'collected' && <div className="grid g2">
                    <Field f={{ name: 'payment_mode', label: 'Paid by', type: 'select', required: true, options: [['cash', 'Cash'], ['bank', 'Bank transfer'], ['upi_outside', 'UPI'], ['cheque', 'Cheque']].map(([value, label]) => ({ value, label })) }} value={v.payment_mode} onChange={set('payment_mode')} />
                    <Field f={{ name: 'reference', label: 'Reference / UTR' }} value={v.reference} onChange={set('reference')} />
                  </div>}
                  <Field f={{ name: 'keep_expiry', label: `Keep the current end date (${enr.expires_at ? fmtDate(enr.expires_at) : 'lifetime'}) – off = new batch validity${quote.to.new_expiry ? ` (${fmtDate(quote.to.new_expiry)})` : ''}`, type: 'toggle' }} value={v.keep_expiry} onChange={set('keep_expiry')} />
                  {waReady && <Field f={{ name: 'send_whatsapp', label: v.mode === 'payment_link' ? 'Send the payment link on WhatsApp' : 'Tell the student on WhatsApp', type: 'toggle' }} value={v.send_whatsapp} onChange={set('send_whatsapp')} />}
                  <Field f={{ name: 'reason', label: 'Reason (kept in history)', placeholder: 'Wants the evening batch / upgraded to LDC + LGS combo' }} value={v.reason} onChange={set('reason')} />
                  <Button variant="primary" loading={sw.isLoading} onClick={submit}>{v.mode === 'payment_link' ? 'Create payment link' : 'Swap now'}</Button>
                </>}
              </div>
            )}
          </Card>
        </div>
      )}
    </div>
  );
}

function SwapHistory() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useSwapsQuery(f);
  const [cancel] = useCancelSwapMutation();
  const [confirm, setConfirm] = useState(null);
  return (
    <Card flush>
      <div style={{ padding: '14px 16px 0' }}>
        <Filters value={f} onChange={setF} placeholder="Name or phone" filters={[
          { name: 'status', label: 'Status', options: [['done', 'Done'], ['pending_payment', 'Waiting for payment'], ['cancelled', 'Cancelled']].map(([value, label]) => ({ value, label })) },
          { name: 'mode', label: 'Type', options: [['free', 'Free'], ['collected', 'Received offline'], ['payment_link', 'Payment link']].map(([value, label]) => ({ value, label })) },
        ]} />
      </div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
        columns={[
          { key: 'created_at', label: 'When', render: (s) => fmtDate(s.created_at, true) },
          { key: 'student', label: 'Student', render: (s) => <div><b>{s.student?.name}</b><div className="small muted">{s.student?.phone}</div></div> },
          { key: 'move', label: 'From → To', render: (s) => <div className="small">{s.from}<div>→ <b>{s.to}</b></div></div> },
          { key: 'money', label: 'Difference / collected', render: (s) => <div className="small">{s.difference} / {s.collected}{s.order_no && <div className="muted">{s.order_no}</div>}</div> },
          { key: 'mode', label: 'Type', render: (s) => s.mode.replace('_', ' ') },
          { key: 'status', label: 'Status', render: (s) => <Badge tone={{ done: 'green', pending_payment: 'orange', cancelled: 'grey' }[s.status]}>{s.status.replace('_', ' ')}</Badge> },
          { key: 'by', label: 'By' },
          { key: 'x', label: '', render: (s) => s.status === 'pending_payment' && <>
            {s.payment_link_url && <Button size="sm" onClick={() => navigator.clipboard.writeText(s.payment_link_url)}>Copy link</Button>}
            <Button size="sm" variant="ghost" onClick={() => setConfirm(s.id)}>Cancel</Button></> },
        ]} />
      {confirm && <Confirm text="Cancel this swap? The payment link stops working and the student keeps the old course." onYes={() => cancel(confirm)} onClose={() => setConfirm(null)} />}
    </Card>
  );
}

function History() {
  const [f, setF] = useState({ page: 1, channel: 'admin' });
  const { data, isFetching } = usePaymentsQuery(f);
  return (
    <Card flush>
      <div style={{ padding: '14px 16px 0' }}>
        <Filters value={f} onChange={setF} placeholder="Name, phone or order" filters={[
          { name: 'channel', label: 'Type', options: [{ value: 'admin', label: 'Manual admissions' }, { value: 'payment_link', label: 'Payment links' }] },
          { name: 'status', label: 'Status', options: ['paid', 'pending', 'expired', 'cancelled'].map((x) => ({ value: x, label: x })) },
        ]} />
      </div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
        columns={[
          { key: 'created_at', label: 'When', render: (o) => fmtDate(o.created_at, true) },
          { key: 'name', label: 'Student', render: (o) => <div><b>{o.name}</b><div className="small muted">{o.phone}</div></div> },
          { key: 'course', label: 'Course', render: (o) => <div>{o.course}<div className="small muted">{o.batch}</div></div> },
          { key: 'total', label: 'Amount', render: (o) => <div><b>{o.total}</b><div className="small muted">{o.manual_mode || o.gateway}</div></div> },
          { key: 'status', label: 'Status', render: (o) => <Badge>{o.status}</Badge> },
          { key: 'created_by', label: 'By' },
        ]} />
    </Card>
  );
}

export default function AdmissionsPage() {
  const [params, setParams] = useSearchParams();
  const can = useCan();
  const { data: courses = [] } = useCourseOptionsQuery();
  const { data: wa } = useWaStatusQuery();
  const tabs = [
    can('payments.create_link') && { key: 'link', label: '💳 Payment link' },
    can('enrollments.add_manual') && { key: 'manual', label: '🧾 Manual admission' },
    can('enrollments.swap') && { key: 'swap', label: '🔁 Course swap' },
    can('payments.view') && { key: 'history', label: '📜 Admission history' },
    can('enrollments.swap') && { key: 'swaps', label: '🔁 Swap history' },
  ].filter(Boolean);
  const tab = params.get('tab') || tabs[0]?.key;
  const setTab = (t) => setParams({ tab: t });
  const initial = { phone: params.get('phone') || '', name: params.get('name') || '', batch_id: params.get('batch') || '' };

  return (
    <>
      <PageHead title="Admissions" sub={<span>Payment links, offline admissions and course swaps. WhatsApp API: {wa?.share_ready ? <Badge tone="green">connected</Badge> : <><Badge tone="grey">not set up</Badge> <Can perm="whatsapp.manage"><Link to="/whatsapp" className="small">set up</Link></Can></>}</span>} />
      <Tabs value={tab} onChange={setTab} tabs={tabs} />
      {tab === 'link' && <LinkTab initial={initial} courses={courses} waReady={!!wa?.share_ready} auto={wa?.auto} />}
      {tab === 'manual' && <ManualTab initial={initial} courses={courses} waReady={!!wa?.share_ready} />}
      {tab === 'swap' && <SwapTab initialPhone={params.get('phone')} courses={courses} waReady={!!wa?.share_ready} auto={wa?.auto} />}
      {tab === 'history' && <History />}
      {tab === 'swaps' && <SwapHistory />}
    </>
  );
}
