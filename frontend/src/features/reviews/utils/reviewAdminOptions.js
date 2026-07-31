export const REVIEW_STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'pending_review', label: 'Pending review' },
  { value: 'published', label: 'Published' },
  { value: 'rejected', label: 'Rejected' },
];

export const REVIEW_RATING_OPTIONS = [
  { value: '', label: 'All ratings' },
  { value: '5', label: '5 stars' },
  { value: '4', label: '4 stars' },
  { value: '3', label: '3 stars' },
  { value: '2', label: '2 stars' },
  { value: '1', label: '1 star' },
];

export const REVIEW_RISK_LEVEL_OPTIONS = [
  { value: '', label: 'All risk levels' },
  { value: 'high', label: 'High risk' },
];

const STATUS_LABELS = {
  pending_review: 'Pending review',
  published: 'Published',
  rejected: 'Rejected',
};

const RISK_REASON_LABELS = {
  burst_pattern: 'Burst review pattern',
  shared_network_cluster: 'Shared network pattern',
  new_user_high_velocity: 'New account velocity',
  booking_pattern: 'Booking pattern signal',
};

export const reviewStatusLabel = (value) => STATUS_LABELS[value] || value || 'Unknown';

export const reviewRiskReasonLabel = (value) => (
  RISK_REASON_LABELS[value] || String(value || '').replaceAll('_', ' ') || 'Unknown signal'
);

export const isRejectedReview = (review) => review?.status === 'rejected';
export const canPublishReview = (review) => review?.status === 'pending_review';
export const canRejectReview = (review) => ['pending_review', 'published'].includes(review?.status);

export function formatReviewDate(value) {
  if (!value) return 'Not set';

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

export function cleanReviewError(error, fallback = 'Unable to update review.') {
  return error?.response?.data?.message || fallback;
}
