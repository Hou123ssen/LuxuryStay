import { FiAlertTriangle, FiMessageSquare, FiStar } from 'react-icons/fi';
import { formatAdminBookingCount } from '../utils/adminBookingFormatters';

export default function AdminBookingSignals({ signals = {}, compact = false }) {
  const items = [
    { label: 'Review', value: signals.has_review ? 'Yes' : 'No', icon: FiStar },
    { label: 'Reviews', value: formatAdminBookingCount(signals.reviews_count), icon: FiMessageSquare },
    { label: 'Reports', value: formatAdminBookingCount(signals.reports_count), icon: FiAlertTriangle },
  ];

  return (
    <div className={`grid gap-2 ${compact ? 'grid-cols-3' : 'sm:grid-cols-3'}`}>
      {items.map((item) => {
        const Icon = item.icon;
        return (
          <div key={item.label} className="rounded-xl border border-white/5 bg-black/20 px-3 py-2">
            <div className="flex items-center gap-2 text-cream/40">
              <Icon size={13} />
              <span className="text-[11px] uppercase tracking-[0.12em]">{item.label}</span>
            </div>
            <div className="mt-1 text-sm font-medium text-cream">{item.value}</div>
          </div>
        );
      })}
    </div>
  );
}
