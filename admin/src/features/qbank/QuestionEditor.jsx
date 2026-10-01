import { useState } from 'react';
import { useSaveQuestionMutation, useQFoldersQuery, useLabelsQuery, flatFolders, LANGS } from './api';
import { Button, Modal, Tabs, Toggle } from '../../components/ui';
import { Field, useFormErrors } from '../../components/Form';

const blank = { type: 'mcq_single', difficulty: 'moderate', default_marks: 1, default_negative: 0.33, translations: { en: { text: '', solution: '' } }, options: [0, 1, 2, 3].map(() => ({ is_correct: false, text: {} })), label_ids: [] };

/** Question editor: text/solution per language, options with the correct answer, marks, labels. HTML allowed (images via <img>). */
export default function QuestionEditor({ question, defaultFolder, onClose }) {
  const [q, setQ] = useState(() => (question ? {
    ...question,
    label_ids: (question.labels || []).map((l) => l.id),
    options: (question.options || []).map((o) => ({ is_correct: o.is_correct, text: { ...o.text } })),
    translations: { ...question.translations },
  } : { ...blank, folder_id: defaultFolder || '' }));
  const [lang, setLang] = useState('en');
  const [save, s] = useSaveQuestionMutation();
  const { data: folders } = useQFoldersQuery();
  const { data: labels = [] } = useLabelsQuery();
  const [errors, run] = useFormErrors();
  const set = (k) => (v) => setQ((x) => ({ ...x, [k]: v }));
  const setT = (field, v) => setQ((x) => ({ ...x, translations: { ...x.translations, [lang]: { ...(x.translations[lang] || {}), [field]: v } } }));
  const setOpt = (i, patch) => setQ((x) => ({ ...x, options: x.options.map((o, j) => (j === i ? { ...o, ...patch } : (patch.is_correct && x.type !== 'mcq_multi' ? { ...o, is_correct: false } : o))) }));
  const langs = Array.from(new Set(['en', 'ml', ...Object.keys(q.translations)]));

  const submit = async () => {
    const body = { ...q, folder_id: q.folder_id || null, options: q.type === 'numeric' ? [] : q.options };
    delete body.labels; delete body.folder; delete body.languages; delete body.used_in_tests;
    await run(save(body));
    onClose();
  };

  return (
    <Modal wide title={question ? `Edit question #${question.id}` : 'New question'} onClose={onClose}
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" loading={s.isLoading} onClick={submit}>Save question</Button></>}>
      <div className="grid g4" style={{ marginBottom: 12 }}>
        <Field f={{ name: 'type', label: 'Type', type: 'select', required: true, options: [{ value: 'mcq_single', label: 'Single answer' }, { value: 'mcq_multi', label: 'Multiple answers' }, { value: 'true_false', label: 'True / False' }, { value: 'numeric', label: 'Numeric' }] }} value={q.type} onChange={(t) => setQ((x) => ({ ...x, type: t, options: t === 'true_false' ? [{ is_correct: true, text: { en: 'True', ml: 'ശരി' } }, { is_correct: false, text: { en: 'False', ml: 'തെറ്റ്' } }] : x.options }))} />
        <Field f={{ name: 'difficulty', label: 'Difficulty', type: 'select', required: true, options: ['easy', 'moderate', 'hard'].map((x) => ({ value: x, label: x })) }} value={q.difficulty} onChange={set('difficulty')} />
        <Field f={{ name: 'default_marks', label: 'Marks', type: 'number', step: '0.25' }} value={q.default_marks} onChange={set('default_marks')} />
        <Field f={{ name: 'default_negative', label: 'Negative', type: 'number', step: '0.01' }} value={q.default_negative} onChange={set('default_negative')} />
        <Field f={{ name: 'subject', label: 'Subject' }} value={q.subject} onChange={set('subject')} />
        <Field f={{ name: 'topic', label: 'Topic' }} value={q.topic} onChange={set('topic')} />
        <Field f={{ name: 'folder_id', label: 'Folder', type: 'select', options: flatFolders(folders?.folders) }} value={q.folder_id} onChange={set('folder_id')} />
        <Field f={{ name: 'year', label: 'Year (PYQ)', type: 'number' }} value={q.year} onChange={set('year')} />
      </div>
      <Tabs value={lang} onChange={setLang} tabs={langs.map((l) => ({ key: l, label: `${LANGS.find((x) => x.value === l)?.label || l}${q.translations[l]?.text ? ' ✓' : ''}` }))} />
      <div className="col">
        <Field f={{ name: `text_${lang}`, label: `Question (${lang})`, type: 'textarea', rows: 3, hint: 'HTML allowed – <b>, <sup>, <img src="…">' }} value={q.translations[lang]?.text} onChange={(v) => setT('text', v)} error={errors[`translations.${lang}.text`]} />
        {q.type === 'numeric' ? (
          <Field f={{ name: 'numeric_answer', label: 'Correct answer' }} value={q.numeric_answer} onChange={set('numeric_answer')} />
        ) : (
          <div className="col" style={{ gap: 8 }}>
            <label className="small b muted">Options ({lang}) – tick the correct answer{q.type === 'mcq_multi' ? 's' : ''}</label>
            {q.options.map((o, i) => (
              <div key={i} className="row">
                <Toggle on={o.is_correct} onChange={(v) => setOpt(i, { is_correct: v })} label={`Option ${String.fromCharCode(65 + i)} correct`} />
                <b>{String.fromCharCode(65 + i)}</b>
                <input className="input grow" value={o.text[lang] || ''} onChange={(e) => setOpt(i, { text: { ...o.text, [lang]: e.target.value } })} aria-label={`Option ${String.fromCharCode(65 + i)}`} />
                {q.type !== 'true_false' && q.options.length > 2 && <Button size="sm" variant="ghost" onClick={() => setQ((x) => ({ ...x, options: x.options.filter((_, j) => j !== i) }))} aria-label="Remove option">✕</Button>}
              </div>
            ))}
            {q.type !== 'true_false' && q.options.length < 6 && <Button size="sm" onClick={() => setQ((x) => ({ ...x, options: [...x.options, { is_correct: false, text: {} }] }))}>+ Option</Button>}
          </div>
        )}
        <Field f={{ name: `sol_${lang}`, label: `Solution / explanation (${lang})`, type: 'textarea', rows: 3 }} value={q.translations[lang]?.solution} onChange={(v) => setT('solution', v)} />
        <Field f={{ name: 'label_ids', label: 'Labels', type: 'multiselect', options: labels.map((l) => ({ value: l.id, label: l.name })) }} value={q.label_ids} onChange={set('label_ids')} />
        {errors.options && <div className="small" style={{ color: 'var(--red)' }}>{errors.options}</div>}
      </div>
    </Modal>
  );
}
