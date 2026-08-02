import { formatAdminUserDate, statusLabel } from '../utils/adminUserFormatters';

export default function AdminUserRecentBookings({ rows = [] }) {
  if (!rows.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No recent bookings.</p>;
  }

  return (
    <div className="space-y-2">
      {rows.map((booking) => (
        <div key={booking.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-medium text-cream">{booking.property_title || `Property #${booking.property_id}`}</span>
            <span className="rounded-full border border-white/10 px-2 py-0.5 text-xs text-cream/55">{statusLabel(booking.status)}</span>
          </div>
          <p className="mt-1 text-xs text-cream/45">
            {booking.start_date || 'Unknown'} to {booking.end_date || 'Unknown'}
          </p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminUserDate(booking.created_at)}</p>
        </div>
      ))}
    </div>
  );
}
