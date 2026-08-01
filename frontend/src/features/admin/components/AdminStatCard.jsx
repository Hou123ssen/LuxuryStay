import { formatNumber } from '../utils/adminDashboardFormatters';

export default function AdminStatCard({ label, value, icon: Icon, tone = 'default' }) {
  const toneClasses = {
    default: 'border-gold/10 bg-white/[0.03]',
    warning: 'border-amber-400/25 bg-amber-400/10',
    critical: 'border-red-400/25 bg-red-500/10',
  };

  return (
    <div className={`rounded-2xl border p-4 ${toneClasses[tone] || toneClasses.default}`}>
      <div className="mb-3 flex items-center justify-between gap-3">
        <span className="text-xs uppercase tracking-[0.18em] text-cream/45">{label}</span>
        {Icon && (
          <span className="rounded-full border border-gold/15 bg-gold/5 p-2 text-gold">
            <Icon size={15} />
          </span>
        )}
      </div>
      <div className="font-display text-3xl text-cream">{formatNumber(value)}</div>
    </div>
  );
}
