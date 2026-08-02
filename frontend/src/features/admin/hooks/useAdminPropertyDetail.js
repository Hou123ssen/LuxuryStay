import { useCallback, useEffect, useState } from 'react';
import { adminPropertiesApi } from '../api/adminPropertiesApi';

export function useAdminPropertyDetail(selectedPropertyId) {
  const [property, setProperty] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const loadProperty = useCallback(async () => {
    if (!selectedPropertyId) {
      setProperty(null);
      setError('');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await adminPropertiesApi.getAdminPropertyDetail(selectedPropertyId);
      setProperty(response.data?.data || null);
    } catch (err) {
      setError('Property details could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [selectedPropertyId]);

  useEffect(() => {
    loadProperty();
  }, [loadProperty]);

  const reset = useCallback(() => {
    setProperty(null);
    setError('');
  }, []);

  return {
    property,
    loading,
    error,
    retry: loadProperty,
    reset,
  };
}
