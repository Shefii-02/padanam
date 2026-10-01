import { useState } from 'react';
import { Bar, BarChart, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { useRevenueReportQuery, useActivityReportQuery, usePerformanceReportQuery } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { download } from '../../app/api';
import { Button, Can, Card, PageHead, Spinner, Stat, Tabs, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';

function Revenue() {
  const [f, setF] = useState({ group_by: 'course', from: `${new Date().getFullYear()}-01-01`, to: new Date().toISOString().slice(0, 10) });
  const { data, isFetching } = useRevenueReportQuery(f);
  return (
    <div className="col">
      <div className="row wrap">
        <select className="input" style={{ width: 'auto' }} value={f.group_by} onChange={(e) => setF({ ...f, group_by: e.target.value })} aria-label="Group by">
          {['course', 'batch', 'month', 'gateway', 'coupon'].map((x) => <option key={x} value={x}>By {x}</option>)}
        </select>
        <input type="date" className="input" style={{ width: 'auto' }} value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} aria-label="From" />
        <input type="date" className="input" style={{ width: 'auto' }} value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} aria-label="To" />
        <Can perm="reports.export"><Button onClick={() => download('admin/reports/revenue/export', f)}>⬇️ CSV</Button></Can>
      </div>
      {isFetching || !data ? <Spinner /> : <>
        <div className="grid g3"><Stat label="Revenue" value={data.total} /><Stat label="Refunds" value={data.refunds} /><Stat label="Net" value={data.net} /></div>
        <Card><div style={{ height: 260 }}><ResponsiveContainer><BarChart data={data.rows.slice(0, 15)}><XAxis dataKey="label" fontSize={11} interval={0} angle={-15} height={60} /><YAxis fontSize={11} /><Tooltip formatter={(v) => `₹${Number(v).toLocaleString('en-IN')}`} /><Bar dataKey="revenue" fill="#3B4FD8" radius={[4, 4, 0, 0]} /></BarChart></ResponsiveContainer></div></Card>
        <Card flush><DataTable rows={data.rows} columns={[{ key: 'label', label: f.group_by }, { key: 'orders', label: 'Orders' }, { key: 'revenue_text', label: 'Revenue' }, { key: 'discount_text', label: 'Discounts given' }]} /></Card>
      </>}
    </div>
  );
}

function Activity() {
  const [days, setDays] = useState(14);
  const { data } = useActivityReportQuery({ inactive_days: days });
  if (!data) return <Spinner />;
  return (
    <div className="col">
      <label className="row small">Inactive means not opened for <input className="input" type="number" style={{ width: 80 }} value={days} onChange={(e) => setDays(e.target.value)} aria-label="Days" /> days</label>
      <div className="grid g4">
        <Stat label="Students" value={data.total} /><Stat label="Active" value={data.active} /><Stat label="Inactive" value={data.inactive} />
        <Stat label="Paid but inactive" value={data.paid_inactive} hint="Call or push them – at risk of not renewing" />
      </div>
      <Card title="Daily active students (30 days)"><div style={{ height: 240 }}><ResponsiveContainer><LineChart data={data.daily}><XAxis dataKey="date" fontSize={11} tickFormatter={(d) => d.slice(5)} /><YAxis fontSize={11} /><Tooltip /><Line dataKey="users" stroke="#0F7A55" dot={false} /></LineChart></ResponsiveContainer></div></Card>
      <Card title="Active students by district"><div className="chips">{Object.entries(data.by_district || {}).map(([d, n]) => <span key={d} className="chip">{d} · {n}</span>)}</div></Card>
    </div>
  );
}

function Performance() {
  const { data: courses = [] } = useCourseOptionsQuery();
  const [f, setF] = useState({ min_tests: 1, limit: 100 });
  const { data = [], isFetching } = usePerformanceReportQuery(f);
  return (
    <div className="col">
      <div className="row wrap">
        <select className="input" style={{ width: 'auto' }} value={f.course_id || ''} onChange={(e) => setF({ ...f, course_id: e.target.value })} aria-label="Course">
          <option value="">All courses</option>{courses.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
        </select>
        <label className="row small">Min tests <input className="input" type="number" style={{ width: 70 }} value={f.min_tests} onChange={(e) => setF({ ...f, min_tests: e.target.value })} aria-label="Minimum tests" /></label>
        <Can perm="reports.export"><Button onClick={() => download('admin/reports/performance/export', f)}>⬇️ CSV</Button></Can>
      </div>
      <Card flush><DataTable loading={isFetching} rows={data} columns={[{ key: 'rank', label: 'Rank', render: (r) => <b>{r.rank}</b> }, { key: 'name', label: 'Student' }, { key: 'district', label: 'District' }, { key: 'tests', label: 'Tests' }, { key: 'avg_percent', label: 'Avg %' }, { key: 'accuracy', label: 'Accuracy %' }]} /></Card>
    </div>
  );
}

export default function ReportsPage() {
  const can = useCan();
  const [tab, setTab] = useState(can('payments.view') ? 'revenue' : 'activity');
  return (
    <>
      <PageHead title="Reports" />
      <Tabs value={tab} onChange={setTab} tabs={[can('payments.view') && { key: 'revenue', label: '💰 Revenue' }, { key: 'activity', label: '📈 Active / inactive' }, { key: 'performance', label: '🏆 Performance ranking' }]} />
      {tab === 'revenue' && <Revenue />}
      {tab === 'activity' && <Activity />}
      {tab === 'performance' && <Performance />}
    </>
  );
}
