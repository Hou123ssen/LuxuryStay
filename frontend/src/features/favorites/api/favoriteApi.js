import api from '../../../shared/api/api';

export const favoriteService = {
  list:   () => api.get('/favorites'),
  toggle: (propertyId) => api.post('/favorites/toggle', { property_id: propertyId }),
};
