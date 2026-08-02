import { useCallback, useEffect, useMemo, useState } from 'react';
import { adminPropertiesApi } from '../api/adminPropertiesApi';
import { initialPropertyFilters } from '../utils/adminPropertyFormatters';

export function useAdminProperties() {
  const [filters, setFilters] = useState(initialPropertyFilters);
  const [debouncedSearch, setDebouncedSearch] = useState(initialPropertyFilters.search);
  const [page, setPage] = useState(1);
  const [properties, setProperties] = useState([]);
  const [meta, setMeta] = useState(null);
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
    };

    return Object.fromEntries(
      Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined)
    );
  }, [debouncedSearch, filters, page]);

  const loadProperties = useCallback(async ({ silent = false } = {}) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setError('');

    try {
      const response = await adminPropertiesApi.getAdminProperties(params);
      setProperties(response.data?.data || []);
      setMeta(response.data?.meta || null);
    } catch (err) {
      setError('Properties could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [params]);

  useEffect(() => {
    loadProperties();
  }, [loadProperties]);

  const updateFilter = useCallback((key, value) => {
    setFilters((current) => ({ ...current, [key]: value }));
    if (key !== 'search') setPage(1);
  }, []);

  const clearFilters = useCallback(() => {
    setFilters(initialPropertyFilters);
    setDebouncedSearch('');
    setPage(1);
  }, []);

  return {
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
    refresh: () => loadProperties({ silent: true }),
    retry: () => loadProperties(),
  };
}
