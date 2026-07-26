import api from '../../../shared/api/api';

export const bookingService = {
  list:          ()     => api.get('/bookings'),
  create:        (data) => api.post('/bookings', data),
  accept:        (id)   => api.post(`/bookings/${id}/accept`),
  reject:        (id)   => api.post(`/bookings/${id}/reject`),
  ownerBookings: ()     => api.get('/owner/bookings'),
  cancel:        (id)   => api.delete(`/bookings/${id}`),
};
