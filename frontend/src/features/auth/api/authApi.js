import api from '../../../shared/api/api';

export const authService = {
  register: (data) => api.post('/register', data),
  login:    (data) => api.post('/login', data),
  me:       ()     => api.get('/user'),
  logout:   ()     => api.post('/logout'),
};
