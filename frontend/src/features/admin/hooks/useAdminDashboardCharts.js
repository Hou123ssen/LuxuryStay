import { useCallback, useEffect, useState } from 'react';
import { adminChartsApi } from '../api/adminChartsApi';

export function useAdminDashboardCharts(defaultDays = '30', includeDemo = true) {
  const [days, setDays] = useState(defaultDays);
  const [charts, setCharts] = useState(null);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const loadCharts = useCallback(async ({ silent = false, selectedDays = days } = {}) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    setError('');

    try {
      const response = await adminChartsApi.getAdminDashboardCharts({
        days: selectedDays,
        include_demo: includeDemo,
      });
      setCharts(response.data?.data || null);
      setMeta(response.data?.meta || null);
    } catch (err) {
      setError(err.response?.data?.message || 'Dashboard charts could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [days, includeDemo]);

  useEffect(() => {
    loadCharts({ selectedDays: days });
  }, [days, loadCharts]);

  return {
    charts,
    meta,
    days,
    setDays,
    loading,
    refreshing,
    error,
    refresh: () => loadCharts({ silent: true, selectedDays: days }),
    retry: () => loadCharts({ selectedDays: days }),
  };
}
