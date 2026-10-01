import { useState } from 'react';
import { useRevenueQuery, useConfigureRevenueMutation, useLedgerQuery, usePayoutsQuery, useAddPayoutMutation } from './api';
import { Button, Can, Card, Modal, PageHead, Spinner, Stat, Tabs, Toggle, fmtDate } from '../../components/ui';
import DataTable from '../../components/DataTable';
import { SchemaForm } from '../../components/Form';

export default function RevenueSharePage() {
  const { data: s, isLoading } = useRevenueQuery();
  const [configure] = useConfigureRevenueMutation();
  const [page, setPage] = useState(1);
  const { data: ledger, isFetching } = useLedgerQuery({ page });
  const { data: payouts = [] } = usePayoutsQuery();
  const [addPayout, ap] = useAddPayoutMutation();
  const [tab, setTab] = useState('ledger');
  const [pct, setPct] = useState(null);
  const [adding, setAdding] = useState(false);
  if (isLoading || !s) return <Spinner />;

  return (
    <>
      <PageHead title="Revenue share" sub={`${s.partner_name} gets a share of every new payment while this is on.`}>
        <Can perm="revenue_share.manage"><Button variant="primary" onClick={() => setAdding(true)}>+ Record payout</Button></Can>
      </PageHead>
      <Card className="row wrap" >
        <Can perm="revenue_share.manage" fallback={<b>{s.enabled ? 'On' : 'Off'}</b>}>
          <Toggle on={s.enabled} onChange={(v) => configure({ enabled: v, percent: pct ?? s.percent })} label="Revenue share" />
        </Can>
        <b>{s.enabled ? 'Revenue share is ON' : 'Revenue share is OFF'}</b>
        <span className="grow" />
        <label className="row small">Share %
          <input className="input" type="number" style={{ width: 90 }} value={pct ?? s.percent} onChange={(e) => setPct(e.target.value)} min={0} max={100} step="0.5" aria-label="Percent" />
        </label>
        <Can perm="revenue_share.manage"><Button onClick={() => configure({ enabled: s.enabled, percent: pct ?? s.percent })}>Save %</Button></Can>
        <span className="small muted" style={{ width: '100%' }}>Changes apply to new payments only. Refunds reduce the share automatically.</span>
      </Card>
      <div className="grid g4" style={{ margin: '14px 0' }}>
        <Stat label="Total share earned" value={s.total_share_text} />
        <Stat label="Paid out" value={s.paid_out_text} />
        <Stat label="Balance to pay" value={s.balance_text} hint="Share − payouts" />
        <Stat label="This month" value={s.this_month_share} />
      </div>
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'ledger', label: 'Share per payment' }, { key: 'payouts', label: `Payout history (${payouts.length})` }]} />
      <Card flush>
        {tab === 'ledger' ? (
          <DataTable loading={isFetching} rows={ledger?.items} meta={ledger?.meta} page={page} onPage={setPage}
            columns={[{ key: 'date', label: 'Date' }, { key: 'order_no', label: 'Order' }, { key: 'student', label: 'Student' }, { key: 'batch', label: 'Batch' }, { key: 'order_total', label: 'Payment' }, { key: 'percent', label: '%' }, { key: 'share', label: 'Share', render: (r) => <b>{r.share}</b> }]} />
        ) : (
          <DataTable rows={payouts} columns={[{ key: 'paid_on', label: 'Date', render: (p) => fmtDate(p.paid_on) }, { key: 'amount', label: 'Amount', render: (p) => <b>{p.amount}</b> }, { key: 'method', label: 'Method' }, { key: 'reference', label: 'Reference' }, { key: 'note', label: 'Note' }, { key: 'by', label: 'By' }]} />
        )}
      </Card>
      {adding && (
        <Modal title="Record a payout" onClose={() => setAdding(false)}>
          <p className="small muted" style={{ marginTop: 0 }}>Balance now: <b>{s.balance_text}</b></p>
          <SchemaForm loading={ap.isLoading} initial={{ paid_on: new Date().toISOString().slice(0, 10), method: 'bank' }} onCancel={() => setAdding(false)}
            fields={[{ name: 'amount', label: 'Amount ₹', type: 'number', required: true, min: 1 }, { name: 'paid_on', label: 'Paid on', type: 'date', required: true },
              { name: 'method', label: 'Method', type: 'select', required: true, options: ['bank', 'upi', 'cash', 'cheque'].map((x) => ({ value: x, label: x })) }, { name: 'reference', label: 'Reference / UTR' },
              { name: 'note', label: 'Note', cols: 2 }, { name: 'allow_advance', label: 'Allow more than the balance (advance)', type: 'toggle', cols: 2 }]}
            onSubmit={async (v) => { await addPayout(v).unwrap(); setAdding(false); }} />
        </Modal>
      )}
    </>
  );
}
