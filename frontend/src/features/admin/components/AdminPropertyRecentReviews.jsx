import { formatAdminPropertyDate, statusLabel } from '../utils/adminPropertyFormatters';

export default function AdminPropertyRecentReviews({ rows = [] }) {
  if (!rows.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No recent reviews.</p>;
  }

  return (
    <div className="space-y-2">
      {rows.map((review) => (
        <div key={review.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-start justify-between gap-3">
            <span className="text-sm font-medium text-cream">{review.reviewer_name || `User #${review.user_id}`}</span>
            <span className="text-sm text-gold">Star {review.rating || 'N/A'}</span>
          </div>
          <p className="mt-1 text-xs text-cream/45">{statusLabel(review.status)}</p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminPropertyDate(review.created_at)}</p>
        </div>
      ))}
    </div>
  );
}
