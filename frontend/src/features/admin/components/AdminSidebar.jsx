import { NavLink } from 'react-router-dom';
import { FiAlertTriangle, FiBarChart2, FiGlobe, FiStar } from 'react-icons/fi';

const NAV_ITEMS = [
  { to: '/admin', label: 'Overview', icon: FiBarChart2, end: true },
  { to: '/admin/geography', label: 'Geography & Usage', icon: FiGlobe },
  { to: '/admin/reports', label: 'Reports Moderation', icon: FiAlertTriangle },
  { to: '/admin/reviews', label: 'Reviews Moderation', icon: FiStar },
];

export default function AdminSidebar() {
  return (
    <aside className="rounded-2xl border border-gold/10 bg-white/[0.03] p-3 lg:sticky lg:top-20">
      <div className="mb-3 hidden px-3 lg:block">
        <p className="text-xs uppercase tracking-[0.28em] text-gold/50">Admin Center</p>
        <p className="mt-1 text-sm text-cream/40">Platform controls</p>
      </div>

      <nav aria-label="Admin navigation" className="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
        {NAV_ITEMS.map((item) => {
          const Icon = item.icon;

          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `inline-flex min-w-fit items-center gap-2 rounded-xl border px-3 py-2 text-sm transition-all lg:w-full ${
                  isActive
                    ? 'border-gold/35 bg-gold/10 text-gold shadow-[0_0_22px_rgba(201,168,76,0.12)]'
                    : 'border-transparent text-cream/55 hover:border-gold/15 hover:bg-white/[0.04] hover:text-cream'
                }`
              }
            >
              <Icon size={15} />
              <span>{item.label}</span>
            </NavLink>
          );
        })}
      </nav>
    </aside>
  );
}
