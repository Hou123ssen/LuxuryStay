import {
  buildLinePoints,
  chartLabelEvery,
  formatChartDateLabel,
  formatChartNumber,
  hasChartData,
  maxCount,
  normalizeSeries,
} from '../utils/adminChartFormatters';

const WIDTH = 640;
const HEIGHT = 210;
const PADDING = 28;

export default function AdminLineChart({ lines = [], emptyMessage = 'No activity recorded for this period yet.' }) {
  const normalizedLines = lines.map((line) => ({
    ...line,
    data: normalizeSeries(line.data),
  }));
  const maximum = maxCount(...normalizedLines.map((line) => line.data));
  const primarySeries = normalizedLines[0]?.data || [];
  const labelEvery = chartLabelEvery(primarySeries.length);
  const hasData = hasChartData(...normalizedLines.map((line) => line.data));

  return (
    <div className="space-y-3">
      <div className="relative overflow-hidden rounded-xl border border-white/5 bg-black/20">
        <svg
          viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
          role="img"
          aria-label={normalizedLines.map((line) => line.label).join(' and ') || 'Dashboard activity chart'}
          className="h-56 w-full"
          preserveAspectRatio="none"
        >
          <defs>
            <linearGradient id="admin-chart-grid" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="rgba(212,175,55,0.14)" />
              <stop offset="100%" stopColor="rgba(255,255,255,0.02)" />
            </linearGradient>
          </defs>
          <rect x="0" y="0" width={WIDTH} height={HEIGHT} fill="url(#admin-chart-grid)" />
          {[0, 1, 2, 3].map((index) => {
            const y = PADDING + ((HEIGHT - PADDING * 2) * index) / 3;
            return (
              <line
                key={index}
                x1={PADDING}
                x2={WIDTH - PADDING}
                y1={y}
                y2={y}
                stroke="rgba(255,255,255,0.07)"
                strokeWidth="1"
              />
            );
          })}

          {normalizedLines.map((line) => (
            <polyline
              key={line.label}
              points={buildLinePoints(line.data, WIDTH, HEIGHT, PADDING, maximum)}
              fill="none"
              stroke={line.color}
              strokeWidth="3"
              strokeLinecap="round"
              strokeLinejoin="round"
              vectorEffect="non-scaling-stroke"
            />
          ))}

          {normalizedLines.map((line) => {
            const lastIndex = Math.max(1, line.data.length - 1);
            return line.data.map((point, index) => {
              const x = PADDING + ((WIDTH - PADDING * 2) * index) / lastIndex;
              const y = PADDING + (HEIGHT - PADDING * 2) - ((point.count / maximum) * (HEIGHT - PADDING * 2));

              return (
                <circle
                  key={`${line.label}-${point.date}`}
                  cx={x}
                  cy={y}
                  r="4"
                  fill={line.color}
                  opacity={point.count > 0 ? 0.95 : 0.35}
                  tabIndex="0"
                  aria-label={`${line.label}, ${formatChartDateLabel(point.date)}: ${formatChartNumber(point.count)}`}
                >
                  <title>{`${line.label} - ${formatChartDateLabel(point.date)}: ${formatChartNumber(point.count)}`}</title>
                </circle>
              );
            });
          })}
        </svg>

        {!hasData && (
          <div className="absolute inset-x-4 top-4 rounded-lg border border-white/5 bg-black/45 px-3 py-2 text-xs text-cream/55">
            {emptyMessage}
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          {normalizedLines.map((line) => (
            <span key={line.label} className="inline-flex items-center gap-2 text-xs text-cream/55">
              <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: line.color }} />
              {line.label}
            </span>
          ))}
        </div>
        <div className="flex max-w-full gap-3 overflow-hidden text-[11px] text-cream/35">
          {primarySeries
            .filter((_, index) => index % labelEvery === 0 || index === primarySeries.length - 1)
            .map((point) => (
              <span key={point.date} className="whitespace-nowrap">{formatChartDateLabel(point.date)}</span>
            ))}
        </div>
      </div>
    </div>
  );
}
