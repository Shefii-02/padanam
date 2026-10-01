import { useState } from 'react';
import { useFoldersQuery, useSaveFolderMutation, useDeleteFolderMutation, useContentsQuery, useSaveContentMutation, useDeleteContentMutation, useReorderContentsMutation, CONTENT_TYPES } from '../api';
import { Badge, Button, Can, Card, Confirm, Empty, Modal, Spinner, fmtDate } from '../../../components/ui';
import { SchemaForm, useFormErrors } from '../../../components/Form';
import ContentForm from './ContentForm';

function Tree({ nodes, selected, onSelect, depth = 0 }) {
  return nodes.map((n) => (
    <div key={n.id}>
      <div className={`node ${selected === n.id ? 'on' : ''}`} onClick={() => onSelect(n.id)} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && onSelect(n.id)}>
        <span>📁</span><span className="grow">{n.title}</span>
        {n.unlock_at && <span title={`Opens ${fmtDate(n.unlock_at)}`}>🔒</span>}
        {n.batch_ids?.length > 0 && <span title="Only some batches">👥</span>}
        <span className="small muted">{n.contents_count}</span>
      </div>
      {n.children?.length > 0 && <div className="kids"><Tree nodes={n.children} selected={selected} onSelect={onSelect} depth={depth + 1} /></div>}
    </div>
  ));
}

const flatten = (nodes, out = []) => { nodes.forEach((n) => { out.push(n); flatten(n.children || [], out); }); return out; };

export default function ContentTab({ course }) {
  const { data: folders = [], isLoading } = useFoldersQuery(course.id);
  const [folderId, setFolderId] = useState(null);   // null = outside folders
  const { data: items = [], isFetching } = useContentsQuery({ courseId: course.id, folderId });
  const [saveFolder, sf] = useSaveFolderMutation();
  const [delFolder] = useDeleteFolderMutation();
  const [delContent] = useDeleteContentMutation();
  const [reorder] = useReorderContentsMutation();
  const [folderEdit, setFolderEdit] = useState(null);
  const [contentEdit, setContentEdit] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [errors, run] = useFormErrors();

  if (isLoading) return <Spinner />;
  const all = flatten(folders);
  const current = all.find((f) => f.id === folderId);
  const batchOpts = (course.batches || []).map((b) => ({ value: b.id, label: b.name }));

  const move = (idx, dir) => {
    const list = [...items];
    const j = idx + dir;
    if (j < 0 || j >= list.length) return;
    [list[idx], list[j]] = [list[j], list[idx]];
    reorder({ courseId: course.id, silent: true, items: list.map((c, i) => ({ id: c.id, sort: i + 1, folder_id: c.folder_id })) });
  };

  return (
    <div className="grid" style={{ gridTemplateColumns: 'minmax(220px,300px) 1fr', alignItems: 'start' }}>
      <Card title="Folders" actions={<Can perm="content.create"><Button size="sm" onClick={() => setFolderEdit({ parent_id: folderId })}>+ Folder</Button></Can>}>
        <div className="tree">
          <div className={`node ${folderId === null ? 'on' : ''}`} onClick={() => setFolderId(null)} role="button" tabIndex={0}><span>🗃️</span><span className="grow">Outside folders</span></div>
          <Tree nodes={folders} selected={folderId} onSelect={setFolderId} />
        </div>
        {!folders.length && <p className="small muted">Create folders like “General Knowledge → Kerala Renaissance”. Students see them in the same order.</p>}
      </Card>
      <Card title={current ? `📁 ${current.title}` : '🗃️ Outside folders'}
        actions={<>
          {current && <Can perm="content.edit"><Button size="sm" onClick={() => setFolderEdit(current)}>Folder settings</Button>
            <Button size="sm" variant="danger" onClick={() => setConfirm(() => async () => { await delFolder(current.id).unwrap(); setFolderId(current.parent_id ?? null); })}>Delete folder</Button></Can>}
          <Can perm="content.create"><Button size="sm" variant="primary" onClick={() => setContentEdit({ type: 'video', access: 'premium', folder_id: folderId })}>+ Add content</Button></Can>
        </>}>
        {isFetching ? <Spinner /> : !items.length ? <Empty icon="📭" title="No items here">Add videos, PDFs, notes, tests or live classes.</Empty> : (
          <table className="t">
            <thead><tr><th>#</th><th>Item</th><th>Access</th><th>Visible to</th><th /></tr></thead>
            <tbody>
              {items.map((c, i) => (
                <tr key={c.id}>
                  <td className="nowrap">
                    <button className="btn ghost sm" aria-label="Move up" onClick={() => move(i, -1)} disabled={i === 0}>↑</button>
                    <button className="btn ghost sm" aria-label="Move down" onClick={() => move(i, 1)} disabled={i === items.length - 1}>↓</button>
                  </td>
                  <td>
                    <div className="b">{CONTENT_TYPES[c.type]?.split(' ')[0]} {c.title}</div>
                    <div className="small muted">
                      {c.type === 'video' && (c.meta?.source === 'aws' ? 'AWS' : `YouTube ${c.meta?.youtube_id || ''}`)}
                      {c.type === 'live' && `${fmtDate(c.meta?.starts_at, true)} · ${c.meta?.status}`}
                      {['test', 'quiz'].includes(c.type) && `${c.meta?.questions} Qs · ${c.meta?.duration_min} min · ${c.meta?.status}`}
                      {c.publish_at && new Date(c.publish_at) > new Date() && ` · opens ${fmtDate(c.publish_at, true)}`}
                    </div>
                  </td>
                  <td><Badge tone={c.access === 'premium' ? 'purple' : 'green'}>{c.access}</Badge></td>
                  <td className="small">{c.batch_ids?.length ? c.batch_ids.map((id) => batchOpts.find((b) => b.value === id)?.label).join(', ') : 'All batches'}</td>
                  <td className="right nowrap">
                    <Can perm="content.edit"><Button size="sm" onClick={() => setContentEdit(c)}>Edit</Button></Can>
                    <Can perm="content.delete"><Button size="sm" variant="ghost" onClick={() => setConfirm(() => () => delContent(c.id))}>🗑️</Button></Can>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {folderEdit && (
        <Modal title={folderEdit.id ? 'Folder settings' : 'New folder'} onClose={() => setFolderEdit(null)}>
          <SchemaForm errors={errors} loading={sf.isLoading} initial={folderEdit} onCancel={() => setFolderEdit(null)}
            fields={[
              { name: 'title', label: 'Folder name', required: true, cols: 2 },
              { name: 'parent_id', label: 'Inside', type: 'select', empty: 'Top level', options: all.filter((f) => f.id !== folderEdit.id).map((f) => ({ value: f.id, label: f.title })) },
              { name: 'unlock_at', label: 'Unlock on (optional)', type: 'datetime' },
              { name: 'batch_ids', label: 'Only for these batches (empty = all)', type: 'multiselect', options: batchOpts, cols: 2 },
            ]}
            onSubmit={async (v) => { await run(saveFolder({ id: folderEdit.id, course_id: course.id, title: v.title, parent_id: v.parent_id || null, unlock_at: v.unlock_at || null, batch_ids: v.batch_ids?.length ? v.batch_ids : null })); setFolderEdit(null); }} />
        </Modal>
      )}
      {contentEdit && <ContentForm course={course} initial={contentEdit} folders={all} batchOpts={batchOpts} onClose={() => setContentEdit(null)} />}
      {confirm && <Confirm text="Delete this? Items inside a deleted folder move up one level." onYes={confirm} onClose={() => setConfirm(null)} />}
    </div>
  );
}
