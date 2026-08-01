import api from '../../../shared/api/api';

export const adminDashboardApi = {
  getAdminDashboardOverview: () => api.get('/admin/dashboard/overview'),
};
