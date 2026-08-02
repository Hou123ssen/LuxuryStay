import { formatAdminPropertyDate, formatAdminPropertyPrice, statusLabel } from '../utils/adminPropertyFormatters';

export default function AdminPropertyRecentBookings({ rows = [] }) {
  if (!rows.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No recent bookings.</p>;
  }

  return (
    <div className="space-y-2">
      {rows.map((booking) => (
        <div key={booking.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-start justify-between gap-3">
            <div>
              <span className="text-sm font-medium text-cream">{booking.guest_name || `Guest #${booking.user_id}`}</span>
              <p className="mt-1 text-xs text-cream/45">
                {booking.start_date || 'Unknown'} to {booking.end_date || 'Unknown'}
              </p>
            </div>
            <span className="rounded-full border border-white/10 px-2 py-0.5 text-xs text-cream/55">{statusLabel(booking.status)}</span>
          </div>
          <p className="mt-2 text-xs text-gold/70">{formatAdminPropertyPrice(booking.total_price)}</p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminPropertyDate(booking.created_at)}</p>
        </div>
      ))}
    </div>
  );
}
