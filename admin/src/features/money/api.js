import { api, one, list, clean } from '../../app/api';

export const moneyApi = api.injectEndpoints({
  endpoints: (b) => ({
    payments: b.query({ query: (p) => ({ url: 'admin/payments', params: clean(p) }), transformResponse: list, providesTags: ['Order'] }),
    payment: b.query({ query: (id) => `admin/payments/${id}`, transformResponse: one, providesTags: ['Order'] }),
    refreshPayment: b.mutation({ query: (id) => ({ url: `admin/payments/${id}/refresh`, method: 'POST' }), invalidatesTags: ['Order'] }),
    refund: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/payments/${id}/refund`, method: 'POST', body }), invalidatesTags: ['Order', 'Enrollment', 'Revenue'] }),
    invoiceLinks: b.mutation({ query: (id) => ({ url: `admin/payments/${id}/invoice`, method: 'POST' }), transformResponse: one }),
    linkWhatsapp: b.query({ query: (id) => `admin/payments/${id}/whatsapp`, transformResponse: one }),

    coupons: b.query({ query: (p) => ({ url: 'admin/coupons', params: clean(p) }), transformResponse: list, providesTags: ['Coupon'] }),
    saveCoupon: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/coupons/${id}` : 'admin/coupons', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Coupon'] }),
    deleteCoupon: b.mutation({ query: (id) => ({ url: `admin/coupons/${id}`, method: 'DELETE' }), invalidatesTags: ['Coupon'] }),

    revenue: b.query({ query: () => 'admin/revenue-share', transformResponse: one, providesTags: ['Revenue'] }),
    configureRevenue: b.mutation({ query: (body) => ({ url: 'admin/revenue-share', method: 'PUT', body }), invalidatesTags: ['Revenue'] }),
    ledger: b.query({ query: (p) => ({ url: 'admin/revenue-share/ledger', params: clean(p) }), transformResponse: list, providesTags: ['Revenue'] }),
    payouts: b.query({ query: () => 'admin/revenue-share/payouts', transformResponse: one, providesTags: ['Revenue'] }),
    addPayout: b.mutation({ query: (body) => ({ url: 'admin/revenue-share/payouts', method: 'POST', body }), invalidatesTags: ['Revenue'] }),
  }),
});
export const {
  usePaymentsQuery, usePaymentQuery, useRefreshPaymentMutation, useRefundMutation, useInvoiceLinksMutation, useLazyLinkWhatsappQuery,
  useCouponsQuery, useSaveCouponMutation, useDeleteCouponMutation, useRevenueQuery, useConfigureRevenueMutation, useLedgerQuery, usePayoutsQuery, useAddPayoutMutation,
} = moneyApi;
