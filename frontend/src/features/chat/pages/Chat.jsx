import { useState, useEffect, useRef, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { chatService } from '../api/chatApi';
import { format } from 'date-fns';
import { FiBell, FiSend, FiMessageCircle, FiSearch, FiChevronLeft, FiPhone, FiPhoneOff } from 'react-icons/fi';
import { useAuth } from '../../../app/providers/AuthContext';
import toast from 'react-hot-toast';
import Pagination from '../../../shared/components/common/Pagination';
import { useConversationsPagination } from '../hooks/useConversationsPagination';
import {
  enableIncomingCallAlerts,
  startIncomingCallAlert,
  stopIncomingCallAlert,
} from '../utils/callAlerts';
import { notifyNavbarCountsChanged } from '../../../shared/utils/navbarCountsEvents';

const CALL_PARTICIPANT_BUSY_MESSAGE = 'This user is currently busy on another call. Please try again later.';

const getCallErrorMessage = (err, fallback) => {
  if (err.response?.data?.code === 'CALL_PARTICIPANT_BUSY') {
    return CALL_PARTICIPANT_BUSY_MESSAGE;
  }

  return err.response?.data?.message || fallback;
};

export default function Chat() {
  const { user }         = useAuth();
  const [searchParams]   = useSearchParams();
  const navigate         = useNavigate();
  const {
    conversations,
    setConversations,
    meta: conversationsMeta,
    loading,
    loadError,
    page: conversationsPage,
    goToPage: goToConversationsPage,
    upsertConversation,
  } = useConversationsPagination();
  const [activeConv,     setActiveConv]     = useState(null);
  const [messages,       setMessages]       = useState([]);
  const [newMsg,         setNewMsg]         = useState('');
  const [sending,        setSending]        = useState(false);
  const [isStartingCall, setIsStartingCall] = useState(false);
  const [activeCallSession, setActiveCallSession] = useState(null);
  const [incomingCallSession, setIncomingCallSession] = useState(null);
  const [isHandlingCall, setIsHandlingCall] = useState(false);
  const [callAlertsEnabled, setCallAlertsEnabled] = useState(
    () => localStorage.getItem('luxurrstay_call_alerts_enabled') === 'true'
  );
  const [callAlertBlocked, setCallAlertBlocked] = useState(false);
  const [mobileView,     setMobileView]     = useState('list');
  const bottomRef = useRef(null);
  const pollRef   = useRef(null);
  const activeCallPollRef = useRef(null);
  const incomingCallPollRef = useRef(null);
  const initialRouteHandledRef = useRef(false);

  // ── تحميل المحادثات ────────────────────────────────────────────────────────
  useEffect(() => {
    return () => clearInterval(pollRef.current);
  }, []);

  useEffect(() => {
    if (loading || initialRouteHandledRef.current) return;

    initialRouteHandledRef.current = true;
    const convIdParam = searchParams.get('conversation_id');
    const propIdParam = searchParams.get('property_id');

    if (convIdParam) {
      const found = conversations.find(c => String(c.id) === convIdParam);
      if (found) openConversation(found);
      return;
    }

    if (!propIdParam) return;

    (async () => {
      try {
        const cr = await chatService.createConversation({ property_id: propIdParam });
        const conversation = cr.data?.data || cr.data;

        if (conversation?.id) {
          upsertConversation(conversation);
          openConversation(conversation);
          navigate(`/chat?conversation_id=${conversation.id}`, { replace: true });
        }
      } catch (err) {
        toast.error(err.response?.data?.message || 'Could not start conversation');
      }
    })();
  }, [conversations, loading, navigate, searchParams, upsertConversation]);

  // ── تحميل الرسائل ─────────────────────────────────────────────────────────
  const markConversationAsRead = useCallback(async (convId) => {
    const hadUnread = conversations.some(c =>
      String(c.id) === String(convId) && Number(c.unread_message_count || 0) > 0
    ) || (
      String(activeConv?.id) === String(convId)
      && Number(activeConv?.unread_message_count || 0) > 0
    );

    await chatService.markConversationAsRead(convId);

    setConversations(prev => prev.map(c =>
      String(c.id) === String(convId)
        ? { ...c, unread_message_count: 0 }
        : c
    ));
    setActiveConv(prev => prev && String(prev.id) === String(convId)
      ? { ...prev, unread_message_count: 0 }
      : prev
    );

    if (hadUnread) {
      notifyNavbarCountsChanged();
    }
  }, [activeConv?.id, activeConv?.unread_message_count, conversations]);

  const loadMessages = useCallback(async (convId, markAsRead = false) => {
    try {
      const res  = await chatService.getMessages(convId);
      const msgs = res.data?.data || res.data || [];
      setMessages(msgs);
      if (markAsRead) await markConversationAsRead(convId);
    } catch {}
  }, [markConversationAsRead]);

  const loadActiveCallSession = useCallback(async (convId) => {
    try {
      const res = await chatService.getActiveCallSession(convId);
      const callSession = res.data?.data || null;
      if (callSession) {
        setActiveCallSession(callSession);
        return;
      }

      setActiveCallSession(prev => {
        if (!prev || String(prev.conversation_id) !== String(convId)) return null;

        if (prev.status === 'ringing' && String(prev.started_by_id) === String(user?.id)) {
          chatService.getCurrentCall()
            .then((currentRes) => {
              const currentCall = currentRes.data?.data || null;
              if (String(currentCall?.id) === String(prev.id)) {
                const label = currentCall.status === 'missed'
                  ? 'Call missed.'
                  : currentCall.status === 'declined'
                    ? 'Call declined.'
                    : 'Call ended.';
                toast(label);
              } else {
                toast('Call ended.');
              }
            })
            .catch(() => toast('Unable to refresh call status.'));
        }

        return null;
      });
    } catch {
      setActiveCallSession(null);
    }
  }, [user?.id]);

  const loadIncomingCall = useCallback(async () => {
    if (!user?.id) {
      setIncomingCallSession(null);
      return;
    }

    try {
      const res = await chatService.getIncomingCall();
      const callSession = res.data?.data || null;
      setIncomingCallSession(
        callSession
          && callSession.status === 'ringing'
          && String(callSession.started_by_id) !== String(user.id)
          ? callSession
          : null
      );
    } catch {
      setIncomingCallSession(null);
    }
  }, [user?.id]);

  const openConversation = (conv) => {
    setActiveConv(conv);
    setActiveCallSession(null);
    setMobileView('chat');
    loadMessages(conv.id, true);
    clearInterval(pollRef.current);
    pollRef.current = setInterval(() => loadMessages(conv.id, true), 4000);
  };

  useEffect(() => {
    clearInterval(activeCallPollRef.current);

    if (!activeConv?.id) {
      setActiveCallSession(null);
      return undefined;
    }

    loadActiveCallSession(activeConv.id);
    activeCallPollRef.current = setInterval(() => loadActiveCallSession(activeConv.id), 8000);

    return () => clearInterval(activeCallPollRef.current);
  }, [activeConv?.id, loadActiveCallSession]);

  useEffect(() => {
    clearInterval(incomingCallPollRef.current);

    if (!user?.id) {
      setIncomingCallSession(null);
      return undefined;
    }

    loadIncomingCall();
    incomingCallPollRef.current = setInterval(loadIncomingCall, 4000);

    return () => {
      clearInterval(incomingCallPollRef.current);
      stopIncomingCallAlert();
    };
  }, [loadIncomingCall, user?.id]);

  useEffect(() => {
    let active = true;

    if (!incomingCallSession?.id) {
      stopIncomingCallAlert();
      setCallAlertBlocked(false);
      return undefined;
    }

    startIncomingCallAlert().then((result) => {
      if (!active) return;
      setCallAlertBlocked(!!result?.soundBlocked || !result?.soundEnabled);
    });

    return () => {
      active = false;
      stopIncomingCallAlert();
    };
  }, [incomingCallSession?.id]);

  useEffect(() => {
    if (
      activeCallSession?.status === 'accepted'
      && String(activeCallSession.started_by_id) === String(user?.id)
      && activeCallSession.conversation_id
    ) {
      navigate(`/call?conversation_id=${activeCallSession.conversation_id}&call_session_id=${activeCallSession.id}`, {
        state: { callSession: activeCallSession },
      });
    }
  }, [activeCallSession, navigate, user?.id]);

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

      setActiveCallSession(callSession);

      if (callSession.status === 'accepted') {
        navigate(`/call?conversation_id=${activeConv.id}&call_session_id=${callSession.id}`, {
          state: { callSession },
        });
      }
    } catch (err) {
      toast.error(getCallErrorMessage(err, 'Unable to start call. Please try again.'));
    } finally {
      setIsStartingCall(false);
    }
  };

  const enableCallAlerts = async () => {
    try {
      const enabled = await enableIncomingCallAlerts();
      setCallAlertsEnabled(enabled);
      setCallAlertBlocked(!enabled);
      toast.success(enabled ? 'Call alerts enabled' : 'Call alerts are not supported in this browser');
    } catch {
      setCallAlertBlocked(true);
      toast.error('Could not enable call alerts in this browser');
    }
  };

  const acceptIncomingCall = async () => {
    if (!incomingCallSession?.id || isHandlingCall) return;

    setIsHandlingCall(true);
    try {
      const res = await chatService.acceptCallSession(incomingCallSession.id);
      const callSession = res.data?.data || res.data;
      stopIncomingCallAlert();
      setIncomingCallSession(null);
      setActiveCallSession(callSession);
      navigate(`/call?conversation_id=${callSession.conversation_id}&call_session_id=${callSession.id}`, {
        state: { callSession },
      });
    } catch (err) {
      toast.error(getCallErrorMessage(err, 'Unable to accept call.'));
    } finally {
      setIsHandlingCall(false);
    }
  };

  const declineIncomingCall = async () => {
    if (!incomingCallSession?.id || isHandlingCall) return;

    setIsHandlingCall(true);
    try {
      await chatService.declineCallSession(incomingCallSession.id);
      stopIncomingCallAlert();
      setIncomingCallSession(null);
      setActiveCallSession(null);
    } catch (err) {
      toast.error(getCallErrorMessage(err, 'Unable to decline call.'));
    } finally {
      setIsHandlingCall(false);
    }
  };

  const cancelOutgoingCall = async () => {
    if (!activeCallSession?.id || isHandlingCall) return;

    setIsHandlingCall(true);
    try {
      await chatService.endCallSession(activeCallSession.id);
      setActiveCallSession(null);
    } catch (err) {
      toast.error(getCallErrorMessage(err, 'Unable to end call.'));
    } finally {
      setIsHandlingCall(false);
    }
  };

  // ── helpers ────────────────────────────────────────────────────────────────
  const joinActiveCall = () => {
    if (!activeConv?.id || !activeCallSession?.id) return;

    navigate(`/call?conversation_id=${activeConv.id}&call_session_id=${activeCallSession.id}`, {
      state: { callSession: activeCallSession },
    });
  };

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

  const getCallPropertyLabel = (callSession) => {
    const property = callSession?.conversation?.property;
    if (!property) return getPropertyLabel(activeConv);

    return [property.title || property.name, property.city]
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
          <div className="mb-3 flex items-center justify-between gap-3">
            <h2 className="font-display text-xl text-cream">Messages</h2>
            <button
              type="button"
              onClick={enableCallAlerts}
              className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[10px] transition-colors ${
                callAlertsEnabled
                  ? 'border-gold/35 bg-gold/10 text-gold'
                  : 'border-white/10 bg-white/5 text-cream/45 hover:border-gold/35 hover:text-gold'
              }`}
            >
              <FiBell size={12} />
              <span className="hidden lg:inline">{callAlertsEnabled ? 'Alerts on' : 'Enable call alerts'}</span>
              <span className="lg:hidden">Alerts</span>
            </button>
          </div>
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
            <>
              {conversations.map(conv => {
                const other    = getOtherUser(conv);
                const isActive = activeConv?.id === conv.id;
                const lastMsg  = conv.last_message?.message || conv.last_message?.body || '';
                const propertyLabel = getPropertyLabel(conv);
                const unreadMessageCount = Number(conv.unread_message_count || 0);

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
                        <span className="ml-2 flex shrink-0 items-center gap-1.5">
                          {unreadMessageCount > 0 && (
                            <span className="min-w-5 rounded-full border border-gold/50 bg-gold px-1.5 py-0.5 text-center text-[10px] font-semibold leading-none text-[#0e0e1c] shadow-[0_0_18px_rgba(201,168,76,0.18)]">
                              {unreadMessageCount}
                            </span>
                          )}
                          {conv.last_message?.created_at && (
                            <span className="text-[10px] text-cream/25">
                              {format(new Date(conv.last_message.created_at), 'HH:mm')}
                            </span>
                          )}
                        </span>
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
              })}
              <div className="px-3 pb-4">
                <Pagination
                  meta={conversationsMeta}
                  currentPage={conversationsPage}
                  onPageChange={goToConversationsPage}
                  className="mt-4"
                />
              </div>
            </>
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
            {activeCallSession && !incomingCallSession && (
              <div
                className="border-b px-4 py-3 sm:px-5"
                style={{
                  borderColor: 'rgba(201,168,76,0.14)',
                  background: 'linear-gradient(135deg, rgba(201,168,76,0.16), rgba(255,255,255,0.035))',
                }}
              >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex min-w-0 items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gold/30 bg-gold/15 text-gold">
                      <FiPhone size={16} />
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-cream">Active audio call</p>
                      <p className="text-xs text-cream/45">
                        {activeCallSession.status === 'ringing'
                          ? 'Calling... waiting for an answer.'
                          : String(activeCallSession.started_by_id) === String(user?.id)
                            ? 'Your secure call was accepted.'
                            : 'The other participant started a secure audio call.'}
                      </p>
                    </div>
                  </div>
                  {activeCallSession.status === 'ringing' && String(activeCallSession.started_by_id) === String(user?.id) ? (
                    <button
                      type="button"
                      onClick={cancelOutgoingCall}
                      disabled={isHandlingCall}
                      className="inline-flex items-center justify-center gap-1.5 rounded-full border border-red-500/35 bg-red-500/15 px-4 py-2 text-xs font-medium text-red-200 transition-colors hover:bg-red-500/25 disabled:cursor-wait disabled:opacity-60"
                    >
                      <FiPhoneOff size={13} />
                      Cancel
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={joinActiveCall}
                      className="inline-flex items-center justify-center gap-1.5 rounded-full border border-gold/35 bg-gold/15 px-4 py-2 text-xs font-medium text-gold transition-colors hover:border-gold hover:bg-gold/20"
                    >
                      <FiPhone size={13} />
                      Join call
                    </button>
                  )}
                </div>
              </div>
            )}

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

      {incomingCallSession && (
        <div className="fixed inset-0 z-[420] flex items-center justify-center bg-black/70 px-5 text-cream backdrop-blur-md">
          <div className="relative w-full max-w-sm overflow-hidden rounded-[2rem] border border-gold/25 bg-[#0e0e1c]/95 px-6 py-7 text-center shadow-[0_30px_120px_rgba(0,0,0,0.6),0_0_80px_rgba(201,168,76,0.14)]">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(201,168,76,0.18),transparent_42%)]" />
            <div className="relative">
              <p className="mb-2 text-[10px] uppercase tracking-[0.28em] text-gold/70">Incoming audio call</p>
              <div className="relative mx-auto my-6 flex h-36 w-36 items-center justify-center">
                <div className="absolute inset-0 rounded-full border border-gold/20 animate-ping" />
                <div className="absolute inset-5 rounded-full border border-gold/15 animate-pulse" />
                <div className="absolute inset-2 rounded-full bg-gold/5 blur-2xl" />
                <div className="relative flex h-24 w-24 items-center justify-center rounded-full border border-gold/35 bg-gold/15 font-display text-4xl text-gold">
                  {getAvatar(incomingCallSession.started_by?.name)}
                </div>
              </div>
              <h3 className="font-display text-3xl text-cream">
                {incomingCallSession.started_by?.name || 'Guest'}
              </h3>
              <p className="mt-2 text-sm text-gold/65">{getCallPropertyLabel(incomingCallSession)}</p>
              {callAlertBlocked && (
                <button
                  type="button"
                  onClick={enableCallAlerts}
                  className="mt-4 inline-flex items-center gap-1.5 rounded-full border border-gold/30 bg-gold/10 px-3 py-1.5 text-xs text-gold transition-colors hover:border-gold"
                >
                  <FiBell size={12} />
                  Tap Enable call alerts for sound
                </button>
              )}
              <div className="mt-7 grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={declineIncomingCall}
                  disabled={isHandlingCall}
                  className="flex items-center justify-center gap-2 rounded-2xl border border-red-500/35 bg-red-500/15 px-4 py-3 text-sm font-medium text-red-200 transition-colors hover:bg-red-500/25 disabled:cursor-wait disabled:opacity-60"
                >
                  <FiPhoneOff size={18} />
                  Decline
                </button>
                <button
                  type="button"
                  onClick={acceptIncomingCall}
                  disabled={isHandlingCall}
                  className="flex items-center justify-center gap-2 rounded-2xl border border-gold/45 bg-gold px-4 py-3 text-sm font-semibold text-[#0e0e1c] transition-colors hover:bg-gold/90 disabled:cursor-wait disabled:opacity-60"
                >
                  <FiPhone size={18} />
                  Accept
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
