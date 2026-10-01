import { useSaveContentMutation, CONTENT_TYPES } from '../api';
import { useTestsQuery } from '../../tests/api';
import { useArticlesQuery } from '../../articles/api';
import { useLiveClassesQuery } from '../../live/api';
import { Modal } from '../../../components/ui';
import { SchemaForm, useFormErrors } from '../../../components/Form';

/** One form for every content type; fields change with the type. */
export default function ContentForm({ course, initial, folders, batchOpts, onClose }) {
  const [save, s] = useSaveContentMutation();
  const [errors, run] = useFormErrors();
  const { data: tests } = useTestsQuery({ course_id: course.id, per_page: 100 });
  const { data: articles } = useArticlesQuery({ per_page: 100 });
  const { data: live } = useLiveClassesQuery({ course_id: course.id, when: 'upcoming' });
  const editing = !!initial.id;
  const start = {
    ...initial,
    url: initial.meta?.url || initial.meta?.youtube_id || '',
    source: initial.meta?.source || 'youtube',
    downloadable: initial.meta?.downloadable ?? true,
    ref_id: initial.meta?.test_id || initial.meta?.article_id || initial.meta?.live_class_id,
    body_en: '', body_ml: '',
  };

  return (
    <Modal wide title={editing ? `Edit – ${initial.title}` : 'Add content'} onClose={onClose}>
      <SchemaForm errors={errors} loading={s.isLoading} initial={start} onCancel={onClose}
        fields={[
          { name: 'type', label: 'Type', type: 'select', required: true, options: Object.entries(CONTENT_TYPES).map(([value, label]) => ({ value, label })), show: () => !editing },
          { name: 'title', label: 'Title', required: true },
          // video
          { name: 'source', label: 'Video source', type: 'select', options: [{ value: 'youtube', label: 'YouTube (unlisted)' }, { value: 'aws', label: 'AWS (HLS)' }], show: (v) => v.type === 'video' },
          { name: 'url', label: 'YouTube link', show: (v) => v.type === 'video' && v.source !== 'aws', placeholder: 'https://youtu.be/…', hint: 'Set the video to Unlisted on YouTube' },
          { name: 'hls_path', label: 'AWS HLS path / S3 key', show: (v) => v.type === 'video' && v.source === 'aws', placeholder: 'videos/ldc/class1/index.m3u8' },
          { name: 'duration_sec', label: 'Duration (seconds)', type: 'number', show: (v) => v.type === 'video' },
          // pdf
          { name: 'file', label: editing ? 'Replace PDF' : 'PDF file', type: 'file', accept: 'application/pdf', show: (v) => v.type === 'pdf' },
          { name: 'downloadable', label: 'Students can download', type: 'toggle', show: (v) => v.type === 'pdf' },
          // note
          { name: 'body_en', label: 'Note – English (HTML allowed)', type: 'textarea', rows: 6, cols: 2, show: (v) => v.type === 'note', hint: editing ? 'Leave empty to keep the current text' : '' },
          { name: 'body_ml', label: 'Note – മലയാളം', type: 'textarea', rows: 6, cols: 2, show: (v) => v.type === 'note' },
          // link
          { name: 'url', label: 'Link', show: (v) => v.type === 'link' },
          // references
          { name: 'ref_id', label: 'Test', type: 'select', required: true, show: (v) => ['test', 'quiz'].includes(v.type) && !editing, options: (tests?.items || []).map((t) => ({ value: t.id, label: `${t.title} (${t.total_questions} Qs, ${t.status})` })) },
          { name: 'ref_id', label: 'Article', type: 'select', required: true, show: (v) => v.type === 'article' && !editing, options: (articles?.items || []).map((a) => ({ value: a.id, label: a.title })) },
          { name: 'ref_id', label: 'Live class', type: 'select', required: true, show: (v) => v.type === 'live' && !editing, options: (live?.items || []).map((l) => ({ value: l.id, label: `${l.title} · ${new Date(l.starts_at).toLocaleString('en-IN')}` })), hint: 'Schedule it first in Live classes' },
          // common
          { name: 'access', label: 'Access', type: 'select', required: true, options: [{ value: 'premium', label: '🔒 Premium (enrolled students)' }, { value: 'free', label: '🆓 Free for everyone' }, { value: 'demo', label: '👀 Demo (shown on the course page)' }] },
          { name: 'folder_id', label: 'Folder', type: 'select', empty: 'Outside folders', options: folders.map((f) => ({ value: f.id, label: f.title })) },
          { name: 'publish_at', label: 'Publish at (optional)', type: 'datetime', hint: 'Students get a notification when it opens' },
          { name: 'batch_ids', label: 'Only for batches (empty = all)', type: 'multiselect', options: batchOpts },
          { name: 'description', label: 'Description', type: 'textarea', cols: 2 },
        ]}
        onSubmit={async (v) => {
          const body = {
            id: initial.id, course_id: course.id, title: v.title, description: v.description, access: v.access,
            folder_id: v.folder_id || null, publish_at: v.publish_at || null, batch_ids: v.batch_ids?.length ? v.batch_ids : null,
          };
          if (!editing) body.type = v.type;
          const type = editing ? initial.type : v.type;
          if (type === 'video') Object.assign(body, { source: v.source, ...(v.source === 'aws' ? { hls_path: v.hls_path } : { url: v.url }), duration_sec: v.duration_sec || undefined });
          if (type === 'pdf') Object.assign(body, { file: v.file, downloadable: !!v.downloadable });
          if (type === 'note' && (v.body_en || v.body_ml)) body.body = Object.fromEntries(Object.entries({ en: v.body_en, ml: v.body_ml }).filter(([, x]) => x));
          if (type === 'link') body.url = v.url;
          if (['test', 'quiz', 'article', 'live'].includes(type) && !editing) body.ref_id = v.ref_id;
          await run(save(body));
          onClose();
        }} />
    </Modal>
  );
}
