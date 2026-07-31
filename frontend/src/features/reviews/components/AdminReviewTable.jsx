import { FiEye, FiStar } from 'react-icons/fi';
import AdminReviewRiskBadge from './AdminReviewRiskBadge';
import AdminReviewStatusBadge from './AdminReviewStatusBadge';
import { formatReviewDate } from '../utils/reviewAdminOptions';

function Stars({ rating }) {
  return (
    <span className="inline-flex items-center gap-1 text-gold">
      <FiStar size={14} />
      {Number(rating || 0).toFixed(1)}
    </span>
  );
}

export default function AdminReviewTable({ reviews, loading, onOpenReview }) {
  if (loading) {
    return (
      <div className="space-y-3">
        {[1, 2, 3].map((item) => (
          <div key={item} className="h-20 rounded-2xl shimmer" />
        ))}
      </div>
    );
  }

  if (reviews.length === 0) {
    return (
      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] px-6 py-16 text-center">
        <p className="font-display text-2xl text-cream/45">No reviews found</p>
        <p className="mt-2 text-sm text-cream/35">Try adjusting the filters.</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-gold/10 bg-white/[0.03]">
      <div className="hidden overflow-x-auto lg:block">
        <table className="w-full text-left">
          <thead className="border-b border-gold/10 bg-black/20 text-xs uppercase tracking-[0.18em] text-gold/55">
            <tr>
              <th className="px-4 py-3 font-medium">Review</th>
              <th className="px-4 py-3 font-medium">Property</th>
              <th className="px-4 py-3 font-medium">Status</th>
              <th className="px-4 py-3 font-medium">Risk</th>
              <th className="px-4 py-3 font-medium">Created</th>
              <th className="px-4 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gold/5">
            {reviews.map((review) => (
              <tr key={review.id} className="transition-colors hover:bg-gold/[0.04]">
                <td className="px-4 py-4">
                  <p className="text-sm font-medium text-cream"><Stars rating={review.rating} /></p>
                  <p className="mt-1 max-w-xs truncate text-xs text-cream/40">
                    #{review.id} by {review.user?.name || `User #${review.user_id}`}
                  </p>
                </td>
                <td className="px-4 py-4">
                  <p className="text-sm text-cream/80">{review.property?.title || 'Unknown property'}</p>
                  <p className="mt-1 text-xs text-cream/40">Property #{review.property_id}</p>
                </td>
                <td className="px-4 py-4">
                  <AdminReviewStatusBadge value={review.status} />
                </td>
                <td className="px-4 py-4">
                  <AdminReviewRiskBadge score={review.risk_score} />
                </td>
                <td className="px-4 py-4 text-sm text-cream/55">{formatReviewDate(review.created_at)}</td>
                <td className="px-4 py-4 text-right">
                  <button
                    type="button"
                    onClick={() => onOpenReview(review)}
                    className="inline-flex items-center gap-2 rounded-xl border border-gold/20 px-3 py-2 text-sm text-gold/80 transition-colors hover:border-gold/50 hover:text-gold"
                  >
                    <FiEye size={15} />
                    View
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="divide-y divide-gold/5 lg:hidden">
        {reviews.map((review) => (
          <button
            key={review.id}
            type="button"
            onClick={() => onOpenReview(review)}
            className="block w-full px-4 py-4 text-left transition-colors hover:bg-gold/[0.04]"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-cream"><Stars rating={review.rating} /></p>
                <p className="mt-1 text-xs text-cream/40">{review.property?.title || `Property #${review.property_id}`}</p>
              </div>
              <AdminReviewStatusBadge value={review.status} />
            </div>
            <div className="mt-3 flex items-center justify-between gap-3">
              <AdminReviewRiskBadge score={review.risk_score} />
              <span className="text-xs text-cream/35">{formatReviewDate(review.created_at)}</span>
            </div>
          </button>
        ))}
      </div>
    </div>
  );
}
