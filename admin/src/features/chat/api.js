import { api, one } from '../../app/api';

const paged = (r) => ({ items: r.data?.data || [], meta: { pagination: r.data && { page: r.data.current_page, last_page: r.data.last_page, total: r.data.total } } });

export const chatApi = api.injectEndpoints({
  endpoints: (b) => ({
    adminRooms: b.query({ query: (p) => ({ url: 'admin/chat/rooms', params: p }), transformResponse: paged, providesTags: ['Chat'] }),
    myRooms: b.query({ query: () => 'app/chat/rooms', transformResponse: one, providesTags: ['Chat'] }),
    room: b.query({ query: (id) => `app/chat/rooms/${id}`, transformResponse: one, providesTags: (r, e, id) => [{ type: 'Chat', id }] }),
    messages: b.query({ query: ({ id, before_id }) => ({ url: `app/chat/rooms/${id}/messages`, params: before_id ? { before_id } : {} }), transformResponse: one }),
    members: b.query({ query: (id) => `app/chat/rooms/${id}/members`, transformResponse: one, providesTags: ['Chat'] }),
    requests: b.query({ query: (id) => `app/chat/rooms/${id}/requests`, transformResponse: one, providesTags: ['Chat'] }),
    createGroup: b.mutation({ query: (body) => ({ url: 'app/chat/groups', method: 'POST', body }), transformResponse: one, invalidatesTags: ['Chat'] }),
    updateGroup: b.mutation({ query: ({ id, ...body }) => ({ url: `app/chat/rooms/${id}`, method: 'PATCH', body }), invalidatesTags: ['Chat'] }),
    resetInvite: b.mutation({ query: (id) => ({ url: `app/chat/rooms/${id}/invite/reset`, method: 'POST' }), invalidatesTags: ['Chat'] }),
    addMembers: b.mutation({ query: ({ id, ...body }) => ({ url: `app/chat/rooms/${id}/members`, method: 'POST', body }), invalidatesTags: ['Chat'] }),
    removeMember: b.mutation({ query: ({ id, userId }) => ({ url: `app/chat/rooms/${id}/members/${userId}`, method: 'DELETE' }), invalidatesTags: ['Chat'] }),
    memberRole: b.mutation({ query: ({ id, userId, role }) => ({ url: `app/chat/rooms/${id}/members/${userId}/role`, method: 'POST', body: { role } }), invalidatesTags: ['Chat'] }),
    muteMember: b.mutation({ query: ({ id, userId, minutes }) => ({ url: `app/chat/rooms/${id}/members/${userId}/mute`, method: 'POST', body: { minutes } }), invalidatesTags: ['Chat'] }),
    handleRequest: b.mutation({ query: ({ id, approve }) => ({ url: `app/chat/requests/${id}`, method: 'POST', body: { approve } }), invalidatesTags: ['Chat'] }),
    deleteRoom: b.mutation({ query: (id) => ({ url: `admin/chat/rooms/${id}`, method: 'DELETE' }), invalidatesTags: ['Chat'] }),
    upload: b.mutation({ query: ({ id, file }) => { const fd = new FormData(); fd.append('file', file); return { url: `app/chat/rooms/${id}/upload`, method: 'POST', body: fd }; }, transformResponse: one }),
    reports: b.query({ query: () => 'admin/chat/reports', transformResponse: paged, providesTags: ['Chat'] }),
    handleReport: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/chat/reports/${id}`, method: 'POST', body }), invalidatesTags: ['Chat'] }),
    policies: b.query({ query: () => 'admin/chat/policies', transformResponse: one, providesTags: ['ChatPolicy'] }),
    savePolicies: b.mutation({ query: (rules) => ({ url: 'admin/chat/policies', method: 'PUT', body: { rules } }), invalidatesTags: ['ChatPolicy'] }),
  }),
});
export const {
  useAdminRoomsQuery, useMyRoomsQuery, useRoomQuery, useLazyMessagesQuery, useMembersQuery, useRequestsQuery, useCreateGroupMutation, useUpdateGroupMutation,
  useResetInviteMutation, useAddMembersMutation, useRemoveMemberMutation, useMemberRoleMutation, useMuteMemberMutation, useHandleRequestMutation, useDeleteRoomMutation,
  useUploadMutation, useReportsQuery, useHandleReportMutation, usePoliciesQuery, useSavePoliciesMutation,
} = chatApi;
