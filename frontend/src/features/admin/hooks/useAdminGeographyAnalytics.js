import { useCallback, useEffect, useState } from 'react';
import { adminGeographyApi } from '../api/adminGeographyApi';

export function useAdminGeographyAnalytics(initialDays = '30') {
  const [days, setDays] = useState(initialDays);
  const [geography, setGeography] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const loadGeography = useCallback(async ({ silent = false } = {}) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setError('');

    try {
      const response = await adminGeographyApi.getAdminGeographyAnalytics({ days });
      setGeography(response.data?.data || null);
    } catch (err) {
      setError('Geography analytics could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [days]);

  useEffect(() => {
    loadGeography();
  }, [loadGeography]);

  return {
    days,
    setDays,
    geography,
    loading,
    refreshing,
    error,
    refresh: () => loadGeography({ silent: true }),
    retry: () => loadGeography(),
  };
}
