import { api, one, list, clean } from '../../app/api';

export const studentsApi = api.injectEndpoints({
  endpoints: (b) => ({
    students: b.query({ query: (p) => ({ url: 'admin/students', params: clean(p) }), transformResponse: list, providesTags: ['Student'] }),
    studentSummary: b.query({ query: () => 'admin/students/summary', transformResponse: one, providesTags: ['Student'] }),
    student: b.query({ query: (id) => `admin/students/${id}`, transformResponse: one, providesTags: ['Student', 'Enrollment'] }),
    blockStudent: b.mutation({ query: ({ id, block }) => ({ url: `admin/students/${id}/${block ? 'block' : 'unblock'}`, method: 'POST' }), invalidatesTags: ['Student'] }),
    admit: b.mutation({ query: (body) => ({ url: 'admin/admissions', method: 'POST', body }), transformResponse: one, invalidatesTags: ['Student', 'Enrollment', 'Order', 'Batch'] }),
    swapLookup: b.query({ query: (phone) => ({ url: 'admin/swaps/lookup', params: { phone } }), transformResponse: one, providesTags: ['Swap', 'Enrollment'] }),
    swapQuote: b.query({ query: ({ enrollmentId, toBatchId }) => ({ url: `admin/enrollments/${enrollmentId}/swap-quote`, params: { to_batch_id: toBatchId } }), transformResponse: one }),
    doSwap: b.mutation({ query: ({ enrollmentId, ...body }) => ({ url: `admin/enrollments/${enrollmentId}/swap`, method: 'POST', body }), transformResponse: one, invalidatesTags: ['Swap', 'Enrollment', 'Student', 'Order', 'Batch'] }),
    swaps: b.query({ query: (p) => ({ url: 'admin/swaps', params: clean(p) }), transformResponse: list, providesTags: ['Swap'] }),
    cancelSwap: b.mutation({ query: (id) => ({ url: `admin/swaps/${id}/cancel`, method: 'POST' }), invalidatesTags: ['Swap', 'Order'] }),
    paymentLink: b.mutation({ query: (body) => ({ url: 'admin/payment-links', method: 'POST', body }), transformResponse: one, invalidatesTags: ['Order'] }),
  }),
});
export const { useLazySwapLookupQuery, useSwapQuoteQuery, useDoSwapMutation, useSwapsQuery, useCancelSwapMutation, useStudentsQuery, useStudentSummaryQuery, useStudentQuery, useBlockStudentMutation, useAdmitMutation, usePaymentLinkMutation } = studentsApi;
