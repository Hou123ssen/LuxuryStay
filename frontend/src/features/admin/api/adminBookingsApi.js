import api from '../../../shared/api/api';

export const adminBookingsApi = {
  getAdminBookings: (params = {}) => api.get('/admin/bookings', { params }),
  getAdminBookingDetail: (bookingId, params = {}) => api.get(`/admin/bookings/${bookingId}`, { params }),
};
