import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCoursesQuery, useCreateCourseMutation, COURSE_TYPES } from './api';
import { useCategoriesQuery, categoryOptions } from '../catalog/api';
import { Badge, Button, Can, Card, Modal, PageHead } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm, useFormErrors } from '../../components/Form';

export default function CoursesPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useCoursesQuery(f);
  const { data: cats = [] } = useCategoriesQuery();
  const [create, cs] = useCreateCourseMutation();
  const [open, setOpen] = useState(false);
  const [errors, run] = useFormErrors();
  const nav = useNavigate();

  return (
    <>
      <PageHead title="Courses" sub="Every course has content (folders, videos, tests). Price, validity and seats live on its batches.">
        <Can perm="courses.create"><Button variant="primary" onClick={() => setOpen(true)}>+ New course</Button></Can>
      </PageHead>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} filters={[
            { name: 'status', label: 'Status', options: ['draft', 'published', 'archived'].map((x) => ({ value: x, label: x })) },
            { name: 'category_id', label: 'Category', options: categoryOptions(cats) },
            { name: 'course_type', label: 'Type', options: COURSE_TYPES },
            { name: 'pricing', label: 'Price', options: [{ value: 'free', label: 'Free' }, { value: 'paid', label: 'Paid' }] },
          ]} />
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={(c) => nav(`/courses/${c.id}`)}
          columns={[
            { key: 'title', label: 'Course', render: (c) => <div><div className="b">{c.title}</div><div className="small muted">{c.category?.name || 'No category'} · {COURSE_TYPES.find((t) => t.value === c.course_type)?.label}</div></div> },
            { key: 'price_from', label: 'From', render: (c) => c.price_from || '—' },
            { key: 'batches_count', label: 'Batches' },
            { key: 'students_count', label: 'Students' },
            { key: 'staff', label: 'Teachers', render: (c) => (c.staff || []).map((s) => s.name.split(' ')[0]).join(', ') || '—' },
            { key: 'status', label: 'Status', render: (c) => <Badge>{c.status}</Badge> },
          ]} />
      </Card>
      {open && (
        <Modal title="New course" onClose={() => setOpen(false)}>
          <SchemaForm errors={errors} loading={cs.isLoading} submitText="Create course" onCancel={() => setOpen(false)}
            initial={{ course_type: 'live_recorded', language: 'Malayalam', price: 0 }}
            fields={[
              { name: 'title', label: 'Course name', required: true, cols: 2, placeholder: 'Kerala PSC LDC 2027 – Complete Course' },
              { name: 'exam_category_id', label: 'Category', type: 'select', options: categoryOptions(cats) },
              { name: 'course_type', label: 'Course type', type: 'select', required: true, options: COURSE_TYPES, hint: 'Sets the default feature switches' },
              { name: 'language', label: 'Language' },
              { name: 'price', label: 'Price (₹) for the default batch', type: 'number', min: 0, hint: '0 = free' },
              { name: 'mrp', label: 'MRP (₹, shown struck-through)', type: 'number', min: 0 },
              { name: 'short_description', label: 'Short description', type: 'textarea', cols: 2 },
            ]}
            onSubmit={async (v) => { const c = await run(create(v)); setOpen(false); nav(`/courses/${c.id}`); }} />
        </Modal>
      )}
    </>
  );
}
