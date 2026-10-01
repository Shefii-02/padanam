import { api, one, list, clean, toForm } from '../../app/api';

export const coursesApi = api.injectEndpoints({
  endpoints: (b) => ({
    courses: b.query({ query: (p) => ({ url: 'admin/courses', params: clean(p) }), transformResponse: list, providesTags: ['Course'] }),
    courseOptions: b.query({ query: () => 'admin/courses/options', transformResponse: one, providesTags: ['Course', 'Batch'] }),
    course: b.query({ query: (id) => `admin/courses/${id}`, transformResponse: one, providesTags: (r, e, id) => [{ type: 'Course', id }, 'Batch'] }),
    createCourse: b.mutation({ query: (body) => ({ url: 'admin/courses', method: 'POST', body: body.thumbnail instanceof File ? toForm(body) : body }), transformResponse: one, invalidatesTags: ['Course'] }),
    updateCourse: b.mutation({
      query: ({ id, ...body }) => (body.thumbnail instanceof File
        ? { url: `admin/courses/${id}`, method: 'POST', body: toForm(body) }
        : { url: `admin/courses/${id}`, method: 'PATCH', body }),
      invalidatesTags: ['Course'],
    }),
    courseStatus: b.mutation({ query: ({ id, action }) => ({ url: `admin/courses/${id}/${action}`, method: 'POST' }), invalidatesTags: ['Course'] }),
    courseStaff: b.mutation({ query: ({ id, staff }) => ({ url: `admin/courses/${id}/staff`, method: 'PUT', body: { staff } }), invalidatesTags: ['Course'] }),
    deleteCourse: b.mutation({ query: (id) => ({ url: `admin/courses/${id}`, method: 'DELETE' }), invalidatesTags: ['Course'] }),
    shareCourse: b.query({ query: (id) => `admin/courses/${id}/share`, transformResponse: one }),

    batches: b.query({ query: (courseId) => `admin/courses/${courseId}/batches`, transformResponse: one, providesTags: ['Batch'] }),
    saveBatch: b.mutation({
      query: ({ id, course_id, ...body }) => ({ url: id ? `admin/batches/${id}` : `admin/courses/${course_id}/batches`, method: id ? 'PATCH' : 'POST', body }),
      invalidatesTags: ['Batch', 'Course'],
    }),
    cloneBatch: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/batches/${id}/clone`, method: 'POST', body }), invalidatesTags: ['Batch'] }),
    batchEnrollment: b.mutation({ query: ({ id, open }) => ({ url: `admin/batches/${id}/enrollment`, method: 'POST', body: { open } }), invalidatesTags: ['Batch'] }),
    deleteBatch: b.mutation({ query: (id) => ({ url: `admin/batches/${id}`, method: 'DELETE' }), invalidatesTags: ['Batch'] }),

    folders: b.query({ query: (courseId) => `admin/courses/${courseId}/folders`, transformResponse: one, providesTags: ['Folder'] }),
    saveFolder: b.mutation({
      query: ({ id, course_id, ...body }) => ({ url: id ? `admin/folders/${id}` : `admin/courses/${course_id}/folders`, method: id ? 'PATCH' : 'POST', body }),
      invalidatesTags: ['Folder'],
    }),
    deleteFolder: b.mutation({ query: (id) => ({ url: `admin/folders/${id}`, method: 'DELETE' }), invalidatesTags: ['Folder', 'Content'] }),

    contents: b.query({ query: ({ courseId, folderId }) => ({ url: `admin/courses/${courseId}/contents`, params: folderId ? { folder_id: folderId } : { outside: 1 } }), transformResponse: one, providesTags: ['Content'] }),
    saveContent: b.mutation({
      query: ({ id, course_id, ...body }) => {
        const multipart = body.file instanceof File;
        if (id) return { url: `admin/contents/${id}`, method: multipart ? 'POST' : 'PATCH', body: multipart ? toForm(body) : body };
        return { url: `admin/courses/${course_id}/contents`, method: 'POST', body: multipart ? toForm(body) : body };
      },
      invalidatesTags: ['Content', 'Folder'],
    }),
    reorderContents: b.mutation({ query: ({ courseId, items }) => ({ url: `admin/courses/${courseId}/contents/reorder`, method: 'POST', body: { items } }), invalidatesTags: ['Content'] }),
    deleteContent: b.mutation({ query: (id) => ({ url: `admin/contents/${id}`, method: 'DELETE' }), invalidatesTags: ['Content', 'Folder'] }),

    batchStudents: b.query({ query: ({ batchId, ...p }) => ({ url: `admin/batches/${batchId}/students`, params: clean(p) }), transformResponse: list, providesTags: ['Enrollment'] }),
    removeEnrollment: b.mutation({ query: ({ id, reason }) => ({ url: `admin/enrollments/${id}/remove`, method: 'POST', body: { reason } }), invalidatesTags: ['Enrollment', 'Batch'] }),
    extendEnrollment: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/enrollments/${id}/extend`, method: 'POST', body }), invalidatesTags: ['Enrollment'] }),
    moveEnrollment: b.mutation({ query: ({ id, batch_id }) => ({ url: `admin/enrollments/${id}/move`, method: 'POST', body: { batch_id } }), invalidatesTags: ['Enrollment', 'Batch'] }),

    staffOptions: b.query({ query: (teachersOnly) => ({ url: 'admin/staff/options', params: teachersOnly ? { teachers_only: 1 } : {} }), transformResponse: one, providesTags: ['Staff'] }),
  }),
});

export const {
  useCoursesQuery, useCourseOptionsQuery, useCourseQuery, useCreateCourseMutation, useUpdateCourseMutation, useCourseStatusMutation, useCourseStaffMutation,
  useDeleteCourseMutation, useLazyShareCourseQuery, useBatchesQuery, useSaveBatchMutation, useCloneBatchMutation, useBatchEnrollmentMutation, useDeleteBatchMutation,
  useFoldersQuery, useSaveFolderMutation, useDeleteFolderMutation, useContentsQuery, useSaveContentMutation, useReorderContentsMutation, useDeleteContentMutation,
  useBatchStudentsQuery, useRemoveEnrollmentMutation, useExtendEnrollmentMutation, useMoveEnrollmentMutation, useStaffOptionsQuery,
} = coursesApi;

export const COURSE_TYPES = [
  { value: 'live_recorded', label: 'Live + recorded' }, { value: 'live_only', label: 'Live only' }, { value: 'recorded_only', label: 'Recorded only' },
  { value: 'tests_only', label: 'Test series' }, { value: 'material_only', label: 'Study material' }, { value: 'custom', label: 'Custom' },
];
export const CONTENT_TYPES = { video: '🎬 Video', live: '🔴 Live class', pdf: '📄 PDF', note: '📝 Note', article: '📰 Article', test: '🧪 Test', quiz: '⚡ Quiz', link: '🔗 Link' };
