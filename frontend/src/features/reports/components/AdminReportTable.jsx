import { FiEye } from 'react-icons/fi';
import AdminReportStatusBadge from './AdminReportStatusBadge';
import {
  formatReportDate,
  reportCategoryLabel,
} from '../utils/reportAdminOptions';

export default function AdminReportTable({ reports, loading, onOpenReport }) {
  if (loading) {
    return (
      <div className="space-y-3">
        {[1, 2, 3].map((item) => (
          <div key={item} className="h-20 rounded-2xl shimmer" />
        ))}
      </div>
    );
  }

  if (reports.length === 0) {
    return (
      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] px-6 py-16 text-center">
        <p className="font-display text-2xl text-cream/45">No reports found</p>
        <p className="mt-2 text-sm text-cream/35">Try adjusting the filters.</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-gold/10 bg-white/[0.03]">
      <div className="hidden overflow-x-auto lg:block">
        <table className="w-full text-left">
          <thead className="border-b border-gold/10 bg-black/20 text-xs uppercase tracking-[0.18em] text-gold/55">
            <tr>
              <th className="px-4 py-3 font-medium">Report</th>
              <th className="px-4 py-3 font-medium">Property</th>
              <th className="px-4 py-3 font-medium">Status</th>
              <th className="px-4 py-3 font-medium">Severity</th>
              <th className="px-4 py-3 font-medium">Created</th>
              <th className="px-4 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gold/5">
            {reports.map((report) => (
              <tr key={report.id} className="transition-colors hover:bg-gold/[0.04]">
                <td className="px-4 py-4">
                  <p className="text-sm font-medium text-cream">{reportCategoryLabel(report.category)}</p>
                  <p className="mt-1 text-xs text-cream/40">#{report.id} · booking #{report.booking_id || 'none'}</p>
                </td>
                <td className="px-4 py-4">
                  <p className="text-sm text-cream/80">{report.property?.title || 'Unknown property'}</p>
                  <p className="mt-1 text-xs text-cream/40">Property #{report.property_id}</p>
                </td>
                <td className="px-4 py-4">
                  <AdminReportStatusBadge value={report.status} />
                </td>
                <td className="px-4 py-4">
                  <AdminReportStatusBadge type="severity" value={report.severity} />
                </td>
                <td className="px-4 py-4 text-sm text-cream/55">{formatReportDate(report.created_at)}</td>
                <td className="px-4 py-4 text-right">
                  <button
                    type="button"
                    onClick={() => onOpenReport(report)}
                    className="inline-flex items-center gap-2 rounded-xl border border-gold/20 px-3 py-2 text-sm text-gold/80 transition-colors hover:border-gold/50 hover:text-gold"
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

      <div className="divide-y divide-gold/5 lg:hidden">
        {reports.map((report) => (
          <button
            key={report.id}
            type="button"
            onClick={() => onOpenReport(report)}
            className="block w-full px-4 py-4 text-left transition-colors hover:bg-gold/[0.04]"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-cream">{reportCategoryLabel(report.category)}</p>
                <p className="mt-1 text-xs text-cream/40">{report.property?.title || `Property #${report.property_id}`}</p>
              </div>
              <AdminReportStatusBadge value={report.status} />
            </div>
            <div className="mt-3 flex items-center justify-between gap-3">
              <AdminReportStatusBadge type="severity" value={report.severity} />
              <span className="text-xs text-cream/35">{formatReportDate(report.created_at)}</span>
            </div>
          </button>
        ))}
      </div>
    </div>
  );
}
