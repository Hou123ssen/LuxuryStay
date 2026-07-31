import { useState } from 'react';
import { FiCheckCircle, FiX, FiXCircle } from 'react-icons/fi';
import AdminReportStatusBadge from './AdminReportStatusBadge';
import ModerateReportModal from './ModerateReportModal';
import {
  formatReportDate,
  isClosedReport,
  reportCategoryLabel,
} from '../utils/reportAdminOptions';

function DetailRow({ label, children }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-[0.2em] text-gold/45">{label}</p>
      <div className="mt-1 text-sm text-cream/75">{children || 'Not set'}</div>
    </div>
  );
}

export default function AdminReportDetailModal({
  report,
  loading = false,
  actionLoading = false,
  onClose,
  onModerate,
}) {
  const [moderationAction, setModerationAction] = useState(null);

  if (!report) return null;

  const closed = isClosedReport(report);
  const canReview = report.status === 'pending';
  const canClose = !closed;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center px-4 py-8">
      <button
        type="button"
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
        onClick={() => {
          if (!actionLoading) onClose();
        }}
        aria-label="Close report details"
      />

      <div className="relative max-h-[88vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-gold/15 bg-obsidian shadow-2xl">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-gold/10 bg-obsidian/95 p-5 backdrop-blur">
          <div>
            <p className="text-xs uppercase tracking-[0.25em] text-gold/55">Report #{report.id}</p>
            <h2 className="mt-1 font-display text-2xl text-cream">{reportCategoryLabel(report.category)}</h2>
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
              <AdminReportStatusBadge value={report.status} />
              <AdminReportStatusBadge type="severity" value={report.severity} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <DetailRow label="Property">
                {report.property?.title || 'Unknown property'} <span className="text-cream/35">#{report.property_id}</span>
              </DetailRow>
              <DetailRow label="Booking">#{report.booking_id || 'Not linked'}</DetailRow>
              <DetailRow label="Reporter">
                {report.reporter?.name || 'Unknown user'} <span className="text-cream/35">#{report.reporter_user_id}</span>
              </DetailRow>
              <DetailRow label="Reported user">
                {report.reported_user?.name || 'Unknown user'} <span className="text-cream/35">#{report.reported_user_id}</span>
              </DetailRow>
              <DetailRow label="Created">{formatReportDate(report.created_at)}</DetailRow>
              <DetailRow label="Reviewed">{formatReportDate(report.reviewed_at)}</DetailRow>
              <DetailRow label="Resolved">{formatReportDate(report.resolved_at)}</DetailRow>
              <DetailRow label="Reviewed by">
                {report.reviewed_by_user_id ? `User #${report.reviewed_by_user_id}` : 'Not reviewed yet'}
              </DetailRow>
            </div>

            <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-4">
              <p className="text-xs uppercase tracking-[0.2em] text-gold/45">Description</p>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-cream/75">
                {report.description || 'No description provided.'}
              </p>
            </div>

            <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-4">
              <p className="text-xs uppercase tracking-[0.2em] text-gold/45">Admin notes</p>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-cream/65">
                {report.admin_notes || 'No admin notes yet.'}
              </p>
            </div>

            {canClose && (
              <div className="flex flex-col gap-2 border-t border-gold/10 pt-5 sm:flex-row sm:justify-end">
                {canReview && (
                  <button
                    type="button"
                    onClick={() => setModerationAction('review')}
                    disabled={actionLoading}
                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-sky-300/25 px-4 py-2.5 text-sm text-sky-100 transition-colors hover:bg-sky-300/10 disabled:opacity-50"
                  >
                    <FiCheckCircle size={16} />
                    Mark reviewed
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => setModerationAction('resolve')}
                  disabled={actionLoading}
                  className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300/25 px-4 py-2.5 text-sm text-emerald-100 transition-colors hover:bg-emerald-300/10 disabled:opacity-50"
                >
                  <FiCheckCircle size={16} />
                  Resolve
                </button>
                <button
                  type="button"
                  onClick={() => setModerationAction('reject')}
                  disabled={actionLoading}
                  className="inline-flex items-center justify-center gap-2 rounded-xl border border-red-300/25 px-4 py-2.5 text-sm text-red-100 transition-colors hover:bg-red-300/10 disabled:opacity-50"
                >
                  <FiXCircle size={16} />
                  Reject
                </button>
              </div>
            )}
          </div>
        )}
      </div>

      <ModerateReportModal
        action={moderationAction}
        report={report}
        loading={actionLoading}
        onClose={() => setModerationAction(null)}
        onConfirm={onModerate}
      />
    </div>
  );
}
