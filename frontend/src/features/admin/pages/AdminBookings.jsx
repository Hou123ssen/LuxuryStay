import { useState } from 'react';
import Pagination from '../../../shared/components/common/Pagination';
import AdminBookingDetailDrawer from '../components/AdminBookingDetailDrawer';
import AdminBookingsFilters from '../components/AdminBookingsFilters';
import AdminBookingsTable from '../components/AdminBookingsTable';
import { useAdminBookingDetail } from '../hooks/useAdminBookingDetail';
import { useAdminBookings } from '../hooks/useAdminBookings';

export default function AdminBookings() {
  const [selectedBookingId, setSelectedBookingId] = useState(null);

  const {
    bookings,
    meta,
    filters,
    page,
    setPage,
    updateFilter,
    clearFilters,
    loading,
    refreshing,
    error,
    refresh,
    retry,
  } = useAdminBookings();

  const {
    booking: selectedBooking,
    loading: detailLoading,
    error: detailError,
    retry: retryDetail,
    reset: resetDetail,
  } = useAdminBookingDetail(selectedBookingId);

  const closeDrawer = () => {
    setSelectedBookingId(null);
    resetDetail();
  };

  return (
    <div className="space-y-6">
      <header className="fade-up">
        <div className="ornament-divider mb-3 max-w-sm">
          <span className="text-xs uppercase tracking-[0.3em] text-gold/55">Admin</span>
        </div>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h1 className="font-display text-4xl font-light text-cream sm:text-5xl">
              Bookings <span className="text-gold-gradient italic">Management</span>
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
              Inspect reservations, guests, properties, owners, and safety signals
            </p>
          </div>
          {meta && (
            <div className="rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
              {meta.total || 0} bookings
            </div>
          )}
        </div>
      </header>

      <AdminBookingsFilters
        filters={filters}
        onFilterChange={updateFilter}
        onClear={clearFilters}
        onRefresh={refresh}
        refreshing={refreshing}
      />

      {error && (
        <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-6 text-center">
          <p className="text-sm text-red-100">{error}</p>
          <button
            type="button"
            onClick={retry}
            className="mt-4 rounded-full border border-gold/30 px-4 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
          >
            Retry
          </button>
        </div>
      )}

      {!error && (
        <>
          <AdminBookingsTable
            bookings={bookings}
            loading={loading}
            onViewBooking={setSelectedBookingId}
          />

          <Pagination
            meta={meta}
            currentPage={page}
            onPageChange={setPage}
            disabled={loading || refreshing}
            className="mt-6"
          />
        </>
      )}

      <AdminBookingDetailDrawer
        open={Boolean(selectedBookingId)}
        booking={selectedBooking}
        loading={detailLoading}
        error={detailError}
        onClose={closeDrawer}
        onRetry={retryDetail}
      />
    </div>
  );
}
