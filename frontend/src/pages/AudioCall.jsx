import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
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
import { chatService } from '../services/api';

export default function AudioCall() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const conversationId = searchParams.get('conversation_id');
  const [conversation, setConversation] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isMuted, setIsMuted] = useState(false);
  const [isSpeakerOn, setIsSpeakerOn] = useState(true);
  const [status] = useState('Calling...');

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

  const participant = conversation?.other_user;
  const participantName = participant?.name || 'Guest';
  const participantEmail = participant?.email || '';
  const propertyContext = useMemo(() => {
    if (!conversation?.property) return 'General conversation';

    return [conversation.property.title || conversation.property.name, conversation.property.city]
      .filter(Boolean)
      .join(' - ');
  }, [conversation]);

  const backToChat = () => {
    if (conversationId) {
      navigate(`/chat?conversation_id=${conversationId}`);
      return;
    }

    navigate('/chat');
  };

  const initial = participantName?.[0]?.toUpperCase() || '?';

  return (
    <div className="fixed inset-0 z-[300] h-[100dvh] overflow-hidden text-cream"
      style={{ background: 'radial-gradient(circle at top, rgba(201,168,76,0.16), transparent 32%), linear-gradient(145deg, #07070d 0%, #10101f 48%, #050508 100%)' }}>
      <div className="absolute inset-0 opacity-40"
        style={{ background: 'linear-gradient(90deg, transparent 0%, rgba(201,168,76,0.05) 50%, transparent 100%)' }} />

      <div className="relative z-10 flex h-[100dvh] flex-col px-5 py-4 sm:px-8 sm:py-6">
        <header className="flex shrink-0 items-center justify-between gap-3">
          <button
            type="button"
            onClick={backToChat}
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
              onClick={() => setIsMuted(value => !value)}
              className={`flex flex-col items-center gap-2 rounded-2xl border px-3 py-4 text-xs transition-all ${
                isMuted
                  ? 'border-gold/45 bg-gold/15 text-gold'
                  : 'border-white/10 bg-white/5 text-cream/75 hover:border-gold/35 hover:text-gold'
              }`}
            >
              {isMuted ? <FiMicOff size={24} /> : <FiMic size={24} />}
              {isMuted ? 'Unmute' : 'Mute'}
            </button>

            <button
              type="button"
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
