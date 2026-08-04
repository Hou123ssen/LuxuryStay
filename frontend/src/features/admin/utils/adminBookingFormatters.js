export const bookingBooleanOptions = [
  { value: '', label: 'All' },
  { value: 'true', label: 'Yes' },
  { value: 'false', label: 'No' },
];

export const bookingStatusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'completed', label: 'Completed' },
];

export const bookingSortOptions = [
  { value: 'newest', label: 'Newest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'start_date', label: 'Start date' },
  { value: 'end_date', label: 'End date' },
  { value: 'total_high', label: 'Total high' },
  { value: 'total_low', label: 'Total low' },
  { value: 'status', label: 'Status' },
  { value: 'property_title', label: 'Property title' },
];

export const bookingPerPageOptions = [15, 25, 50];

export const initialBookingFilters = {
  search: '',
  status: '',
  property_id: '',
  guest_id: '',
  owner_id: '',
  city: '',
  start_date_from: '',
  start_date_to: '',
  end_date_from: '',
  end_date_to: '',
  created_from: '',
  created_to: '',
  min_total: '',
  max_total: '',
  has_review: '',
  has_report: '',
  sort: 'newest',
  per_page: 15,
};

export const formatAdminBookingCount = (value) => (
  new Intl.NumberFormat().format(Number(value || 0))
);

export const formatAdminBookingDate = (value) => {
  if (!value) return 'Not available';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Not available';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
};

export const formatAdminBookingDateTime = (value) => {
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

export const formatAdminBookingMoney = (value) => {
  if (value === null || value === undefined || value === '') return 'Not available';

  const number = Number(value);
  if (Number.isNaN(number)) return 'Not available';

  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: number % 1 === 0 ? 0 : 2,
  }).format(number);
};

export const bookingStatusLabel = (status) => {
  if (!status) return 'Unknown';

  return String(status)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const nightsLabel = (nights) => {
  if (nights === null || nights === undefined) return 'Not available';
  const value = Number(nights);
  if (Number.isNaN(value)) return 'Not available';
  return `${value} ${value === 1 ? 'night' : 'nights'}`;
};

export const bookingDateRangeLabel = (booking = {}) => (
  `${formatAdminBookingDate(booking.start_date)} to ${formatAdminBookingDate(booking.end_date)}`
);
