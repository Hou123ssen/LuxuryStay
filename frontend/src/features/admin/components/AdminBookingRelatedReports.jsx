import { bookingStatusLabel, formatAdminBookingDateTime } from '../utils/adminBookingFormatters';

export default function AdminBookingRelatedReports({ reports = [] }) {
  if (!reports.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No related reports.</p>;
  }

  return (
    <div className="space-y-2">
      {reports.map((report) => (
        <div key={report.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-start justify-between gap-3">
            <span className="text-sm font-medium text-cream">Report #{report.id}</span>
            <span className="rounded-full border border-white/10 px-2 py-0.5 text-xs text-cream/55">{bookingStatusLabel(report.status)}</span>
          </div>
          <p className="mt-1 text-xs text-cream/45">
            {bookingStatusLabel(report.category)} - {bookingStatusLabel(report.severity)}
          </p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminBookingDateTime(report.created_at)}</p>
        </div>
      ))}
    </div>
  );
}
