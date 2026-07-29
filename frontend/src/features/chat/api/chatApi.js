import api from '../../../shared/api/api';

export const chatService = {
  getConversations:   ()                => api.get('/conversations'),

  // Ã™Å Ã™â€šÃ˜Â¨Ã™â€ž property_id Ã˜Â£Ã™Ë† other_user_id
  createConversation: (data)            => api.post('/conversations', data),

  getMessages:        (conversationId)  => api.get(`/messages/${conversationId}`),
  sendMessage:        (data)            => api.post('/messages', data),
  markConversationAsRead: (conversationId) => api.post(`/conversations/${conversationId}/read`),
  getIncomingCall:   ()                => api.get('/call-sessions/incoming'),
  getCurrentCall:    ()                => api.get('/call-sessions/current'),
  getActiveCallSession: (conversationId) => api.get(`/conversations/${conversationId}/call-sessions/active`),
  createCallSession:  (conversationId)  => api.post(`/conversations/${conversationId}/call-sessions`),
  acceptCallSession:  (callSessionId)   => api.post(`/call-sessions/${callSessionId}/accept`),
  declineCallSession: (callSessionId)   => api.post(`/call-sessions/${callSessionId}/decline`),
  endCallSession:     (callSessionId)   => api.post(`/call-sessions/${callSessionId}/end`),
};
