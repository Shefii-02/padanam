import { api, list, clean } from '../../app/api';

export const securityApi = api.injectEndpoints({
  endpoints: (b) => ({
    otpLogs: b.query({ query: (p) => ({ url: 'admin/security/otp-logs', params: clean(p) }), transformResponse: list, providesTags: ['Security'] }),
    logins: b.query({ query: (p) => ({ url: 'admin/security/logins', params: clean(p) }), transformResponse: list, providesTags: ['Security'] }),
    audit: b.query({ query: (p) => ({ url: 'admin/security/audit', params: clean(p) }), transformResponse: list, providesTags: ['Security'] }),
  }),
});
export const { useOtpLogsQuery, useLoginsQuery, useAuditQuery } = securityApi;
