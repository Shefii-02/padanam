import { api, one, list, clean } from '../../app/api';

export const adminApi = api.injectEndpoints({
  endpoints: (b) => ({
    roles: b.query({ query: () => 'admin/roles', transformResponse: one, providesTags: ['Role'] }),
    permissionCatalog: b.query({ query: () => 'admin/permissions', transformResponse: one }),
    saveRole: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/roles/${id}` : 'admin/roles', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Role'] }),
    togglePermission: b.mutation({ query: ({ id, ...body }) => ({ url: `admin/roles/${id}/toggle`, method: 'POST', body }), invalidatesTags: ['Role', 'Me'] }),
    deleteRole: b.mutation({ query: (id) => ({ url: `admin/roles/${id}`, method: 'DELETE' }), invalidatesTags: ['Role'] }),
    staff: b.query({ query: (p) => ({ url: 'admin/staff', params: clean(p) }), transformResponse: list, providesTags: ['Staff'] }),
    saveStaff: b.mutation({ query: ({ id, ...body }) => ({ url: id ? `admin/staff/${id}` : 'admin/staff', method: id ? 'PATCH' : 'POST', body }), invalidatesTags: ['Staff', 'Role'] }),
    blockStaff: b.mutation({ query: ({ id, block }) => ({ url: `admin/staff/${id}/${block ? 'block' : 'unblock'}`, method: 'POST' }), invalidatesTags: ['Staff'] }),
    settings: b.query({ query: () => 'admin/settings', transformResponse: one, providesTags: ['Settings'] }),
    saveSettings: b.mutation({ query: (settings) => ({ url: 'admin/settings', method: 'PUT', body: { settings } }), invalidatesTags: ['Settings'] }),
    versions: b.query({ query: () => 'admin/app-versions', transformResponse: one, providesTags: ['Version'] }),
    saveVersion: b.mutation({ query: ({ platform, ...body }) => ({ url: `admin/app-versions/${platform}`, method: 'PUT', body }), invalidatesTags: ['Version'] }),
  }),
});
export const {
  useRolesQuery, usePermissionCatalogQuery, useSaveRoleMutation, useTogglePermissionMutation, useDeleteRoleMutation, useStaffQuery, useSaveStaffMutation, useBlockStaffMutation,
  useSettingsQuery, useSaveSettingsMutation, useVersionsQuery, useSaveVersionMutation,
} = adminApi;
