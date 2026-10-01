import { useState } from 'react';
import { useCategoriesQuery, useSaveCategoryMutation, useDeleteCategoryMutation, useSaveExamMutation, useDeleteExamMutation } from './api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead, Spinner, Empty } from '../../components/ui';
import { SchemaForm, useFormErrors } from '../../components/Form';

export default function CategoriesPage() {
  const { data = [], isLoading } = useCategoriesQuery();
  const [save, s1] = useSaveCategoryMutation();
  const [del] = useDeleteCategoryMutation();
  const [saveExam, s2] = useSaveExamMutation();
  const [delExam] = useDeleteExamMutation();
  const [edit, setEdit] = useState(null);      // category
  const [exam, setExam] = useState(null);      // exam
  const [confirm, setConfirm] = useState(null);
  const [errors, run] = useFormErrors();

  if (isLoading) return <Spinner />;
  const parents = data.map((c) => ({ value: c.id, label: c.name }));

  return (
    <>
      <PageHead title="Exam categories" sub="Categories and sub-categories group courses in the app (Kerala PSC → LDC, LGS…).">
        <Can perm="categories.manage"><Button variant="primary" onClick={() => setEdit({})}>+ Category</Button></Can>
      </PageHead>
      {!data.length && <Card><Empty icon="🗂️" title="No categories yet" /></Card>}
      <div className="grid g2">
        {data.map((c) => (
          <Card key={c.id} title={<span>{c.icon} {c.name} {!c.is_active && <Badge tone="grey">hidden</Badge>}</span>}
            actions={<Can perm="categories.manage">
              <Button size="sm" onClick={() => setEdit({ parent_id: c.id })}>+ Sub</Button>
              <Button size="sm" onClick={() => setExam({ exam_category_id: c.id })}>+ Exam</Button>
              <Button size="sm" onClick={() => setEdit(c)}>Edit</Button>
              <Button size="sm" variant="danger" onClick={() => setConfirm(() => () => del(c.id))}>Delete</Button>
            </Can>}>
            <div className="small muted" style={{ marginBottom: 8 }}>{c.courses_count ?? 0} courses</div>
            {(c.children || []).length > 0 && <div className="chips" style={{ marginBottom: 10 }}>
              {c.children.map((s) => <span key={s.id} className="chip" onClick={() => setEdit(s)}>{s.name} · {s.courses_count ?? 0}</span>)}
            </div>}
            {(c.exams || []).map((e) => (
              <div key={e.id} className="row" style={{ padding: '6px 0', borderTop: '1px solid var(--line)' }}>
                <span className="grow"><b>{e.name}</b> <span className="small muted">{e.next_exam_date ? `· exam in ${e.days_left} days` : ''}</span></span>
                <Can perm="categories.manage"><Button size="sm" variant="ghost" onClick={() => setExam(e)}>Edit</Button>
                  <Button size="sm" variant="ghost" onClick={() => setConfirm(() => () => delExam(e.id))}>✕</Button></Can>
              </div>
            ))}
          </Card>
        ))}
      </div>

      {edit && (
        <Modal title={edit.id ? 'Edit category' : 'New category'} onClose={() => setEdit(null)}>
          <SchemaForm initial={edit} errors={errors} loading={s1.isLoading} onCancel={() => setEdit(null)}
            fields={[
              { name: 'name', label: 'Name', required: true },
              { name: 'icon', label: 'Icon (emoji)', placeholder: '🏛️' },
              { name: 'parent_id', label: 'Parent', type: 'select', options: parents.filter((p) => p.value !== edit.id), empty: 'None (top level)' },
              { name: 'color', label: 'Colour', type: 'color' },
              { name: 'sort', label: 'Order', type: 'number' },
              { name: 'is_active', label: 'Show in app', type: 'toggle' },
            ]}
            onSubmit={async (v) => { await run(save({ id: edit.id, ...v, parent_id: v.parent_id || null })); setEdit(null); }} />
        </Modal>
      )}
      {exam && (
        <Modal title={exam.id ? 'Edit exam' : 'New exam'} onClose={() => setExam(null)}>
          <SchemaForm initial={exam} errors={errors} loading={s2.isLoading} onCancel={() => setExam(null)}
            fields={[
              { name: 'name', label: 'Short name', required: true, placeholder: 'LDC' },
              { name: 'full_name', label: 'Full name', placeholder: 'Lower Division Clerk' },
              { name: 'next_exam_date', label: 'Next exam date', type: 'date', hint: 'Shows a countdown in the app' },
              { name: 'is_active', label: 'Active', type: 'toggle' },
            ]}
            onSubmit={async (v) => { await run(saveExam({ id: exam.id, exam_category_id: exam.exam_category_id, ...v })); setExam(null); }} />
        </Modal>
      )}
      {confirm && <Confirm text="This cannot be undone." onYes={confirm} onClose={() => setConfirm(null)} />}
    </>
  );
}
