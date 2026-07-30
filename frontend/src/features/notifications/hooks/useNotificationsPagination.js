import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { notificationService } from '../api/notificationApi';
import { notifyNavbarCountsChanged } from '../../../shared/utils/navbarCountsEvents';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 10;

export function useNotificationsPagination() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [notifs, setNotifs] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(parsePage(searchParams.get('page')));

  const markLoadedAsRead = useCallback(async (items) => {
    const unread = items.filter((notification) => !notification.read);
    if (unread.length === 0) {
      notifyNavbarCountsChanged();
      return items;
    }

    await Promise.all(unread.map((notification) => notificationService.markAsRead(notification.id)));
    notifyNavbarCountsChanged();

    return items.map((notification) => ({ ...notification, read: true }));
  }, []);

  const fetchNotifs = useCallback(async (nextPage = page) => {
    setLoading(true);

    try {
      const res = await notificationService.list({ page: nextPage, per_page: PER_PAGE });
      const payload = res.data;
      const loaded = Array.isArray(payload?.data) ? payload.data : [];
      const visibleItems = await markLoadedAsRead(loaded);

      setNotifs(visibleItems);
      setMeta(normalizePaginationMeta(payload));
    } catch (err) {
      console.warn('Could not load or mark notifications as read.', err.response?.status || err.message);
      setNotifs([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, [markLoadedAsRead, page]);

  useEffect(() => {
    fetchNotifs(page);
    setSearchParams(page > 1 ? { page: String(page) } : {});
  }, [fetchNotifs, page, setSearchParams]);

  const goToPage = (nextPage) => {
    setPage(nextPage);
  };

  const markAllRead = async () => {
    const res = await notificationService.markAllAsRead();
    setNotifs((prev) => prev.map((notification) => ({ ...notification, read: true })));
    notifyNavbarCountsChanged({
      unread_notifications_count: res.data?.unread_notifications_count ?? 0,
    });
  };

  const markOneRead = async (id) => {
    const res = await notificationService.markAsRead(id);
    setNotifs((prev) =>
      prev.map((notification) => (
        notification.id === id ? { ...notification, read: true } : notification
      )),
    );
    notifyNavbarCountsChanged({
      unread_notifications_count: res.data?.unread_notifications_count,
    });
  };

  return {
    notifs,
    setNotifs,
    meta,
    loading,
    page,
    goToPage,
    markAllRead,
    markOneRead,
  };
}
