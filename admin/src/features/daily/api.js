import { api, one } from '../../app/api';

export const dailyApi = api.injectEndpoints({
  endpoints: (b) => ({
    dailyCalendar: b.query({ query: (month) => ({ url: 'admin/daily-quiz', params: { month } }), transformResponse: one, providesTags: ['Daily'] }),
    planDaily: b.mutation({ query: (body) => ({ url: 'admin/daily-quiz', method: 'POST', body }), invalidatesTags: ['Daily'] }),
    planRange: b.mutation({ query: (body) => ({ url: 'admin/daily-quiz/range', method: 'POST', body }), invalidatesTags: ['Daily'] }),
    buildDaily: b.mutation({ query: (id) => ({ url: `admin/daily-quiz/${id}/build`, method: 'POST' }), invalidatesTags: ['Daily', 'Test'] }),
    deleteDaily: b.mutation({ query: (id) => ({ url: `admin/daily-quiz/${id}`, method: 'DELETE' }), invalidatesTags: ['Daily'] }),
    planTemplates: b.query({ query: () => 'admin/study-plans', transformResponse: one, providesTags: ['Plan'] }),
    savePlan: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/study-plans/${id}` : 'admin/study-plans', method: id ? 'PUT' : 'POST', body }), invalidatesTags: ['Plan'] }),
  }),
});
export const { useDailyCalendarQuery, usePlanDailyMutation, usePlanRangeMutation, useBuildDailyMutation, useDeleteDailyMutation, usePlanTemplatesQuery, useSavePlanMutation } = dailyApi;
