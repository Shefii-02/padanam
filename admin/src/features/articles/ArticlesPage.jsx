import { useState } from 'react';
import { useArticlesQuery, useLazyArticleQuery, useSaveArticleMutation, useDeleteArticleMutation } from './api';
import { useCategoriesQuery, categoryOptions } from '../catalog/api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead, fmtDate } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm, useFormErrors } from '../../components/Form';

export default function ArticlesPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useArticlesQuery(f);
  const { data: cats = [] } = useCategoriesQuery();
  const [load] = useLazyArticleQuery();
  const [save, s] = useSaveArticleMutation();
  const [del] = useDeleteArticleMutation();
  const [edit, setEdit] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [errors, run] = useFormErrors();

  const open = async (a) => setEdit(a.id ? await load(a.id).unwrap() : a);

  return (
    <>
      <PageHead title="Articles & current affairs" sub="Free articles bring new students; premium ones are for enrolled students.">
        <Can perm="articles.manage"><Button variant="primary" onClick={() => open({ status: 'draft', access: 'free' })}>+ Article</Button></Can>
      </PageHead>
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}><Filters value={f} onChange={setF} filters={[{ name: 'status', label: 'Status', options: ['draft', 'scheduled', 'published'].map((x) => ({ value: x, label: x })) }]} /></div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={open}
          columns={[
            { key: 'title', label: 'Title', render: (a) => <div><div className="b">{a.title}</div><div className="small muted">{a.category || '—'} · {a.reading_min} min read</div></div> },
            { key: 'access', label: 'Access', render: (a) => <Badge tone={a.access === 'free' ? 'green' : 'purple'}>{a.access}</Badge> },
            { key: 'views', label: 'Views' },
            { key: 'published_at', label: 'Published', render: (a) => fmtDate(a.published_at) },
            { key: 'status', label: 'Status', render: (a) => <Badge>{a.status}</Badge> },
            { key: 'x', label: '', className: 'right', render: (a) => <Can perm="articles.manage"><Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); setConfirm(a.id); }}>🗑️</Button></Can> },
          ]} />
      </Card>
      {edit && (
        <Modal wide title={edit.id ? 'Edit article' : 'New article'} onClose={() => setEdit(null)}>
          <SchemaForm errors={errors} loading={s.isLoading} initial={{ ...edit, cover: undefined }} onCancel={() => setEdit(null)}
            fields={[
              { name: 'title', label: 'Title', required: true, cols: 2 },
              { name: 'exam_category_id', label: 'Category', type: 'select', options: categoryOptions(cats) },
              { name: 'access', label: 'Access', type: 'select', required: true, options: [{ value: 'free', label: 'Free' }, { value: 'premium', label: 'Premium' }] },
              { name: 'status', label: 'Status', type: 'select', required: true, options: ['draft', 'scheduled', 'published'].map((x) => ({ value: x, label: x })) },
              { name: 'published_at', label: 'Publish at', type: 'datetime', show: (v) => v.status === 'scheduled' },
              { name: 'cover', label: 'Cover image', type: 'file', accept: 'image/*' },
              { name: 'tags', label: 'Tags', type: 'chips' },
              { name: 'body', label: 'Article (HTML allowed)', type: 'textarea', rows: 14, required: true, cols: 2 },
            ]}
            onSubmit={async (v) => {
              const keys = ['title', 'exam_category_id', 'access', 'status', 'published_at', 'cover', 'tags', 'body'];
              await run(save({ id: edit.id, ...Object.fromEntries(keys.filter((k) => v[k] !== undefined && v[k] !== '').map((k) => [k, v[k]])) }));
              setEdit(null);
            }} />
        </Modal>
      )}
      {confirm && <Confirm text="Delete this article?" onYes={() => del(confirm)} onClose={() => setConfirm(null)} />}
    </>
  );
}
