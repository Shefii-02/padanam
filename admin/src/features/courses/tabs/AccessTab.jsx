import { useState } from 'react';
import { useCourseStaffMutation, useStaffOptionsQuery } from '../api';
import { Badge, Button, Card, useCan } from '../../../components/ui';

/** Staff & teachers assigned here manage/teach the course without buying it. */
export default function AccessTab({ course }) {
  const { data: staff = [] } = useStaffOptionsQuery(false);
  const [saveStaff, s] = useCourseStaffMutation();
  const [sel, setSel] = useState(() => Object.fromEntries((course.staff || []).map((x) => [x.id, x.role])));
  const can = useCan();

  const set = (id, role) => setSel((m) => { const n = { ...m }; if (role) n[id] = role; else delete n[id]; return n; });

  return (
    <Card title="Who can work on this course" actions={can('courses.edit') && <Button variant="primary" loading={s.isLoading}
      onClick={() => saveStaff({ id: course.id, staff: Object.entries(sel).map(([user_id, role]) => ({ user_id: Number(user_id), role })) })}>Save access</Button>}>
      <p className="small muted" style={{ marginTop: 0 }}>
        <b>Manager</b> can edit the course, batches and content. <b>Teacher</b> can add content, go live, make tests and answer doubts.
        Neither needs to buy the course. Admins always have access.
      </p>
      <table className="t">
        <thead><tr><th>Name</th><th>Panel role</th><th>Access to this course</th></tr></thead>
        <tbody>
          {staff.filter((u) => !['super_admin', 'admin'].includes(u.role)).map((u) => (
            <tr key={u.id}>
              <td className="b">{u.name}</td>
              <td><Badge>{u.role}</Badge></td>
              <td>
                <div className="chips">
                  {[['', 'No access'], ['teacher', 'Teacher'], ['manager', 'Manager']].map(([v, l]) => (
                    <button key={v} type="button" className={`chip ${(sel[u.id] || '') === v ? 'on' : ''}`} onClick={() => set(u.id, v)}>{l}</button>
                  ))}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}
