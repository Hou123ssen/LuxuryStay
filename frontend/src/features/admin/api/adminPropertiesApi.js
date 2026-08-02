import api from '../../../shared/api/api';

export const adminPropertiesApi = {
  getAdminProperties: (params = {}) => api.get('/admin/properties', { params }),
  getAdminPropertyDetail: (propertyId, params = {}) => api.get(`/admin/properties/${propertyId}`, { params }),
};
