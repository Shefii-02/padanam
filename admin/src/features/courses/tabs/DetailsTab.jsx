import { useState } from 'react';
import { useUpdateCourseMutation, COURSE_TYPES } from '../api';
import { useCategoriesQuery, categoryOptions } from '../../catalog/api';
import { Card, Toggle, useCan } from '../../../components/ui';
import { SchemaForm, useFormErrors } from '../../../components/Form';

export default function DetailsTab({ course }) {
  const [update, u] = useUpdateCourseMutation();
  const { data: cats = [] } = useCategoriesQuery();
  const [errors, run] = useFormErrors();
  const [features, setFeatures] = useState(course.features || {});
  const can = useCan();
  const editable = can('courses.edit');

  const flip = (k, v) => {
    const next = { ...features, [k]: v };
    setFeatures(next);
    update({ id: course.id, features: { [k]: v } });
  };

  return (
    <div className="grid g2" style={{ alignItems: 'start' }}>
      <Card title="Course details">
        <SchemaForm errors={errors} loading={u.isLoading} initial={{ ...course, exam_category_id: course.exam_category_id ?? '', thumbnail: undefined }}
          fields={[
            { name: 'title', label: 'Name', required: true, cols: 2 },
            { name: 'exam_category_id', label: 'Category', type: 'select', options: categoryOptions(cats) },
            { name: 'course_type', label: 'Type', type: 'select', options: COURSE_TYPES, hint: 'Changing the type resets the feature switches' },
            { name: 'language', label: 'Language' },
            { name: 'level', label: 'Level', placeholder: 'Beginner to advanced' },
            { name: 'intro_video_url', label: 'Intro video (YouTube link)', cols: 2 },
            { name: 'thumbnail', label: 'Thumbnail (16:9)', type: 'file', accept: 'image/*', cols: 2 },
            { name: 'short_description', label: 'Short description', type: 'textarea', cols: 2 },
            { name: 'description', label: 'Full description (HTML allowed)', type: 'textarea', rows: 6, cols: 2 },
            { name: 'what_you_get', label: 'What you get', type: 'chips', cols: 2, placeholder: '300+ recorded classes ⏎' },
            { name: 'is_featured', label: 'Featured in the store', type: 'toggle' },
          ]}
          onSubmit={(v) => {
            const keys = ['title', 'exam_category_id', 'course_type', 'language', 'level', 'intro_video_url', 'thumbnail', 'short_description', 'description', 'what_you_get', 'is_featured'];
            const body = Object.fromEntries(keys.filter((k) => v[k] !== undefined).map((k) => [k, v[k]]));
            return run(update({ id: course.id, ...body, exam_category_id: v.exam_category_id || null }));
          }} submitText="Save details" />
      </Card>
      <Card title="Feature switches">
        <p className="small muted" style={{ marginTop: 0 }}>These decide which tabs students see inside the course.</p>
        {Object.entries(course.feature_labels || {}).map(([k, label]) => (
          <div key={k} className="row between" style={{ padding: '10px 0', borderBottom: '1px solid var(--line)' }}>
            <span className="b">{label}</span>
            <Toggle on={!!features[k]} disabled={!editable} onChange={(v) => flip(k, v)} label={label} />
          </div>
        ))}
      </Card>
    </div>
  );
}
