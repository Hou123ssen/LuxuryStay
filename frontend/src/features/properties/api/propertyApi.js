import api from '../../../shared/api/api';

export const propertyService = {
  list:   (params)     => api.get('/properties', { params }),
  get:    (id)         => api.get(`/properties/${id}`),
  availability: (id)   => api.get(`/properties/${id}/availability`),
  create: (data)       => api.post('/properties', data),
  update: (id, data)   => api.put(`/properties/${id}`, data),
  delete: (id)         => api.delete(`/properties/${id}`),
};

export const reviewService = {
  create: (data) => api.post('/reviews', data),
};

export const imageService = {
  uploadMultiple: (files, propertyId) => {
  const fd = new FormData();

  files.forEach((file) => {
    fd.append('images[]', file); // â† Ø¨Ø¯ÙˆÙ† Ø£ÙŠ ØªØ¹Ø¯ÙŠÙ„
  });

  fd.append('property_id', propertyId);

  return api.post('/images', fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}
}
