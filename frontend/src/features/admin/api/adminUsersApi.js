import api from '../../../shared/api/api';

export const adminUsersApi = {
  getAdminUsers: (params = {}) => api.get('/admin/users', { params }),
  getAdminUserDetail: (userId, params = {}) => api.get(`/admin/users/${userId}`, { params }),
};
