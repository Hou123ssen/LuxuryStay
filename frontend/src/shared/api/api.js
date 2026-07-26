import axios from 'axios';

const BASE_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';

// Derive the root domain for static assets (e.g., /storage/* images)
// by stripping the trailing '/api' path from the BASE_URL.
export const STORAGE_URL = import.meta.env.VITE_API_URL
  ? import.meta.env.VITE_API_URL.replace(/\/api$/, '')
  : 'http://127.0.0.1:8000';

const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
});

// Attach token on every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  
  // Fix for FormData uploads - let browser set multipart boundary
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type'];
  }
  
  return config;
});

// Handle 401
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

//  Bookings 
export const bookingService = {
  list:          ()     => api.get('/bookings'),
  create:        (data) => api.post('/bookings', data),
  accept:        (id)   => api.post(`/bookings/${id}/accept`),
  reject:        (id)   => api.post(`/bookings/${id}/reject`),
  ownerBookings: ()     => api.get('/owner/bookings'),
  cancel:        (id)   => api.delete(`/bookings/${id}`),
};

//  Favorites 
export const favoriteService = {
  list:   () => api.get('/favorites'),
  toggle: (propertyId) => api.post('/favorites/toggle', { property_id: propertyId }),
};

//  Chat 
export const chatService = {
  getConversations:   ()                => api.get('/conversations'),

  // ÙŠÙ‚Ø¨Ù„ property_id Ø£Ùˆ other_user_id
  createConversation: (data)            => api.post('/conversations', data),

  getMessages:        (conversationId)  => api.get(`/messages/${conversationId}`),
  sendMessage:        (data)            => api.post('/messages', data),
};

//  Notifications 
export const notificationService = {
  list: () => api.get('/notifications'),
};

export default api;
