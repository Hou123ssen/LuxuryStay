import { formatAdminUserCount } from '../utils/adminUserFormatters';

const COUNT_ITEMS = [
  ['Properties', 'properties_count'],
  ['Bookings', 'bookings_count'],
  ['Reviews', 'reviews_count'],
  ['Reports', 'reports_count'],
];

export default function AdminUserCounts({ counts = {}, compact = false }) {
  return (
    <div className={`grid gap-2 ${compact ? 'grid-cols-2' : 'grid-cols-4'}`}>
      {COUNT_ITEMS.map(([label, key]) => (
        <div key={key} className="rounded-xl border border-white/5 bg-black/20 px-3 py-2">
          <div className="text-[10px] uppercase tracking-[0.14em] text-cream/35">{label}</div>
          <div className="mt-1 text-sm font-medium text-cream">{formatAdminUserCount(counts[key])}</div>
        </div>
      ))}
    </div>
  );
}
