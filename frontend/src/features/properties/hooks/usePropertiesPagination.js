import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { propertyService } from '../api/propertyApi';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 12;

function cleanParams(params) {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  );
}

export function usePropertiesPagination() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [properties, setProperties] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    city: searchParams.get('city') || '',
    type: searchParams.get('type') || '',
    min_price: searchParams.get('min_price') || '',
    max_price: searchParams.get('max_price') || '',
    sort: searchParams.get('sort') || 'latest',
    page: parsePage(searchParams.get('page')),
  });

  const fetchProperties = useCallback(async (params) => {
    setLoading(true);

    try {
      const res = await propertyService.list(cleanParams({ ...params, per_page: PER_PAGE }));
      const payload = res.data;
      const items = payload?.data || [];

      setProperties(Array.isArray(items) ? items : []);
      setMeta(normalizePaginationMeta(payload));
    } catch {
      setProperties([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProperties(filters);
    setSearchParams(cleanParams(filters));
  }, [fetchProperties, filters, setSearchParams]);

  const updateFilters = (nextFilters) => {
    setFilters({ ...nextFilters, page: 1 });
  };

  const resetFilters = () => {
    setFilters({ city: '', type: '', min_price: '', max_price: '', sort: 'latest', page: 1 });
  };

  const goToPage = (page) => {
    setFilters((current) => ({ ...current, page }));
  };

  return {
    properties,
    meta,
    loading,
    filters,
    setFilters: updateFilters,
    resetFilters,
    goToPage,
  };
}
