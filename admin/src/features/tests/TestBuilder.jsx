import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  useTestQuery, useSaveTestMutation, useTestActionMutation, useSaveSectionMutation, useDeleteSectionMutation, useTestQuestionsQuery,
  useAddTestQuestionsMutation, useAutoPickMutation, useRemoveTestQuestionsMutation, useArrangeTestMutation,
} from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { useQuestionsQuery, useLabelsQuery, useQFoldersQuery, flatFolders } from '../qbank/api';
import { Badge, Button, Can, Card, Confirm, Empty, Modal, PageHead, Spinner, Tabs } from '../../components/ui';
import { SchemaForm, useFormErrors } from '../../components/Form';
import Filters from '../../components/Filters';
import { TEST_FIELDS } from './TestsPage';
import TestResults from './TestResults';

const strip = (h = '') => h.replace(/<img[^>]*>/g, '🖼️').replace(/<[^>]+>/g, '');

function Picker({ test, section, onClose }) {
  const [tab, setTab] = useState('bank');
  const [f, setF] = useState({ page: 1, not_in_test: test.id, include_sub: 1 });
  const { data, isFetching } = useQuestionsQuery(f);
  const { data: labels = [] } = useLabelsQuery();
  const { data: tree } = useQFoldersQuery();
  const [sel, setSel] = useState([]);
  const [add, a] = useAddTestQuestionsMutation();
  const [pick, p] = useAutoPickMutation();

  return (
    <Modal wide title={`Add questions to “${section.name}”`} onClose={onClose}>
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'bank', label: 'Pick from the bank' }, { key: 'auto', label: 'Auto-pick by rules' }]} />
      {tab === 'bank' ? (
        <>
          <Filters value={f} onChange={setF} filters={[
            { name: 'folder_id', label: 'Folder', options: flatFolders(tree?.folders) },
            { name: 'difficulty', label: 'Difficulty', options: ['easy', 'moderate', 'hard'].map((x) => ({ value: x, label: x })) },
          ]}>
            <select className="input" style={{ width: 'auto' }} value={f.label_ids?.[0] || ''} onChange={(e) => setF({ ...f, page: 1, label_ids: e.target.value ? [e.target.value] : [] })} aria-label="Label">
              <option value="">Label: all</option>{labels.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
            </select>
          </Filters>
          {isFetching ? <Spinner /> : (
            <table className="t"><tbody>
              {(data?.items || []).map((q) => (
                <tr key={q.id} className="click" onClick={() => setSel((s) => (s.includes(q.id) ? s.filter((x) => x !== q.id) : [...s, q.id]))}>
                  <td><input type="checkbox" readOnly checked={sel.includes(q.id)} aria-label={`Pick ${q.id}`} /></td>
                  <td>#{q.id} {strip(q.translations?.en?.text || Object.values(q.translations || {})[0]?.text).slice(0, 120)}<div className="small muted">{q.subject} · {q.difficulty} · {(q.languages || []).join(', ')}</div></td>
                </tr>
              ))}
            </tbody></table>
          )}
          <div className="row between" style={{ marginTop: 12 }}>
            <span className="row">
              <Button size="sm" disabled={f.page <= 1} onClick={() => setF({ ...f, page: f.page - 1 })}>←</Button>
              <span className="small muted">page {f.page} / {data?.meta?.pagination?.last_page || 1}</span>
              <Button size="sm" disabled={f.page >= (data?.meta?.pagination?.last_page || 1)} onClick={() => setF({ ...f, page: f.page + 1 })}>→</Button>
            </span>
            <Button variant="primary" disabled={!sel.length} loading={a.isLoading} onClick={async () => { await add({ id: test.id, section_id: section.id, question_ids: sel }).unwrap(); onClose(); }}>Add {sel.length} questions</Button>
          </div>
        </>
      ) : (
        <SchemaForm submitText="Pick questions" loading={p.isLoading} initial={{ mix_easy: 0, mix_moderate: 0, mix_hard: 0, count: 25, exclude_used_in_tests: false }}
          fields={[
            { name: 'label_ids', label: 'From labels', type: 'multiselect', cols: 2, options: labels.map((l) => ({ value: l.id, label: `${l.name} (${l.questions_count})` })) },
            { name: 'folder_id', label: 'From folder (and sub-folders)', type: 'select', options: flatFolders(tree?.folders) },
            { name: 'lang', label: 'Must have language', type: 'select', options: [{ value: 'ml', label: 'Malayalam' }, { value: 'en', label: 'English' }] },
            { name: 'count', label: 'How many (random)', type: 'number', min: 1, hint: 'Or set a difficulty mix below' },
            { name: 'exclude_used_in_tests', label: 'Only questions not used in other tests', type: 'toggle' },
            { name: 'mix_easy', label: 'Easy', type: 'number', min: 0 },
            { name: 'mix_moderate', label: 'Moderate', type: 'number', min: 0 },
            { name: 'mix_hard', label: 'Hard', type: 'number', min: 0 },
          ]}
          onSubmit={async (v) => {
            const mix = { easy: +v.mix_easy || 0, moderate: +v.mix_moderate || 0, hard: +v.mix_hard || 0 };
            const hasMix = mix.easy + mix.moderate + mix.hard > 0;
            await pick({ id: test.id, section_id: section.id, label_ids: v.label_ids, folder_id: v.folder_id || undefined, lang: v.lang || undefined, exclude_used_in_tests: v.exclude_used_in_tests, ...(hasMix ? { mix } : { count: +v.count }) }).unwrap();
            onClose();
          }} />
      )}
    </Modal>
  );
}

export default function TestBuilder() {
  const { id } = useParams();
  const { data: test, isLoading } = useTestQuery(id);
  const { data: groups = [], isFetching } = useTestQuestionsQuery(id);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [save, s] = useSaveTestMutation();
  const [act] = useTestActionMutation();
  const [saveSection] = useSaveSectionMutation();
  const [delSection] = useDeleteSectionMutation();
  const [remove] = useRemoveTestQuestionsMutation();
  const [arrange] = useArrangeTestMutation();
  const [tab, setTab] = useState('questions');
  const [modal, setModal] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [errors, run] = useFormErrors();
  const nav = useNavigate();
  if (isLoading || !test) return <Spinner />;

  return (
    <>
      <PageHead title={test.title} sub={<span>{test.total_questions} questions · {test.total_marks} marks · {test.duration_min} min · <Badge>{test.status}</Badge> {test.mode === 'omr' && <Badge tone="orange">OMR</Badge>}</span>}>
        <Can perm="tests.publish">{test.status !== 'published'
          ? <Button variant="primary" onClick={() => act({ id: test.id, action: 'publish' })}>Publish</Button>
          : <Button onClick={() => act({ id: test.id, action: 'unpublish' })}>Unpublish</Button>}</Can>
        <Can perm="tests.edit"><Button onClick={() => setModal({ kind: 'dup' })}>Duplicate</Button></Can>
      </PageHead>
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'questions', label: '❓ Sections & questions' }, { key: 'settings', label: '⚙️ Settings' }, { key: 'results', label: `📊 Results (${test.attempts_count})` }]} />

      {tab === 'settings' && (
        <Card><SchemaForm errors={errors} loading={s.isLoading} initial={{ ...test, course_id: test.course_id || '' }} fields={TEST_FIELDS(courses)}
          onSubmit={(v) => { const keys = TEST_FIELDS([]).map((x) => x.name); return run(save({ id: test.id, ...Object.fromEntries(keys.map((k) => [k, v[k] === '' ? null : v[k]])) })); }} /></Card>
      )}
      {tab === 'results' && <TestResults test={test} />}
      {tab === 'questions' && (
        <div className="col">
          {isFetching && <Spinner />}
          {groups.map((g, gi) => (
            <Card key={g.section.id} title={`${gi + 1}. ${g.section.name} · ${g.questions.length} Qs · +${g.section.marks_per_question} / −${g.section.negative_per_question}`}
              actions={<Can perm="tests.edit">
                <Button size="sm" onClick={() => setModal({ kind: 'section', v: test.sections.find((x) => x.id === g.section.id) })}>Section settings</Button>
                <Button size="sm" variant="primary" onClick={() => setModal({ kind: 'pick', section: g.section })}>+ Questions</Button>
                {groups.length > 1 && <Button size="sm" variant="ghost" onClick={() => setConfirm(() => () => delSection(g.section.id))}>🗑️</Button>}
              </Can>}>
              {!g.questions.length ? <Empty icon="➕" title="No questions in this section" /> : (
                <table className="t"><tbody>
                  {g.questions.map((tq, i) => (
                    <tr key={tq.id}>
                      <td className="nowrap small muted">{i + 1}</td>
                      <td><div className="qtext">{strip(tq.question.translations?.en?.text || Object.values(tq.question.translations || {})[0]?.text).slice(0, 160)}</div>
                        <div className="small muted">#{tq.question.id} · {tq.question.difficulty} · {(tq.question.languages || []).join(', ')} · ans {(tq.question.options || []).map((o, k) => (o.is_correct ? String.fromCharCode(65 + k) : null)).filter(Boolean).join(',') || tq.question.numeric_answer}</div></td>
                      <td className="nowrap">
                        <select className="input" style={{ width: 'auto' }} value={g.section.id} aria-label="Move to section"
                          onChange={(e) => arrange({ id: test.id, items: [{ id: tq.id, section_id: Number(e.target.value) }] })}>
                          {groups.map((x) => <option key={x.section.id} value={x.section.id}>{x.section.name}</option>)}
                        </select>
                      </td>
                      <td className="right"><Can perm="tests.edit"><Button size="sm" variant="ghost" onClick={() => remove({ id: test.id, ids: [tq.id] })} aria-label="Remove">✕</Button></Can></td>
                    </tr>
                  ))}
                </tbody></table>
              )}
            </Card>
          ))}
          <Can perm="tests.edit"><div><Button onClick={() => setModal({ kind: 'section', v: { marks_per_question: 1, negative_per_question: 0.33 } })}>+ Add section</Button></div></Can>
        </div>
      )}

      {modal?.kind === 'pick' && <Picker test={test} section={modal.section} onClose={() => setModal(null)} />}
      {modal?.kind === 'section' && (
        <Modal title={modal.v.id ? 'Section settings' : 'New section'} onClose={() => setModal(null)}>
          <SchemaForm initial={modal.v} onCancel={() => setModal(null)} fields={[
            { name: 'name', label: 'Name', required: true, placeholder: 'General Awareness' }, { name: 'short_name', label: 'Short name', placeholder: 'GA' },
            { name: 'marks_per_question', label: 'Marks per question', type: 'number', step: '0.25' }, { name: 'negative_per_question', label: 'Negative per wrong answer', type: 'number', step: '0.01' },
            { name: 'duration_min', label: 'Section time (min)', type: 'number', show: () => test.sectional_timing }, { name: 'en_only', label: 'English only (e.g. English section)', type: 'toggle' },
          ]} onSubmit={async (v) => { await saveSection({ id: modal.v.id, test_id: test.id, name: v.name, short_name: v.short_name, marks_per_question: v.marks_per_question, negative_per_question: v.negative_per_question, duration_min: v.duration_min || null, en_only: !!v.en_only }).unwrap(); setModal(null); }} />
        </Modal>
      )}
      {modal?.kind === 'dup' && (
        <Modal title="Duplicate test" onClose={() => setModal(null)}>
          <SchemaForm initial={{ title: `${test.title} (copy)` }} onCancel={() => setModal(null)} fields={[{ name: 'title', label: 'New title', required: true }]}
            onSubmit={async (v) => { const t = await act({ id: test.id, action: 'duplicate', title: v.title }).unwrap(); setModal(null); nav(`/tests/${t.id}`); }} />
        </Modal>
      )}
      {confirm && <Confirm text="Delete this section and its questions from the test?" onYes={confirm} onClose={() => setConfirm(null)} />}
    </>
  );
}
