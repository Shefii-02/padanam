import { Link, useParams } from 'react-router-dom';
import { useStudentQuery, useBlockStudentMutation } from './api';
import { Avatar, Badge, Button, Can, Card, PageHead, Spinner, Stat, ago, fmtDate } from '../../components/ui';

export default function StudentDetail() {
  const { id } = useParams();
  const { data, isLoading } = useStudentQuery(id);
  const [block] = useBlockStudentMutation();
  if (isLoading || !data) return <Spinner />;
  const u = data.user;

  return (
    <>
      <PageHead title={<span className="row"><Avatar name={u.name} emoji={u.avatar} />{u.name || 'Unnamed student'}</span>} sub={`${u.phone} · ${u.district || '—'} · joined ${fmtDate(u.created_at)} · last seen ${ago(u.last_seen_at)}`}>
        <a className="btn" href={`https://wa.me/91${u.phone}`} target="_blank" rel="noreferrer">💬 WhatsApp</a>
        <Can perm="enrollments.add_manual"><Link className="btn" to={`/admissions?tab=manual&phone=${u.phone}&name=${encodeURIComponent(u.name || '')}`}>+ Add to course</Link></Can>
        <Can perm="enrollments.swap"><Link className="btn" to={`/admissions?tab=swap&phone=${u.phone}`}>🔁 Swap course</Link></Can>
        <Can perm="users.block"><Button variant={u.status === 'blocked' ? 'primary' : 'danger'} onClick={() => block({ id: u.id, block: u.status !== 'blocked' })}>{u.status === 'blocked' ? 'Unblock' : 'Block'}</Button></Can>
      </PageHead>
      <div className="grid g4" style={{ marginBottom: 14 }}>
        <Stat label="Tests taken" value={data.stats.tests_taken} />
        <Stat label="Average score" value={`${data.stats.avg_score_percent}%`} hint={data.stats.best_rank ? `Best rank ${data.stats.best_rank}` : ''} />
        <Stat label="Study minutes (30 d)" value={data.stats.watch_minutes_30d} />
        <Stat label="Active days (30 d)" value={data.stats.active_days_30d} />
      </div>
      <div className="grid g2" style={{ alignItems: 'start' }}>
        <Card title="Courses">
          {data.enrollments.map((e) => (
            <div key={e.id} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
              <div className="grow"><Link to={`/courses/${e.course?.id}`} className="b">{e.course?.title}</Link><div className="small muted">{e.batch?.name} · {e.source} · until {e.expires_at ? fmtDate(e.expires_at) : 'lifetime'}</div></div>
              <Badge>{e.status}</Badge>
            </div>
          ))}
          {!data.enrollments.length && <p className="muted">Not enrolled in any course.</p>}
        </Card>
        <Card title="Profile">
          <table className="t"><tbody>
            {[['Gender', u.gender], ['Age', u.age], ['Qualification', u.qualification], ['Language', u.language], ['Target', u.profile?.aim], ['Study hours', u.profile?.study_hours],
              ['Exams', (u.interests || []).map((i) => i.target_post || i.category_id).join(', ')], ['Referral code', u.referral_code]].map(([k, v]) => <tr key={k}><td className="muted">{k}</td><td>{v ?? '—'}</td></tr>)}
          </tbody></table>
        </Card>
        <Card title="Recent tests">
          {data.attempts.map((a) => (
            <div key={a.id} className="row" style={{ padding: '6px 0', borderBottom: '1px solid var(--line)' }}>
              <span className="grow">{a.test?.title}</span><b>{a.score}/{a.test?.total_marks}</b>{a.rank && <Badge>#{a.rank}</Badge>}
            </div>
          ))}
          {!data.attempts.length && <p className="muted">No tests yet.</p>}
        </Card>
        <Card title="Payments">
          {data.orders.map((o) => (
            <div key={o.id} className="row" style={{ padding: '6px 0', borderBottom: '1px solid var(--line)' }}>
              <span className="grow">{o.batch?.name}<div className="small muted">{o.order_no} · {fmtDate(o.created_at)} · {o.gateway}</div></span>
              <b>₹{(o.total / 100).toLocaleString('en-IN')}</b><Badge>{o.status}</Badge>
            </div>
          ))}
          {!data.orders.length && <p className="muted">No payments.</p>}
        </Card>
      </div>
    </>
  );
}
