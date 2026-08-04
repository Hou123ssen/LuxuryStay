import { FiX } from 'react-icons/fi';
import AdminBookingPaymentSummary from './AdminBookingPaymentSummary';
import AdminBookingPeopleSummary from './AdminBookingPeopleSummary';
import AdminBookingPropertySummary from './AdminBookingPropertySummary';
import AdminBookingRelatedReports from './AdminBookingRelatedReports';
import AdminBookingRelatedReview from './AdminBookingRelatedReview';
import AdminBookingSignals from './AdminBookingSignals';
import AdminBookingStatusBadge from './AdminBookingStatusBadge';
import {
  bookingDateRangeLabel,
  formatAdminBookingDateTime,
  formatAdminBookingMoney,
  nightsLabel,
} from '../utils/adminBookingFormatters';

function DetailSection({ title, children }) {
  return (
    <section>
      <h3 className="mb-3 font-display text-xl text-cream">{title}</h3>
      {children}
    </section>
  );
}

export default function AdminBookingDetailDrawer({ open, booking, loading, error, onClose, onRetry }) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        aria-label="Close booking details"
        onClick={onClose}
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
      />

      <aside className="absolute right-0 top-0 flex h-full w-full max-w-3xl flex-col border-l border-gold/15 bg-[#0a0a0f] shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-gold/10 p-5">
          <div className="min-w-0">
            <p className="text-xs uppercase tracking-[0.24em] text-gold/55">Read-only booking detail</p>
            <h2 className="mt-2 truncate font-display text-3xl text-cream">
              {booking ? `Booking #${booking.id}` : 'Booking Details'}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-cream/55 transition-colors hover:border-gold/30 hover:text-cream"
          >
            <FiX size={18} />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {loading && <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-6 text-sm text-cream/45">Loading booking details...</div>}

          {!loading && error && (
            <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-6">
              <p className="text-sm text-red-100">{error}</p>
              <button
                type="button"
                onClick={onRetry}
                className="mt-4 rounded-full border border-gold/30 px-4 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
              >
                Retry
              </button>
            </div>
          )}

          {!loading && !error && booking && (
            <div className="space-y-6">
              <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="text-xl font-medium text-cream">Booking #{booking.id}</h3>
                      <AdminBookingStatusBadge status={booking.status} />
                    </div>
                    <p className="mt-2 text-sm text-cream/55">{bookingDateRangeLabel(booking)}</p>
                    <p className="mt-1 text-xs text-cream/35">{nightsLabel(booking.nights)}</p>
                    <p className="mt-2 text-xs text-cream/35">Created {formatAdminBookingDateTime(booking.created_at)}</p>
                  </div>
                  <div className="rounded-2xl border border-gold/15 bg-gold/5 px-4 py-3 text-right">
                    <p className="text-xs uppercase tracking-[0.16em] text-gold/55">Total</p>
                    <p className="mt-1 text-lg font-medium text-gold">{formatAdminBookingMoney(booking.total_price)}</p>
                  </div>
                </div>

                <div className="mt-5">
                  <AdminBookingSignals signals={booking.signals} />
                </div>
              </div>

              <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
                  <p className="mb-3 text-xs uppercase tracking-[0.16em] text-cream/35">Property</p>
                  <AdminBookingPropertySummary property={booking.property} />
                </div>
                <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
                  <AdminBookingPeopleSummary guest={booking.guest} owner={booking.owner} />
                </div>
              </div>

              <DetailSection title="Related Review">
                <AdminBookingRelatedReview review={booking.review} />
              </DetailSection>

              <DetailSection title="Related Reports">
                <AdminBookingRelatedReports reports={booking.reports || []} />
              </DetailSection>

              <DetailSection title="Payment Summary">
                <AdminBookingPaymentSummary payment={booking.payment} />
              </DetailSection>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}
