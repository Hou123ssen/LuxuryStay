import { useState } from 'react';
import Pagination from '../../../shared/components/common/Pagination';
import AdminPropertiesFilters from '../components/AdminPropertiesFilters';
import AdminPropertiesTable from '../components/AdminPropertiesTable';
import AdminPropertyDetailDrawer from '../components/AdminPropertyDetailDrawer';
import { useAdminProperties } from '../hooks/useAdminProperties';
import { useAdminPropertyDetail } from '../hooks/useAdminPropertyDetail';

export default function AdminProperties() {
  const [selectedPropertyId, setSelectedPropertyId] = useState(null);

  const {
    properties,
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
  } = useAdminProperties();

  const {
    property: selectedProperty,
    loading: detailLoading,
    error: detailError,
    retry: retryDetail,
    reset: resetDetail,
  } = useAdminPropertyDetail(selectedPropertyId);

  const closeDrawer = () => {
    setSelectedPropertyId(null);
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
              Properties <span className="text-gold-gradient italic">Management</span>
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
              Inspect properties, owners, bookings, reviews, and safety signals
            </p>
          </div>
          {meta && (
            <div className="rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
              {meta.total || 0} properties
            </div>
          )}
        </div>
      </header>

      <AdminPropertiesFilters
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
          <AdminPropertiesTable
            properties={properties}
            loading={loading}
            onViewProperty={setSelectedPropertyId}
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

      <AdminPropertyDetailDrawer
        open={Boolean(selectedPropertyId)}
        property={selectedProperty}
        loading={detailLoading}
        error={detailError}
        onClose={closeDrawer}
        onRetry={retryDetail}
      />
    </div>
  );
}
