import { useCallback, useEffect, useState } from 'react';
import { adminBookingsApi } from '../api/adminBookingsApi';

export function useAdminBookingDetail(selectedBookingId) {
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const loadBooking = useCallback(async () => {
    if (!selectedBookingId) {
      setBooking(null);
      setError('');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await adminBookingsApi.getAdminBookingDetail(selectedBookingId);
      setBooking(response.data?.data || null);
    } catch (err) {
      setError('Booking details could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [selectedBookingId]);

  useEffect(() => {
    loadBooking();
  }, [loadBooking]);

  const reset = useCallback(() => {
    setBooking(null);
    setError('');
  }, []);

  return {
    booking,
    loading,
    error,
    retry: loadBooking,
    reset,
  };
}
