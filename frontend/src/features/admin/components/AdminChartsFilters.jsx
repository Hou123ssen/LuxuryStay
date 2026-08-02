import { FiRefreshCw } from 'react-icons/fi';
import { chartDayOptions } from '../utils/adminChartFormatters';

export default function AdminChartsFilters({ days, onDaysChange, onRefresh, refreshing }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div className="inline-flex overflow-hidden rounded-full border border-gold/15 bg-black/20 p-1">
        {chartDayOptions.map((option) => {
          const active = option.value === days;

          return (
            <button
              key={option.value}
              type="button"
              onClick={() => onDaysChange(option.value)}
              className={`rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
                active
                  ? 'bg-gold text-black shadow-[0_0_22px_rgba(212,175,55,0.18)]'
                  : 'text-cream/55 hover:bg-white/5 hover:text-cream'
              }`}
            >
              {option.label}
            </button>
          );
        })}
      </div>

      <button
        type="button"
        onClick={onRefresh}
        disabled={refreshing}
        className="inline-flex items-center justify-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-xs font-medium text-gold transition-colors hover:border-gold hover:bg-gold/10 disabled:cursor-not-allowed disabled:opacity-60"
      >
        <FiRefreshCw size={14} className={refreshing ? 'animate-spin' : ''} />
        {refreshing ? 'Refreshing' : 'Refresh'}
      </button>
    </div>
  );
}
