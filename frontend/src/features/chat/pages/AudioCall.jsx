import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import {
  FiChevronLeft,
  FiMic,
  FiMicOff,
  FiPhoneOff,
  FiShield,
  FiVolume2,
  FiVolumeX,
  FiX,
} from 'react-icons/fi';
import { useAuth } from '../../../app/providers/AuthContext';
import { chatService } from '../api/chatApi';
import { useJitsiAudioCall } from '../hooks/useJitsiAudioCall';
import { useLibJitsiAudioCall } from '../hooks/useLibJitsiAudioCall';

export default function AudioCall() {
  const { user } = useAuth();
  const [searchParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const conversationId = searchParams.get('conversation_id');
  const callSessionId = searchParams.get('call_session_id');
  const isLibJitsiEngine = searchParams.get('audio_engine') === 'libjitsi';
  const audioTransport = searchParams.get('transport') === 'bosh' ? 'bosh' : 'websocket';
  const isDebugJitsi = searchParams.get('debug_jitsi') === '1';
  const isDebugAudio = searchParams.get('debug_audio') === '1';
  const providerContainerRef = useRef(null);
  const [conversation, setConversation] = useState(null);
  const [callSession, setCallSession] = useState(location.state?.callSession || null);
  const [loading, setLoading] = useState(true);
  const [isLoadingCallSession, setIsLoadingCallSession] = useState(!location.state?.callSession);
  const [isLeaving, setIsLeaving] = useState(false);
  const [callError, setCallError] = useState('');
  const [isMuted, setIsMuted] = useState(false);
  const [isSpeakerOn, setIsSpeakerOn] = useState(true);
  const [isPrejoinFallbackActive, setIsPrejoinFallbackActive] = useState(false);
  const handleProviderMutedChange = useCallback((muted) => setIsMuted(muted), []);

  const iframeAudio = useJitsiAudioCall({
    callSession: isLibJitsiEngine ? null : callSession,
    parentRef: providerContainerRef,
    userName: user?.name,
    debug: isDebugJitsi,
    onMutedChange: handleProviderMutedChange,
  });

  const libJitsiAudio = useLibJitsiAudioCall({
    callSession,
    userName: user?.name,
    enabled: isLibJitsiEngine,
    debug: isDebugAudio,
    transport: audioTransport,
  });

  const providerStatus = isLibJitsiEngine ? libJitsiAudio.providerStatus : iframeAudio.providerStatus;
  const providerError = isLibJitsiEngine ? libJitsiAudio.providerError : iframeAudio.providerError;
  const isScriptLoaded = iframeAudio.isScriptLoaded;
  const isApiCreated = iframeAudio.isApiCreated;
  const lastProviderEvent = iframeAudio.lastProviderEvent;
  const isProviderReady = isLibJitsiEngine ? libJitsiAudio.isProviderReady : iframeAudio.isProviderReady;
  const toggleAudio = isLibJitsiEngine ? libJitsiAudio.toggleAudio : iframeAudio.toggleAudio;
  const hangUpProvider = isLibJitsiEngine ? libJitsiAudio.hangUpProvider : iframeAudio.hangUpProvider;
  const effectiveMuted = isLibJitsiEngine ? libJitsiAudio.isMuted : isMuted;

  useEffect(() => {
    let active = true;

    (async () => {
      if (!conversationId) {
        setLoading(false);
        return;
      }

      try {
        const res = await chatService.getConversations();
        const conversations = res.data?.data || res.data || [];
        const found = conversations.find(c => String(c.id) === String(conversationId));

        if (active) setConversation(found || null);
      } catch {
        if (active) setConversation(null);
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => { active = false; };
  }, [conversationId]);

  useEffect(() => {
    let active = true;

    (async () => {
      if (callSession || !conversationId) {
        setIsLoadingCallSession(false);
        return;
      }

      setIsLoadingCallSession(true);
      setCallError('');

      try {
        const res = await chatService.createCallSession(conversationId);
        const session = res.data?.data || res.data;

        if (active) setCallSession(session);
      } catch {
        if (active) setCallError('Unable to start call. Please try again.');
      } finally {
        if (active) setIsLoadingCallSession(false);
      }
    })();

    return () => { active = false; };
  }, [callSession, conversationId, callSessionId]);

  const participant = conversation?.other_user;
  const participantName = participant?.name || 'Guest';
  const participantEmail = participant?.email || '';
  const propertyContext = useMemo(() => {
    if (!conversation?.property) return 'General conversation';

    return [conversation.property.title || conversation.property.name, conversation.property.city]
      .filter(Boolean)
      .join(' - ');
  }, [conversation]);

  const backToChat = async () => {
    if (isLeaving) return;

    setIsLeaving(true);
    try {
      hangUpProvider();

      if (callSession?.id && callSession.status !== 'ended') {
        await chatService.endCallSession(callSession.id);
      }
    } catch (err) {
      console.error('Failed to end call session:', err);
    }

    if (conversationId) {
      navigate(`/chat?conversation_id=${conversationId}`);
      return;
    }

    navigate('/chat');
  };

  const toggleMute = () => {
    if (!isProviderReady) return;
    toggleAudio();
  };

  useEffect(() => {
    const canShowFallback = !isLibJitsiEngine
      && !isDebugJitsi
      && !isProviderReady
      && !isLeaving
      && !callError
      && !providerError
      && (providerStatus === 'connecting' || providerStatus === 'waiting-for-microphone');

    if (!canShowFallback) {
      setIsPrejoinFallbackActive(false);
      return undefined;
    }

    if (providerStatus === 'waiting-for-microphone') {
      setIsPrejoinFallbackActive(true);
      return undefined;
    }

    const fallbackTimer = window.setTimeout(() => {
      setIsPrejoinFallbackActive(true);
    }, 2500);

      return () => window.clearTimeout(fallbackTimer);
  }, [callError, isDebugJitsi, isLeaving, isLibJitsiEngine, isProviderReady, providerError, providerStatus]);

  const status = callError || providerError
    || (isLeaving
      ? 'Leaving...'
      : isLoadingCallSession
        ? 'Loading call session'
        : providerStatus === 'loading-library'
          ? 'Loading audio engine'
        : providerStatus === 'loading-script'
          ? 'Loading script'
          : providerStatus === 'requesting-microphone'
            ? 'Waiting for microphone permission...'
          : providerStatus === 'waiting-for-microphone'
            ? 'Waiting for microphone permission...'
            : providerStatus === 'connecting'
              ? 'Connecting audio...'
              : providerStatus === 'joining'
                ? 'Joining secure call...'
              : providerStatus === 'error'
                ? 'Provider error'
              : callSession?.status === 'ended'
                ? 'Ended'
                : isProviderReady
                  ? 'Call ready'
                  : callSession
                    ? 'Connecting audio...'
                    : 'Calling...');

  const prejoinFallbackActive = isPrejoinFallbackActive;
  const showProviderIframe = !isLibJitsiEngine && (isDebugJitsi || prejoinFallbackActive);
  const initial = participantName?.[0]?.toUpperCase() || '?';

  return (
    <div className="fixed inset-0 z-[300] h-[100dvh] overflow-hidden text-cream"
      style={{ background: 'radial-gradient(circle at top, rgba(201,168,76,0.16), transparent 32%), linear-gradient(145deg, #07070d 0%, #10101f 48%, #050508 100%)' }}>
      <div className="absolute inset-0 opacity-40"
        style={{ background: 'linear-gradient(90deg, transparent 0%, rgba(201,168,76,0.05) 50%, transparent 100%)' }} />
      {!isLibJitsiEngine && (
        <div
          ref={providerContainerRef}
          aria-hidden={!showProviderIframe}
          className={showProviderIframe
            ? 'absolute bottom-4 left-4 z-[360] h-64 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gold/30 bg-black shadow-2xl'
            : 'pointer-events-none absolute bottom-0 left-0 h-40 w-40 overflow-hidden opacity-0'}
        />
      )}
      {!isLibJitsiEngine && isDebugJitsi && (
        <div className="absolute right-4 top-20 z-[370] max-w-xs rounded-xl border border-gold/25 bg-black/80 p-3 font-mono text-[11px] leading-5 text-cream/80 shadow-2xl">
          <div>providerStatus: {providerStatus}</div>
          <div>roomName: {callSession?.room_name || 'none'}</div>
          <div>callSession id: {callSession?.id || 'none'}</div>
          <div>script loaded: {isScriptLoaded ? 'yes' : 'no'}</div>
          <div>api created: {isApiCreated ? 'yes' : 'no'}</div>
          <div>last event: {lastProviderEvent || 'none'}</div>
          <div>last error: {providerError || callError || 'none'}</div>
        </div>
      )}
      {isLibJitsiEngine && isDebugAudio && (
        <div className="absolute right-4 top-20 z-[370] max-w-xs rounded-xl border border-gold/25 bg-black/80 p-3 font-mono text-[11px] leading-5 text-cream/80 shadow-2xl">
          <div>engine: {libJitsiAudio.diagnostics.engine}</div>
          <div>selected transport: {libJitsiAudio.diagnostics.selectedTransport}</div>
          <div>script loaded: {libJitsiAudio.diagnostics.scriptLoaded ? 'yes' : 'no'}</div>
          <div>connection status: {libJitsiAudio.diagnostics.connectionStatus}</div>
          <div>connection established: {libJitsiAudio.diagnostics.connectionEstablished ? 'yes' : 'no'}</div>
          <div>conference status: {libJitsiAudio.diagnostics.conferenceStatus}</div>
          <div>conference joining: {libJitsiAudio.diagnostics.conferenceJoinInProgress ? 'yes' : 'no'}</div>
          <div>room: {libJitsiAudio.diagnostics.roomName || 'none'}</div>
          <div>focusUserJid configured: {libJitsiAudio.diagnostics.focusUserJidConfigured ? 'yes' : 'no'}</div>
          <div>local audio track: {libJitsiAudio.diagnostics.localAudioTrackCreated ? 'yes' : 'no'}</div>
          <div>remote audio tracks: {libJitsiAudio.diagnostics.remoteAudioTrackCount}</div>
          <div>last event: {libJitsiAudio.diagnostics.lastEvent || 'none'}</div>
          <div>failure event: {libJitsiAudio.diagnostics.lastFailureEvent || 'none'}</div>
          <div>connection failed code: {libJitsiAudio.diagnostics.connectionFailedCode || 'none'}</div>
          <div>last error: {libJitsiAudio.diagnostics.lastSafeError || callError || 'none'}</div>
          <div>safe args: {JSON.stringify(libJitsiAudio.diagnostics.lastFailureArgs || [])}</div>
        </div>
      )}

      <div className="relative z-10 flex h-[100dvh] flex-col px-5 py-4 sm:px-8 sm:py-6">
        <header className="flex shrink-0 items-center justify-between gap-3">
            <button
              type="button"
              onClick={backToChat}
              disabled={isLeaving}
              className="flex h-11 w-11 items-center justify-center rounded-full border border-gold/20 bg-white/5 text-cream/70 transition-colors hover:border-gold/50 hover:text-gold"
              aria-label="Back to chat"
            >
            <FiChevronLeft size={20} />
          </button>

          <div className="text-center">
            <p className="font-display text-lg tracking-[0.28em] text-gold sm:text-xl">LUXURRSTAY</p>
            <p className="mt-1 text-[10px] uppercase tracking-[0.24em] text-cream/35">Private audio call</p>
          </div>

            <button
              type="button"
              onClick={backToChat}
              disabled={isLeaving}
              className="flex h-11 w-11 items-center justify-center rounded-full border border-gold/20 bg-white/5 text-cream/70 transition-colors hover:border-gold/50 hover:text-gold"
              aria-label="Close call"
            >
            <FiX size={19} />
          </button>
        </header>

        <section className="mx-auto mt-2 flex min-h-0 w-full flex-1 flex-col overflow-hidden border border-transparent bg-transparent sm:my-6 sm:max-w-[600px] sm:rounded-[2.25rem] sm:border-gold/15 sm:bg-[rgba(17,17,31,0.86)] sm:px-8 sm:ring-1 sm:ring-white/5 sm:shadow-[0_30px_100px_rgba(0,0,0,0.52),0_0_90px_rgba(201,168,76,0.10)] sm:backdrop-blur-2xl lg:max-w-[620px]">
          <main className="mx-auto flex min-h-0 w-full max-w-xl flex-1 flex-col items-center justify-center px-1 py-3 text-center sm:px-0 sm:py-7">
            <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-gold/20 bg-gold/10 px-3 py-1.5 text-xs text-gold/85 sm:mb-6">
              <FiShield size={13} />
              Secure LuxurrStay call
            </div>

            <div className="relative mb-5 flex h-44 w-44 items-center justify-center sm:mb-7 sm:h-60 sm:w-60 lg:h-64 lg:w-64">
              <div className="absolute inset-8 rounded-full border border-gold/20 animate-ping" />
              <div className="absolute inset-4 rounded-full border border-gold/15" />
              <div className="absolute inset-0 rounded-full bg-gold/5 blur-2xl" />
              <div className="relative flex h-28 w-28 items-center justify-center rounded-full border border-gold/30 bg-gold/15 font-display text-5xl text-gold shadow-2xl sm:h-40 sm:w-40 sm:text-6xl">
                {initial}
              </div>
            </div>

            <p className="mb-2 text-xs uppercase tracking-[0.24em] text-cream/35">{status}</p>
            {prejoinFallbackActive && (
              <p className="mb-3 max-w-xs text-xs text-gold/70">
                Please confirm microphone access to join the secure call.
              </p>
            )}
            <h1 className="font-display text-3xl text-cream sm:text-4xl lg:text-5xl">
              {loading ? 'Connecting' : participantName}
            </h1>
            {participantEmail && (
              <p className="mt-2 text-sm text-cream/35">{participantEmail}</p>
            )}
            <p className="mt-3 max-w-sm text-sm text-gold/70">{propertyContext}</p>

            <div className="mt-5 flex items-end justify-center gap-1.5 sm:mt-7">
              {[1, 2, 3, 4, 5].map((bar) => (
                <span
                  key={bar}
                  className="w-1.5 rounded-full bg-gold/50"
                  style={{
                    height: `${18 + (bar % 3) * 12}px`,
                    animation: `pulse ${0.9 + bar * 0.08}s ease-in-out infinite`,
                    animationDelay: `${bar * 0.08}s`,
                  }}
                />
              ))}
            </div>
          </main>

          <footer className="mx-auto grid w-full max-w-sm shrink-0 grid-cols-3 gap-3 pb-5 sm:max-w-md sm:gap-4 sm:pb-8"
            style={{ paddingBottom: 'max(1.25rem, env(safe-area-inset-bottom))' }}>
            <button
              type="button"
              onClick={toggleMute}
              disabled={!isProviderReady || isLeaving}
              className={`flex flex-col items-center gap-2 rounded-2xl border px-3 py-4 text-xs transition-all ${
                effectiveMuted
                  ? 'border-gold/45 bg-gold/15 text-gold'
                  : 'border-white/10 bg-white/5 text-cream/75 hover:border-gold/35 hover:text-gold'
              } disabled:cursor-wait disabled:opacity-60`}
            >
              {effectiveMuted ? <FiMicOff size={24} /> : <FiMic size={24} />}
              {effectiveMuted ? 'Unmute' : 'Mute'}
            </button>

            <button
              type="button"
              // Real audio output device selection is deferred until a dedicated speaker-device flow exists.
              onClick={() => setIsSpeakerOn(value => !value)}
              className={`flex flex-col items-center gap-2 rounded-2xl border px-3 py-4 text-xs transition-all ${
                isSpeakerOn
                  ? 'border-gold/45 bg-gold/15 text-gold'
                  : 'border-white/10 bg-white/5 text-cream/75 hover:border-gold/35 hover:text-gold'
              }`}
            >
              {isSpeakerOn ? <FiVolume2 size={24} /> : <FiVolumeX size={24} />}
              Speaker
            </button>

            <button
              type="button"
              onClick={backToChat}
              disabled={isLeaving}
              className="flex flex-col items-center gap-2 rounded-2xl border border-red-500/40 bg-red-500/15 px-3 py-4 text-xs text-red-300 transition-all hover:bg-red-500/25 hover:text-red-200"
            >
              <FiPhoneOff size={24} />
              Leave
            </button>
          </footer>
        </section>
      </div>
    </div>
  );
}
