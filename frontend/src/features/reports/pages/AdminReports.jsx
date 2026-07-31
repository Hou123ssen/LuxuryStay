import { FiShield } from 'react-icons/fi';
import Pagination from '../../../shared/components/common/Pagination';
import AdminReportDetailModal from '../components/AdminReportDetailModal';
import AdminReportFilters from '../components/AdminReportFilters';
import AdminReportTable from '../components/AdminReportTable';
import { useAdminReports } from '../hooks/useAdminReports';

export default function AdminReports() {
  const {
    reports,
    meta,
    loading,
    error,
    page,
    filters,
    selectedReport,
    detailLoading,
    actionLoading,
    goToPage,
    updateFilter,
    resetFilters,
    openReport,
    closeReport,
    moderateReport,
  } = useAdminReports();

  return (
    <div className="min-h-screen px-4 py-10">
      <div className="mx-auto max-w-6xl">
        <div className="mb-8 fade-up">
          <div className="ornament-divider mb-3 max-w-sm">
            <span className="text-xs uppercase tracking-[0.3em] text-gold/55">Admin</span>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="font-display text-4xl font-light text-cream sm:text-5xl">
                Reports <span className="text-gold-gradient italic">Dashboard</span>
              </h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
                Review guest reports, update moderation status, and keep internal notes without exposing private data.
              </p>
            </div>
            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
              <FiShield size={15} />
              Admin only
            </div>
          </div>
        </div>

        <div className="space-y-5">
          <AdminReportFilters
            filters={filters}
            onFilterChange={updateFilter}
            onReset={resetFilters}
            disabled={loading}
          />

          {error && (
            <div className="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-100">
              {error}
            </div>
          )}

          <AdminReportTable reports={reports} loading={loading} onOpenReport={openReport} />

          <Pagination
            meta={meta}
            currentPage={page}
            onPageChange={goToPage}
            disabled={loading}
            className="mt-6"
          />
        </div>
      </div>

      <AdminReportDetailModal
        report={selectedReport}
        loading={detailLoading}
        actionLoading={actionLoading}
        onClose={closeReport}
        onModerate={moderateReport}
      />
    </div>
  );
}
