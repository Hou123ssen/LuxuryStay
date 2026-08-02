export const propertyBooleanOptions = [
  { value: '', label: 'All' },
  { value: 'true', label: 'Yes' },
  { value: 'false', label: 'No' },
];

export const propertySortOptions = [
  { value: 'newest', label: 'Newest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'title', label: 'Title' },
  { value: 'bookings_count', label: 'Bookings' },
  { value: 'reviews_count', label: 'Reviews' },
  { value: 'reports_count', label: 'Reports' },
  { value: 'rating', label: 'Rating' },
  { value: 'price_low', label: 'Price low' },
  { value: 'price_high', label: 'Price high' },
];

export const propertyPerPageOptions = [15, 25, 50];

export const initialPropertyFilters = {
  search: '',
  owner_id: '',
  city: '',
  status: '',
  has_bookings: '',
  has_reviews: '',
  has_reports: '',
  min_price: '',
  max_price: '',
  sort: 'newest',
  per_page: 15,
};

export const formatAdminPropertyCount = (value) => (
  new Intl.NumberFormat().format(Number(value || 0))
);

export const formatAdminPropertyDate = (value) => {
  if (!value) return 'Not available';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Not available';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

export const formatAdminPropertyPrice = (value) => {
  if (value === null || value === undefined || value === '') return 'Not available';

  const number = Number(value);
  if (Number.isNaN(number)) return 'Not available';

  return `${new Intl.NumberFormat(undefined, {
    maximumFractionDigits: number % 1 === 0 ? 0 : 2,
  }).format(number)} / night`;
};

export const statusLabel = (status) => {
  if (!status) return 'No status';

  return String(status)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const propertyLocationLabel = (property = {}) => {
  const parts = [
    property.city,
    property.region_name || property.region,
    property.country_name || property.country_code,
  ].filter(Boolean);

  return parts.length ? parts.join(', ') : 'Location not available';
};

export const ratingSummaryLabel = (rating = {}) => {
  if (rating.rating_label) return rating.rating_label;
  if (!rating.average_rating) return 'New';

  const count = Number(rating.reviews_count || 0);
  const stayLabel = count === 1 ? 'verified stay' : 'verified stays';

  return `${Number(rating.average_rating).toFixed(1)} from ${formatAdminPropertyCount(count)} ${stayLabel}`;
};

export const trustSummaryLabel = (rating = {}) => rating.trust_label || 'No trust badge';
