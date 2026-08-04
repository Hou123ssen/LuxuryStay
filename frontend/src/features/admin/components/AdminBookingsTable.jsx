import { FiEye } from 'react-icons/fi';
import AdminBookingPeopleSummary from './AdminBookingPeopleSummary';
import AdminBookingPropertySummary from './AdminBookingPropertySummary';
import AdminBookingSignals from './AdminBookingSignals';
import AdminBookingStatusBadge from './AdminBookingStatusBadge';
import {
  bookingDateRangeLabel,
  formatAdminBookingDateTime,
  formatAdminBookingMoney,
  nightsLabel,
} from '../utils/adminBookingFormatters';

export default function AdminBookingsTable({ bookings = [], loading = false, onViewBooking }) {
  if (loading) {
    return (
      <div className="grid gap-3">
        {[1, 2, 3].map((item) => (
          <div key={item} className="h-32 animate-pulse rounded-2xl border border-gold/10 bg-white/[0.03]" />
        ))}
      </div>
    );
  }

  if (bookings.length === 0) {
    return (
      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-8 text-center text-sm text-cream/45">
        No bookings found for these filters.
      </div>
    );
  }

  return (
    <section className="overflow-hidden rounded-2xl border border-gold/10 bg-white/[0.03]">
      <div className="hidden overflow-x-auto 2xl:block">
        <table className="min-w-full divide-y divide-white/5">
          <thead className="bg-black/20">
            <tr className="text-left text-[11px] uppercase tracking-[0.16em] text-cream/35">
              <th className="px-4 py-3">Booking</th>
              <th className="px-4 py-3">Property</th>
              <th className="px-4 py-3">People</th>
              <th className="px-4 py-3">Stay</th>
              <th className="px-4 py-3">Total</th>
              <th className="px-4 py-3">Signals</th>
              <th className="px-4 py-3">Created</th>
              <th className="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5">
            {bookings.map((booking) => (
              <tr key={booking.id} className="transition-colors hover:bg-white/[0.025]">
                <td className="px-4 py-4">
                  <div className="font-medium text-cream">#{booking.id}</div>
                  <div className="mt-2"><AdminBookingStatusBadge status={booking.status} /></div>
                </td>
                <td className="px-4 py-4 min-w-[180px]"><AdminBookingPropertySummary property={booking.property} /></td>
                <td className="px-4 py-4 min-w-[280px]"><AdminBookingPeopleSummary guest={booking.guest} owner={booking.owner} /></td>
                <td className="px-4 py-4 min-w-[170px]">
                  <p className="text-sm text-cream">{bookingDateRangeLabel(booking)}</p>
                  <p className="mt-1 text-xs text-cream/45">{nightsLabel(booking.nights)}</p>
                </td>
                <td className="px-4 py-4 text-sm text-gold/80">{formatAdminBookingMoney(booking.total_price)}</td>
                <td className="px-4 py-4 min-w-[230px]"><AdminBookingSignals signals={booking.signals} /></td>
                <td className="px-4 py-4 text-sm text-cream/45">{formatAdminBookingDateTime(booking.created_at)}</td>
                <td className="px-4 py-4 text-right">
                  <button
                    type="button"
                    onClick={() => onViewBooking(booking.id)}
                    className="inline-flex items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
                  >
                    <FiEye size={15} />
                    View
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="grid gap-3 p-3 2xl:hidden">
        {bookings.map((booking) => (
          <article key={booking.id} className="rounded-2xl border border-white/5 bg-black/15 p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="font-medium text-cream">Booking #{booking.id}</h3>
                  <AdminBookingStatusBadge status={booking.status} />
                </div>
                <p className="mt-1 text-sm text-cream/45">{bookingDateRangeLabel(booking)}</p>
                <p className="mt-1 text-sm text-gold/75">{formatAdminBookingMoney(booking.total_price)}</p>
              </div>
              <button
                type="button"
                onClick={() => onViewBooking(booking.id)}
                className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-sm text-gold"
              >
                <FiEye size={15} />
                View
              </button>
            </div>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <AdminBookingPropertySummary property={booking.property} />
              <AdminBookingPeopleSummary guest={booking.guest} owner={booking.owner} compact />
            </div>
            <div className="mt-4"><AdminBookingSignals signals={booking.signals} compact /></div>
          </article>
        ))}
      </div>
    </section>
  );
}
