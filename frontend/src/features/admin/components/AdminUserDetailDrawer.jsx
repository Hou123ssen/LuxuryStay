import { FiX } from 'react-icons/fi';
import AdminUserCounts from './AdminUserCounts';
import AdminUserGeoBadge from './AdminUserGeoBadge';
import AdminUserRecentActivity from './AdminUserRecentActivity';
import AdminUserRecentBookings from './AdminUserRecentBookings';
import AdminUserRecentReports from './AdminUserRecentReports';
import AdminUserRecentReviews from './AdminUserRecentReviews';
import AdminUserRoleBadge from './AdminUserRoleBadge';
import { formatAdminUserDate } from '../utils/adminUserFormatters';

function DetailSection({ title, children }) {
  return (
    <section>
      <h3 className="mb-3 font-display text-xl text-cream">{title}</h3>
      {children}
    </section>
  );
}

export default function AdminUserDetailDrawer({ open, user, loading, error, onClose, onRetry }) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        aria-label="Close user details"
        onClick={onClose}
        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
      />

      <aside className="absolute right-0 top-0 flex h-full w-full max-w-2xl flex-col border-l border-gold/15 bg-[#0a0a0f] shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-gold/10 p-5">
          <div>
            <p className="text-xs uppercase tracking-[0.24em] text-gold/55">Read-only user detail</p>
            <h2 className="mt-2 font-display text-3xl text-cream">{user?.name || 'User Details'}</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 text-cream/55 transition-colors hover:border-gold/30 hover:text-cream"
          >
            <FiX size={18} />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {loading && <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-6 text-sm text-cream/45">Loading user details...</div>}

          {!loading && error && (
            <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-6">
              <p className="text-sm text-red-100">{error}</p>
              <button
                type="button"
                onClick={onRetry}
                className="mt-4 rounded-full border border-gold/30 px-4 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
              >
                Retry
              </button>
            </div>
          )}

          {!loading && !error && user && (
            <div className="space-y-6">
              <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="truncate text-xl font-medium text-cream">{user.name}</h3>
                      <AdminUserRoleBadge role={user.role} />
                      {user.is_demo_user && <span className="rounded-full border border-amber-300/25 bg-amber-300/10 px-2 py-0.5 text-xs text-amber-100">Demo</span>}
                    </div>
                    <p className="mt-1 truncate text-sm text-cream/45">{user.email}</p>
                    <p className="mt-2 text-xs text-cream/35">Joined {formatAdminUserDate(user.created_at)}</p>
                  </div>
                </div>

                <div className="mt-5">
                  <AdminUserCounts counts={user.counts} />
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
                  <AdminUserGeoBadge
                    label="Registered location"
                    countryCode={user.registered_country_code}
                    countryName={user.registered_country_name}
                    regionName={user.registered_region_name}
                    cityName={user.registered_city_name}
                  />
                </div>
                <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
                  <AdminUserGeoBadge
                    label={`Last seen ${formatAdminUserDate(user.last_seen_at)}`}
                    countryCode={user.last_seen_country_code}
                    countryName={user.last_seen_country_name}
                    regionName={user.last_seen_region_name}
                    cityName={user.last_seen_city_name}
                  />
                </div>
              </div>

              <DetailSection title="Recent Bookings">
                <AdminUserRecentBookings rows={user.recent_bookings || []} />
              </DetailSection>

              <DetailSection title="Recent Reviews">
                <AdminUserRecentReviews rows={user.recent_reviews || []} />
              </DetailSection>

              <DetailSection title="Recent Reports">
                <AdminUserRecentReports rows={user.recent_reports || []} />
              </DetailSection>

              <DetailSection title="Recent Analytics Activity">
                <AdminUserRecentActivity rows={user.recent_analytics_activity || []} />
              </DetailSection>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}
