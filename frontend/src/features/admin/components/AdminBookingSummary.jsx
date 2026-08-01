import { formatNumber } from '../utils/adminDashboardFormatters';

const BOOKING_ROWS = [
  ['Pending', 'pending_bookings_count'],
  ['Accepted', 'accepted_bookings_count'],
  ['Completed', 'completed_bookings_count'],
  ['Cancelled', 'cancelled_bookings_count'],
  ['Owner cancelled', 'owner_cancelled_bookings_count'],
  ['Guest cancelled', 'guest_cancelled_bookings_count'],
];

export default function AdminBookingSummary({ bookings = {}, moderation = {} }) {
  return (
    <section className="grid gap-4 lg:grid-cols-2">
      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
        <h2 className="font-display text-2xl text-cream">Booking Summary</h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          {BOOKING_ROWS.map(([label, key]) => (
            <div key={key} className="rounded-xl border border-white/5 bg-black/15 px-4 py-3">
              <div className="text-xs uppercase tracking-[0.16em] text-cream/40">{label}</div>
              <div className="mt-1 text-2xl text-cream">{formatNumber(bookings[key])}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
        <h2 className="font-display text-2xl text-cream">Moderation Summary</h2>
        <div className="mt-4 space-y-3">
          {[
            ['Pending reports', moderation.pending_reports_count],
            ['Unresolved reports', moderation.unresolved_reports_count],
            ['Pending reviews', moderation.pending_reviews_count],
            ['High risk reviews', moderation.high_risk_reviews_count],
            ['Rejected reviews', moderation.rejected_reviews_count],
            ['Published reviews', moderation.published_reviews_count],
          ].map(([label, value]) => (
            <div key={label} className="flex items-center justify-between border-b border-white/5 pb-2 text-sm last:border-b-0 last:pb-0">
              <span className="text-cream/55">{label}</span>
              <span className="font-medium text-cream">{formatNumber(value)}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
