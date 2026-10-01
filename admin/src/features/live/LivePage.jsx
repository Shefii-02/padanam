import { useState } from 'react';
import { useLiveClassesQuery, useScheduleLiveMutation, useLiveActionMutation, useAttendanceQuery } from './api';
import { useCourseOptionsQuery, useFoldersQuery, useStaffOptionsQuery } from '../courses/api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead, Tabs, fmtDate, useCan } from '../../components/ui';
import DataTable from '../../components/DataTable';
import { SchemaForm, useFormErrors, Field } from '../../components/Form';

const flat = (nodes, out = []) => { nodes.forEach((n) => { out.push(n); flat(n.children || [], out); }); return out; };

function ScheduleModal({ onClose }) {
  const { data: courses = [] } = useCourseOptionsQuery();
  const { data: teachers = [] } = useStaffOptionsQuery(true);
  const [courseId, setCourseId] = useState('');
  const { data: folders = [] } = useFoldersQuery(courseId, { skip: !courseId });
  const [schedule, s] = useScheduleLiveMutation();
  const [errors, run] = useFormErrors();
  const course = courses.find((c) => String(c.id) === String(courseId));
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  return (
    <Modal wide title="Schedule a live class" onClose={onClose}>
      <div style={{ marginBottom: 12 }}>
        <Field f={{ name: 'course', label: 'Course', type: 'select', required: true, options: courses.map((c) => ({ value: c.id, label: c.title })) }} value={courseId} onChange={setCourseId} />
      </div>
      {course && (
        <SchemaForm key={courseId} errors={errors} loading={s.isLoading} onCancel={onClose} submitText="Schedule"
          initial={{ duration_min: 60, source: 'meet_youtube', save_recording: true, repeat_weekdays: [] }}
          fields={[
            { name: 'batch_id', label: 'Batch', type: 'select', required: true, options: course.batches.map((b) => ({ value: b.id, label: b.name })) },
            { name: 'teacher_id', label: 'Teacher', type: 'select', options: teachers.map((t) => ({ value: t.id, label: t.name })) },
            { name: 'title', label: 'Title', required: true, cols: 2, placeholder: 'Kerala Renaissance – Part 3' },
            { name: 'starts_at', label: 'Starts at', type: 'datetime', required: true },
            { name: 'duration_min', label: 'Duration (minutes)', type: 'number', min: 10 },
            { name: 'folder_id', label: 'Save in folder', type: 'select', empty: 'Outside folders', options: flat(folders).map((f) => ({ value: f.id, label: f.title })) },
            { name: 'source', label: 'How you stream', type: 'select', options: [{ value: 'meet_youtube', label: 'Google Meet → YouTube' }, { value: 'youtube', label: 'YouTube Studio / OBS' }, { value: 'aws', label: 'AWS IVS' }] },
            { name: 'meet_url', label: 'Google Meet link (for the teacher)', show: (v) => v.source === 'meet_youtube', cols: 2 },
            { name: 'save_recording', label: 'Save the recording to the folder when it ends', type: 'toggle', cols: 2 },
            { name: 'repeat_weekdays', label: 'Repeat every week on (optional)', type: 'multiselect', options: days.map((d, i) => ({ value: i + 1, label: d })) },
            { name: 'repeat_until', label: 'Repeat until', type: 'date', show: (v) => v.repeat_weekdays?.length > 0 },
          ]}
          onSubmit={async (v) => {
            const body = { ...v, teacher_id: v.teacher_id || null, folder_id: v.folder_id || null };
            if (!v.repeat_weekdays?.length) { delete body.repeat_weekdays; delete body.repeat_until; }
            await run(schedule(body));
            onClose();
          }} />
      )}
    </Modal>
  );
}

function GoLiveModal({ lc, onClose }) {
  const [act, s] = useLiveActionMutation();
  const [url, setUrl] = useState('');
  return (
    <Modal title={`Go live – ${lc.title}`} onClose={onClose}
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" loading={s.isLoading} disabled={!url} onClick={async () => { await act({ id: lc.id, action: 'go-live', url }).unwrap(); onClose(); }}>🔴 Start & notify students</Button></>}>
      {lc.source === 'meet_youtube' && (
        <ol className="small" style={{ marginTop: 0, paddingLeft: 18, lineHeight: 1.7 }}>
          <li>Open the Google Meet{lc.meet_url && <> (<a href={lc.meet_url} target="_blank" rel="noreferrer">open</a>)</>} and start the meeting.</li>
          <li>Click <b>Activities → Live streaming</b>, choose the Padanam YouTube channel, visibility <b>Unlisted</b>.</li>
          <li>Copy the YouTube link and paste it below. Students get a “Live now” notification.</li>
        </ol>
      )}
      <Field f={{ name: 'url', label: 'YouTube live link', placeholder: 'https://youtube.com/live/…' }} value={url} onChange={setUrl} />
      <p className="small muted">Needs a Google Workspace plan with live streaming and a YouTube channel enabled for live (first time takes up to 24 h).</p>
    </Modal>
  );
}

function AttendanceModal({ lc, onClose }) {
  const { data } = useAttendanceQuery(lc.id);
  return (
    <Modal title={`Attendance – ${lc.title}`} onClose={onClose}>
      {data && <>
        <p><b>{data.present}</b> of {data.enrolled} students joined ({data.percent}%)</p>
        <table className="t"><tbody>{data.students.map((s) => <tr key={s.id}><td>{s.name}</td><td>{s.phone}</td><td className="small muted">{fmtDate(s.joined_at, true)}</td></tr>)}</tbody></table>
      </>}
    </Modal>
  );
}

export default function LivePage() {
  const [when, setWhen] = useState('upcoming');
  const [page, setPage] = useState(1);
  const [mine, setMine] = useState(false);
  const { data, isFetching } = useLiveClassesQuery({ when, page, mine: mine ? 1 : '' }, { pollingInterval: 30000 });
  const [act] = useLiveActionMutation();
  const [modal, setModal] = useState(null);
  const can = useCan();

  return (
    <>
      <PageHead title="Live classes" sub="Students get a ring 5 minutes before the class (they can turn it off per course).">
        <Can perm="live_classes.schedule"><Button variant="primary" onClick={() => setModal({ kind: 'schedule' })}>+ Schedule class</Button></Can>
      </PageHead>
      <Card flush>
        <div style={{ padding: '6px 16px 0' }} className="row between">
          <Tabs value={when} onChange={(w) => { setWhen(w); setPage(1); }} tabs={[{ key: 'upcoming', label: 'Upcoming & live' }, { key: 'past', label: 'Finished' }]} />
          <label className="row small"><input type="checkbox" checked={mine} onChange={(e) => setMine(e.target.checked)} /> Only my classes</label>
        </div>
        <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={page} onPage={setPage}
          columns={[
            { key: 'starts_at', label: 'When', render: (l) => <span className="nowrap b">{fmtDate(l.starts_at, true)}</span> },
            { key: 'title', label: 'Class', render: (l) => <div><div className="b">{l.title}</div><div className="small muted">{l.course} · {l.batch}</div></div> },
            { key: 'teacher', label: 'Teacher', render: (l) => l.teacher?.name || '—' },
            { key: 'status', label: 'Status', render: (l) => <Badge>{l.status}</Badge> },
            { key: 'attendance_count', label: 'Joined' },
            { key: 'x', label: '', className: 'right nowrap', render: (l) => (
              <>
                {l.status === 'scheduled' && can('live_classes.go_live') && <Button size="sm" variant="primary" onClick={() => setModal({ kind: 'golive', lc: l })}>🔴 Go live</Button>}
                {l.status === 'live' && <><a className="btn sm" href={l.stream_url} target="_blank" rel="noreferrer">Watch</a><Button size="sm" onClick={() => setModal({ kind: 'end', lc: l })}>End class</Button></>}
                {l.status === 'scheduled' && can('live_classes.schedule') && <Button size="sm" variant="ghost" onClick={() => setModal({ kind: 'cancel', lc: l })}>Cancel</Button>}
                {l.status === 'ended' && <Button size="sm" onClick={() => setModal({ kind: 'att', lc: l })}>Attendance</Button>}
              </>
            ) },
          ]} />
      </Card>
      {modal?.kind === 'schedule' && <ScheduleModal onClose={() => setModal(null)} />}
      {modal?.kind === 'golive' && <GoLiveModal lc={modal.lc} onClose={() => setModal(null)} />}
      {modal?.kind === 'att' && <AttendanceModal lc={modal.lc} onClose={() => setModal(null)} />}
      {modal?.kind === 'end' && <Confirm danger={false} yes="End class" text="End the class? The YouTube video is saved as the recording in the course folder." onYes={() => act({ id: modal.lc.id, action: 'end' })} onClose={() => setModal(null)} />}
      {modal?.kind === 'cancel' && <Confirm yes="Cancel class" text="Cancel this class? It is removed from the course." onYes={() => act({ id: modal.lc.id, action: 'cancel' })} onClose={() => setModal(null)} />}
    </>
  );
}
