import { api, one, list, clean } from '../../app/api';

export const liveApi = api.injectEndpoints({
  endpoints: (b) => ({
    liveClasses: b.query({ query: (p) => ({ url: 'admin/live-classes', params: clean(p) }), transformResponse: list, providesTags: ['Live'] }),
    scheduleLive: b.mutation({ query: (body) => ({ url: 'admin/live-classes', method: 'POST', body }), invalidatesTags: ['Live', 'Content', 'Dashboard'] }),
    updateLive: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/live-classes/${id}`, method: 'PATCH', body }), invalidatesTags: ['Live'] }),
    liveAction: b.mutation({ query: ({ id, action, ...body }) => ({ url: `admin/live-classes/${id}/${action}`, method: 'POST', body }), invalidatesTags: ['Live', 'Content', 'Dashboard'] }),
    attendance: b.query({ query: (id) => `admin/live-classes/${id}/attendance`, transformResponse: one }),
  }),
});
export const { useLiveClassesQuery, useScheduleLiveMutation, useUpdateLiveMutation, useLiveActionMutation, useAttendanceQuery } = liveApi;
