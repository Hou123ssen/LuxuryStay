import api from '../../../shared/api/api';

export const chatService = {
  getConversations:   ()                => api.get('/conversations'),

  // Ã™Å Ã™â€šÃ˜Â¨Ã™â€ž property_id Ã˜Â£Ã™Ë† other_user_id
  createConversation: (data)            => api.post('/conversations', data),

  getMessages:        (conversationId)  => api.get(`/messages/${conversationId}`),
  sendMessage:        (data)            => api.post('/messages', data),
  createCallSession:  (conversationId)  => api.post(`/conversations/${conversationId}/call-sessions`),
  endCallSession:     (callSessionId)   => api.post(`/call-sessions/${callSessionId}/end`),
};
