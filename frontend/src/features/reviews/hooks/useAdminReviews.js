import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { adminReviewService } from '../api/adminReviewApi';
import { cleanReviewError } from '../utils/reviewAdminOptions';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 15;
const FILTER_KEYS = ['status', 'rating', 'property_id', 'user_id', 'risk_level'];

const filtersFromParams = (searchParams) => ({
  status: searchParams.get('status') || '',
  rating: searchParams.get('rating') || '',
  property_id: searchParams.get('property_id') || '',
  user_id: searchParams.get('user_id') || '',
  risk_level: searchParams.get('risk_level') || '',
});

export function useAdminReviews() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [reviews, setReviews] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedReview, setSelectedReview] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [page, setPage] = useState(parsePage(searchParams.get('page')));
  const [filters, setFilters] = useState(() => filtersFromParams(searchParams));

  const queryParams = useMemo(() => ({
    page,
    per_page: PER_PAGE,
    ...Object.fromEntries(
      Object.entries(filters).filter(([, value]) => Boolean(value)),
    ),
  }), [filters, page]);

  const syncUrl = useCallback((nextPage, nextFilters) => {
    const params = {};

    if (nextPage > 1) params.page = String(nextPage);
    FILTER_KEYS.forEach((key) => {
      if (nextFilters[key]) params[key] = nextFilters[key];
    });

    setSearchParams(params);
  }, [setSearchParams]);

  const fetchReviews = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const res = await adminReviewService.list(queryParams);
      const payload = res.data;

      setReviews(Array.isArray(payload?.data) ? payload.data : []);
      setMeta(normalizePaginationMeta(payload));
    } catch (err) {
      setReviews([]);
      setMeta(null);
      setError(cleanReviewError(err, 'Unable to load reviews.'));
    } finally {
      setLoading(false);
    }
  }, [queryParams]);

  useEffect(() => {
    fetchReviews();
    syncUrl(page, filters);
  }, [fetchReviews, filters, page, syncUrl]);

  const goToPage = (nextPage) => setPage(nextPage);

  const updateFilter = (key, value) => {
    setFilters((current) => ({ ...current, [key]: value }));
    setPage(1);
  };

  const resetFilters = () => {
    setFilters({ status: '', rating: '', property_id: '', user_id: '', risk_level: '' });
    setPage(1);
  };

  const openReview = async (review) => {
    setSelectedReview(review);
    setDetailLoading(true);

    try {
      const res = await adminReviewService.get(review.id);
      setSelectedReview(res.data?.data || review);
    } catch (err) {
      toast.error(cleanReviewError(err, 'Unable to load review details.'));
    } finally {
      setDetailLoading(false);
    }
  };

  const closeReview = () => {
    if (!actionLoading) setSelectedReview(null);
  };

  const moderateReview = async (action, review, data = {}) => {
    const actionMap = {
      publish: adminReviewService.publish,
      reject: adminReviewService.reject,
    };
    const request = actionMap[action];
    if (!request || !review) return null;

    setActionLoading(true);

    try {
      const res = await request(review.id, data);
      const updated = res.data?.data;

      if (updated) {
        setSelectedReview(updated);
        setReviews((current) => current.map((item) => (
          item.id === updated.id ? { ...item, ...updated } : item
        )));
      }

      toast.success(res.data?.message || 'Review updated.');
      await fetchReviews();
      return updated;
    } catch (err) {
      toast.error(cleanReviewError(err));
      return null;
    } finally {
      setActionLoading(false);
    }
  };

  return {
    reviews,
    meta,
    loading,
    error,
    page,
    filters,
    selectedReview,
    detailLoading,
    actionLoading,
    goToPage,
    updateFilter,
    resetFilters,
    openReview,
    closeReview,
    moderateReview,
    refresh: fetchReviews,
  };
}
