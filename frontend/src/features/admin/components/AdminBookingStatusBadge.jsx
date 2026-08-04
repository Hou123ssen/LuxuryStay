import { bookingStatusLabel } from '../utils/adminBookingFormatters';

const statusClasses = {
  pending: 'border-amber-300/25 bg-amber-300/10 text-amber-100',
  accepted: 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100',
  completed: 'border-gold/25 bg-gold/10 text-gold',
  rejected: 'border-red-300/25 bg-red-300/10 text-red-100',
  cancelled: 'border-white/10 bg-white/[0.04] text-cream/50',
};

export default function AdminBookingStatusBadge({ status }) {
  return (
    <span className={`inline-flex w-fit rounded-full border px-2.5 py-1 text-xs ${statusClasses[status] || 'border-white/10 bg-white/[0.03] text-cream/45'}`}>
      {bookingStatusLabel(status)}
    </span>
  );
}
