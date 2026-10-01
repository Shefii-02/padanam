import { api, one, list, clean } from '../../app/api';

export const marketingApi = api.injectEndpoints({
  endpoints: (b) => ({
    marketingTypes: b.query({ query: () => 'admin/marketing/types', transformResponse: one }),
    audienceCount: b.query({ query: (p) => ({ url: 'admin/marketing/count', params: clean(p) }), transformResponse: one }),
    exportHistory: b.query({ query: (p) => ({ url: 'admin/marketing/exports', params: clean(p) }), transformResponse: list, providesTags: ['Export'] }),
  }),
});
export const { useMarketingTypesQuery, useAudienceCountQuery, useExportHistoryQuery } = marketingApi;
