import api from '../../../shared/api/api';

export const notificationService = {
  list: (params) => api.get('/notifications', { params }),
  markAllAsRead: () => api.put('/notifications/read-all'),
  markAsRead: (id) => api.put(`/notifications/${id}/read`),
};
