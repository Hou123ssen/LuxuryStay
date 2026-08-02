import api from '../../../shared/api/api';

export const adminDashboardApi = {
  getAdminDashboardOverview: (params = {}) => api.get('/admin/dashboard/overview', { params }),
};
