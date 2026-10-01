import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useCourseQuery, useCourseStatusMutation, useDeleteCourseMutation, useLazyShareCourseQuery } from './api';
import { Badge, Button, Can, Confirm, PageHead, Spinner, Tabs } from '../../components/ui';
import DetailsTab from './tabs/DetailsTab';
import BatchesTab from './tabs/BatchesTab';
import ContentTab from './tabs/ContentTab';
import AccessTab from './tabs/AccessTab';
import StudentsTab from './tabs/StudentsTab';

export default function CourseBuilder() {
  const { id } = useParams();
  const { data: course, isLoading } = useCourseQuery(id);
  const [tab, setTab] = useState('content');
  const [status] = useCourseStatusMutation();
  const [del] = useDeleteCourseMutation();
  const [share] = useLazyShareCourseQuery();
  const [confirm, setConfirm] = useState(false);
  const nav = useNavigate();

  if (isLoading || !course) return <Spinner />;

  const doShare = async () => {
    const s = await share(course.id).unwrap();
    window.open(s.whatsapp, '_blank', 'noopener');
  };

  return (
    <>
      <PageHead title={course.title} sub={<span>{course.category?.name || 'No category'} · {course.students_count} students · <Badge>{course.status}</Badge></span>}>
        <Button onClick={doShare}>📤 Share on WhatsApp</Button>
        <Can perm="courses.publish">
          {course.status !== 'published'
            ? <Button variant="primary" onClick={() => status({ id: course.id, action: 'publish' })}>Publish</Button>
            : <Button onClick={() => status({ id: course.id, action: 'unpublish' })}>Move to draft</Button>}
          {course.status !== 'archived' && <Button onClick={() => status({ id: course.id, action: 'archive' })}>Archive</Button>}
        </Can>
        <Can perm="courses.delete"><Button variant="danger" onClick={() => setConfirm(true)}>Delete</Button></Can>
      </PageHead>
      <Tabs value={tab} onChange={setTab} tabs={[
        { key: 'content', label: '📁 Content' },
        { key: 'batches', label: `🗓️ Batches (${course.batches?.length || 0})` },
        { key: 'details', label: '✏️ Details & features' },
        { key: 'access', label: '👩‍🏫 Staff access' },
        { key: 'students', label: '👥 Students' },
      ]} />
      {tab === 'details' && <DetailsTab course={course} />}
      {tab === 'batches' && <BatchesTab course={course} />}
      {tab === 'content' && <ContentTab course={course} />}
      {tab === 'access' && <AccessTab course={course} />}
      {tab === 'students' && <StudentsTab course={course} />}
      {confirm && <Confirm text="Delete this course? Courses with active students can only be archived." onClose={() => setConfirm(false)}
        onYes={async () => { await del(course.id).unwrap(); nav('/courses'); }} />}
    </>
  );
}
