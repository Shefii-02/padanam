import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useStudentsQuery, useStudentSummaryQuery } from './api';
import { useCategoriesQuery } from '../catalog/api';
import { useCourseOptionsQuery } from '../courses/api';
import { download } from '../../app/api';
import { Badge, Button, Can, Card, Modal, PageHead, Stat, ago } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { Field } from '../../components/Form';

const FIELDS = [['name', 'Name'], ['phone', 'Phone'], ['email', 'Email'], ['district', 'District'], ['interests', 'Exams'], ['courses', 'Courses'], ['last_seen', 'Last seen'], ['avg_score', 'Avg score'], ['joined', 'Joined']];

export default function StudentsPage() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useStudentsQuery(f);
  const { data: sum } = useStudentSummaryQuery();
  const { data: cats = [] } = useCategoriesQuery();
  const { data: courses = [] } = useCourseOptionsQuery();
  const [exp, setExp] = useState(null);
  const nav = useNavigate();

  return (
    <>
      <PageHead title="Students" sub="Active = opened the app in the last 14 days.">
        <Can perm="users.export"><Button onClick={() => setExp(FIELDS.slice(0, 5).map((x) => x[0]))}>⬇️ Export contacts</Button></Can>
        <Can perm="enrollments.add_manual"><Button variant="primary" onClick={() => nav('/admissions')}>+ Admission</Button></Can>
      </PageHead>
      {sum && <div className="grid g4" style={{ marginBottom: 14 }}>
        <Stat label="Total students" value={sum.total} hint={`${sum.new_this_week} new this week`} />
        <Stat label="Active (7 days)" value={sum.active_7d} />
        <Stat label="Inactive 14+ days" value={sum.inactive_14d} hint="Good audience for a comeback push" />
        <Stat label="Never purchased" value={sum.never_purchased} hint="Free users – offer a coupon" />
      </div>}
      <Card flush>
        <div style={{ padding: '14px 16px 0' }}>
          <Filters value={f} onChange={setF} placeholder="Name, phone, district" filters={[
            { name: 'activity', label: 'Activity', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] },
            { name: 'paid', label: 'Paid', options: [{ value: '1', label: 'Paid students' }, { value: '0', label: 'Free users' }] },
            { name: 'category_id', label: 'Exam', options: cats.map((c) => ({ value: c.id, label: c.name })) },
            { name: 'course_id', label: 'Course', options: courses.map((c) => ({ value: c.id, label: c.title })) },
            { name: 'expiring_days', label: 'Access ending', options: [{ value: 7, label: 'in 7 days' }, { value: 30, label: 'in 30 days' }] },
            { name: 'status', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'blocked', label: 'Blocked' }] },
          ]} />
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={(s) => nav(`/students/${s.id}`)}
          columns={[
            { key: 'name', label: 'Student', render: (s) => <div className="row"><span className="avatar">{s.initials}</span><div><div className="b">{s.name}</div><div className="small muted">{s.phone} · {s.district || '—'}</div></div></div> },
            { key: 'interests', label: 'Exams', render: (s) => (s.interests || []).join(', ') || '—' },
            { key: 'active_courses', label: 'Courses' },
            { key: 'avg', label: 'Avg score', render: (s) => (s.avg_score_percent !== null ? `${s.avg_score_percent}%` : '—') },
            { key: 'seen', label: 'Last seen', render: (s) => <span>{s.is_active ? '🟢' : '⚪'} {ago(s.last_seen_at)}</span> },
            { key: 'status', label: 'Status', render: (s) => <Badge>{s.status}</Badge> },
          ]} />
      </Card>
      {exp && (
        <Modal title="Export contacts (CSV)" onClose={() => setExp(null)}
          footer={<Button variant="primary" onClick={() => { download('admin/students/export', { ...f, page: undefined, fields: exp }); setExp(null); }}>Download</Button>}>
          <p className="small muted" style={{ marginTop: 0 }}>Uses the current filters. Exports are recorded in the audit log.</p>
          <Field f={{ name: 'fields', label: 'Columns', type: 'multiselect', options: FIELDS.map(([value, label]) => ({ value, label })) }} value={exp} onChange={setExp} />
        </Modal>
      )}
    </>
  );
}
