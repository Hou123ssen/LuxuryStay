import { useCallback, useEffect, useState } from 'react';
import { adminUsersApi } from '../api/adminUsersApi';

export function useAdminUserDetail(selectedUserId, includeDemo = true) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const loadUser = useCallback(async () => {
    if (!selectedUserId) {
      setUser(null);
      setError('');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await adminUsersApi.getAdminUserDetail(selectedUserId, {
        include_demo: includeDemo,
      });
      setUser(response.data?.data || null);
    } catch (err) {
      setError('User details could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [includeDemo, selectedUserId]);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  const reset = useCallback(() => {
    setUser(null);
    setError('');
  }, []);

  return {
    user,
    loading,
    error,
    retry: loadUser,
    reset,
  };
}
