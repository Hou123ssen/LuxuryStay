import { FiDatabase } from 'react-icons/fi';

export default function AdminDemoDataNotice({ demoData, includeDemo, onIncludeDemoChange }) {
  const available = Boolean(demoData?.available);
  const included = Boolean(demoData?.included);

  if (!available) {
    return null;
  }

  return (
    <div className={`rounded-2xl border p-4 ${
      included
        ? 'border-amber-300/25 bg-amber-300/10'
        : 'border-white/10 bg-white/[0.03]'
    }`}>
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex items-start gap-3">
          <span className={`rounded-full border p-2 ${
            included
              ? 'border-amber-300/25 bg-amber-300/10 text-amber-100'
              : 'border-white/10 bg-white/5 text-cream/60'
          }`}>
            <FiDatabase size={16} />
          </span>
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="font-display text-xl text-cream">
                {included ? 'Demo data included' : 'Demo data excluded'}
              </h3>
              <span className="rounded-full border border-gold/20 bg-gold/10 px-2.5 py-1 text-xs font-medium text-gold">
                {Number(demoData.demo_events_count || 0).toLocaleString()} demo events
              </span>
            </div>
            <p className="mt-1 text-sm leading-6 text-cream/50">
              {demoData.message || 'Local demo analytics data is available for testing dashboard analytics.'}
            </p>
          </div>
        </div>

        <label className="flex w-fit cursor-pointer items-center gap-3 rounded-full border border-gold/20 bg-black/20 px-3 py-2 text-sm text-cream/75">
          <input
            type="checkbox"
            checked={includeDemo}
            onChange={(event) => onIncludeDemoChange(event.target.checked)}
            className="h-4 w-4 accent-gold"
          />
          Include demo data
        </label>
      </div>
    </div>
  );
}
