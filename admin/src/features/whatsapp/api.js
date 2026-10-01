import { api, one, list, clean } from '../../app/api';

export const waApi = api.injectEndpoints({
  endpoints: (b) => ({
    waStatus: b.query({ query: () => 'admin/whatsapp/status', transformResponse: one, providesTags: ['WhatsApp'] }),
    waSettings: b.query({ query: () => 'admin/whatsapp/settings', transformResponse: one, providesTags: ['WhatsApp'] }),
    saveWaAccount: b.mutation({ query: ({ purpose, ...body }) => ({ url: `admin/whatsapp/accounts/${purpose}`, method: 'PUT', body }), invalidatesTags: ['WhatsApp'] }),
    saveWaTemplates: b.mutation({ query: (body) => ({ url: 'admin/whatsapp/templates', method: 'PUT', body }), invalidatesTags: ['WhatsApp'] }),
    testWa: b.mutation({ query: ({ purpose, phone }) => ({ url: `admin/whatsapp/accounts/${purpose}/test`, method: 'POST', body: { phone } }), invalidatesTags: ['WhatsApp', 'WaMessage'] }),
    waMessages: b.query({ query: (p) => ({ url: 'admin/whatsapp/messages', params: clean(p) }), transformResponse: list, providesTags: ['WaMessage'] }),
    retryWa: b.mutation({ query: (id) => ({ url: `admin/whatsapp/messages/${id}/retry`, method: 'POST' }), invalidatesTags: ['WaMessage'] }),
    sendWa: b.mutation({ query: (body) => ({ url: 'admin/whatsapp/send', method: 'POST', body }), invalidatesTags: ['WaMessage'] }),
    sendLinkWa: b.mutation({ query: (orderId) => ({ url: `admin/payments/${orderId}/whatsapp/link`, method: 'POST' }), invalidatesTags: ['WaMessage'] }),
    sendInvoiceWa: b.mutation({ query: (orderId) => ({ url: `admin/payments/${orderId}/whatsapp/invoice`, method: 'POST' }), invalidatesTags: ['WaMessage'] }),
  }),
});
export const {
  useWaStatusQuery, useWaSettingsQuery, useSaveWaAccountMutation, useSaveWaTemplatesMutation, useTestWaMutation, useWaMessagesQuery,
  useRetryWaMutation, useSendWaMutation, useSendLinkWaMutation, useSendInvoiceWaMutation,
} = waApi;
