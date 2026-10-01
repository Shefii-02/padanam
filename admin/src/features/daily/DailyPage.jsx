import { useState } from 'react';
import { useDailyCalendarQuery, usePlanDailyMutation, usePlanRangeMutation, useBuildDailyMutation, useDeleteDailyMutation, usePlanTemplatesQuery, useSavePlanMutation } from './api';
import { useCategoriesQuery } from '../catalog/api';
import { useLabelsQuery } from '../qbank/api';
import { useCourseOptionsQuery } from '../courses/api';
import { Badge, Button, Can, Card, Modal, PageHead, Tabs } from '../../components/ui';
import { SchemaForm } from '../../components/Form';

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function Calendar() {
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
  const { data = [] } = useDailyCalendarQuery(month);
  const { data: cats = [] } = useCategoriesQuery();
  const { data: labels = [] } = useLabelsQuery();
  const [plan] = usePlanDailyMutation();
  const [range] = usePlanRangeMutation();
  const [build] = useBuildDailyMutation();
  const [del] = useDeleteDailyMutation();
  const [modal, setModal] = useState(null);
  const first = new Date(`${month}-01T00:00:00`);
  const daysIn = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
  const offset = (first.getDay() + 6) % 7;
  const byDate = data.reduce((m, d) => ({ ...m, [d.date]: [...(m[d.date] || []), d] }), {});
  const shift = (n) => { const d = new Date(first); d.setMonth(d.getMonth() + n); setMonth(d.toISOString().slice(0, 7)); };
  const catOpts = cats.map((c) => ({ value: c.id, label: c.name }));
  const labelOpts = labels.map((l) => ({ value: l.id, label: `${l.name} (${l.questions_count})` }));

  return (
    <Card title={first.toLocaleString('en-IN', { month: 'long', year: 'numeric' })} actions={<>
      <Button size="sm" onClick={() => shift(-1)}>←</Button><Button size="sm" onClick={() => shift(1)}>→</Button>
      <Can perm="daily_quiz.manage"><Button size="sm" variant="primary" onClick={() => setModal({ kind: 'range' })}>Plan many days</Button></Can>
    </>}>
      <p className="small muted" style={{ marginTop: 0 }}>Each day's quiz is built from its label at 6 AM, using questions not used in earlier daily quizzes. Students who picked that exam get a reminder.</p>
      <div className="grid" style={{ gridTemplateColumns: 'repeat(7,minmax(0,1fr))', gap: 6 }}>
        {DAYS.map((d) => <div key={d} className="small b muted" style={{ textAlign: 'center' }}>{d}</div>)}
        {Array.from({ length: offset }).map((_, i) => <div key={`o${i}`} />)}
        {Array.from({ length: daysIn }, (_, i) => {
          const date = `${month}-${String(i + 1).padStart(2, '0')}`;
          const items = byDate[date] || [];
          return (
            <div key={date} className="card" style={{ padding: 6, minHeight: 84, cursor: 'pointer' }} onClick={() => setModal({ kind: 'day', date, items })}>
              <div className="small b">{i + 1}</div>
              {items.map((q) => <div key={q.id} className="small" style={{ marginTop: 2 }}><Badge>{q.status}</Badge> {q.category || 'All'}{q.participants ? ` · ${q.participants}👤` : ''}</div>)}
            </div>
          );
        })}
      </div>
      {modal?.kind === 'day' && (
        <Modal title={`Daily quiz – ${modal.date}`} onClose={() => setModal(null)}>
          {modal.items.map((q) => (
            <div key={q.id} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
              <div className="grow"><b>{q.category || 'All exams'}</b> · {q.label || 'fixed test'} · {q.questions} Qs <Badge>{q.status}</Badge></div>
              {q.status === 'planned' && <Button size="sm" onClick={() => build(q.id)}>Build now</Button>}
              {q.status !== 'published' && <Button size="sm" variant="ghost" onClick={() => { del(q.id); setModal(null); }}>✕</Button>}
            </div>
          ))}
          <h3 style={{ marginTop: 16 }}>Plan a quiz for this day</h3>
          <SchemaForm initial={{ questions: 10 }} submitText="Plan" onSubmit={async (v) => { await plan({ date: modal.date, ...v, exam_category_id: v.exam_category_id || null }).unwrap(); setModal(null); }}
            fields={[
              { name: 'exam_category_id', label: 'Exam', type: 'select', empty: 'All exams', options: catOpts },
              { name: 'label_id', label: 'Questions from label', type: 'select', required: true, options: labelOpts },
              { name: 'topic', label: 'Topic (shown to students)' },
              { name: 'questions', label: 'Questions', type: 'number', min: 3 },
            ]} />
        </Modal>
      )}
      {modal?.kind === 'range' && (
        <Modal title="Plan many days" onClose={() => setModal(null)}>
          <SchemaForm initial={{ questions: 10, skip_sundays: false }} submitText="Plan days" onCancel={() => setModal(null)}
            onSubmit={async (v) => { await range({ ...v, exam_category_id: v.exam_category_id || null }).unwrap(); setModal(null); }}
            fields={[
              { name: 'from', label: 'From', type: 'date', required: true }, { name: 'to', label: 'To', type: 'date', required: true },
              { name: 'exam_category_id', label: 'Exam', type: 'select', empty: 'All exams', options: catOpts },
              { name: 'label_id', label: 'Questions from label', type: 'select', required: true, options: labelOpts },
              { name: 'questions', label: 'Questions per day', type: 'number', min: 3 }, { name: 'skip_sundays', label: 'Skip Sundays', type: 'toggle' },
            ]} />
        </Modal>
      )}
    </Card>
  );
}

function Plans() {
  const { data = [] } = usePlanTemplatesQuery();
  const { data: courses = [] } = useCourseOptionsQuery();
  const [save, s] = useSavePlanMutation();
  const [edit, setEdit] = useState(null);
  const setItem = (day, i, patch) => setEdit((e) => ({ ...e, week: e.week.map((d) => (d.day === day ? { ...d, items: d.items.map((it, j) => (j === i ? { ...it, ...patch } : it)) } : d)) }));

  return (
    <Card title="Weekly study plans" actions={<Can perm="study_plans.manage"><Button size="sm" variant="primary" onClick={() => setEdit({ title: '', course_id: courses[0]?.id, week: DAYS.map((_, i) => ({ day: i + 1, items: [] })) })}>+ Plan</Button></Can>}>
      <p className="small muted" style={{ marginTop: 0 }}>A weekly template for a course or batch. Every student gets dated tasks they can tick off in the app.</p>
      {data.map((p) => (
        <div key={p.id} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
          <div className="grow"><b>{p.title}</b> <span className="small muted">· {p.course?.title} {p.batch && `· ${p.batch.name}`} · {(p.week || []).reduce((n, d) => n + d.items.length, 0)} tasks/week</span></div>
          <Button size="sm" onClick={() => setEdit({ ...p, week: DAYS.map((_, i) => p.week?.find((d) => d.day === i + 1) || { day: i + 1, items: [] }) })}>Edit</Button>
        </div>
      ))}
      {edit && (
        <Modal wide title={edit.id ? 'Edit study plan' : 'New study plan'} onClose={() => setEdit(null)}
          footer={<Button variant="primary" loading={s.isLoading} onClick={async () => { await save({ id: edit.id, title: edit.title, course_id: edit.course_id, batch_id: edit.batch_id || null, week: edit.week.filter((d) => d.items.length) }).unwrap(); setEdit(null); }}>Save plan</Button>}>
          <div className="grid g3" style={{ marginBottom: 12 }}>
            <input className="input" placeholder="Plan name" value={edit.title} onChange={(e) => setEdit({ ...edit, title: e.target.value })} aria-label="Plan name" />
            <select className="input" value={edit.course_id || ''} onChange={(e) => setEdit({ ...edit, course_id: Number(e.target.value), batch_id: null })} aria-label="Course">
              {courses.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
            </select>
            <select className="input" value={edit.batch_id || ''} onChange={(e) => setEdit({ ...edit, batch_id: e.target.value ? Number(e.target.value) : null })} aria-label="Batch">
              <option value="">All batches</option>{courses.find((c) => c.id === edit.course_id)?.batches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </select>
          </div>
          {edit.week.map((d) => (
            <div key={d.day} style={{ marginBottom: 10 }}>
              <div className="row"><b style={{ width: 40 }}>{DAYS[d.day - 1]}</b>
                <Button size="sm" onClick={() => setEdit((e) => ({ ...e, week: e.week.map((x) => (x.day === d.day ? { ...x, items: [...x.items, { title: '', type: 'video', minutes: 30 }] } : x)) }))}>+ task</Button></div>
              {d.items.map((it, i) => (
                <div key={i} className="row" style={{ marginTop: 6, marginLeft: 40 }}>
                  <select className="input" style={{ width: 110 }} value={it.type} onChange={(e) => setItem(d.day, i, { type: e.target.value })} aria-label="Type">{['video', 'notes', 'test', 'quiz', 'revise', 'study'].map((t) => <option key={t}>{t}</option>)}</select>
                  <input className="input grow" placeholder="e.g. Watch Kerala Renaissance class" value={it.title} onChange={(e) => setItem(d.day, i, { title: e.target.value })} aria-label="Task" />
                  <input className="input" style={{ width: 80 }} type="number" value={it.minutes} onChange={(e) => setItem(d.day, i, { minutes: Number(e.target.value) })} aria-label="Minutes" />
                  <Button size="sm" variant="ghost" onClick={() => setEdit((e) => ({ ...e, week: e.week.map((x) => (x.day === d.day ? { ...x, items: x.items.filter((_, j) => j !== i) } : x)) }))} aria-label="Remove">✕</Button>
                </div>
              ))}
            </div>
          ))}
        </Modal>
      )}
    </Card>
  );
}

export default function DailyPage() {
  const [tab, setTab] = useState('quiz');
  return (
    <>
      <PageHead title="Daily quiz & study plans" />
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'quiz', label: '⚡ Daily quiz' }, { key: 'plans', label: '📅 Study plans' }]} />
      {tab === 'quiz' ? <Calendar /> : <Plans />}
    </>
  );
}
