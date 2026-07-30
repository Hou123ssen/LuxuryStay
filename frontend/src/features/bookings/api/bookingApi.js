import api from '../../../shared/api/api';

export const bookingService = {
  list:          (params) => api.get('/bookings', { params }),
  create:        (data) => api.post('/bookings', data),
  accept:        (id)   => api.post(`/bookings/${id}/accept`),
  reject:        (id)   => api.post(`/bookings/${id}/reject`),
  ownerBookings: (params) => api.get('/owner/bookings', { params }),
  cancel:        (id)   => api.delete(`/bookings/${id}`),
};
