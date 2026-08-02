import { useCallback, useEffect, useState } from 'react';
import { adminDashboardApi } from '../api/adminDashboardApi';

export function useAdminDashboardOverview(includeDemo = true) {
  const [overview, setOverview] = useState(null);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const loadOverview = useCallback(async ({ silent = false } = {}) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setError('');

    try {
      const response = await adminDashboardApi.getAdminDashboardOverview({ include_demo: includeDemo });
      setOverview(response.data?.data || null);
      setMeta(response.data?.meta || null);
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to load admin dashboard overview.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [includeDemo]);

  useEffect(() => {
    loadOverview();
  }, [loadOverview]);

  return {
    overview,
    meta,
    loading,
    refreshing,
    error,
    refresh: () => loadOverview({ silent: true }),
    retry: () => loadOverview(),
  };
}
