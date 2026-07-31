import { useEffect, useState } from 'react';
import { FiX } from 'react-icons/fi';

const ACTION_COPY = {
  publish: {
    title: 'Publish review',
    body: 'Add an optional internal reason before publishing this verified-stay review.',
    button: 'Publish review',
  },
  reject: {
    title: 'Reject review',
    body: 'Add an optional internal reason before rejecting this review.',
    button: 'Reject review',
  },
};

export default function ModerateReviewModal({
  action,
  review,
  loading = false,
  onClose,
  onConfirm,
}) {
  const [reason, setReason] = useState('');
  const copy = ACTION_COPY[action];

  useEffect(() => {
    if (action) setReason('');
  }, [action, review?.id]);

  if (!copy || !review) return null;

  const handleSubmit = async (event) => {
    event.preventDefault();
    const updated = await onConfirm(action, review, {
      reason: reason.trim() || null,
    });
    if (updated) onClose();
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center px-4">
      <button
        type="button"
        className="absolute inset-0 bg-black/75 backdrop-blur-sm"
        onClick={() => {
          if (!loading) onClose();
        }}
        aria-label="Close moderation dialog"
      />

      <form
        onSubmit={handleSubmit}
        className="relative w-full max-w-md rounded-2xl border border-gold/15 bg-obsidian p-5 shadow-2xl"
      >
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="font-display text-2xl text-cream">{copy.title}</h2>
            <p className="mt-1 text-sm text-cream/45">{copy.body}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="rounded-full border border-cream/10 p-2 text-cream/45 hover:text-cream disabled:opacity-50"
            aria-label="Close"
          >
            <FiX size={16} />
          </button>
        </div>

        <label className="mt-5 block text-xs uppercase tracking-[0.22em] text-gold/60">
          Reason
          <textarea
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            maxLength={2000}
            rows={5}
            className="mt-2 w-full resize-none rounded-xl border border-gold/15 bg-[#101018] px-3 py-3 text-sm normal-case tracking-normal text-cream outline-none placeholder:text-cream/25 focus:border-gold/60"
            placeholder="Optional internal reason."
          />
        </label>

        <div className="mt-5 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="rounded-xl border border-cream/10 px-4 py-2 text-sm text-cream/60 hover:text-cream disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className="btn-gold rounded-xl px-4 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
          >
            {loading ? 'Saving...' : copy.button}
          </button>
        </div>
      </form>
    </div>
  );
}
