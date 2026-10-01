import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTestsQuery, useSaveTestMutation, KINDS } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { Badge, Button, Can, Card, Modal, PageHead, fmtDate } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm, useFormErrors } from '../../components/Form';

export const TEST_FIELDS = (courses) => [
  { name: 'title', label: 'Title', required: true, cols: 2, placeholder: 'SSC CGL Tier-1 Mock 5' },
  { name: 'course_id', label: 'Course', type: 'select', empty: 'Standalone (not in a course)', options: courses.map((c) => ({ value: c.id, label: c.title })) },
  { name: 'kind', label: 'Kind', type: 'select', required: true, options: KINDS },
  { name: 'mode', label: 'Mode', type: 'select', required: true, options: [{ value: 'online', label: 'Online (in the app)' }, { value: 'omr', label: 'OMR sheet (offline)' }] },
  { name: 'access', label: 'Access', type: 'select', required: true, options: [{ value: 'premium', label: 'Premium' }, { value: 'free', label: 'Free' }] },
  { name: 'duration_min', label: 'Duration (minutes)', type: 'number', required: true, min: 1 },
  { name: 'attempts_allowed', label: 'Attempts allowed', type: 'number', min: 1 },
  { name: 'languages', label: 'Languages', type: 'multiselect', options: [{ value: 'en', label: 'English' }, { value: 'ml', label: 'Malayalam' }, { value: 'hi', label: 'Hindi' }] },
  { name: 'show_result', label: 'Show result', type: 'select', options: [{ value: 'instant', label: 'Right after submitting' }, { value: 'after_end', label: 'After the test window ends' }, { value: 'manual', label: 'When I publish it' }] },
  { name: 'starts_at', label: 'Opens at', type: 'datetime' },
  { name: 'ends_at', label: 'Closes at', type: 'datetime' },
  { name: 'sectional_timing', label: 'Separate time for each section (no going back)', type: 'toggle' },
  { name: 'shuffle', label: 'Shuffle questions per student', type: 'toggle' },
  { name: 'instructions', label: 'Instructions', type: 'textarea', cols: 2 },
];

export default function TestsPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useTestsQuery(f);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [save, s] = useSaveTestMutation();
  const [open, setOpen] = useState(false);
  const [errors, run] = useFormErrors();
  const nav = useNavigate();

  return (
    <>
      <PageHead title="Tests & OMR" sub="Mock tests, sectionals, chapter tests and OMR exams.">
        <Can perm="tests.create"><Button variant="primary" onClick={() => setOpen(true)}>+ New test</Button></Can>
      </PageHead>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} filters={[
            { name: 'status', label: 'Status', options: ['draft', 'published', 'archived'].map((x) => ({ value: x, label: x })) },
            { name: 'kind', label: 'Kind', options: KINDS },
            { name: 'mode', label: 'Mode', options: [{ value: 'online', label: 'Online' }, { value: 'omr', label: 'OMR' }] },
            { name: 'course_id', label: 'Course', options: courses.map((c) => ({ value: c.id, label: c.title })) },
          ]} />
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={(t) => nav(`/tests/${t.id}`)}
          columns={[
            { key: 'title', label: 'Test', render: (t) => <div><div className="b">{t.title}</div><div className="small muted">{t.course || 'Standalone'} · {t.kind} {t.mode === 'omr' && '· OMR'}</div></div> },
            { key: 'q', label: 'Questions', render: (t) => `${t.total_questions} · ${t.total_marks} marks` },
            { key: 'duration_min', label: 'Time', render: (t) => `${t.duration_min} min` },
            { key: 'attempts_count', label: 'Attempts' },
            { key: 'window', label: 'Window', render: (t) => (t.starts_at ? `${fmtDate(t.starts_at, true)} →` : 'Always open') },
            { key: 'status', label: 'Status', render: (t) => <><Badge>{t.status}</Badge> <Badge tone={t.access === 'free' ? 'green' : 'purple'}>{t.access}</Badge></> },
          ]} />
      </Card>
      {open && (
        <Modal wide title="New test" onClose={() => setOpen(false)}>
          <SchemaForm errors={errors} loading={s.isLoading} onCancel={() => setOpen(false)} submitText="Create & add questions"
            initial={{ kind: 'mock', mode: 'online', access: 'premium', duration_min: 60, attempts_allowed: 1, languages: ['en', 'ml'], show_result: 'instant' }}
            fields={TEST_FIELDS(courses)}
            onSubmit={async (v) => { const t = await run(save({ ...v, course_id: v.course_id || null, starts_at: v.starts_at || null, ends_at: v.ends_at || null, sections: [{ name: 'Section 1', marks_per_question: 1, negative_per_question: 0.33 }] })); nav(`/tests/${t.id}`); }} />
        </Modal>
      )}
    </>
  );
}
