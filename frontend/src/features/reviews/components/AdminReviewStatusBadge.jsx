import { reviewStatusLabel } from '../utils/reviewAdminOptions';

const STATUS_STYLES = {
  pending_review: 'border-amber-300/35 bg-amber-300/10 text-amber-100',
  published: 'border-emerald-300/30 bg-emerald-300/10 text-emerald-100',
  rejected: 'border-red-300/30 bg-red-300/10 text-red-100',
};

export default function AdminReviewStatusBadge({ value }) {
  return (
    <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${STATUS_STYLES[value] || 'border-cream/15 bg-white/5 text-cream/60'}`}>
      {reviewStatusLabel(value)}
    </span>
  );
}
