import { FiStar } from 'react-icons/fi';

export default function RatingDisplay({
  rating,
  average,
  count = 0,
  label = null,
  size = 'sm',
  showEmpty = true,
  ariaPrefix = 'Marketplace rating',
  className = '',
}) {
  const reviewCount = Number(count || 0);
  const displayRating = rating ?? average;
  const hasReviews = reviewCount > 0 && displayRating !== null && displayRating !== undefined;
  const textSize = size === 'lg' ? 'text-sm' : 'text-xs';
  const iconSize = size === 'lg' ? 14 : 11;

  if (!hasReviews && !showEmpty) return null;

  if (!hasReviews) {
    return (
      <span
        className={`inline-flex items-center gap-1 rounded-full border border-gold/15 bg-gold/5 px-2 py-1 ${textSize} text-cream/40 ${className}`}
        aria-label="No reviews yet"
      >
        <FiStar size={iconSize} className="text-gold/45" />
        {label || 'New'}
      </span>
    );
  }

  const roundedRating = Number(displayRating).toFixed(1);
  const reviewLabel = reviewCount === 1 ? 'review' : 'reviews';

  return (
    <span
      className={`inline-flex items-center gap-1 text-gold ${textSize} ${className}`}
      aria-label={`${ariaPrefix} ${roundedRating} out of 5 based on ${reviewCount} ${reviewLabel}`}
    >
      <FiStar size={iconSize} fill="currentColor" />
      <span>{roundedRating}</span>
      <span className="text-cream/30">({reviewCount})</span>
    </span>
  );
}
