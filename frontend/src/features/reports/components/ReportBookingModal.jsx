import { useEffect, useState } from 'react';
import { FiAlertTriangle, FiX } from 'react-icons/fi';
import { reportService } from '../api/reportApi';
import { REPORT_CATEGORIES } from '../utils/reportOptions';

export default function ReportBookingModal({ booking, isOpen, onClose, onSubmitted }) {
  const [category, setCategory] = useState('host_issue');
  const [description, setDescription] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!isOpen) return;

    setCategory('host_issue');
    setDescription('');
    setError('');
  }, [isOpen, booking?.id]);

  if (!isOpen || !booking) return null;

  const property = booking.property || {};

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError('');

    try {
      await reportService.create({
        property_id: property.id,
        booking_id: booking.id,
        category,
        description: description.trim() || null,
      });
      onSubmitted?.();
      onClose();
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to submit report. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center px-4">
      <button
        type="button"
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
        onClick={() => {
          if (!loading) onClose();
        }}
        aria-label="Close report dialog"
      />

      <form
        onSubmit={handleSubmit}
        className="relative w-full max-w-md rounded-2xl border border-gold/15 bg-obsidian p-5 shadow-2xl"
      >
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-gold">
              <FiAlertTriangle size={16} />
              <h2 className="font-display text-xl text-cream">Report an issue</h2>
            </div>
            <p className="mt-1 text-sm text-cream/45">{property.title || 'This booking'}</p>
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
          Category
          <select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
            className="mt-2 w-full rounded-xl border border-gold/15 bg-midnight px-3 py-3 text-sm normal-case tracking-normal text-cream outline-none focus:border-gold/60"
          >
            {REPORT_CATEGORIES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="mt-4 block text-xs uppercase tracking-[0.22em] text-gold/60">
          Details
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            maxLength={2000}
            rows={4}
            className="mt-2 w-full resize-none rounded-xl border border-gold/15 bg-midnight px-3 py-3 text-sm normal-case tracking-normal text-cream outline-none placeholder:text-cream/25 focus:border-gold/60"
            placeholder="Share a brief description if helpful."
          />
        </label>

        {error && (
          <p className="mt-3 rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-2 text-sm text-red-200">
            {error}
          </p>
        )}

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
            className="btn-gold rounded-xl px-4 py-2 text-sm disabled:opacity-60"
          >
            {loading ? 'Submitting...' : 'Submit report'}
          </button>
        </div>
      </form>
    </div>
  );
}
