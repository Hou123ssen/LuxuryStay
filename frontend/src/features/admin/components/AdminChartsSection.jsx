import { FiAlertTriangle } from 'react-icons/fi';
import AdminBarChart from './AdminBarChart';
import AdminChartCard from './AdminChartCard';
import AdminChartsFilters from './AdminChartsFilters';
import AdminLineChart from './AdminLineChart';
import AdminStatusBreakdownChart from './AdminStatusBreakdownChart';
import { useAdminDashboardCharts } from '../hooks/useAdminDashboardCharts';
import {
  chartColors,
  formatChartNumber,
  normalizeSeries,
} from '../utils/adminChartFormatters';

export default function AdminChartsSection() {
  const {
    charts,
    days,
    setDays,
    loading,
    refreshing,
    error,
    refresh,
    retry,
  } = useAdminDashboardCharts('30');

  const series = charts?.series || {};
  const breakdowns = charts?.breakdowns || {};
  const totals = charts?.totals || {};

  const totalItems = [
    { label: 'Registrations', value: totals.registrations, color: chartColors.registrations },
    { label: 'Logins', value: totals.logins, color: chartColors.logins },
    { label: 'Bookings', value: totals.bookings, color: chartColors.bookings },
    { label: 'Reviews', value: totals.reviews, color: chartColors.reviews },
    { label: 'Reports', value: totals.reports, color: chartColors.reports },
  ];

  return (
    <section className="space-y-4">
      <div className="rounded-2xl border border-gold/10 bg-gradient-to-br from-white/[0.045] to-black/20 p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <span className="text-xs uppercase tracking-[0.24em] text-gold/55">Analytics</span>
            <h2 className="mt-2 font-display text-3xl text-cream">Platform Trends</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-cream/45">
              Registrations, logins, bookings, and moderation activity
            </p>
          </div>
          <AdminChartsFilters
            days={days}
            onDaysChange={setDays}
            onRefresh={refresh}
            refreshing={refreshing}
          />
        </div>
      </div>

      {loading && (
        <div className="grid gap-4 lg:grid-cols-2">
          {[1, 2, 3, 4].map((item) => (
            <div key={item} className="h-80 animate-pulse rounded-2xl border border-gold/10 bg-white/[0.03]" />
          ))}
        </div>
      )}

      {!loading && error && (
        <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-center">
          <FiAlertTriangle className="mx-auto text-red-100" size={24} />
          <p className="mt-3 text-sm text-red-100">Dashboard charts could not be loaded.</p>
          <button
            type="button"
            onClick={retry}
            className="mt-4 rounded-full border border-gold/30 px-4 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
          >
            Retry
          </button>
        </div>
      )}

      {!loading && !error && (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {totalItems.map((item) => (
              <div key={item.label} className="rounded-2xl border border-white/5 bg-black/20 p-4">
                <div className="text-xs uppercase tracking-[0.16em] text-cream/35">{item.label}</div>
                <div className="mt-2 text-2xl text-cream">{formatChartNumber(item.value)}</div>
                <div className="mt-3 h-1 rounded-full" style={{ backgroundColor: item.color }} />
              </div>
            ))}
          </div>

          <div className="grid gap-4 xl:grid-cols-2">
            <AdminChartCard title="Registrations" subtitle="New accounts created over the selected period">
              <AdminLineChart
                lines={[{
                  label: 'Registrations',
                  data: normalizeSeries(series.registrations),
                  color: chartColors.registrations,
                }]}
              />
            </AdminChartCard>

            <AdminChartCard title="Login Activity" subtitle="Authenticated platform activity over time">
              <AdminLineChart
                lines={[{
                  label: 'Logins',
                  data: normalizeSeries(series.logins),
                  color: chartColors.logins,
                }]}
              />
            </AdminChartCard>

            <AdminChartCard title="Booking Activity" subtitle="Booking records created over time">
              <AdminLineChart
                lines={[{
                  label: 'Bookings',
                  data: normalizeSeries(series.bookings),
                  color: chartColors.bookings,
                }]}
              />
            </AdminChartCard>

            <AdminChartCard title="Reviews vs Reports" subtitle="Moderation-related activity over time">
              <AdminLineChart
                lines={[
                  {
                    label: 'Reviews',
                    data: normalizeSeries(series.reviews),
                    color: chartColors.reviews,
                  },
                  {
                    label: 'Reports',
                    data: normalizeSeries(series.reports),
                    color: chartColors.reports,
                  },
                ]}
              />
            </AdminChartCard>
          </div>

          <div className="grid gap-4 xl:grid-cols-3">
            <AdminChartCard title="Bookings by Status">
              <AdminStatusBreakdownChart breakdown={breakdowns.bookings_by_status} />
            </AdminChartCard>

            <AdminChartCard title="Reviews by Status">
              <AdminStatusBreakdownChart breakdown={breakdowns.reviews_by_status} />
            </AdminChartCard>

            <AdminChartCard title="Reports by Status">
              <AdminStatusBreakdownChart breakdown={breakdowns.reports_by_status} />
            </AdminChartCard>
          </div>

          <AdminChartCard title="Period Totals" subtitle="Compact comparison of aggregate activity">
            <AdminBarChart items={totalItems} />
          </AdminChartCard>
        </>
      )}
    </section>
  );
}
