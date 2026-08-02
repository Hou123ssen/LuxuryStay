import { countryLabel, locationLabel } from '../utils/adminUserFormatters';

export default function AdminUserGeoBadge({ label, countryCode, countryName, regionName, cityName }) {
  const location = locationLabel(cityName, regionName, countryName);

  return (
    <div className="min-w-0">
      <div className="text-[11px] uppercase tracking-[0.16em] text-cream/35">{label}</div>
      <div className="mt-1 truncate text-sm text-cream/70">{location}</div>
      <div className="mt-0.5 truncate text-xs text-cream/35">{countryLabel(countryCode, countryName)}</div>
    </div>
  );
}
