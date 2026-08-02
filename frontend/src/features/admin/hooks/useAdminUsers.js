import { useCallback, useEffect, useMemo, useState } from 'react';
import { adminUsersApi } from '../api/adminUsersApi';
import { initialUserFilters } from '../utils/adminUserFormatters';

export function useAdminUsers(includeDemo = true) {
  const [filters, setFilters] = useState(initialUserFilters);
  const [debouncedSearch, setDebouncedSearch] = useState(initialUserFilters.search);
  const [page, setPage] = useState(1);
  const [users, setUsers] = useState([]);
  const [meta, setMeta] = useState(null);
  const [demoMeta, setDemoMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(filters.search);
      setPage(1);
    }, 350);

    return () => window.clearTimeout(timer);
  }, [filters.search]);

  const params = useMemo(() => {
    const next = {
      ...filters,
      search: debouncedSearch,
      page,
      include_demo: includeDemo,
    };

    return Object.fromEntries(
      Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined)
    );
  }, [debouncedSearch, filters, includeDemo, page]);

  const loadUsers = useCallback(async ({ silent = false } = {}) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setError('');

    try {
      const response = await adminUsersApi.getAdminUsers(params);
      setUsers(response.data?.data || []);
      setMeta(response.data?.meta || null);
      setDemoMeta(response.data?.meta?.demo_data || null);
    } catch (err) {
      setError('Users could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [params]);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const updateFilter = useCallback((key, value) => {
    setFilters((current) => ({ ...current, [key]: value }));
    if (key !== 'search') setPage(1);
  }, []);

  const clearFilters = useCallback(() => {
    setFilters(initialUserFilters);
    setDebouncedSearch('');
    setPage(1);
  }, []);

  return {
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
    refresh: () => loadUsers({ silent: true }),
    retry: () => loadUsers(),
  };
}
