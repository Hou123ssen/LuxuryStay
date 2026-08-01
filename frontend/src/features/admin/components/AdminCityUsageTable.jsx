import {
  cityLabel,
  formatGeoNumber,
  regionCountryLabel,
} from '../utils/adminGeographyFormatters';

export default function AdminCityUsageTable({ title, rows = [], emptyLabel = 'No city activity yet.' }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <h3 className="font-display text-xl text-cream">{title}</h3>

      {rows.length === 0 ? (
        <div className="mt-4 rounded-xl border border-white/5 bg-black/15 px-4 py-5 text-sm text-cream/45">
          {emptyLabel}
        </div>
      ) : (
        <div className="mt-4 space-y-2">
          {rows.slice(0, 8).map((row) => (
            <div
              key={`${row.city_name || 'unknown'}-${row.region_name || 'unknown'}-${row.country_code || 'unknown'}`}
              className="flex items-center justify-between gap-4 rounded-xl border border-white/5 bg-black/15 px-4 py-3"
            >
              <div className="min-w-0">
                <div className="truncate text-sm font-medium text-cream">{cityLabel(row)}</div>
                <div className="mt-0.5 truncate text-xs text-cream/40">{regionCountryLabel(row)}</div>
              </div>
              <div className="shrink-0 font-display text-xl text-gold">
                {formatGeoNumber(row.count)}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
