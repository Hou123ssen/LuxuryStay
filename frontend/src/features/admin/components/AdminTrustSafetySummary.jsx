import { FiShield } from 'react-icons/fi';
import { formatNumber } from '../utils/adminDashboardFormatters';

const ITEMS = [
  ['Properties with trust badge', 'properties_with_trust_badge_count'],
  ['Properties with unresolved reports', 'properties_with_unresolved_reports_count'],
  ['Properties with serious report signals', 'properties_with_serious_report_signals_count'],
  ['Owners with high cancellation rate', 'owners_with_high_cancellation_rate_count'],
];

export default function AdminTrustSafetySummary({ trustAndSafety = {} }) {
  return (
    <section className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <div className="flex items-center gap-3">
        <span className="rounded-full border border-gold/15 bg-gold/5 p-2 text-gold">
          <FiShield size={16} />
        </span>
        <div>
          <h2 className="font-display text-2xl text-cream">Trust & Safety</h2>
          <p className="text-sm text-cream/45">Internal platform health signals.</p>
        </div>
      </div>

      <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {ITEMS.map(([label, key]) => (
          <div key={key} className="rounded-xl border border-white/5 bg-black/15 px-4 py-3">
            <div className="text-xs uppercase tracking-[0.14em] text-cream/40">{label}</div>
            <div className="mt-2 text-2xl text-cream">{formatNumber(trustAndSafety[key])}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
