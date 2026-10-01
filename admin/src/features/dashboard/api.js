import { api, one } from '../../app/api';

export const dashApi = api.injectEndpoints({
  endpoints: (b) => ({
    dashboard: b.query({ query: () => 'admin/dashboard', transformResponse: one, providesTags: ['Dashboard'] }),
  }),
});
export const { useDashboardQuery } = dashApi;
