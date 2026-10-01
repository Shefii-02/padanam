import { useState } from 'react';
import {
  useQuestionsQuery, useLazyQuestionQuery, useFacetsQuery, useDeleteQuestionMutation, useBulkQuestionsMutation,
  useQFoldersQuery, useSaveQFolderMutation, useDeleteQFolderMutation, useLabelsQuery, useSaveLabelMutation, useMergeLabelMutation, flatFolders,
} from './api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm, Field } from '../../components/Form';
import QuestionEditor from './QuestionEditor';
import ImportWizard from './ImportWizard';

function FolderTree({ nodes, sel, onSel }) {
  return nodes.map((n) => (
    <div key={n.id}>
      <div className={`node ${sel === n.id ? 'on' : ''}`} onClick={() => onSel(n.id)} role="button" tabIndex={0}>📁 <span className="grow">{n.name}</span><span className="small muted">{n.count}</span></div>
      {n.children?.length > 0 && <div className="kids"><FolderTree nodes={n.children} sel={sel} onSel={onSel} /></div>}
    </div>
  ));
}

const strip = (h = '') => h.replace(/<img[^>]*>/g, '🖼️').replace(/<[^>]+>/g, '');

export default function QuestionBankPage() {
  const [f, setF] = useState({ page: 1, include_sub: 1 });
  const { data, isFetching } = useQuestionsQuery(f);
  const { data: tree } = useQFoldersQuery();
  const { data: labels = [] } = useLabelsQuery();
  const { data: facets } = useFacetsQuery();
  const [loadQ] = useLazyQuestionQuery();
  const [del] = useDeleteQuestionMutation();
  const [bulk] = useBulkQuestionsMutation();
  const [saveFolder] = useSaveQFolderMutation();
  const [delFolder] = useDeleteQFolderMutation();
  const [saveLabel] = useSaveLabelMutation();
  const [merge] = useMergeLabelMutation();
  const [editor, setEditor] = useState(null);
  const [importing, setImporting] = useState(false);
  const [sel, setSel] = useState([]);
  const [modal, setModal] = useState(null);
  const [confirm, setConfirm] = useState(null);

  const toggle = (id) => setSel((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
  const rows = data?.items || [];

  return (
    <>
      <PageHead title="Question bank" sub={`${tree?.total ?? 0} questions · folders, labels, many languages`}>
        <Can perm="question_bank.import"><Button onClick={() => setImporting(true)}>⬆️ Import CSV / Word</Button></Can>
        <Can perm="question_bank.create"><Button variant="primary" onClick={() => setEditor({ folder: f.folder_id })}>+ Question</Button></Can>
      </PageHead>
      <div className="grid" style={{ gridTemplateColumns: 'minmax(210px,270px) 1fr', alignItems: 'start' }}>
        <div className="col">
          <Card title="Folders" actions={<Can perm="question_bank.edit"><Button size="sm" onClick={() => setModal({ kind: 'folder', v: { parent_id: f.folder_id } })}>+</Button></Can>}>
            <div className="tree">
              <div className={`node ${!f.folder_id ? 'on' : ''}`} onClick={() => setF({ ...f, folder_id: '', page: 1 })} role="button" tabIndex={0}>🗃️ <span className="grow">All questions</span></div>
              <FolderTree nodes={tree?.folders || []} sel={f.folder_id} onSel={(id) => setF({ ...f, folder_id: id, page: 1 })} />
            </div>
            {f.folder_id && <Can perm="question_bank.edit"><div className="row" style={{ marginTop: 8 }}>
              <Button size="sm" onClick={() => setModal({ kind: 'folder', v: flatFolders(tree.folders).find((x) => x.value === f.folder_id) && { id: f.folder_id, name: flatFolders(tree.folders).find((x) => x.value === f.folder_id).label.replace(/^(— )+/, '') } })}>Rename</Button>
              <Button size="sm" variant="danger" onClick={() => setConfirm(() => () => { delFolder(f.folder_id); setF({ ...f, folder_id: '' }); })}>Delete</Button>
            </div></Can>}
          </Card>
          <Card title="Labels" actions={<Can perm="question_bank.edit"><Button size="sm" onClick={() => setModal({ kind: 'label', v: { color: '#3B4FD8' } })}>+</Button></Can>}>
            <div className="chips">
              {labels.map((l) => (
                <button key={l.id} className={`chip ${(f.label_ids || []).includes(l.id) ? 'on' : ''}`} style={{ borderColor: l.color }}
                  onClick={() => setF({ ...f, page: 1, label_ids: (f.label_ids || []).includes(l.id) ? f.label_ids.filter((x) => x !== l.id) : [...(f.label_ids || []), l.id] })}
                  onDoubleClick={() => setModal({ kind: 'label', v: l })} title="Double-click to edit">
                  {l.name} <span className="muted">{l.questions_count}</span>
                </button>
              ))}
            </div>
          </Card>
        </div>
        <Card flush>
          <div style={{ padding: '14px 16px 0' }}>
            <Filters value={f} onChange={setF} placeholder="Search text or #id" filters={[
              { name: 'difficulty', label: 'Difficulty', options: ['easy', 'moderate', 'hard'].map((x) => ({ value: x, label: x })) },
              { name: 'type', label: 'Type', options: [['mcq_single', 'Single'], ['mcq_multi', 'Multi'], ['true_false', 'True/False'], ['numeric', 'Numeric']].map(([value, label]) => ({ value, label })) },
              { name: 'subject', label: 'Subject', options: (facets?.subjects || []).map((x) => ({ value: x, label: x })) },
              { name: 'missing_lang', label: 'Missing language', options: [{ value: 'ml', label: 'No Malayalam' }, { value: 'en', label: 'No English' }] },
              { name: 'used', label: 'Used in tests', options: [{ value: '1', label: 'Used' }, { value: '0', label: 'Not used' }] },
            ]} />
            {sel.length > 0 && (
              <div className="row wrap card" style={{ marginBottom: 12, background: 'var(--lav)', border: 0 }}>
                <b>{sel.length} selected</b>
                <Button size="sm" onClick={() => setModal({ kind: 'bulk', action: 'move' })}>Move to folder</Button>
                <Button size="sm" onClick={() => setModal({ kind: 'bulk', action: 'label' })}>Add labels</Button>
                <Button size="sm" onClick={() => setModal({ kind: 'bulk', action: 'difficulty' })}>Set difficulty</Button>
                <Can perm="question_bank.delete"><Button size="sm" variant="danger" onClick={() => setConfirm(() => async () => { await bulk({ ids: sel, action: 'delete' }).unwrap(); setSel([]); })}>Delete</Button></Can>
                <Button size="sm" variant="ghost" onClick={() => setSel([])}>Clear</Button>
              </div>
            )}
          </div>
          <DataTable loading={isFetching} rows={rows} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
            columns={[
              { key: 'sel', label: <input type="checkbox" aria-label="Select all" checked={rows.length > 0 && rows.every((r) => sel.includes(r.id))} onChange={(e) => setSel(e.target.checked ? rows.map((r) => r.id) : [])} />, render: (q) => <input type="checkbox" aria-label={`Select ${q.id}`} checked={sel.includes(q.id)} onChange={() => toggle(q.id)} /> },
              { key: 'q', label: 'Question', render: (q) => (
                <div style={{ cursor: 'pointer' }} onClick={async () => setEditor({ q: await loadQ(q.id).unwrap() })}>
                  <div className="b">#{q.id} · {strip(q.translations?.en?.text || Object.values(q.translations || {})[0]?.text).slice(0, 140)}</div>
                  <div className="small muted">{q.subject || '—'}{q.topic && ` › ${q.topic}`} · {(q.languages || []).join(', ')} · {q.folder?.name || 'No folder'}</div>
                  <div className="chips" style={{ marginTop: 4 }}>{(q.labels || []).map((l) => <span key={l.id} className="badge" style={{ background: 'transparent', border: `1px solid ${l.color}`, color: l.color }}>{l.name}</span>)}</div>
                </div>
              ) },
              { key: 'difficulty', label: 'Level', render: (q) => <Badge tone={{ easy: 'green', moderate: 'orange', hard: 'red' }[q.difficulty]}>{q.difficulty}</Badge> },
              { key: 'marks', label: 'Marks', render: (q) => `+${q.default_marks} / −${q.default_negative}` },
              { key: 'used_in_tests', label: 'In tests' },
              { key: 'x', label: '', render: (q) => <Can perm="question_bank.delete"><Button size="sm" variant="ghost" onClick={() => setConfirm(() => () => del(q.id))} aria-label="Delete">🗑️</Button></Can> },
            ]} />
        </Card>
      </div>

      {editor && <QuestionEditor question={editor.q} defaultFolder={editor.folder} onClose={() => setEditor(null)} />}
      {importing && <ImportWizard onClose={() => setImporting(false)} folders={flatFolders(tree?.folders)} labels={labels} />}
      {modal?.kind === 'folder' && (
        <Modal title={modal.v?.id ? 'Rename folder' : 'New folder'} onClose={() => setModal(null)}>
          <SchemaForm initial={modal.v || {}} onCancel={() => setModal(null)} fields={[
            { name: 'name', label: 'Name', required: true },
            { name: 'parent_id', label: 'Inside', type: 'select', empty: 'Top level', options: flatFolders(tree?.folders).filter((x) => x.value !== modal.v?.id), show: () => !modal.v?.id },
          ]} onSubmit={async (v) => { await saveFolder({ id: modal.v?.id, name: v.name, ...(modal.v?.id ? {} : { parent_id: v.parent_id || null }) }).unwrap(); setModal(null); }} />
        </Modal>
      )}
      {modal?.kind === 'label' && (
        <Modal title={modal.v.id ? 'Edit label' : 'New label'} onClose={() => setModal(null)}>
          <SchemaForm initial={modal.v} onCancel={() => setModal(null)} fields={[
            { name: 'name', label: 'Name', required: true }, { name: 'color', label: 'Colour', type: 'color' },
            { name: 'into_id', label: 'Merge into another label', type: 'select', options: labels.filter((l) => l.id !== modal.v.id).map((l) => ({ value: l.id, label: l.name })), show: () => !!modal.v.id, hint: 'All its questions move to the chosen label' },
          ]} onSubmit={async (v) => {
            if (v.into_id) await merge({ id: modal.v.id, into_id: v.into_id }).unwrap();
            else await saveLabel({ id: modal.v.id, name: v.name, color: v.color }).unwrap();
            setModal(null);
          }} />
        </Modal>
      )}
      {modal?.kind === 'bulk' && (
        <Modal title={`${sel.length} questions`} onClose={() => setModal(null)}>
          <SchemaForm onCancel={() => setModal(null)} submitText="Apply" fields={[
            modal.action === 'move' && { name: 'folder_id', label: 'Folder', type: 'select', required: true, options: flatFolders(tree?.folders) },
            modal.action === 'label' && { name: 'label_ids', label: 'Labels', type: 'multiselect', options: labels.map((l) => ({ value: l.id, label: l.name })) },
            modal.action === 'difficulty' && { name: 'difficulty', label: 'Difficulty', type: 'select', required: true, options: ['easy', 'moderate', 'hard'].map((x) => ({ value: x, label: x })) },
          ].filter(Boolean)} onSubmit={async (v) => { await bulk({ ids: sel, action: modal.action, ...v }).unwrap(); setModal(null); setSel([]); }} />
        </Modal>
      )}
      {confirm && <Confirm text="Delete? Questions used in published tests are kept." onYes={confirm} onClose={() => setConfirm(null)} />}
    </>
  );
}

export { Field };
