import api from './api';

export const navbarCountsService = {
  getCounts: () => api.get('/navbar-counts'),
};
