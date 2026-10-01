import { useState } from 'react';
import { useDoubtsQuery, useAnswerDoubtMutation } from './api';
import { Badge, Button, Card, Empty, PageHead, Spinner, Tabs, ago } from '../../components/ui';

export default function DoubtsPage() {
  const [status, setStatus] = useState('open');
  const [page, setPage] = useState(1);
  const { data, isFetching } = useDoubtsQuery({ status, page });
  const [answer, s] = useAnswerDoubtMutation();
  const [text, setText] = useState({});

  return (
    <>
      <PageHead title="Doubts" sub="Questions from students in your courses. They get a notification when you reply." />
      <Tabs value={status} onChange={(x) => { setStatus(x); setPage(1); }} tabs={[{ key: 'open', label: 'Waiting' }, { key: 'answered', label: 'Answered' }]} />
      {isFetching && <Spinner />}
      {!isFetching && !data?.items.length && <Card><Empty icon="🎉" title={status === 'open' ? 'No doubts waiting' : 'Nothing answered yet'} /></Card>}
      <div className="col">
        {data?.items.map((d) => (
          <Card key={d.id}>
            <div className="row between"><div><b>{d.student?.name}</b> <span className="small muted">· {d.course || 'General'} {d.subject && `· ${d.subject}`} · {ago(d.created_at)}</span></div>{d.answer ? <Badge tone="green">answered</Badge> : <Badge tone="orange">waiting</Badge>}</div>
            <p style={{ whiteSpace: 'pre-wrap' }}>{d.text}</p>
            {d.image_url && <img src={d.image_url} alt="Doubt attachment" style={{ maxWidth: 320, borderRadius: 10 }} />}
            {d.answer ? <div className="card" style={{ background: 'var(--mint)', border: 0 }}><div className="small b">{d.answered_by}</div><div style={{ whiteSpace: 'pre-wrap' }}>{d.answer}</div></div> : (
              <div className="row" style={{ alignItems: 'flex-end' }}>
                <textarea className="input grow" rows={2} placeholder="Write your answer…" value={text[d.id] || ''} onChange={(e) => setText({ ...text, [d.id]: e.target.value })} aria-label="Answer" />
                <Button variant="primary" loading={s.isLoading} disabled={!text[d.id]} onClick={() => answer({ id: d.id, answer: text[d.id] })}>Send</Button>
              </div>
            )}
          </Card>
        ))}
      </div>
      {data?.meta?.pagination?.last_page > page && <div className="row" style={{ justifyContent: 'center', marginTop: 12 }}><Button onClick={() => setPage(page + 1)}>Next page</Button></div>}
    </>
  );
}
