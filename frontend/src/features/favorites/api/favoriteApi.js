import api from '../../../shared/api/api';

export const favoriteService = {
  list:   (params) => api.get('/favorites', { params }),
  toggle: (propertyId) => api.post('/favorites/toggle', { property_id: propertyId }),
};
