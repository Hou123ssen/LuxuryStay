export const DAY_FILTER_OPTIONS = [
  { value: '7', label: '7 days' },
  { value: '30', label: '30 days' },
  { value: '90', label: '90 days' },
  { value: 'all', label: 'All time' },
];

export const COUNTRY_CENTROIDS = {
  MA: { coordinates: [-6.84, 31.79] },
  FR: { coordinates: [2.21, 46.23] },
  ES: { coordinates: [-3.75, 40.46] },
  BE: { coordinates: [4.47, 50.5] },
  US: { coordinates: [-98.58, 39.83] },
  GB: { coordinates: [-3.44, 55.38] },
  DE: { coordinates: [10.45, 51.17] },
  IT: { coordinates: [12.57, 41.87] },
  NL: { coordinates: [5.29, 52.13] },
  CA: { coordinates: [-106.35, 56.13] },
};

export const COUNTRY_NUMERIC_CODES = {
  MA: '504',
  FR: '250',
  ES: '724',
  BE: '056',
  US: '840',
  GB: '826',
  DE: '276',
  IT: '380',
  NL: '528',
  CA: '124',
};

export const formatGeoNumber = (value) => (
  new Intl.NumberFormat().format(Number(value || 0))
);

export const formatGeoDateTime = (value) => {
  if (!value) return 'Not available';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Not available';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

export const countryLabel = (row = {}) => {
  const name = row.country_name || 'Unknown';
  return row.country_code ? `${name} (${row.country_code})` : name;
};

export const cityLabel = (row = {}) => row.city_name || 'Unknown';

export const regionCountryLabel = (row = {}) => (
  [row.region_name, countryLabel(row)]
    .filter((value) => value && value !== 'Unknown')
    .join(' - ') || 'Unknown'
);

export const eventTypeLabel = (eventType) => ({
  user_registered: 'Registered',
  user_logged_in: 'Logged in',
}[eventType] || 'Activity');

export const usageIntensity = (count, maxCount) => {
  if (!count || !maxCount) return 0;
  return Math.max(0.18, Math.min(1, Number(count) / Number(maxCount)));
};

export const mapMarkerRadius = (count, maxCount) => {
  const intensity = usageIntensity(count, maxCount);
  return 4 + intensity * 7;
};
