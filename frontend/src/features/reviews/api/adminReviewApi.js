import api from '../../../shared/api/api';

export const getAdminReviews = (params = {}) => api.get('/admin/reviews', { params });
export const getAdminReview = (id) => api.get(`/admin/reviews/${id}`);
export const publishReview = (id, data = {}) => api.put(`/admin/reviews/${id}/publish`, data);
export const rejectReview = (id, data = {}) => api.put(`/admin/reviews/${id}/reject`, data);

export const adminReviewService = {
  list: getAdminReviews,
  get: getAdminReview,
  publish: publishReview,
  reject: rejectReview,
};
