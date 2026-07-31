import api from '../../../shared/api/api';

export const adminReportService = {
  list: (params = {}) => api.get('/admin/reports', { params }),
  get: (id) => api.get(`/admin/reports/${id}`),
  review: (id, data = {}) => api.put(`/admin/reports/${id}/review`, data),
  resolve: (id, data = {}) => api.put(`/admin/reports/${id}/resolve`, data),
  reject: (id, data = {}) => api.put(`/admin/reports/${id}/reject`, data),
};
