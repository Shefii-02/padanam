import { api, one, list, clean, toForm } from '../../app/api';

export const articlesApi = api.injectEndpoints({
  endpoints: (b) => ({
    articles: b.query({ query: (p) => ({ url: 'admin/articles', params: clean(p) }), transformResponse: (r) => ({ items: r.data?.data || [], meta: { pagination: r.data && { page: r.data.current_page, last_page: r.data.last_page, total: r.data.total } } }), providesTags: ['Article'] }),
    article: b.query({ query: (id) => `admin/articles/${id}`, transformResponse: one, providesTags: ['Article'] }),
    saveArticle: b.mutation({
      query: ({ id, ...body }) => ({ url: id ? `admin/articles/${id}` : 'admin/articles', method: 'POST', body: toForm(body) }),
      invalidatesTags: ['Article'],
    }),
    deleteArticle: b.mutation({ query: (id) => ({ url: `admin/articles/${id}`, method: 'DELETE' }), invalidatesTags: ['Article'] }),
    doubts: b.query({ query: (p) => ({ url: 'admin/doubts', params: clean(p) }), transformResponse: list, providesTags: ['Doubt'] }),
    answerDoubt: b.mutation({ query: ({ id, answer }) => ({ url: `admin/doubts/${id}/answer`, method: 'POST', body: { answer } }), invalidatesTags: ['Doubt', 'Dashboard'] }),
  }),
});
export const { useArticlesQuery, useLazyArticleQuery, useSaveArticleMutation, useDeleteArticleMutation, useDoubtsQuery, useAnswerDoubtMutation } = articlesApi;
