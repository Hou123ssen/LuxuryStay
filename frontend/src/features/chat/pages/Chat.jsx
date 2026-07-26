import { useState, useEffect, useRef, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { chatService } from '../api/chatApi';
import { format } from 'date-fns';
import { FiSend, FiMessageCircle, FiSearch, FiChevronLeft, FiPhone } from 'react-icons/fi';
import { useAuth } from '../../../app/providers/AuthContext';
import toast from 'react-hot-toast';

export default function Chat() {
  const { user }         = useAuth();
  const [searchParams]   = useSearchParams();
  const navigate         = useNavigate();
  const [conversations,  setConversations]  = useState([]);
  const [activeConv,     setActiveConv]     = useState(null);
  const [messages,       setMessages]       = useState([]);
  const [newMsg,         setNewMsg]         = useState('');
  const [loading,        setLoading]        = useState(true);
  const [loadError,      setLoadError]      = useState('');
  const [sending,        setSending]        = useState(false);
  const [isStartingCall, setIsStartingCall] = useState(false);
  const [mobileView,     setMobileView]     = useState('list');
  const bottomRef = useRef(null);
  const pollRef   = useRef(null);

  const upsertConversation = (conversation) => {
    if (!conversation?.id) return;

    setConversations(prev => {
      const exists = prev.some(c => String(c.id) === String(conversation.id));

      if (!exists) return [conversation, ...prev];

      const updated = prev.map(c => String(c.id) === String(conversation.id) ? { ...c, ...conversation } : c);

      return [
        updated.find(c => String(c.id) === String(conversation.id)),
        ...updated.filter(c => String(c.id) !== String(conversation.id)),
      ];
    });
  };

  // ── تحميل المحادثات ────────────────────────────────────────────────────────
  useEffect(() => {
    (async () => {
      try {
        const res   = await chatService.getConversations();
        const convs = res.data?.data || res.data || [];
        setLoadError('');
        setConversations(convs);

        // فتح محادثة محددة من URL
        const convIdParam = searchParams.get('conversation_id');
        const propIdParam = searchParams.get('property_id');

        if (convIdParam) {
          const found = convs.find(c => String(c.id) === convIdParam);
          if (found) openConversation(found);
        } else if (propIdParam) {
          try {
            const cr           = await chatService.createConversation({ property_id: propIdParam });
            const conversation = cr.data?.data || cr.data;

            if (conversation?.id) {
              upsertConversation(conversation);
              openConversation(conversation);
              navigate(`/chat?conversation_id=${conversation.id}`, { replace: true });
            }
          } catch (err) {
            toast.error(err.response?.data?.message || 'Could not start conversation');
          }
        }
      } catch {
        setConversations([]);
        setLoadError('Could not load conversations.');
      }
      setLoading(false);
    })();
    return () => clearInterval(pollRef.current);
  }, []);

  // ── تحميل الرسائل ─────────────────────────────────────────────────────────
  const loadMessages = useCallback(async (convId) => {
    try {
      const res  = await chatService.getMessages(convId);
      const msgs = res.data?.data || res.data || [];
      setMessages(msgs);
    } catch {}
  }, []);

  const openConversation = (conv) => {
    setActiveConv(conv);
    setMobileView('chat');
    loadMessages(conv.id);
    clearInterval(pollRef.current);
    pollRef.current = setInterval(() => loadMessages(conv.id), 4000);
  };

  // ── scroll للأسفل عند رسالة جديدة ─────────────────────────────────────────
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // ── إرسال رسالة ───────────────────────────────────────────────────────────
  const sendMessage = async (e) => {
    e.preventDefault();
    if (!newMsg.trim() || !activeConv) return;
    setSending(true);
    const text = newMsg.trim();
    setNewMsg('');

    // Optimistic UI
    const optimistic = {
      id:              `opt_${Date.now()}`,
      conversation_id: activeConv.id,
      message:         text,        // ✅ message وليس body
      sender_id:       user?.id,
      sender:          user,
      created_at:      new Date().toISOString(),
      _optimistic:     true,
    };
    setMessages(p => [...p, optimistic]);

    try {
      await chatService.sendMessage({
        conversation_id: activeConv.id,
        body:            text,       // ✅ Laravel يتوقع body في الـ request
      });
      await loadMessages(activeConv.id);

      // تحديث آخر رسالة في قائمة المحادثات
      const sentAt = new Date().toISOString();
      const lastMessage = { message: text, created_at: sentAt, sender_id: user?.id };

      setConversations(prev => {
        const updated = prev.map(c =>
          String(c.id) === String(activeConv.id)
            ? { ...c, last_message: lastMessage, updated_at: sentAt }
            : c
        );

        return [
          updated.find(c => String(c.id) === String(activeConv.id)),
          ...updated.filter(c => String(c.id) !== String(activeConv.id)),
        ].filter(Boolean);
      });
      setActiveConv(prev => prev && String(prev.id) === String(activeConv.id)
        ? { ...prev, last_message: lastMessage, updated_at: sentAt }
        : prev
      );
    } catch (err) {
      setMessages(p => p.filter(m => m.id !== optimistic.id));
      toast.error(err.response?.data?.message || 'Failed to send message');
      setNewMsg(text);
    }
    setSending(false);
  };

  const startCall = async () => {
    if (!activeConv?.id || isStartingCall) return;

    setIsStartingCall(true);
    try {
      const res = await chatService.createCallSession(activeConv.id);
      const callSession = res.data?.data || res.data;

      if (!callSession?.id) throw new Error('Call session missing id.');

      navigate(`/call?conversation_id=${activeConv.id}&call_session_id=${callSession.id}`, {
        state: { callSession },
      });
    } catch (err) {
      toast.error(err.response?.data?.message || 'Unable to start call. Please try again.');
    } finally {
      setIsStartingCall(false);
    }
  };

  // ── helpers ────────────────────────────────────────────────────────────────
  const isOwn     = (msg) => String(msg.sender_id) === String(user?.id);
  const getAvatar = (name) => name?.[0]?.toUpperCase() || '?';

  // الشخص الآخر في المحادثة
  const getOtherUser = (conv) => {
    if (!conv) return null;
    if (conv.other_user) return conv.other_user;
    const isOne = String(conv.user_one_id) === String(user?.id);
    return isOne ? conv.user_two : conv.user_one;
  };

  const getPropertyLabel = (conv) => {
    if (!conv?.property_id || !conv?.property) return 'General conversation';

    return [conv.property.title || conv.property.name, conv.property.city]
      .filter(Boolean)
      .join(' - ');
  };

  // ── اسم المرسل ─────────────────────────────────────────────────────────────
  const getSenderName = (msg) => {
    if (isOwn(msg)) return 'You';
    // من الـ sender object
    if (msg.sender?.name) return msg.sender.name;
    // من المحادثة
    const other = getOtherUser(activeConv);
    return other?.name || 'Guest';
  };

  // ── avatar URL ─────────────────────────────────────────────────────────────
  const getAvatarUrl = (msg) => {
    const name = getSenderName(msg);
    return null; // يمكن إضافة صورة لاحقاً
  };

  return (
    <div className="h-[calc(100vh-4rem)] flex" style={{ background: 'var(--obsidian)' }}>

      {/* ══ Sidebar: قائمة المحادثات ══ */}
      <div className={`w-full sm:w-80 lg:w-96 flex-shrink-0 flex flex-col border-r ${
        mobileView === 'chat' ? 'hidden sm:flex' : 'flex'
      }`} style={{ borderColor: 'rgba(201,168,76,0.1)', background: '#0e0e1c' }}>

        {/* Header */}
        <div className="px-5 py-4 border-b" style={{ borderColor: 'rgba(201,168,76,0.1)' }}>
          <h2 className="font-display text-xl text-cream mb-3">Messages</h2>
          <div className="relative">
            <FiSearch size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-cream/30" />
            <input placeholder="Search conversations…"
              className="luxury-input w-full pl-8 pr-3 py-2 rounded-lg text-xs" />
          </div>
        </div>

        {/* قائمة المحادثات */}
        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="p-4 space-y-3">
              {[1,2,3].map(i => (
                <div key={i} className="flex gap-3 items-center">
                  <div className="w-10 h-10 rounded-full shimmer shrink-0" />
                  <div className="flex-1 space-y-2">
                    <div className="h-3 w-3/4 shimmer rounded" />
                    <div className="h-2.5 w-1/2 shimmer rounded" />
                  </div>
                </div>
              ))}
            </div>
          ) : loadError ? (
            <div className="text-center py-16 px-4">
              <FiMessageCircle size={32} className="mx-auto text-cream/15 mb-3" />
              <p className="text-cream/35 text-sm">{loadError}</p>
              <p className="text-cream/20 text-xs mt-1">Please try again in a moment.</p>
            </div>
          ) : conversations.length === 0 ? (
            <div className="text-center py-16 px-4">
              <FiMessageCircle size={32} className="mx-auto text-cream/15 mb-3" />
              <p className="text-cream/35 text-sm">No conversations yet.</p>
              <p className="text-cream/20 text-xs mt-1">Start by contacting a host from a property page.</p>
            </div>
          ) : (
            conversations.map(conv => {
              const other    = getOtherUser(conv);
              const isActive = activeConv?.id === conv.id;
              const lastMsg  = conv.last_message?.message || conv.last_message?.body || '';
              const propertyLabel = getPropertyLabel(conv);

              return (
                <button key={conv.id} onClick={() => openConversation(conv)}
                  className={`w-full flex items-center gap-3 px-4 py-3.5 text-left transition-colors border-b ${
                    isActive ? 'bg-gold/8 border-gold/10' : 'hover:bg-white/3 border-white/4'
                  }`}>
                  {/* Avatar */}
                  <div className="w-10 h-10 rounded-full shrink-0 flex items-center justify-center text-sm font-medium"
                    style={{ background: 'rgba(201,168,76,0.15)', color: 'var(--gold)' }}>
                    {getAvatar(other?.name)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex justify-between items-center mb-0.5">
                      <span className={`text-sm font-medium truncate ${isActive ? 'text-gold' : 'text-cream/80'}`}>
                        {other?.name || 'User'}
                      </span>
                      {conv.last_message?.created_at && (
                        <span className="text-[10px] text-cream/25 shrink-0 ml-2">
                          {format(new Date(conv.last_message.created_at), 'HH:mm')}
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-cream/35 truncate">
                      {propertyLabel || lastMsg || 'Start a conversation'}
                    </p>
                    {lastMsg && (
                      <p className="text-[10px] text-cream/25 truncate mt-0.5">{lastMsg}</p>
                    )}
                  </div>
                </button>
              );
            })
          )}
        </div>
      </div>

      {/* ══ Chat Panel ══ */}
      <div className={`flex-1 flex flex-col ${mobileView === 'list' ? 'hidden sm:flex' : 'flex'}`}>
        {activeConv ? (
          <>
            {/* Chat Header */}
            <div className="px-5 py-3.5 border-b flex items-center gap-3"
              style={{ borderColor: 'rgba(201,168,76,0.1)', background: '#0e0e1c' }}>
              <button onClick={() => setMobileView('list')} className="sm:hidden text-cream/50 mr-1">
                <FiChevronLeft size={20} />
              </button>
              <div className="w-9 h-9 rounded-full flex items-center justify-center text-sm"
                style={{ background: 'rgba(201,168,76,0.15)', color: 'var(--gold)' }}>
                {getAvatar(getOtherUser(activeConv)?.name)}
              </div>
              <div className="min-w-0 flex-1">
                <h3 className="text-sm font-medium text-cream">
                  {getOtherUser(activeConv)?.name || 'User'}
                </h3>
                <p className="text-xs text-cream/35">
                  {getOtherUser(activeConv)?.email || ''}
                </p>
                {getPropertyLabel(activeConv) && (
                  <p className="text-[10px] text-gold/60 truncate">
                    {getPropertyLabel(activeConv)}
                  </p>
                )}
              </div>
              <button
                type="button"
                onClick={startCall}
                disabled={isStartingCall}
                className="ml-auto flex items-center gap-1.5 rounded-full border border-gold/25 px-3 py-2 text-xs text-gold/80 transition-colors hover:border-gold hover:text-gold disabled:cursor-wait disabled:opacity-60"
                aria-label="Start audio call"
              >
                <FiPhone size={13} />
                <span className="hidden sm:inline">{isStartingCall ? 'Preparing...' : 'Call'}</span>
              </button>
            </div>

            {/* ══ الرسائل ══ */}
            <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4"
              style={{ background: 'var(--obsidian)' }}>
              {messages.length === 0 ? (
                <div className="text-center py-16 px-4">
                  <FiMessageCircle size={30} className="mx-auto text-cream/15 mb-3" />
                  <p className="text-cream/35 text-sm">No messages yet.</p>
                  <p className="text-cream/20 text-xs mt-1">Send the first message.</p>
                </div>
              ) : (
                messages.map((msg, i) => {
                  const own          = isOwn(msg);
                  const senderName   = getSenderName(msg);
                  // النص — يقبل message أو body
                  const text         = msg.message || msg.body || '';
                  const showSender   = !own && (i === 0 || messages[i-1]?.sender_id !== msg.sender_id);

                  return (
                    <div key={msg.id} className={`flex flex-col ${own ? 'items-end' : 'items-start'}`}>

                      {/* ── اسم المرسل فوق الرسالة ── */}
                      {showSender && !own && (
                        <div className="flex items-center gap-2 mb-1 ml-1">
                          <div className="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-medium"
                            style={{ background: 'rgba(201,168,76,0.2)', color: 'var(--gold)' }}>
                            {getAvatar(senderName)}
                          </div>
                          <span className="text-xs text-cream/45 font-medium">{senderName}</span>
                        </div>
                      )}

                      {/* ── فقاعة الرسالة ── */}
                      <div className={`max-w-xs sm:max-w-sm lg:max-w-md px-4 py-2.5 text-sm ${
                        own ? 'bubble-sent' : 'bubble-received'
                      } ${msg._optimistic ? 'opacity-60' : ''}`}>
                        {text}
                      </div>

                      {/* ── الوقت ── */}
                      <span className="text-[10px] text-cream/20 mt-1 mx-1">
                        {msg.created_at ? format(new Date(msg.created_at), 'HH:mm') : ''}
                        {own && msg._optimistic && ' · sending…'}
                      </span>

                    </div>
                  );
                })
              )}
              <div ref={bottomRef} />
            </div>

            {/* ══ Input ══ */}
            <form onSubmit={sendMessage}
              className="px-4 py-3 border-t flex gap-3 items-center"
              style={{ borderColor: 'rgba(201,168,76,0.1)', background: '#0e0e1c' }}>
              <input
                value={newMsg}
                onChange={e => setNewMsg(e.target.value)}
                placeholder="Type a message…"
                className="luxury-input flex-1 px-4 py-2.5 rounded-full text-sm"
              />
              <button type="submit" disabled={sending || !newMsg.trim()}
                className={`p-2.5 rounded-full transition-all ${
                  newMsg.trim() ? 'btn-gold' : 'border border-gold/20 text-cream/25 cursor-not-allowed'
                }`}>
                <FiSend size={16} />
              </button>
            </form>
          </>
        ) : (
          <div className="flex-1 flex flex-col items-center justify-center gap-3"
            style={{ background: 'var(--obsidian)' }}>
            <div className="w-16 h-16 rounded-full flex items-center justify-center"
              style={{ background: 'rgba(201,168,76,0.08)', border: '1px solid rgba(201,168,76,0.15)' }}>
              <FiMessageCircle size={28} className="text-gold/40" />
            </div>
            <p className="text-cream/35 text-sm">Select a conversation to start messaging.</p>
            <p className="text-cream/20 text-xs">Your property conversations will appear here.</p>
          </div>
        )}
      </div>
    </div>
  );
}
