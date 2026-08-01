import { FiActivity, FiMapPin, FiNavigation, FiUsers } from 'react-icons/fi';
import { formatGeoNumber } from '../utils/adminGeographyFormatters';

const cards = [
  ['Registered countries', 'known_registered_country_users_count', FiUsers],
  ['Unknown registered', 'unknown_registered_country_users_count', FiMapPin],
  ['Last-seen users', 'known_last_seen_country_users_count', FiNavigation],
  ['Unknown last-seen', 'unknown_last_seen_country_users_count', FiMapPin],
  ['Usage events', 'usage_events_count', FiActivity],
  ['Known city users', 'known_last_seen_city_users_count', FiMapPin],
];

export default function AdminGeoSummaryCards({ summary = {} }) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
      {cards.map(([label, key, Icon]) => (
        <div key={key} className="rounded-2xl border border-white/5 bg-black/20 p-4">
          <div className="flex items-center justify-between gap-3">
            <span className="text-xs uppercase tracking-[0.14em] text-cream/40">{label}</span>
            <span className="rounded-full border border-gold/15 bg-gold/5 p-2 text-gold">
              <Icon size={14} />
            </span>
          </div>
          <div className="mt-3 font-display text-2xl text-cream">{formatGeoNumber(summary[key])}</div>
        </div>
      ))}
    </div>
  );
}
