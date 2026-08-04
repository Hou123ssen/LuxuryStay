import { NavLink } from 'react-router-dom';
import { FiAlertTriangle, FiBarChart2, FiBookOpen, FiChevronLeft, FiChevronRight, FiGlobe, FiHome, FiStar, FiUsers } from 'react-icons/fi';

const NAV_ITEMS = [
  { to: '/admin', label: 'Overview', icon: FiBarChart2, end: true },
  { to: '/admin/geography', label: 'Geography & Usage', icon: FiGlobe },
  { to: '/admin/users', label: 'Users', icon: FiUsers },
  { to: '/admin/properties', label: 'Properties', icon: FiHome },
  { to: '/admin/bookings', label: 'Bookings', icon: FiBookOpen },
  { to: '/admin/reports', label: 'Reports Moderation', icon: FiAlertTriangle },
  { to: '/admin/reviews', label: 'Reviews Moderation', icon: FiStar },
];

const linkClasses = (isActive, collapsed = false) =>
  `group relative inline-flex items-center rounded-xl border text-sm transition-all ${
    collapsed ? 'h-11 w-11 justify-center px-0' : 'gap-3 px-3 py-2.5'
  } ${
    isActive
      ? 'border-gold/40 bg-gold/10 text-gold shadow-[inset_3px_0_0_rgba(201,168,76,0.95),0_0_24px_rgba(201,168,76,0.12)]'
      : 'border-transparent text-cream/55 hover:border-gold/15 hover:bg-white/[0.05] hover:text-cream'
  }`;

function AdminNavItems({ collapsed = false, mobile = false }) {
  return NAV_ITEMS.map((item) => {
    const Icon = item.icon;

    return (
      <NavLink
        key={item.to}
        to={item.to}
        end={item.end}
        title={collapsed ? item.label : undefined}
        className={({ isActive }) =>
          mobile
            ? `inline-flex min-w-fit items-center gap-2 rounded-full border px-3 py-2 text-sm transition-all ${
                isActive
                  ? 'border-gold/40 bg-gold/10 text-gold'
                  : 'border-white/5 bg-black/10 text-cream/55 hover:border-gold/20 hover:text-cream'
              }`
            : linkClasses(isActive, collapsed)
        }
      >
        <Icon size={17} className="shrink-0" />
        {!collapsed && <span className="truncate">{item.label}</span>}
      </NavLink>
    );
  });
}

export default function AdminSidebar({ collapsed = false, onToggle, variant = 'desktop' }) {
  if (variant === 'mobile') {
    return (
      <div className="sticky top-16 z-30 border-b border-gold/10 bg-[#0a0a0f]/95 px-4 py-3 backdrop-blur-xl">
        <div className="mb-2 flex items-center justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.24em] text-gold/55">Admin Center</p>
            <p className="text-xs text-cream/35">Platform controls</p>
          </div>
        </div>
        <nav aria-label="Admin navigation" className="flex gap-2 overflow-x-auto pb-1">
          <AdminNavItems mobile />
        </nav>
      </div>
    );
  }

  return (
    <aside
      className={`sticky top-16 flex h-[calc(100vh-4rem)] shrink-0 flex-col border-r border-gold/10 bg-[#0a0a0f]/95 px-3 py-4 backdrop-blur-xl transition-[width] duration-300 ${
        collapsed ? 'w-[72px]' : 'w-60'
      }`}
    >
      <div className={`mb-5 flex items-start ${collapsed ? 'justify-center' : 'justify-between gap-3'}`}>
        {!collapsed && (
          <div className="min-w-0 px-1">
            <p className="text-xs uppercase tracking-[0.24em] text-gold/55">Admin Center</p>
            <p className="mt-1 truncate text-sm text-cream/40">Platform controls</p>
          </div>
        )}
        <button
          type="button"
          onClick={onToggle}
          aria-label={collapsed ? 'Expand admin sidebar' : 'Collapse admin sidebar'}
          title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gold/20 bg-gold/5 text-gold transition-colors hover:border-gold/45 hover:bg-gold/10"
        >
          {collapsed ? <FiChevronRight size={17} /> : <FiChevronLeft size={17} />}
        </button>
      </div>

      <nav aria-label="Admin navigation" className="flex flex-1 flex-col gap-2">
        <AdminNavItems collapsed={collapsed} />
      </nav>

      {!collapsed && (
        <div className="rounded-xl border border-gold/10 bg-gold/[0.04] px-3 py-3 text-xs leading-5 text-cream/40">
          Internal tools for moderation, analytics, and platform health.
        </div>
      )}
    </aside>
  );
}
