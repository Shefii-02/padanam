import { api, one, list, clean, toForm } from '../../app/api';

export const qbApi = api.injectEndpoints({
  endpoints: (b) => ({
    questions: b.query({ query: (p) => ({ url: 'admin/question-bank/questions', params: clean(p) }), transformResponse: list, providesTags: ['Question'] }),
    question: b.query({ query: (id) => `admin/question-bank/questions/${id}`, transformResponse: one, providesTags: ['Question'] }),
    facets: b.query({ query: () => 'admin/question-bank/questions/facets', transformResponse: one, providesTags: ['Question'] }),
    saveQuestion: b.mutation({
      query: ({ id, ...body }) => ({ url: id ? `admin/question-bank/questions/${id}` : 'admin/question-bank/questions', method: id ? 'PATCH' : 'POST', body }),
      invalidatesTags: ['Question', 'QFolder', 'Label'],
    }),
    deleteQuestion: b.mutation({ query: (id) => ({ url: `admin/question-bank/questions/${id}`, method: 'DELETE' }), invalidatesTags: ['Question', 'QFolder'] }),
    bulkQuestions: b.mutation({ query: (body) => ({ url: 'admin/question-bank/questions/bulk', method: 'POST', body }), invalidatesTags: ['Question', 'QFolder', 'Label'] }),

    qFolders: b.query({ query: () => 'admin/question-bank/folders', transformResponse: one, providesTags: ['QFolder'] }),
    saveQFolder: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/question-bank/folders/${id}` : 'admin/question-bank/folders', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['QFolder'] }),
    deleteQFolder: b.mutation({ query: (id) => ({ url: `admin/question-bank/folders/${id}`, method: 'DELETE' }), invalidatesTags: ['QFolder', 'Question'] }),
    labels: b.query({ query: () => 'admin/question-bank/labels', transformResponse: one, providesTags: ['Label'] }),
    saveLabel: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/question-bank/labels/${id}` : 'admin/question-bank/labels', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Label'] }),
    mergeLabel: b.mutation({ query: ({ id, into_id }) => ({ url: `admin/question-bank/labels/${id}/merge`, method: 'POST', body: { into_id } }), invalidatesTags: ['Label', 'Question'] }),
    deleteLabel: b.mutation({ query: (id) => ({ url: `admin/question-bank/labels/${id}`, method: 'DELETE' }), invalidatesTags: ['Label'] }),

    imports: b.query({ query: () => 'admin/question-bank/imports', transformResponse: one, providesTags: ['Import'] }),
    importStatus: b.query({ query: (id) => `admin/question-bank/imports/${id}`, transformResponse: one, providesTags: ['Import'] }),
    uploadImport: b.mutation({ query: (body) => ({ url: 'admin/question-bank/imports', method: 'POST', body: toForm(body) }), transformResponse: one, invalidatesTags: ['Import'] }),
    confirmImport: b.mutation({ query: ({ id, skip_duplicates }) => ({ url: `admin/question-bank/imports/${id}/confirm`, method: 'POST', body: { skip_duplicates } }), transformResponse: one, invalidatesTags: ['Import', 'Question', 'QFolder', 'Label'] }),
  }),
});
export const {
  useQuestionsQuery, useLazyQuestionQuery, useFacetsQuery, useSaveQuestionMutation, useDeleteQuestionMutation, useBulkQuestionsMutation,
  useQFoldersQuery, useSaveQFolderMutation, useDeleteQFolderMutation, useLabelsQuery, useSaveLabelMutation, useMergeLabelMutation, useDeleteLabelMutation,
  useImportsQuery, useImportStatusQuery, useUploadImportMutation, useConfirmImportMutation,
} = qbApi;

export const flatFolders = (nodes = [], depth = 0, out = []) => {
  nodes.forEach((n) => { out.push({ value: n.id, label: `${'— '.repeat(depth)}${n.name}` }); flatFolders(n.children, depth + 1, out); });
  return out;
};
export const LANGS = [{ value: 'en', label: 'English' }, { value: 'ml', label: 'മലയാളം' }, { value: 'hi', label: 'हिन्दी' }, { value: 'ta', label: 'தமிழ்' }];
