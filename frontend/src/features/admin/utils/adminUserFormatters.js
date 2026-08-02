export const roleOptions = [
  { value: '', label: 'All roles' },
  { value: 'admin', label: 'Admin' },
  { value: 'owner', label: 'Owner' },
  { value: 'user', label: 'User' },
  { value: 'guest', label: 'Guest' },
];

export const booleanOptions = [
  { value: '', label: 'All' },
  { value: 'true', label: 'Yes' },
  { value: 'false', label: 'No' },
];

export const sortOptions = [
  { value: 'newest', label: 'Newest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'name', label: 'Name' },
  { value: 'last_seen', label: 'Last seen' },
  { value: 'bookings_count', label: 'Bookings' },
  { value: 'properties_count', label: 'Properties' },
];

export const perPageOptions = [15, 25, 50];

export const formatAdminUserCount = (value) => (
  new Intl.NumberFormat().format(Number(value || 0))
);

export const formatAdminUserDate = (value) => {
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

export const roleLabel = (role) => {
  if (!role) return 'Unknown';
  return roleOptions.find((option) => option.value === role)?.label || String(role);
};

export const statusLabel = (status) => {
  if (!status) return 'Unknown';

  return String(status)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const locationLabel = (city, region, country) => {
  const parts = [city, region, country]
    .filter(Boolean)
    .filter((part) => String(part).toLowerCase() !== 'unknown');

  return parts.length ? parts.join(', ') : 'Not available';
};

export const countryLabel = (code, name) => {
  if (!code && !name) return 'Not available';
  if (code && name) return `${name} (${code})`;
  return name || code;
};

export const initialUserFilters = {
  search: '',
  role: '',
  country_code: '',
  city: '',
  has_properties: '',
  has_bookings: '',
  demo: '',
  sort: 'newest',
  per_page: 15,
};
