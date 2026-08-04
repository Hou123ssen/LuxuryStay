import { formatAdminBookingDateTime, formatAdminBookingMoney, bookingStatusLabel } from '../utils/adminBookingFormatters';

export default function AdminBookingPaymentSummary({ payment }) {
  if (!payment) {
    return (
      <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">
        No safe payment summary available yet.
      </p>
    );
  }

  return (
    <div className="rounded-xl border border-white/5 bg-black/20 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-cream">{bookingStatusLabel(payment.status)}</p>
          <p className="mt-1 text-xs text-cream/45">{payment.currency || 'Currency not available'}</p>
        </div>
        <span className="text-sm text-gold">{formatAdminBookingMoney(payment.amount)}</span>
      </div>
      <p className="mt-2 text-xs text-cream/30">{formatAdminBookingDateTime(payment.created_at)}</p>
    </div>
  );
}
