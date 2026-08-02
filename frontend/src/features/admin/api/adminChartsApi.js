import api from '../../../shared/api/api';

export const adminChartsApi = {
  getAdminDashboardCharts: (params = {}) => api.get('/admin/dashboard/charts', { params }),
};
