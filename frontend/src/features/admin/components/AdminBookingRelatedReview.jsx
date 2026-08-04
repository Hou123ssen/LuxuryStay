import { formatAdminBookingDateTime, bookingStatusLabel } from '../utils/adminBookingFormatters';

export default function AdminBookingRelatedReview({ review }) {
  if (!review) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No related review.</p>;
  }

  return (
    <div className="rounded-xl border border-white/5 bg-black/20 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-cream">Review #{review.id}</p>
          <p className="mt-1 text-xs text-cream/45">{bookingStatusLabel(review.status)}</p>
        </div>
        <span className="text-sm text-gold">Star {review.rating || 'N/A'}</span>
      </div>
      <p className="mt-2 text-xs text-cream/30">{formatAdminBookingDateTime(review.created_at)}</p>
    </div>
  );
}
