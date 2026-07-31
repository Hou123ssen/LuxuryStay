import { FiStar } from 'react-icons/fi';

export default function RatingDisplay({
  rating,
  average,
  count = 0,
  label = null,
  ratingState = null,
  trustBadge = null,
  trustLabel = null,
  size = 'sm',
  showEmpty = true,
  variant = 'compact',
  className = '',
}) {
  const reviewCount = Number(count || 0);
  const displayRating = rating ?? average;
  const state = ratingState || (reviewCount === 0 ? 'new' : reviewCount < 5 ? 'forming' : 'established');
  const hasEstablishedRating = state === 'established' && displayRating !== null && displayRating !== undefined;
  const isLarge = size === 'lg';
  const textSize = isLarge ? 'text-sm' : 'text-xs';
  const ratingTextSize = isLarge ? 'text-lg' : textSize;
  const iconSize = isLarge ? 16 : 11;

  if (state === 'new' && !showEmpty) return null;

  if (state === 'new') {
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

  const stayLabel = reviewCount === 1 ? 'verified stay' : 'verified stays';
  const formingLabel = label || `Rating forming · ${reviewCount} ${stayLabel}`;

  if (state === 'forming') {
    if (variant === 'detail') {
      return (
        <span
          className={`inline-flex flex-col gap-0.5 ${className}`}
          aria-label={`Rating forming based on ${reviewCount} ${stayLabel}. Overall rating will appear after 5 verified stays.`}
        >
          <span className={`inline-flex items-center gap-1 text-gold ${textSize}`}>
            <FiStar size={iconSize} className="text-gold/55" />
            <span>{formingLabel}</span>
          </span>
          <span className="text-xs text-cream/40">
            Overall rating will appear after 5 verified stays.
          </span>
        </span>
      );
    }

    return (
      <span
        className={`inline-flex items-center gap-1 rounded-full border border-gold/15 bg-gold/5 px-2 py-1 ${textSize} text-gold/75 ${className}`}
        aria-label={`Rating forming based on ${reviewCount} ${stayLabel}`}
      >
        <FiStar size={iconSize} className="text-gold/55" />
        {formingLabel}
      </span>
    );
  }

  if (!hasEstablishedRating && !showEmpty) return null;
  if (!hasEstablishedRating) return null;

  const roundedRating = Number(displayRating).toFixed(1);
  const ariaLabel = `Guest rating ${roundedRating} out of 5 based on ${reviewCount} ${stayLabel}`;
  const badgeText = trustBadge ? trustLabel : null;
  const badgeClass = isLarge
    ? 'w-fit rounded-full border border-gold/25 bg-gold/8 px-2 py-0.5 text-[11px] text-gold/80'
    : 'ml-1 rounded-full border border-gold/20 bg-gold/8 px-1.5 py-0.5 text-[10px] text-gold/75';

  if (variant === 'detail') {
    return (
      <span
        className={`inline-flex flex-col gap-0.5 ${className}`}
        aria-label={badgeText ? `${ariaLabel}. ${badgeText} property.` : ariaLabel}
      >
        <span className={`inline-flex items-center gap-1 text-gold ${ratingTextSize}`}>
          <FiStar size={iconSize} fill="currentColor" />
          <span>{roundedRating}</span>
        </span>
        <span className="text-xs text-cream/40">
          Based on {reviewCount} {stayLabel}
        </span>
        {badgeText && <span className={badgeClass}>{badgeText}</span>}
      </span>
    );
  }

  return (
    <span
      className={`inline-flex items-center gap-1 text-gold ${textSize} ${className}`}
      aria-label={badgeText ? `${ariaLabel}. ${badgeText} property.` : ariaLabel}
    >
      <FiStar size={iconSize} fill="currentColor" />
      <span>{roundedRating}</span>
      <span className="text-cream/30">·</span>
      <span className="text-cream/45 whitespace-nowrap">
        {reviewCount} {stayLabel}
      </span>
      {badgeText && <span className={badgeClass}>{badgeText}</span>}
    </span>
  );
}
