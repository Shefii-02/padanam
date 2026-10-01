import { Area, AreaChart, Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { useDashboardQuery } from './api';
import { Badge, Card, Empty, PageHead, Spinner, Stat, useMe } from '../../components/ui';

export default function DashboardPage() {
  const { data, isLoading } = useDashboardQuery(undefined, { pollingInterval: 60000 });
  const me = useMe();
  if (isLoading || !data) return <Spinner />;
  const r = data.revenue;
  const hour = new Date().getHours();

  return (
    <>
      <PageHead title={`${hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'}, ${me?.name?.split(' ')[0] || ''} 👋`} sub="Here is how Padanam is doing today." />
      <div className="grid g4" style={{ marginBottom: 14 }}>
        {r && <Stat label="Revenue today" value={r.today} hint={`${r.orders_today} orders · month ${r.month}`} icon="💰" />}
        <Stat label="Active students" value={data.students.total} hint={`${data.students.active_today} online today`} icon="👥" />
        <Stat label="New sign-ups today" value={data.students.new_today} hint={`${data.students.active_7d} active this week`} icon="✨" />
        <Stat label="Tests taken today" value={data.tests_today} hint={`${data.open_doubts} doubts waiting`} icon="📝" />
      </div>
      <div className="grid g2" style={{ marginBottom: 14 }}>
        {r && (
          <Card title="Revenue – last 30 days" actions={<span className="small muted">Last month {r.last_month}</span>}>
            <div style={{ height: 220 }}>
              <ResponsiveContainer>
                <AreaChart data={r.series_30d}>
                  <XAxis dataKey="date" tickFormatter={(d) => d.slice(8)} fontSize={11} />
                  <YAxis fontSize={11} width={60} tickFormatter={(v) => `₹${v >= 1000 ? `${Math.round(v / 1000)}k` : v}`} />
                  <Tooltip formatter={(v) => `₹${Number(v).toLocaleString('en-IN')}`} />
                  <Area type="monotone" dataKey="value" stroke="#3B4FD8" fill="#E9ECFB" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </Card>
        )}
        <Card title="Sign-ups – last 30 days">
          <div style={{ height: 220 }}>
            <ResponsiveContainer>
              <BarChart data={data.signups_30d}>
                <XAxis dataKey="date" tickFormatter={(d) => d.slice(8)} fontSize={11} />
                <YAxis fontSize={11} width={30} allowDecimals={false} />
                <Tooltip />
                <Bar dataKey="value" fill="#F2701B" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Card>
      </div>
      <div className="grid g2">
        <Card title="Live classes today">
          {data.live_today.length ? data.live_today.map((l) => (
            <div key={l.id} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
              <span className="b nowrap">{l.time}</span>
              <div className="grow"><div className="b">{l.title}</div><div className="small muted">{l.batch} · {l.teacher || '—'}</div></div>
              <Badge>{l.status}</Badge>
            </div>
          )) : <Empty icon="📺" title="No live classes today" />}
        </Card>
        <Card title="Top courses">
          {data.top_courses.map((c, i) => (
            <div key={c.id} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
              <span className="b">{i + 1}</span><span className="grow">{c.title}</span><Badge tone="green">{c.students} students</Badge>
            </div>
          ))}
          {data.expiring_7d !== undefined && <p className="small muted">{data.expiring_7d} enrolments end in the next 7 days – send a renewal reminder from Notifications.</p>}
        </Card>
      </div>
    </>
  );
}
