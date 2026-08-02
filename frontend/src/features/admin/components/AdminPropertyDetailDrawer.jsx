import { FiX } from 'react-icons/fi';
import AdminPropertyCounts from './AdminPropertyCounts';
import AdminPropertyImagesPreview from './AdminPropertyImagesPreview';
import AdminPropertyOwnerBadge from './AdminPropertyOwnerBadge';
import AdminPropertyRatingSummary from './AdminPropertyRatingSummary';
import AdminPropertyRecentBookings from './AdminPropertyRecentBookings';
import AdminPropertyRecentReports from './AdminPropertyRecentReports';
import AdminPropertyRecentReviews from './AdminPropertyRecentReviews';
import AdminPropertyStatusBadge from './AdminPropertyStatusBadge';
import {
  formatAdminPropertyDate,
  formatAdminPropertyPrice,
  propertyLocationLabel,
} from '../utils/adminPropertyFormatters';

function DetailSection({ title, children }) {
  return (
    <section>
      <h3 className="mb-3 font-display text-xl text-cream">{title}</h3>
      {children}
    </section>
  );
}

export default function AdminPropertyDetailDrawer({
  open,
  property,
  loading,
  error,
  onClose,
  onRetry,
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        aria-label="Close property details"
        onClick={onClose}
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
      />

      <aside className="absolute right-0 top-0 flex h-full w-full max-w-3xl flex-col border-l border-gold/15 bg-[#0a0a0f] shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-gold/10 p-5">
          <div className="min-w-0">
            <p className="text-xs uppercase tracking-[0.24em] text-gold/55">Read-only property detail</p>
            <h2 className="mt-2 truncate font-display text-3xl text-cream">{property?.title || 'Property Details'}</h2>
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
          {loading && <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-6 text-sm text-cream/45">Loading property details...</div>}

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

          {!loading && !error && property && (
            <div className="space-y-6">
              <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="truncate text-xl font-medium text-cream">{property.title}</h3>
                      <AdminPropertyStatusBadge status={property.status} />
                    </div>
                    <p className="mt-2 text-sm text-cream/45">{propertyLocationLabel(property)}</p>
                    <p className="mt-2 text-sm text-gold/80">{formatAdminPropertyPrice(property.price_per_night)}</p>
                    <p className="mt-2 text-xs text-cream/35">Created {formatAdminPropertyDate(property.created_at)}</p>
                  </div>
                  <AdminPropertyRatingSummary rating={property.rating} />
                </div>

                {property.description_excerpt && (
                  <p className="mt-5 rounded-xl border border-white/5 bg-black/20 p-4 text-sm leading-6 text-cream/55">
                    {property.description_excerpt}
                  </p>
                )}

                <div className="mt-5">
                  <AdminPropertyCounts counts={property.counts} />
                </div>
              </div>

              <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
                  <p className="mb-3 text-xs uppercase tracking-[0.16em] text-cream/35">Owner</p>
                  <AdminPropertyOwnerBadge owner={property.owner} />
                </div>
                <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
                  <p className="mb-3 text-xs uppercase tracking-[0.16em] text-cream/35">Host reliability</p>
                  <p className="text-sm text-cream">{property.owner_reliability?.owner_reliability_label || 'Not available'}</p>
                  <p className="mt-1 text-xs text-cream/40">
                    {property.owner_reliability?.owner_cancellation_rate ?? 'No'} cancellation rate
                  </p>
                </div>
              </div>

              <DetailSection title="Images">
                <AdminPropertyImagesPreview images={property.images || []} />
              </DetailSection>

              <DetailSection title="Recent Bookings">
                <AdminPropertyRecentBookings rows={property.recent_bookings || []} />
              </DetailSection>

              <DetailSection title="Recent Reviews">
                <AdminPropertyRecentReviews rows={property.recent_reviews || []} />
              </DetailSection>

              <DetailSection title="Recent Reports">
                <AdminPropertyRecentReports rows={property.recent_reports || []} />
              </DetailSection>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}
