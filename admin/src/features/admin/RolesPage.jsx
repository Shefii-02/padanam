import { useState } from 'react';
import { useRolesQuery, usePermissionCatalogQuery, useSaveRoleMutation, useTogglePermissionMutation, useDeleteRoleMutation, useStaffQuery, useSaveStaffMutation, useBlockStaffMutation } from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead, Spinner, Tabs, Toggle, ago, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { SchemaForm, useFormErrors } from '../../components/Form';

function Matrix() {
  const { data: roles = [], isLoading } = useRolesQuery();
  const { data: catalog = [] } = usePermissionCatalogQuery();
  const [toggle] = useTogglePermissionMutation();
  const [saveRole] = useSaveRoleMutation();
  const [del] = useDeleteRoleMutation();
  const [adding, setAdding] = useState(false);
  const [confirm, setConfirm] = useState(null);
  const can = useCan()('roles.manage');
  if (isLoading) return <Spinner />;
  const cols = roles.filter((r) => r.name !== 'student');

  return (
    <Card flush title="Permission matrix" actions={can && <Button size="sm" variant="primary" onClick={() => setAdding(true)}>+ Custom role</Button>}>
      <div className="tablewrap" style={{ marginTop: 10 }}>
        <table className="t matrix">
          <thead><tr><th>Permission</th>{cols.map((r) => (
            <th key={r.id}>{r.label}<div className="small muted">{r.users_count} people</div>{!r.is_system && can && <button className="btn ghost sm" onClick={() => setConfirm(r.id)} aria-label={`Delete ${r.label}`}>🗑️</button>}</th>
          ))}</tr></thead>
          <tbody>
            {catalog.map((m) => [
              <tr key={m.module}><td colSpan={cols.length + 1} className="b" style={{ background: 'var(--bg)' }}>{m.label}</td></tr>,
              ...m.actions.map((a) => (
                <tr key={a.name}>
                  <td>{a.label} <span className="small muted kbd">{a.name}</span></td>
                  {cols.map((r) => {
                    const on = r.permissions?.includes('*') || r.permissions?.includes(a.name);
                    return <td key={r.id}><Toggle on={on} disabled={!can || r.name === 'super_admin'} onChange={(v) => toggle({ id: r.id, permission: a.name, enabled: v, silent: true })} label={`${r.label}: ${a.name}`} /></td>;
                  })}
                </tr>
              )),
            ])}
          </tbody>
        </table>
      </div>
      {adding && <Modal title="New role" onClose={() => setAdding(false)}>
        <SchemaForm onCancel={() => setAdding(false)} fields={[{ name: 'label', label: 'Role name', required: true, placeholder: 'Content editor', cols: 2 },
          { name: 'copy', label: 'Start with the permissions of', type: 'select', options: cols.map((r) => ({ value: r.id, label: r.label })), cols: 2 }]}
          onSubmit={async (v) => { await saveRole({ label: v.label, permissions: (cols.find((r) => String(r.id) === String(v.copy))?.permissions || []).filter((p) => p !== '*') }).unwrap(); setAdding(false); }} />
      </Modal>}
      {confirm && <Confirm text="Delete this role? Move its people to another role first." onYes={() => del(confirm)} onClose={() => setConfirm(null)} />}
    </Card>
  );
}

function Staff() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useStaffQuery(f);
  const { data: roles = [] } = useRolesQuery();
  const { data: courses = [] } = useCourseOptionsQuery();
  const [save, s] = useSaveStaffMutation();
  const [block] = useBlockStaffMutation();
  const [edit, setEdit] = useState(null);
  const [errors, run] = useFormErrors();

  return (
    <Card flush title="Staff & teachers" actions={<Can perm="users.create"><Button size="sm" variant="primary" onClick={() => setEdit({ role: 'teacher' })}>+ Add person</Button></Can>}>
      <div style={{ padding: '10px 16px 0' }}><Filters value={f} onChange={setF} filters={[{ name: 'role', label: 'Role', options: roles.filter((r) => r.name !== 'student').map((r) => ({ value: r.name, label: r.label })) }]} /></div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })}
        onRow={(u) => setEdit({ ...u, role: u.roles?.[0], designation: u.staff?.designation, course_ids: [] })}
        columns={[
          { key: 'name', label: 'Name', render: (u) => <div><div className="b">{u.name}</div><div className="small muted">{u.email} · {u.phone || '—'}</div></div> },
          { key: 'role', label: 'Role', render: (u) => <Badge>{u.roles?.[0]}</Badge> },
          { key: 'designation', label: 'Designation', render: (u) => u.staff?.designation || '—' },
          { key: 'seen', label: 'Last active', render: (u) => ago(u.last_seen_at) },
          { key: 'status', label: 'Status', render: (u) => <Badge>{u.status}</Badge> },
          { key: 'x', label: '', render: (u) => <Can perm="users.block"><Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); block({ id: u.id, block: u.status !== 'blocked' }); }}>{u.status === 'blocked' ? 'Unblock' : 'Block'}</Button></Can> },
        ]} />
      {edit && <Modal title={edit.id ? `Edit ${edit.name}` : 'Add staff / teacher'} onClose={() => setEdit(null)}>
        <SchemaForm errors={errors} loading={s.isLoading} initial={edit} onCancel={() => setEdit(null)} fields={[
          { name: 'name', label: 'Name', required: true }, { name: 'email', label: 'Email (login)', type: 'email', required: true },
          { name: 'phone', label: 'Mobile (for the app)' }, { name: 'password', label: edit.id ? 'New password (optional)' : 'Password', type: 'password', required: !edit.id },
          { name: 'role', label: 'Role', type: 'select', required: true, options: roles.filter((r) => r.name !== 'student').map((r) => ({ value: r.name, label: r.label })) },
          { name: 'designation', label: 'Designation', placeholder: 'Senior faculty – GK' },
          { name: 'course_ids', label: edit.id ? 'Replace course access (optional)' : 'Courses they work on', type: 'multiselect', cols: 2, options: courses.map((c) => ({ value: c.id, label: c.title })) },
        ]} onSubmit={async (v) => {
          const body = { id: edit.id, name: v.name, email: v.email, phone: v.phone || null, role: v.role, designation: v.designation, is_teacher: v.role === 'teacher' };
          if (v.password) body.password = v.password;
          if (!edit.id || v.course_ids?.length) body.course_ids = v.course_ids || [];
          await run(save(body));
          setEdit(null);
        }} />
      </Modal>}
    </Card>
  );
}

export default function RolesPage() {
  const [tab, setTab] = useState('staff');
  return (
    <>
      <PageHead title="Roles & staff" sub="A role decides WHAT someone can do. Course access (Courses → Staff access) decides WHERE." />
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'staff', label: '👩‍🏫 People' }, { key: 'matrix', label: '🛡️ Permissions' }]} />
      {tab === 'staff' ? <Staff /> : <Matrix />}
    </>
  );
}
