import { formatAdminUserDate, statusLabel } from '../utils/adminUserFormatters';

export default function AdminUserRecentReports({ rows = [] }) {
  if (!rows.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No recent reports.</p>;
  }

  return (
    <div className="space-y-2">
      {rows.map((report) => (
        <div key={report.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-medium text-cream">{report.property_title || `Property #${report.property_id}`}</span>
            <span className="rounded-full border border-white/10 px-2 py-0.5 text-xs text-cream/55">{statusLabel(report.status)}</span>
          </div>
          <p className="mt-1 text-xs text-cream/45">
            {statusLabel(report.category)} · {statusLabel(report.severity)}
          </p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminUserDate(report.created_at)}</p>
        </div>
      ))}
    </div>
  );
}
