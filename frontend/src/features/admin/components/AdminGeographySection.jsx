import { FiGlobe } from 'react-icons/fi';
import { useAdminGeographyAnalytics } from '../hooks/useAdminGeographyAnalytics';
import AdminCityUsageTable from './AdminCityUsageTable';
import AdminCountryUsageTable from './AdminCountryUsageTable';
import AdminGeoSummaryCards from './AdminGeoSummaryCards';
import AdminDemoDataNotice from './AdminDemoDataNotice';
import AdminGeographyFilters from './AdminGeographyFilters';
import AdminRecentGeoActivity from './AdminRecentGeoActivity';
import AdminWorldUsageMap from './AdminWorldUsageMap';

export default function AdminGeographySection({ includeDemo = true, onIncludeDemoChange }) {
  const {
    days,
    setDays,
    geography,
    meta,
    loading,
    refreshing,
    error,
    refresh,
    retry,
  } = useAdminGeographyAnalytics('30', includeDemo);

  if (loading) {
    return (
      <section className="rounded-2xl border border-gold/10 bg-white/[0.03] p-6">
        <div className="text-sm text-cream/45">Loading geography analytics...</div>
      </section>
    );
  }

  if (error) {
    return (
      <section className="rounded-2xl border border-red-400/20 bg-red-500/10 p-6">
        <h2 className="font-display text-2xl text-cream">Geography & Usage</h2>
        <p className="mt-2 text-sm text-red-100">{error}</p>
        <button
          type="button"
          onClick={retry}
          className="mt-4 rounded-full border border-gold/30 px-4 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
        >
          Retry
        </button>
      </section>
    );
  }

  const summary = geography?.summary || {};

  return (
    <section className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex items-start gap-3">
          <span className="rounded-full border border-gold/15 bg-gold/5 p-2 text-gold">
            <FiGlobe size={17} />
          </span>
          <div>
            <h2 className="font-display text-2xl text-cream">Geography & Usage</h2>
            <p className="mt-1 text-sm text-cream/45">
              Country and city-level platform usage.
            </p>
          </div>
        </div>

        <AdminGeographyFilters
          days={days}
          onDaysChange={setDays}
          onRefresh={refresh}
          refreshing={refreshing}
        />
      </div>

      <div className="mt-5 space-y-5">
        <AdminDemoDataNotice
          demoData={meta?.demo_data}
          includeDemo={includeDemo}
          onIncludeDemoChange={onIncludeDemoChange}
        />
        <AdminGeoSummaryCards summary={summary} />

        <AdminWorldUsageMap
          usageRows={geography?.usage_events_by_country || []}
          loginRows={geography?.login_events_by_country || []}
          registrationRows={geography?.registration_events_by_country || []}
        />

        <div className="grid gap-4 xl:grid-cols-3">
          <AdminCountryUsageTable
            title="Top Countries"
            rows={geography?.usage_events_by_country || []}
          />
          <AdminCountryUsageTable
            title="Login Countries"
            rows={geography?.login_events_by_country || []}
          />
          <AdminCountryUsageTable
            title="Registration Countries"
            rows={geography?.registration_events_by_country || []}
          />
        </div>

        <div className="grid gap-4 xl:grid-cols-3">
          <AdminCityUsageTable
            title="Top Cities"
            rows={geography?.usage_events_by_city || []}
          />
          <AdminCityUsageTable
            title="Login Cities"
            rows={geography?.login_events_by_city || []}
          />
          <AdminCityUsageTable
            title="Registration Cities"
            rows={geography?.registration_events_by_city || []}
          />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <AdminCityUsageTable
            title="Registered User Cities"
            rows={geography?.users_by_registered_city || []}
            emptyLabel="No registered user city data yet."
          />
          <AdminCityUsageTable
            title="Last-Seen User Cities"
            rows={geography?.users_by_last_seen_city || []}
            emptyLabel="No last-seen city data yet."
          />
        </div>

        <AdminRecentGeoActivity rows={geography?.recent_country_activity || []} />
      </div>
    </section>
  );
}
