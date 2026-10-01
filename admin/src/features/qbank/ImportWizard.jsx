import { useState } from 'react';
import { useUploadImportMutation, useConfirmImportMutation, useImportStatusQuery, LANGS } from './api';
import { download } from '../../app/api';
import { Badge, Button, Modal, Spinner } from '../../components/ui';
import { Field } from '../../components/Form';

/** 3 steps: upload → check preview → import (queued, polled). */
export default function ImportWizard({ onClose, folders, labels }) {
  const [step, setStep] = useState(1);
  const [opts, setOpts] = useState({ lang: 'en', default_marks: 1, default_negative: 0.33, label_ids: [], match_translations: false });
  const [file, setFile] = useState(null);
  const [imp, setImp] = useState(null);
  const [skipDup, setSkipDup] = useState(true);
  const [upload, u] = useUploadImportMutation();
  const [confirm, c] = useConfirmImportMutation();
  const { data: live } = useImportStatusQuery(imp?.id, { skip: step !== 3 || !imp, pollingInterval: 1500 });
  const status = live || imp;
  const set = (k) => (v) => setOpts((o) => ({ ...o, [k]: v }));

  return (
    <Modal wide title={`Import questions – step ${step} of 3`} onClose={onClose}
      footer={<>
        {step === 1 && <><Button onClick={() => download('admin/question-bank/imports/template', {}, 'padanam_questions_template.csv')}>⬇️ CSV template</Button>
          <Button variant="primary" disabled={!file} loading={u.isLoading} onClick={async () => { setImp(await upload({ ...opts, folder_id: opts.folder_id || undefined, file }).unwrap()); setStep(2); }}>Check file</Button></>}
        {step === 2 && <><Button onClick={() => setStep(1)}>Back</Button>
          <Button variant="primary" disabled={!imp?.ready && !(imp?.duplicates && !skipDup)} loading={c.isLoading} onClick={async () => { setImp(await confirm({ id: imp.id, skip_duplicates: skipDup }).unwrap()); setStep(3); }}>
            Import {skipDup ? imp?.ready : imp.ready + imp.duplicates} questions</Button></>}
        {step === 3 && <Button variant="primary" onClick={onClose}>Close</Button>}
      </>}>
      {step === 1 && (
        <div className="col">
          <div className="grid g2">
            <Field f={{ name: 'file', label: 'File (.csv or .docx)', type: 'file', accept: '.csv,.docx', required: true }} value={file} onChange={setFile} />
            <Field f={{ name: 'lang', label: 'Language of the file', type: 'select', required: true, options: LANGS }} value={opts.lang} onChange={set('lang')} />
            <Field f={{ name: 'folder_id', label: 'Put into folder', type: 'select', options: folders }} value={opts.folder_id} onChange={set('folder_id')} />
            <Field f={{ name: 'label_ids', label: 'Add labels', type: 'multiselect', options: labels.map((l) => ({ value: l.id, label: l.name })) }} value={opts.label_ids} onChange={set('label_ids')} />
            <Field f={{ name: 'default_marks', label: 'Marks (if not in the file)', type: 'number', step: '0.25' }} value={opts.default_marks} onChange={set('default_marks')} />
            <Field f={{ name: 'default_negative', label: 'Negative marks', type: 'number', step: '0.01' }} value={opts.default_negative} onChange={set('default_negative')} />
            <Field f={{ name: 'match_translations', label: 'This file adds a translation to existing questions (uses the “ref” column = question id)', type: 'toggle', cols: 2 }} value={opts.match_translations} onChange={set('match_translations')} />
          </div>
          <div className="card small" style={{ background: 'var(--bg)' }}>
            <b>Word format</b> – type questions like this (pictures are kept):
            <pre style={{ margin: '6px 0 0', whiteSpace: 'pre-wrap' }}>{`1. Who founded the SNDP Yogam?
ML: SNDP യോഗം സ്ഥാപിച്ചത് ആര്?
A) Sree Narayana Guru || ശ്രീനാരായണഗുരു
B) Ayyankali
C) Chattampi Swamikal
D) Mannathu Padmanabhan
Answer: A
Solution: Founded in 1903.
Subject: GK | Topic: Renaissance | Difficulty: easy | Labels: LDC, PYQ`}</pre>
          </div>
        </div>
      )}
      {step === 2 && imp && (
        <div className="col">
          <div className="row wrap">
            <Badge tone="green">{imp.ready} ready</Badge><Badge tone="orange">{imp.duplicates} already in the bank</Badge><Badge tone="red">{imp.failed} with problems</Badge><span className="muted small">of {imp.total}</span>
          </div>
          {imp.duplicates > 0 && <label className="row small"><input type="checkbox" checked={skipDup} onChange={(e) => setSkipDup(e.target.checked)} /> Skip questions that are already in the bank</label>}
          <div className="tablewrap"><table className="t">
            <thead><tr><th>Line</th><th>Question</th><th>Options</th><th>Answer</th><th>Status</th></tr></thead>
            <tbody>{(imp.preview || []).map((r) => (
              <tr key={r.line}>
                <td>{r.line}</td>
                <td><div>{r.has_image && '🖼️ '}{r.question}</div><div className="small muted">{r.languages.join(', ')} {r.subject && `· ${r.subject}`} {r.labels?.length > 0 && `· ${r.labels.join(', ')}`}</div></td>
                <td className="small">{r.options.map((o, i) => <div key={i}>{String.fromCharCode(65 + i)}) {o}</div>)}</td>
                <td className="b">{r.numeric ?? r.answers.join(', ')}</td>
                <td>{r.status === 'error' ? <span className="small" style={{ color: 'var(--red)' }}>{r.errors.join('; ')}</span> : r.status === 'duplicate' ? <Badge tone="orange">duplicate of #{r.duplicate_of}</Badge> : <Badge tone="green">ready</Badge>}</td>
              </tr>
            ))}</tbody>
          </table></div>
          {imp.total > 50 && <p className="small muted">Showing the first 50 of {imp.total}.</p>}
        </div>
      )}
      {step === 3 && status && (
        <div className="empty">
          {status.status !== 'done' && status.status !== 'failed' ? <><Spinner /><div>Importing… {status.imported || 0} done</div></> : status.status === 'done' ? (
            <>
              <div className="e">✅</div><div className="b">{status.imported} questions imported</div>
              {status.failed > 0 && <Button onClick={() => download(`admin/question-bank/imports/${status.id}/errors`)}>⬇️ Download {status.failed} problem rows</Button>}
            </>
          ) : <><div className="e">⚠️</div><div className="b">Import failed. Please check the file and try again.</div></>}
        </div>
      )}
    </Modal>
  );
}
