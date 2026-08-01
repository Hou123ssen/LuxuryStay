import {
  cityLabel,
  countryLabel,
  eventTypeLabel,
  formatGeoDateTime,
} from '../utils/adminGeographyFormatters';

export default function AdminRecentGeoActivity({ rows = [] }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <h3 className="font-display text-xl text-cream">Recent Geo Activity</h3>

      {rows.length === 0 ? (
        <div className="mt-4 rounded-xl border border-white/5 bg-black/15 px-4 py-5 text-sm text-cream/45">
          No recent geography activity yet.
        </div>
      ) : (
        <div className="mt-4 space-y-2">
          {rows.map((row) => (
            <div
              key={row.id}
              className="grid gap-2 rounded-xl border border-white/5 bg-black/15 px-4 py-3 text-sm text-cream/65 sm:grid-cols-[1fr_auto]"
            >
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="rounded-full border border-gold/20 bg-gold/10 px-2 py-0.5 text-xs text-gold">
                    {eventTypeLabel(row.event_type)}
                  </span>
                  <span className="text-xs text-cream/35">User #{row.user_id}</span>
                </div>
                <div className="mt-2 truncate text-cream">
                  {cityLabel(row)} - {row.region_name || 'Unknown'} - {countryLabel(row)}
                </div>
              </div>
              <div className="text-xs text-cream/40 sm:text-right">
                {formatGeoDateTime(row.occurred_at)}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
