import api from '../../../shared/api/api';

export const reportService = {
  create: (data) => api.post('/reports', data),
};
