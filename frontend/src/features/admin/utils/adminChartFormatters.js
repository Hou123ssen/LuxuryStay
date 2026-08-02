export const chartDayOptions = [
  { value: '7', label: '7 days' },
  { value: '30', label: '30 days' },
  { value: '90', label: '90 days' },
  { value: 'all', label: 'All time' },
];

export const chartColors = {
  registrations: '#d4af37',
  logins: '#93c5fd',
  bookings: '#34d399',
  reviews: '#fbbf24',
  reports: '#f87171',
};

export const statusLabels = {
  accepted: 'Accepted',
  cancelled: 'Cancelled',
  completed: 'Completed',
  pending: 'Pending',
  pending_review: 'Pending review',
  published: 'Published',
  rejected: 'Rejected',
  resolved: 'Resolved',
  reviewed: 'Reviewed',
};

export const formatChartNumber = (value) => (
  new Intl.NumberFormat().format(Number(value || 0))
);

export const formatChartDateLabel = (value) => {
  if (!value) return 'Unknown';

  if (/^\d{4}-\d{2}$/.test(value)) {
    const [year, month] = value.split('-').map(Number);
    return new Intl.DateTimeFormat(undefined, { month: 'short', year: 'numeric' })
      .format(new Date(year, month - 1, 1));
  }

  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(date);
};

export const formatStatusLabel = (status) => (
  statusLabels[status] || String(status || 'Unknown').replace(/_/g, ' ')
);

export const normalizeSeries = (series = []) => (
  Array.isArray(series)
    ? series.map((point) => ({
      date: point?.date || '',
      count: Number(point?.count || 0),
    }))
    : []
);

export const normalizeBreakdown = (breakdown = []) => (
  Array.isArray(breakdown)
    ? breakdown.map((item) => ({
      status: item?.status || 'unknown',
      count: Number(item?.count || 0),
    }))
    : []
);

export const sumSeries = (series = []) => (
  normalizeSeries(series).reduce((total, point) => total + point.count, 0)
);

export const maxCount = (...seriesGroups) => {
  const values = seriesGroups
    .flatMap((series) => normalizeSeries(series).map((point) => point.count));

  return Math.max(1, ...values);
};

export const hasChartData = (...seriesGroups) => (
  seriesGroups.some((series) => sumSeries(series) > 0)
);

export const buildLinePoints = (series, width, height, padding, maximum) => {
  const normalized = normalizeSeries(series);
  if (normalized.length === 0) return '';

  const innerWidth = width - padding * 2;
  const innerHeight = height - padding * 2;
  const lastIndex = Math.max(1, normalized.length - 1);

  return normalized
    .map((point, index) => {
      const x = padding + (innerWidth * index) / lastIndex;
      const y = padding + innerHeight - ((point.count / maximum) * innerHeight);
      return `${x.toFixed(2)},${y.toFixed(2)}`;
    })
    .join(' ');
};

export const chartLabelEvery = (length) => {
  if (length <= 8) return 1;
  if (length <= 32) return 5;
  return Math.ceil(length / 6);
};
