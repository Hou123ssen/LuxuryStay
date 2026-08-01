import { FiRefreshCw } from 'react-icons/fi';
import { DAY_FILTER_OPTIONS } from '../utils/adminGeographyFormatters';

export default function AdminGeographyFilters({ days, onDaysChange, onRefresh, refreshing = false }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div className="inline-flex rounded-full border border-white/10 bg-black/20 p-1">
        {DAY_FILTER_OPTIONS.map((option) => {
          const active = days === option.value;

          return (
            <button
              key={option.value}
              type="button"
              onClick={() => onDaysChange(option.value)}
              className={`rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
                active
                  ? 'bg-gold text-black'
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
        className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-xs text-gold transition-colors hover:border-gold hover:bg-gold/10 disabled:cursor-not-allowed disabled:opacity-60"
      >
        <FiRefreshCw size={14} className={refreshing ? 'animate-spin' : ''} />
        {refreshing ? 'Refreshing' : 'Refresh'}
      </button>
    </div>
  );
}
