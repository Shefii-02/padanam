import { api, one, list, clean, toForm } from '../../app/api';

export const testsApi = api.injectEndpoints({
  endpoints: (b) => ({
    tests: b.query({ query: (p) => ({ url: 'admin/tests', params: clean(p) }), transformResponse: list, providesTags: ['Test'] }),
    test: b.query({ query: (id) => `admin/tests/${id}`, transformResponse: one, providesTags: (r, e, id) => [{ type: 'Test', id }] }),
    saveTest: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/tests/${id}` : 'admin/tests', method: id ? 'PATCH' : 'POST', body }), transformResponse: one, invalidatesTags: ['Test'] }),
    testAction: b.mutation({ query: ({ id, action, ...body }) => ({ url: `admin/tests/${id}/${action}`, method: 'POST', body }), transformResponse: one, invalidatesTags: ['Test'] }),
    deleteTest: b.mutation({ query: (id) => ({ url: `admin/tests/${id}`, method: 'DELETE' }), invalidatesTags: ['Test'] }),
    saveSection: b.mutation({ query: ({ id, test_id, ...body }) => ({ url: id ? `admin/test-sections/${id}` : `admin/tests/${test_id}/sections`, method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Test'] }),
    deleteSection: b.mutation({ query: (id) => ({ url: `admin/test-sections/${id}`, method: 'DELETE' }), invalidatesTags: ['Test'] }),
    testQuestions: b.query({ query: (id) => `admin/tests/${id}/questions`, transformResponse: one, providesTags: ['Test'] }),
    addTestQuestions: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/tests/${id}/questions`, method: 'POST', body }), invalidatesTags: ['Test', 'Question'] }),
    autoPick: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/tests/${id}/questions/auto-pick`, method: 'POST', body }), invalidatesTags: ['Test', 'Question'] }),
    removeTestQuestions: b.mutation({ query: ({ id, ids }) => ({ url: `admin/tests/${id}/questions/remove`, method: 'POST', body: { ids } }), invalidatesTags: ['Test'] }),
    arrangeTest: b.mutation({ query: ({ id, items }) => ({ url: `admin/tests/${id}/questions/arrange`, method: 'POST', body: { items } }), invalidatesTags: ['Test'] }),
    results: b.query({ query: ({ id, ...p }) => ({ url: `admin/tests/${id}/results`, params: clean(p) }), transformResponse: list, providesTags: ['Test'] }),
    omrPending: b.query({ query: (id) => `admin/tests/${id}/omr/pending`, transformResponse: one, providesTags: ['Test'] }),
    omrImport: b.mutation({ query: ({ id, file }) => ({ url: `admin/tests/${id}/omr/import`, method: 'POST', body: toForm({ file }) }), transformResponse: one, invalidatesTags: ['Test'] }),
    omrEnter: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/tests/${id}/omr/enter`, method: 'POST', body }), transformResponse: one, invalidatesTags: ['Test'] }),
  }),
});
export const {
  useTestsQuery, useTestQuery, useSaveTestMutation, useTestActionMutation, useDeleteTestMutation, useSaveSectionMutation, useDeleteSectionMutation,
  useTestQuestionsQuery, useAddTestQuestionsMutation, useAutoPickMutation, useRemoveTestQuestionsMutation, useArrangeTestMutation, useResultsQuery,
  useOmrPendingQuery, useOmrImportMutation, useOmrEnterMutation,
} = testsApi;

export const KINDS = ['mock', 'sectional', 'chapter', 'pyq', 'practice', 'daily_quiz'].map((x) => ({ value: x, label: x.replace('_', ' ') }));
