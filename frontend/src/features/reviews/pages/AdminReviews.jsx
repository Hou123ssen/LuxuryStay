import { FiShield } from 'react-icons/fi';
import Pagination from '../../../shared/components/common/Pagination';
import AdminReviewDetailModal from '../components/AdminReviewDetailModal';
import AdminReviewFilters from '../components/AdminReviewFilters';
import AdminReviewTable from '../components/AdminReviewTable';
import { useAdminReviews } from '../hooks/useAdminReviews';

export default function AdminReviews() {
  const {
    reviews,
    meta,
    loading,
    error,
    page,
    filters,
    selectedReview,
    detailLoading,
    actionLoading,
    goToPage,
    updateFilter,
    resetFilters,
    openReview,
    closeReview,
    moderateReview,
  } = useAdminReviews();

  return (
    <div>
      <div className="max-w-6xl">
        <div className="mb-8 fade-up">
          <div className="ornament-divider mb-3 max-w-sm">
            <span className="text-xs uppercase tracking-[0.3em] text-gold/55">Admin</span>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="font-display text-4xl font-light text-cream sm:text-5xl">
                Review <span className="text-gold-gradient italic">Moderation</span>
              </h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
                Review flagged guest feedback, publish valid reviews, and reject harmful reviews without exposing private signals.
              </p>
            </div>
            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
              <FiShield size={15} />
              Admin only
            </div>
          </div>
        </div>

        <div className="space-y-5">
          <AdminReviewFilters
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

          <AdminReviewTable reviews={reviews} loading={loading} onOpenReview={openReview} />

          <Pagination
            meta={meta}
            currentPage={page}
            onPageChange={goToPage}
            disabled={loading}
            className="mt-6"
          />
        </div>
      </div>

      <AdminReviewDetailModal
        review={selectedReview}
        loading={detailLoading}
        actionLoading={actionLoading}
        onClose={closeReview}
        onModerate={moderateReview}
      />
    </div>
  );
}
