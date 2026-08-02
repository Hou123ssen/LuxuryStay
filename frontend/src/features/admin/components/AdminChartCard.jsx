export default function AdminChartCard({ title, subtitle, children, action }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5 shadow-[0_18px_60px_rgba(0,0,0,0.22)]">
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h3 className="font-display text-xl text-cream">{title}</h3>
          {subtitle && <p className="mt-1 text-xs leading-5 text-cream/45">{subtitle}</p>}
        </div>
        {action}
      </div>
      {children}
    </div>
  );
}
