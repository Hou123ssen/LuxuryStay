import { FiAward, FiStar } from 'react-icons/fi';
import { ratingSummaryLabel, trustSummaryLabel } from '../utils/adminPropertyFormatters';

export default function AdminPropertyRatingSummary({ rating = {} }) {
  const established = rating?.rating_state === 'established' && rating?.average_rating;
  const trustLabel = trustSummaryLabel(rating);

  return (
    <div className="space-y-2">
      <div className="inline-flex items-center gap-2 rounded-full border border-gold/15 bg-gold/5 px-3 py-1.5 text-sm text-cream">
        <FiStar className="text-gold" size={14} />
        <span>{established ? Number(rating.average_rating).toFixed(1) : ratingSummaryLabel(rating)}</span>
      </div>
      {rating?.trust_label && (
        <div className="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1 text-xs text-emerald-100">
          <FiAward size={13} />
          {trustLabel}
        </div>
      )}
      {!rating?.trust_label && (
        <p className="text-xs text-cream/35">{trustLabel}</p>
      )}
    </div>
  );
}
