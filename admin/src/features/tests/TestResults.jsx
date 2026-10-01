import { useState } from 'react';
import { useResultsQuery, useTestActionMutation, useOmrImportMutation, useOmrEnterMutation, useOmrPendingQuery } from './api';
import { download } from '../../app/api';
import { Button, Can, Card, Modal, Stat, fmtDate } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { Field } from '../../components/Form';

export default function TestResults({ test }) {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useResultsQuery({ id: test.id, ...f });
  const [act] = useTestActionMutation();
  const [omrImport, oi] = useOmrImportMutation();
  const [omrEnter, oe] = useOmrEnterMutation();
  const { data: pending = [] } = useOmrPendingQuery(test.id, { skip: test.mode !== 'omr' });
  const [file, setFile] = useState(null);
  const [sheet, setSheet] = useState(null);
  const [bubbles, setBubbles] = useState({});
  const sm = data?.meta?.summary || {};
  const qs = data?.meta?.question_stats || [];
  const hardest = [...qs].filter((q) => q.accuracy !== null).sort((a, b) => a.accuracy - b.accuracy).slice(0, 5);

  return (
    <div className="col">
      <div className="grid g4">
        <Stat label="Students" value={sm.participants} hint={`${sm.in_progress || 0} writing now`} />
        <Stat label="Average" value={sm.average} hint={`of ${test.total_marks}`} />
        <Stat label="Highest" value={sm.highest} />
        <Stat label="Lowest" value={sm.lowest} />
      </div>
      <div className="row wrap">
        <Button onClick={() => download(`admin/tests/${test.id}/results/export`)}>⬇️ Export CSV</Button>
        {test.show_result === 'manual' && !test.result_published_at && <Can perm="tests.publish"><Button variant="primary" onClick={() => act({ id: test.id, action: 'publish-result' })}>Publish result & notify</Button></Can>}
        {test.mode === 'omr' && <>
          <Button onClick={() => download(`admin/tests/${test.id}/omr-sheet`)}>🖨️ Blank OMR sheet (PDF)</Button>
          <span className="row"><input type="file" accept=".csv" className="input" style={{ width: 240 }} onChange={(e) => setFile(e.target.files[0])} aria-label="Scanner CSV" />
            <Button disabled={!file} loading={oi.isLoading} onClick={() => omrImport({ id: test.id, file })}>Import scanner CSV</Button></span>
        </>}
      </div>
      {test.mode === 'omr' && pending.length > 0 && (
        <Card title={`OMR photos to check (${pending.length})`}>
          {pending.map((p) => <div key={p.id} className="row" style={{ padding: '6px 0' }}><span className="grow">{p.user?.name} · {p.user?.phone}</span><Button size="sm" onClick={() => { setSheet(p); setBubbles({}); }}>Enter answers</Button></div>)}
        </Card>
      )}
      {hardest.length > 0 && <Card title="Hardest questions"><div className="chips">{hardest.map((q) => <span key={q.id} className="chip">Q{q.id}: {q.accuracy}% correct</span>)}</div></Card>}
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}><Filters value={f} onChange={setF} placeholder="Name or phone" /></div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
          columns={[
            { key: 'rank', label: 'Rank', render: (r) => <b>{r.rank ?? '—'}</b> },
            { key: 'name', label: 'Student', render: (r) => <div><div className="b">{r.name}</div><div className="small muted">{r.phone} · {r.district}</div></div> },
            { key: 'score', label: 'Score', render: (r) => <b>{r.score}</b> },
            { key: 'cws', label: '✓ / ✗ / –', render: (r) => `${r.correct} / ${r.wrong} / ${r.skipped}` },
            { key: 'time', label: 'Time', render: (r) => `${Math.round(r.time_sec / 60)} min` },
            { key: 'percentile', label: 'Percentile' },
            { key: 'submitted_at', label: 'Submitted', render: (r) => fmtDate(r.submitted_at, true) },
          ]} />
      </Card>
      {sheet && (
        <Modal wide title={`OMR – ${sheet.user?.name}`} onClose={() => setSheet(null)}
          footer={<Button variant="primary" loading={oe.isLoading} onClick={async () => { await omrEnter({ id: test.id, user_id: sheet.user.id, sheet_id: sheet.id, answers: bubbles }).unwrap(); setSheet(null); }}>Evaluate</Button>}>
          <div className="grid g2">
            <img src={sheet.image_url} alt="OMR sheet" style={{ width: '100%', borderRadius: 10 }} />
            <div className="grid g3" style={{ alignContent: 'start' }}>
              {Array.from({ length: test.total_questions }, (_, i) => i + 1).map((n) => (
                <Field key={n} f={{ name: `q${n}`, label: `Q${n}`, placeholder: 'A / B,D' }} value={bubbles[n]} onChange={(v) => setBubbles({ ...bubbles, [n]: v.toUpperCase() })} />
              ))}
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
