import {
  formatChartNumber,
  formatStatusLabel,
  normalizeBreakdown,
} from '../utils/adminChartFormatters';
import { statusClasses } from '../utils/adminDashboardFormatters';

export default function AdminStatusBreakdownChart({ breakdown = [], emptyMessage = 'No records in this period.' }) {
  const items = normalizeBreakdown(breakdown);
  const maximum = Math.max(1, ...items.map((item) => item.count));
  const total = items.reduce((sum, item) => sum + item.count, 0);

  if (items.length === 0 || total === 0) {
    return (
      <div className="rounded-xl border border-white/5 bg-black/20 px-4 py-6 text-center text-sm text-cream/45">
        {emptyMessage}
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {items.map((item) => {
        const width = `${Math.max(4, (item.count / maximum) * 100)}%`;

        return (
          <div key={item.status}>
            <div className="mb-1.5 flex items-center justify-between gap-3">
              <span className={`rounded-full border px-2.5 py-1 text-xs ${statusClasses(item.status)}`}>
                {formatStatusLabel(item.status)}
              </span>
              <span className="text-sm font-medium text-cream">{formatChartNumber(item.count)}</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-white/5">
              <div
                className="h-full rounded-full bg-gold"
                style={{ width }}
                role="img"
                aria-label={`${formatStatusLabel(item.status)}: ${formatChartNumber(item.count)}`}
                title={`${formatStatusLabel(item.status)}: ${formatChartNumber(item.count)}`}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}
