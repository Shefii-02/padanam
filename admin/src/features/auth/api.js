import { api, one } from '../../app/api';

export const authApi = api.injectEndpoints({
  endpoints: (b) => ({
    login: b.mutation({ query: (body) => ({ url: 'auth/login', method: 'POST', body: { ...body, silent: undefined } }), transformResponse: one }),
    me: b.query({ query: () => 'auth/me', transformResponse: one, providesTags: ['Me'] }),
    logoutApi: b.mutation({ query: () => ({ url: 'auth/logout', method: 'POST' }) }),
  }),
});
export const { useLoginMutation, useMeQuery, useLogoutApiMutation } = authApi;
