import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { bookingService } from '../api/bookingApi';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 6;

export const QUERY_TAB_TO_STATE = {
  upcoming: 'upcoming',
  past: 'past',
  'owner-bookings': 'owner',
};

export const STATE_TAB_TO_QUERY = {
  upcoming: 'upcoming',
  past: 'past',
  owner: 'owner-bookings',
};

export function useBookingsPagination() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(QUERY_TAB_TO_STATE[searchParams.get('tab')] || 'upcoming');
  const [page, setPage] = useState(parsePage(searchParams.get('page')));
  const [highlightedBookingId, setHighlightedBookingId] = useState(searchParams.get('booking_id'));
  const bookingRefs = useRef({});
  const targetBookingId = searchParams.get('booking_id');

  const fetchBookings = useCallback(async () => {
    setLoading(true);

    try {
      const params = { page, per_page: PER_PAGE };
      const res = tab === 'owner'
        ? await bookingService.ownerBookings(params)
        : await bookingService.list({ ...params, tab });
      const payload = res.data;

      setItems(Array.isArray(payload?.data) ? payload.data : []);
      setMeta(normalizePaginationMeta(payload));
    } catch {
      setItems([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, [page, tab]);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

  useEffect(() => {
    const requestedTab = QUERY_TAB_TO_STATE[searchParams.get('tab')];
    const requestedPage = parsePage(searchParams.get('page'));

    if (requestedTab && requestedTab !== tab) setTab(requestedTab);
    if (requestedPage !== page) setPage(requestedPage);
    setHighlightedBookingId(searchParams.get('booking_id'));
  }, [page, searchParams, tab]);

  useEffect(() => {
    if (loading || !targetBookingId) return undefined;

    const target = items.find((booking) => String(booking.id) === String(targetBookingId));
    if (!target) return undefined;

    setHighlightedBookingId(targetBookingId);
    window.setTimeout(() => {
      bookingRefs.current[targetBookingId]?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
      });
    }, 80);

    const timer = window.setTimeout(() => setHighlightedBookingId(null), 3500);
    return () => window.clearTimeout(timer);
  }, [items, loading, targetBookingId]);

  const handleTabChange = (nextTab) => {
    setTab(nextTab);
    setPage(1);

    const next = new URLSearchParams(searchParams);
    next.set('tab', STATE_TAB_TO_QUERY[nextTab] || nextTab);
    next.delete('booking_id');
    next.delete('page');
    setSearchParams(next);
  };

  const goToPage = (nextPage) => {
    setPage(nextPage);

    const next = new URLSearchParams(searchParams);
    next.set('tab', STATE_TAB_TO_QUERY[tab] || tab);
    next.delete('booking_id');
    if (nextPage > 1) {
      next.set('page', String(nextPage));
    } else {
      next.delete('page');
    }
    setSearchParams(next);
  };

  return {
    items,
    setItems,
    meta,
    loading,
    tab,
    page,
    bookingRefs,
    highlightedBookingId,
    refresh: fetchBookings,
    handleTabChange,
    goToPage,
  };
}
