import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { adminReportService } from '../api/adminReportApi';
import { cleanReportError } from '../utils/reportAdminOptions';
import { normalizePaginationMeta, parsePage } from '../../../shared/utils/pagination';

const PER_PAGE = 15;
const FILTER_KEYS = ['status', 'severity', 'category'];

const filtersFromParams = (searchParams) => ({
  status: searchParams.get('status') || '',
  severity: searchParams.get('severity') || '',
  category: searchParams.get('category') || '',
});

export function useAdminReports() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [reports, setReports] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedReport, setSelectedReport] = useState(null);
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

  const fetchReports = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const res = await adminReportService.list(queryParams);
      const payload = res.data;

      setReports(Array.isArray(payload?.data) ? payload.data : []);
      setMeta(normalizePaginationMeta(payload));
    } catch (err) {
      setReports([]);
      setMeta(null);
      setError(cleanReportError(err, 'Unable to load reports.'));
    } finally {
      setLoading(false);
    }
  }, [queryParams]);

  useEffect(() => {
    fetchReports();
    syncUrl(page, filters);
  }, [fetchReports, filters, page, syncUrl]);

  const goToPage = (nextPage) => {
    setPage(nextPage);
  };

  const updateFilter = (key, value) => {
    setFilters((current) => ({ ...current, [key]: value }));
    setPage(1);
  };

  const resetFilters = () => {
    setFilters({ status: '', severity: '', category: '' });
    setPage(1);
  };

  const openReport = async (report) => {
    setSelectedReport(report);
    setDetailLoading(true);

    try {
      const res = await adminReportService.get(report.id);
      setSelectedReport(res.data?.data || report);
    } catch (err) {
      toast.error(cleanReportError(err, 'Unable to load report details.'));
    } finally {
      setDetailLoading(false);
    }
  };

  const closeReport = () => {
    if (!actionLoading) setSelectedReport(null);
  };

  const moderateReport = async (action, report, data = {}) => {
    const actionMap = {
      review: adminReportService.review,
      resolve: adminReportService.resolve,
      reject: adminReportService.reject,
    };
    const request = actionMap[action];
    if (!request || !report) return null;

    setActionLoading(true);

    try {
      const res = await request(report.id, data);
      const updated = res.data?.data;

      if (updated) {
        setSelectedReport(updated);
        setReports((current) => current.map((item) => (
          item.id === updated.id ? { ...item, ...updated } : item
        )));
      }

      toast.success(res.data?.message || 'Report updated.');
      await fetchReports();
      return updated;
    } catch (err) {
      toast.error(cleanReportError(err));
      return null;
    } finally {
      setActionLoading(false);
    }
  };

  return {
    reports,
    meta,
    loading,
    error,
    page,
    filters,
    selectedReport,
    detailLoading,
    actionLoading,
    goToPage,
    updateFilter,
    resetFilters,
    openReport,
    closeReport,
    moderateReport,
    refresh: fetchReports,
  };
}
