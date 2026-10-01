import { api, one, list, clean } from '../../app/api';

const paged = (r) => ({ items: r.data?.data || [], meta: { pagination: r.data && { page: r.data.current_page, last_page: r.data.last_page, total: r.data.total } } });

export const growApi = api.injectEndpoints({
  endpoints: (b) => ({
    campaigns: b.query({ query: (p) => ({ url: 'admin/campaigns', params: clean(p) }), transformResponse: paged, providesTags: ['Campaign'] }),
    channels: b.query({ query: () => 'admin/notification-channels', transformResponse: one }),
    audiencePreview: b.mutation({ query: (body) => ({ url: 'admin/campaigns/preview', method: 'POST', body }), transformResponse: one }),
    saveCampaign: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/campaigns/${id}` : 'admin/campaigns', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Campaign'] }),
    campaignAction: b.mutation({ query: ({ id, action }) => ({ url: `admin/campaigns/${id}/${action}`, method: 'POST' }), invalidatesTags: ['Campaign'] }),
    templates: b.query({ query: () => 'admin/notification-templates', transformResponse: one, providesTags: ['Template'] }),
    saveTemplate: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/notification-templates/${id}`, method: 'PATCH', body }), invalidatesTags: ['Template'] }),

    leads: b.query({ query: (p) => ({ url: 'admin/leads', params: clean(p) }), transformResponse: list, providesTags: ['Lead'] }),
    lead: b.query({ query: (id) => `admin/leads/${id}`, transformResponse: one, providesTags: ['Lead'] }),
    saveLead: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/leads/${id}` : 'admin/leads', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Lead'] }),
    leadActivity: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/leads/${id}/activity`, method: 'POST', body }), invalidatesTags: ['Lead'] }),
    bulkLeads: b.mutation({ query: (body) => ({ url: 'admin/leads/bulk', method: 'POST', body }), invalidatesTags: ['Lead'] }),

    revenueReport: b.query({ query: (p) => ({ url: 'admin/reports/revenue', params: clean(p) }), transformResponse: one }),
    activityReport: b.query({ query: (p) => ({ url: 'admin/reports/activity', params: clean(p) }), transformResponse: one }),
    performanceReport: b.query({ query: (p) => ({ url: 'admin/reports/performance', params: clean(p) }), transformResponse: one }),
  }),
});
export const {
  useCampaignsQuery, useChannelsQuery, useAudiencePreviewMutation, useSaveCampaignMutation, useCampaignActionMutation, useTemplatesQuery, useSaveTemplateMutation,
  useLeadsQuery, useLeadQuery, useSaveLeadMutation, useLeadActivityMutation, useBulkLeadsMutation, useRevenueReportQuery, useActivityReportQuery, usePerformanceReportQuery,
} = growApi;
