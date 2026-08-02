import { useState } from 'react';
import Pagination from '../../../shared/components/common/Pagination';
import AdminDemoDataNotice from '../components/AdminDemoDataNotice';
import AdminUserDetailDrawer from '../components/AdminUserDetailDrawer';
import AdminUsersFilters from '../components/AdminUsersFilters';
import AdminUsersTable from '../components/AdminUsersTable';
import { useAdminDemoDataPreference } from '../hooks/useAdminDemoDataPreference';
import { useAdminUserDetail } from '../hooks/useAdminUserDetail';
import { useAdminUsers } from '../hooks/useAdminUsers';

export default function AdminUsers() {
  const [includeDemo, setIncludeDemo] = useAdminDemoDataPreference(true);
  const [selectedUserId, setSelectedUserId] = useState(null);

  const {
    users,
    meta,
    demoMeta,
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
  } = useAdminUsers(includeDemo);

  const {
    user: selectedUser,
    loading: detailLoading,
    error: detailError,
    retry: retryDetail,
    reset: resetDetail,
  } = useAdminUserDetail(selectedUserId, includeDemo);

  const closeDrawer = () => {
    setSelectedUserId(null);
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
              Users <span className="text-gold-gradient italic">Management</span>
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
              Inspect users, activity, and platform participation safely
            </p>
          </div>
          {meta && (
            <div className="rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
              {meta.total || 0} users
            </div>
          )}
        </div>
      </header>

      <AdminDemoDataNotice
        demoData={demoMeta}
        includeDemo={includeDemo}
        onIncludeDemoChange={setIncludeDemo}
      />

      <AdminUsersFilters
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
          <AdminUsersTable
            users={users}
            loading={loading}
            onViewUser={setSelectedUserId}
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

      <AdminUserDetailDrawer
        open={Boolean(selectedUserId)}
        user={selectedUser}
        loading={detailLoading}
        error={detailError}
        onClose={closeDrawer}
        onRetry={retryDetail}
      />
    </div>
  );
}
