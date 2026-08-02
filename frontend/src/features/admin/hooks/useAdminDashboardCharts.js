import { useCallback, useEffect, useState } from 'react';
import { adminChartsApi } from '../api/adminChartsApi';

export function useAdminDashboardCharts(defaultDays = '30') {
  const [days, setDays] = useState(defaultDays);
  const [charts, setCharts] = useState(null);
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
      const response = await adminChartsApi.getAdminDashboardCharts({ days: selectedDays });
      setCharts(response.data?.data || null);
    } catch (err) {
      setError(err.response?.data?.message || 'Dashboard charts could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [days]);

  useEffect(() => {
    loadCharts({ selectedDays: days });
  }, [days, loadCharts]);

  return {
    charts,
    days,
    setDays,
    loading,
    refreshing,
    error,
    refresh: () => loadCharts({ silent: true, selectedDays: days }),
    retry: () => loadCharts({ selectedDays: days }),
  };
}
