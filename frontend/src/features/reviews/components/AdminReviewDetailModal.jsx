import { useState } from 'react';
import { FiCheckCircle, FiStar, FiX, FiXCircle } from 'react-icons/fi';
import AdminReviewRiskBadge from './AdminReviewRiskBadge';
import AdminReviewStatusBadge from './AdminReviewStatusBadge';
import ModerateReviewModal from './ModerateReviewModal';
import {
  canPublishReview,
  canRejectReview,
  formatReviewDate,
  reviewRiskReasonLabel,
} from '../utils/reviewAdminOptions';

function DetailRow({ label, children }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-[0.2em] text-gold/45">{label}</p>
      <div className="mt-1 text-sm text-cream/75">{children || 'Not set'}</div>
    </div>
  );
}

export default function AdminReviewDetailModal({
  review,
  loading = false,
  actionLoading = false,
  onClose,
  onModerate,
}) {
  const [moderationAction, setModerationAction] = useState(null);

  if (!review) return null;

  const riskReasons = Array.isArray(review.risk_reasons) ? review.risk_reasons : [];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center px-4 py-8">
      <button
        type="button"
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
        onClick={() => {
          if (!actionLoading) onClose();
        }}
        aria-label="Close review details"
      />

      <div className="relative max-h-[88vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-gold/15 bg-obsidian shadow-2xl">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-gold/10 bg-obsidian/95 p-5 backdrop-blur">
          <div>
            <p className="text-xs uppercase tracking-[0.25em] text-gold/55">Review #{review.id}</p>
            <h2 className="mt-1 flex items-center gap-2 font-display text-2xl text-cream">
              <FiStar className="text-gold" size={20} />
              {Number(review.rating || 0).toFixed(1)} star review
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={actionLoading}
            className="rounded-full border border-cream/10 p-2 text-cream/45 hover:text-cream disabled:opacity-50"
            aria-label="Close"
          >
            <FiX size={17} />
          </button>
        </div>

        {loading ? (
          <div className="p-5">
            <div className="h-64 rounded-2xl shimmer" />
          </div>
        ) : (
          <div className="space-y-6 p-5">
            <div className="flex flex-wrap gap-2">
              <AdminReviewStatusBadge value={review.status} />
              <AdminReviewRiskBadge score={review.risk_score} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <DetailRow label="Reviewer">
                {review.user?.name || 'Unknown user'} <span className="text-cream/35">#{review.user_id}</span>
              </DetailRow>
              <DetailRow label="Property">
                {review.property?.title || 'Unknown property'} <span className="text-cream/35">#{review.property_id}</span>
              </DetailRow>
              <DetailRow label="Booking">#{review.booking_id || 'Not linked'}</DetailRow>
              <DetailRow label="Stay dates">
                {formatReviewDate(review.booking?.start_date)} to {formatReviewDate(review.booking?.end_date)}
              </DetailRow>
              <DetailRow label="Booking status">{review.booking?.status || 'Unknown'}</DetailRow>
              <DetailRow label="Created">{formatReviewDate(review.created_at)}</DetailRow>
              <DetailRow label="Published">{formatReviewDate(review.published_at)}</DetailRow>
              <DetailRow label="Moderated">{formatReviewDate(review.moderated_at)}</DetailRow>
              <DetailRow label="Moderated by">
                {review.moderated_by ? `User #${review.moderated_by}` : 'Not moderated yet'}
              </DetailRow>
            </div>

            <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-4">
              <p className="text-xs uppercase tracking-[0.2em] text-gold/45">Comment</p>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-cream/75">
                {review.comment || 'No comment provided.'}
              </p>
            </div>

            <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-4">
              <p className="text-xs uppercase tracking-[0.2em] text-gold/45">Risk reasons</p>
              {riskReasons.length > 0 ? (
                <div className="mt-3 flex flex-wrap gap-2">
                  {riskReasons.map((reason) => (
                    <span
                      key={reason}
                      className="rounded-full border border-gold/15 bg-gold/5 px-2.5 py-1 text-xs text-gold/75"
                    >
                      {reviewRiskReasonLabel(reason)}
                    </span>
                  ))}
                </div>
              ) : (
                <p className="mt-3 text-sm text-cream/45">No risk reasons recorded.</p>
              )}
            </div>

            {(canPublishReview(review) || canRejectReview(review)) && (
              <div className="flex flex-col gap-2 border-t border-gold/10 pt-5 sm:flex-row sm:justify-end">
                {canPublishReview(review) && (
                  <button
                    type="button"
                    onClick={() => setModerationAction('publish')}
                    disabled={actionLoading}
                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300/25 px-4 py-2.5 text-sm text-emerald-100 transition-colors hover:bg-emerald-300/10 disabled:opacity-50"
                  >
                    <FiCheckCircle size={16} />
                    Publish
                  </button>
                )}
                {canRejectReview(review) && (
                  <button
                    type="button"
                    onClick={() => setModerationAction('reject')}
                    disabled={actionLoading}
                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-red-300/25 px-4 py-2.5 text-sm text-red-100 transition-colors hover:bg-red-300/10 disabled:opacity-50"
                  >
                    <FiXCircle size={16} />
                    Reject
                  </button>
                )}
              </div>
            )}
          </div>
        )}
      </div>

      <ModerateReviewModal
        action={moderationAction}
        review={review}
        loading={actionLoading}
        onClose={() => setModerationAction(null)}
        onConfirm={onModerate}
      />
    </div>
  );
}
