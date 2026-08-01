import { countryLabel, formatGeoNumber } from '../utils/adminGeographyFormatters';

export default function AdminCountryUsageTable({ title, rows = [], emptyLabel = 'No country activity yet.' }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <h3 className="font-display text-xl text-cream">{title}</h3>

      {rows.length === 0 ? (
        <div className="mt-4 rounded-xl border border-white/5 bg-black/15 px-4 py-5 text-sm text-cream/45">
          {emptyLabel}
        </div>
      ) : (
        <div className="mt-4 overflow-hidden rounded-xl border border-white/5">
          <table className="w-full text-left text-sm">
            <thead className="bg-black/25 text-xs uppercase tracking-[0.14em] text-cream/35">
              <tr>
                <th className="px-4 py-3 font-medium">Country</th>
                <th className="px-4 py-3 text-right font-medium">Count</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {rows.slice(0, 8).map((row) => (
                <tr key={`${row.country_code || 'unknown'}-${row.country_name}`} className="text-cream/70">
                  <td className="px-4 py-3">{countryLabel(row)}</td>
                  <td className="px-4 py-3 text-right font-medium text-cream">
                    {formatGeoNumber(row.count)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
