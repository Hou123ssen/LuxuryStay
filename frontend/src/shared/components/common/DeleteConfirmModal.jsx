/**
 * DeleteConfirmModal
 * ─────────────────────────────────────────────────────────────────────────────
 * Props
 *   isOpen    – boolean
 *   title     – modal heading
 *   message   – body text
 *   onConfirm – async fn called when user confirms
 *   onCancel  – fn called when user cancels / closes
 *   danger    – string label for the confirm button (default "Delete")
 */
import { useEffect, useRef } from 'react';
import { FiAlertTriangle, FiX } from 'react-icons/fi';

export default function DeleteConfirmModal({
  isOpen,
  title   = 'Delete Property',
  message = 'This action cannot be undone. The property and all its data will be permanently removed.',
  onConfirm,
  onCancel,
  danger  = 'Delete',
  loading = false,
}) {
  const confirmBtnRef = useRef(null);

  // Trap focus on open; close on Escape
  useEffect(() => {
    if (!isOpen) return;
    confirmBtnRef.current?.focus();
    const onKey = (e) => { if (e.key === 'Escape') onCancel?.(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen, onCancel]);

  if (!isOpen) return null;

  return (
    /* Backdrop */
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4"
      style={{ background: 'rgba(0,0,0,0.75)', backdropFilter: 'blur(6px)' }}
      onClick={(e) => { if (e.target === e.currentTarget) onCancel?.(); }}
    >
      {/* Panel */}
      <div
        className="relative w-full max-w-md rounded-2xl p-8 fade-up"
        style={{
          background: 'linear-gradient(135deg, #1c1c2e 0%, #16162a 100%)',
          border: '1px solid rgba(239,68,68,0.25)',
          boxShadow: '0 30px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(239,68,68,0.08)',
        }}
      >
        {/* Close */}
        <button
          onClick={onCancel}
          className="absolute top-4 right-4 p-1.5 rounded-lg text-cream/30 hover:text-cream hover:bg-white/5 transition-colors"
        >
          <FiX size={16} />
        </button>

        {/* Icon */}
        <div className="flex items-center gap-4 mb-5">
          <div
            className="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
            style={{ background: 'rgba(239,68,68,0.12)', border: '1px solid rgba(239,68,68,0.25)' }}
          >
            <FiAlertTriangle size={22} className="text-red-400" />
          </div>
          <h2 className="font-display text-2xl text-cream">{title}</h2>
        </div>

        <p className="text-cream/50 text-sm leading-relaxed mb-8">{message}</p>

        {/* Actions */}
        <div className="flex gap-3">
          <button
            type="button"
            onClick={onCancel}
            disabled={loading}
            className="flex-1 py-3 rounded-xl border border-gold/20 text-cream/60 hover:text-cream hover:border-gold/40 transition-colors text-sm"
          >
            Cancel
          </button>
          <button
            type="button"
            ref={confirmBtnRef}
            onClick={onConfirm}
            disabled={loading}
            className={`flex-1 py-3 rounded-xl font-medium text-sm flex items-center justify-center gap-2 transition-all ${
              loading ? 'opacity-60 cursor-not-allowed' : 'hover:opacity-90'
            }`}
            style={{ background: 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)', color: '#fff' }}
          >
            {loading ? (
              <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            ) : (
              danger
            )}
          </button>
        </div>
      </div>
    </div>
  );
}