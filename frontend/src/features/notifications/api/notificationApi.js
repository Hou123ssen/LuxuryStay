import api from '../../../shared/api/api';

export const notificationService = {
  list: () => api.get('/notifications'),
  markAllAsRead: () => api.put('/notifications/read-all'),
  markAsRead: (id) => api.put(`/notifications/${id}/read`),
};
