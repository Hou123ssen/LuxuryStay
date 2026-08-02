import { formatChartNumber } from '../utils/adminChartFormatters';

export default function AdminBarChart({ items = [] }) {
  const normalizedItems = items.map((item) => ({
    label: item.label,
    value: Number(item.value || 0),
    color: item.color || '#d4af37',
  }));
  const maximum = Math.max(1, ...normalizedItems.map((item) => item.value));

  return (
    <div className="space-y-3">
      {normalizedItems.map((item) => {
        const width = `${Math.max(4, (item.value / maximum) * 100)}%`;

        return (
          <div key={item.label}>
            <div className="mb-1 flex items-center justify-between gap-3 text-xs">
              <span className="text-cream/55">{item.label}</span>
              <span className="font-medium text-cream">{formatChartNumber(item.value)}</span>
            </div>
            <div className="h-2.5 overflow-hidden rounded-full bg-white/5">
              <div
                className="h-full rounded-full"
                style={{ width, backgroundColor: item.color }}
                role="img"
                aria-label={`${item.label}: ${formatChartNumber(item.value)}`}
                title={`${item.label}: ${formatChartNumber(item.value)}`}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}
