import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { favoriteService } from '../api/favoriteApi';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 12;

export function useFavoritesPagination() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [favorites, setFavorites] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(parsePage(searchParams.get('page')));

  const fetchFavorites = useCallback(async (nextPage = page) => {
    setLoading(true);

    try {
      const res = await favoriteService.list({ page: nextPage, per_page: PER_PAGE });
      const payload = res.data;
      const items = Array.isArray(payload?.data)
        ? payload.data.map((favorite) => favorite.property).filter(Boolean)
        : [];

      setFavorites(items);
      setMeta(normalizePaginationMeta(payload));
    } catch (err) {
      console.error(err);
      setFavorites([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    fetchFavorites(page);
    setSearchParams(page > 1 ? { page: String(page) } : {});
  }, [fetchFavorites, page, setSearchParams]);

  const goToPage = (nextPage) => {
    setPage(nextPage);
  };

  return {
    favorites,
    meta,
    loading,
    page,
    goToPage,
  };
}
