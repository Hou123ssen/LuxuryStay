import { FiCalendar, FiImage, FiMessageSquare, FiStar } from 'react-icons/fi';
import { formatAdminPropertyCount } from '../utils/adminPropertyFormatters';

const countItems = [
  ['bookings_count', 'Bookings', FiCalendar],
  ['reviews_count', 'Reviews', FiStar],
  ['reports_count', 'Reports', FiMessageSquare],
  ['images_count', 'Images', FiImage],
];

export default function AdminPropertyCounts({ counts = {}, compact = false }) {
  return (
    <div className={`grid gap-2 ${compact ? 'grid-cols-2' : 'grid-cols-2 sm:grid-cols-4'}`}>
      {countItems.map(([key, label, Icon]) => (
        <div key={key} className="rounded-xl border border-white/5 bg-black/20 px-3 py-2">
          <div className="flex items-center gap-2 text-cream/40">
            <Icon size={13} />
            <span className="text-[11px] uppercase tracking-[0.12em]">{label}</span>
          </div>
          <div className="mt-1 text-sm font-medium text-cream">{formatAdminPropertyCount(counts?.[key])}</div>
        </div>
      ))}
    </div>
  );
}
