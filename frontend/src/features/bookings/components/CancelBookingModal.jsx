import { useEffect, useRef, useState } from 'react';
import { FiAlertTriangle, FiX } from 'react-icons/fi';

export default function CancelBookingModal({
  booking,
  isOpen,
  loading = false,
  onClose,
  onConfirm,
}) {
  const [reason, setReason] = useState('');
  const confirmRef = useRef(null);

  useEffect(() => {
    if (!isOpen) return undefined;

    setReason('');
    confirmRef.current?.focus();

    const onKey = (event) => {
      if (event.key === 'Escape' && !loading) onClose?.();
    };

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen, loading, onClose]);

  if (!isOpen || !booking) return null;

  const propertyName = booking.property?.title || booking.property?.name || 'this stay';

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4"
      style={{ background: 'rgba(0,0,0,0.75)', backdropFilter: 'blur(8px)' }}
      onClick={(event) => {
        if (event.target === event.currentTarget && !loading) onClose?.();
      }}
    >
      <div
        className="relative w-full max-w-lg rounded-2xl p-6 sm:p-7 fade-up"
        style={{
          background: 'linear-gradient(135deg, #1c1c2e 0%, #141421 100%)',
          border: '1px solid rgba(201,168,76,0.22)',
          boxShadow: '0 30px 80px rgba(0,0,0,0.58), 0 0 0 1px rgba(201,168,76,0.08)',
        }}
      >
        <button
          type="button"
          onClick={onClose}
          disabled={loading}
          className="absolute top-4 right-4 p-1.5 rounded-lg text-cream/35 hover:text-cream hover:bg-white/5 transition-colors disabled:opacity-50"
          aria-label="Close cancellation dialog"
        >
          <FiX size={16} />
        </button>

        <div className="flex items-center gap-4 mb-5">
          <div
            className="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
            style={{ background: 'rgba(201,168,76,0.12)', border: '1px solid rgba(201,168,76,0.28)' }}
          >
            <FiAlertTriangle size={22} className="text-gold" />
          </div>
          <div>
            <h2 className="font-display text-2xl text-cream">Cancel booking</h2>
            <p className="text-xs text-cream/45 mt-1">{propertyName}</p>
          </div>
        </div>

        <p className="text-sm text-cream/55 leading-relaxed mb-5">
          Confirm that you want to cancel this booking. You can add a short reason for the other guest or owner.
        </p>

        <label className="block text-xs uppercase tracking-[0.2em] text-gold/65 mb-2" htmlFor="cancel-reason">
          Reason optional
        </label>
        <textarea
          id="cancel-reason"
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          maxLength={1000}
          rows={4}
          className="w-full rounded-xl bg-black/20 border border-gold/15 px-4 py-3 text-sm text-cream placeholder:text-cream/25 focus:outline-none focus:border-gold/45 resize-none"
          placeholder="Add a brief note..."
          disabled={loading}
        />
        <div className="mt-1 text-right text-[11px] text-cream/30">{reason.length}/1000</div>

        <div className="flex flex-col sm:flex-row gap-3 mt-6">
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="flex-1 py-3 rounded-xl border border-gold/20 text-cream/65 hover:text-cream hover:border-gold/40 transition-colors text-sm disabled:opacity-50"
          >
            Keep booking
          </button>
          <button
            type="button"
            ref={confirmRef}
            onClick={() => onConfirm?.(reason)}
            disabled={loading}
            className="flex-1 py-3 rounded-xl font-medium text-sm flex items-center justify-center gap-2 transition-all bg-red-500/15 border border-red-500/35 text-red-300 hover:bg-red-500/25 disabled:opacity-60"
          >
            {loading ? (
              <span className="w-4 h-4 border-2 border-red-200/30 border-t-red-200 rounded-full animate-spin" />
            ) : (
              'Cancel booking'
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
