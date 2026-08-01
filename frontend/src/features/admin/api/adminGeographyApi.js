import api from '../../../shared/api/api';

export const adminGeographyApi = {
  getAdminGeographyAnalytics: (params = {}) => (
    api.get('/admin/dashboard/geography', { params })
  ),
};
