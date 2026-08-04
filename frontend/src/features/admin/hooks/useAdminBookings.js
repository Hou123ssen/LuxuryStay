import { useCallback, useEffect, useMemo, useState } from 'react';
import { adminBookingsApi } from '../api/adminBookingsApi';
import { initialBookingFilters } from '../utils/adminBookingFormatters';

export function useAdminBookings() {
  const [filters, setFilters] = useState(initialBookingFilters);
  const [debouncedSearch, setDebouncedSearch] = useState(initialBookingFilters.search);
  const [page, setPage] = useState(1);
  const [bookings, setBookings] = useState([]);
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
    const next = { ...filters, search: debouncedSearch, page };

    return Object.fromEntries(
      Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined)
    );
  }, [debouncedSearch, filters, page]);

  const loadBookings = useCallback(async ({ silent = false } = {}) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setError('');

    try {
      const response = await adminBookingsApi.getAdminBookings(params);
      setBookings(response.data?.data || []);
      setMeta(response.data?.meta || null);
    } catch (err) {
      setError('Bookings could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [params]);

  useEffect(() => {
    loadBookings();
  }, [loadBookings]);

  const updateFilter = useCallback((key, value) => {
    setFilters((current) => ({ ...current, [key]: value }));
    if (key !== 'search') setPage(1);
  }, []);

  const clearFilters = useCallback(() => {
    setFilters(initialBookingFilters);
    setDebouncedSearch('');
    setPage(1);
  }, []);

  return {
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
    refresh: () => loadBookings({ silent: true }),
    retry: () => loadBookings(),
  };
}
