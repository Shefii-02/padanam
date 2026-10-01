import { api, one } from '../../app/api';

export const catalogApi = api.injectEndpoints({
  endpoints: (b) => ({
    categories: b.query({ query: () => 'admin/categories', transformResponse: one, providesTags: ['Category'] }),
    saveCategory: b.mutation({
      query: ({ id, ...body }) => ({ url: id ? `admin/categories/${id}` : 'admin/categories', method: id ? 'PATCH' : 'POST', body }),
      invalidatesTags: ['Category'],
    }),
    deleteCategory: b.mutation({ query: (id) => ({ url: `admin/categories/${id}`, method: 'DELETE' }), invalidatesTags: ['Category'] }),
    saveExam: b.mutation({
      query: ({ id, ...body }) => ({ url: id ? `admin/exams/${id}` : 'admin/exams', method: id ? 'PATCH' : 'POST', body }),
      invalidatesTags: ['Category'],
    }),
    deleteExam: b.mutation({ query: (id) => ({ url: `admin/exams/${id}`, method: 'DELETE' }), invalidatesTags: ['Category'] }),
  }),
});
export const { useCategoriesQuery, useSaveCategoryMutation, useDeleteCategoryMutation, useSaveExamMutation, useDeleteExamMutation } = catalogApi;

/** [{value,label}] incl. sub-categories, for selects */
export const categoryOptions = (tree = []) => tree.flatMap((c) => [{ value: c.id, label: `${c.icon || ''} ${c.name}` }, ...(c.children || []).map((s) => ({ value: s.id, label: `— ${s.name}` }))]);
