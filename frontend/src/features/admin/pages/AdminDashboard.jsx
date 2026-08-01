import { FiRefreshCw, FiShield } from 'react-icons/fi';
import AdminAlertList from '../components/AdminAlertList';
import AdminBookingSummary from '../components/AdminBookingSummary';
import AdminOverviewStats from '../components/AdminOverviewStats';
import AdminQuickActions from '../components/AdminQuickActions';
import AdminRecentActivity from '../components/AdminRecentActivity';
import AdminTrustSafetySummary from '../components/AdminTrustSafetySummary';
import { useAdminDashboardOverview } from '../hooks/useAdminDashboardOverview';

export default function AdminDashboard() {
  const { overview, loading, refreshing, error, refresh, retry } = useAdminDashboardOverview();

  if (loading) {
    return (
      <div className="min-h-screen px-4 py-10">
        <div className="mx-auto max-w-6xl">
          <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-8 text-center text-cream/60">
            Loading platform overview...
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen px-4 py-10">
        <div className="mx-auto max-w-3xl rounded-2xl border border-red-500/20 bg-red-500/10 p-6 text-center">
          <h1 className="font-display text-3xl text-cream">Admin Dashboard</h1>
          <p className="mt-3 text-sm text-red-100">{error}</p>
          <button
            type="button"
            onClick={retry}
            className="mt-5 rounded-full border border-gold/30 px-4 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  const totals = overview?.totals || {};
  const bookings = overview?.bookings || {};
  const moderation = overview?.moderation || {};
  const trustAndSafety = overview?.trust_and_safety || {};
  const recentActivity = overview?.recent_activity || {};
  const alerts = overview?.alerts || [];

  return (
    <div className="min-h-screen px-4 py-10">
      <div className="mx-auto max-w-7xl space-y-6">
        <header className="fade-up">
          <div className="ornament-divider mb-3 max-w-sm">
            <span className="text-xs uppercase tracking-[0.3em] text-gold/55">Admin</span>
          </div>
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="font-display text-4xl font-light text-cream sm:text-5xl">
                Admin <span className="text-gold-gradient italic">Dashboard</span>
              </h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
                Platform control center
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <div className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
                <FiShield size={15} />
                Admin only
              </div>
              <button
                type="button"
                onClick={refresh}
                disabled={refreshing}
                className="inline-flex items-center gap-2 rounded-full border border-gold/30 px-3 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <FiRefreshCw size={15} className={refreshing ? 'animate-spin' : ''} />
                {refreshing ? 'Refreshing' : 'Refresh'}
              </button>
            </div>
          </div>
        </header>

        <AdminOverviewStats totals={totals} moderation={moderation} />
        <AdminAlertList alerts={alerts} />
        <AdminBookingSummary bookings={bookings} moderation={moderation} />
        <AdminTrustSafetySummary trustAndSafety={trustAndSafety} />
        <AdminRecentActivity activity={recentActivity} />
        <AdminQuickActions />
      </div>
    </div>
  );
}
