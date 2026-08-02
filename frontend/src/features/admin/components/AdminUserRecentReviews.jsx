import { formatAdminUserDate, statusLabel } from '../utils/adminUserFormatters';

export default function AdminUserRecentReviews({ rows = [] }) {
  if (!rows.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No recent reviews.</p>;
  }

  return (
    <div className="space-y-2">
      {rows.map((review) => (
        <div key={review.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-medium text-cream">{review.property_title || `Property #${review.property_id}`}</span>
            <span className="text-sm text-gold">★ {review.rating || 'N/A'}</span>
          </div>
          <p className="mt-1 text-xs text-cream/45">{statusLabel(review.status)}</p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminUserDate(review.created_at)}</p>
        </div>
      ))}
    </div>
  );
}
