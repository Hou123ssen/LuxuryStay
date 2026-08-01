import { useMemo, useState } from 'react';
import { feature } from 'topojson-client';
import worldCountries from 'world-atlas/countries-110m.json';
import {
  COUNTRY_CENTROIDS,
  COUNTRY_NUMERIC_CODES,
  countryLabel,
  formatGeoNumber,
  mapMarkerRadius,
  usageIntensity,
} from '../utils/adminGeographyFormatters';
import AdminMapLegend from './AdminMapLegend';

const MAP_WIDTH = 1000;
const MAP_HEIGHT = 500;
const X_PADDING = 38;
const Y_PADDING = 30;
const PROJECTED_WIDTH = MAP_WIDTH - X_PADDING * 2;
const PROJECTED_HEIGHT = MAP_HEIGHT - Y_PADDING * 2;
const MIN_LATITUDE = -58;
const MAX_LATITUDE = 82;
const WORLD_GEOJSON = feature(worldCountries, worldCountries.objects.countries);

export default function AdminWorldUsageMap({
  usageRows = [],
  loginRows = [],
  registrationRows = [],
}) {
  const [activeCountry, setActiveCountry] = useState(null);

  const { countries, countryByNumericCode, maxCount, unknownCount } = useMemo(() => {
    const loginByCode = new Map(loginRows.map((row) => [row.country_code, row.count]));
    const registrationByCode = new Map(registrationRows.map((row) => [row.country_code, row.count]));
    const highestCount = Math.max(...usageRows.map((row) => Number(row.count || 0)), 0);

    const mappedCountries = usageRows
      .filter((row) => row.country_code && COUNTRY_CENTROIDS[row.country_code])
      .map((row) => ({
        ...row,
        login_count: loginByCode.get(row.country_code) || 0,
        registration_count: registrationByCode.get(row.country_code) || 0,
        marker: COUNTRY_CENTROIDS[row.country_code],
        numeric_code: COUNTRY_NUMERIC_CODES[row.country_code],
        intensity: usageIntensity(row.count, highestCount),
        radius: mapMarkerRadius(row.count, highestCount),
      }));

    return {
      countries: mappedCountries,
      countryByNumericCode: new Map(mappedCountries.map((row) => [row.numeric_code, row])),
      maxCount: highestCount,
      unknownCount: usageRows
        .filter((row) => !row.country_code || !COUNTRY_CENTROIDS[row.country_code])
        .reduce((total, row) => total + Number(row.count || 0), 0),
    };
  }, [usageRows, loginRows, registrationRows]);

  const mapCountries = useMemo(() => (
    WORLD_GEOJSON.features
      .filter((geo) => geo.properties?.name !== 'Antarctica')
      .map((geo) => ({
        id: String(geo.id).padStart(3, '0'),
        name: geo.properties?.name || 'Unknown',
        path: geometryToPath(geo.geometry),
      }))
      .filter((geo) => geo.path)
  ), []);

  const tooltipCountry = activeCountry || countries[0];

  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h3 className="font-display text-xl text-cream">World Usage Map</h3>
          <p className="mt-1 text-sm text-cream/45">Real bundled world geometry with usage markers.</p>
        </div>
        {unknownCount > 0 && (
          <div className="text-xs text-cream/40">
            Unknown or unmapped usage: {formatGeoNumber(unknownCount)}
          </div>
        )}
      </div>

      <div className="mt-5 overflow-hidden rounded-2xl border border-white/5 bg-[#070706]">
        <div className="relative aspect-[16/9] min-h-[260px]">
          <svg
            role="img"
            aria-label="World map showing country usage activity"
            viewBox={`0 0 ${MAP_WIDTH} ${MAP_HEIGHT}`}
            className="absolute inset-0 h-full w-full"
          >
            <defs>
              <radialGradient id="admin-map-ocean" cx="50%" cy="45%" r="75%">
                <stop offset="0%" stopColor="#15120b" />
                <stop offset="100%" stopColor="#070706" />
              </radialGradient>
            </defs>
            <rect width={MAP_WIDTH} height={MAP_HEIGHT} fill="url(#admin-map-ocean)" />

            <g>
              {mapCountries.map((geo) => {
                const country = countryByNumericCode.get(geo.id);
                const hasUsage = Boolean(country);
                const opacity = hasUsage ? 0.34 + usageIntensity(country.count, maxCount) * 0.48 : 0.18;

                return (
                  <path
                    key={geo.id}
                    d={geo.path}
                    tabIndex={hasUsage ? 0 : -1}
                    role={hasUsage ? 'button' : undefined}
                    aria-label={hasUsage ? `${countryLabel(country)}: ${formatGeoNumber(country.count)} usage events` : undefined}
                    onMouseEnter={() => hasUsage && setActiveCountry(country)}
                    onFocus={() => hasUsage && setActiveCountry(country)}
                    onMouseLeave={() => setActiveCountry(null)}
                    onBlur={() => setActiveCountry(null)}
                    fill={hasUsage ? '#d4af37' : '#2c2a25'}
                    fillOpacity={opacity}
                    stroke={hasUsage ? '#f3c95a' : '#f5ead2'}
                    strokeOpacity={hasUsage ? 0.5 : 0.16}
                    strokeWidth={hasUsage ? 0.9 : 0.45}
                    className="outline-none transition-colors focus-visible:stroke-[1.8]"
                  />
                );
              })}
            </g>

            <g>
              {countries.map((country) => {
                const [x, y] = project(country.marker.coordinates);

                return (
                  <g
                    key={country.country_code}
                    tabIndex="0"
                    role="button"
                    aria-label={`${countryLabel(country)}: ${formatGeoNumber(country.count)} usage events`}
                    onMouseEnter={() => setActiveCountry(country)}
                    onFocus={() => setActiveCountry(country)}
                    onMouseLeave={() => setActiveCountry(null)}
                    onBlur={() => setActiveCountry(null)}
                    className="outline-none"
                  >
                    <circle
                      cx={x}
                      cy={y}
                      r={country.radius * 1.8}
                      fill="#d4af37"
                      fillOpacity="0.12"
                      stroke="#d4af37"
                      strokeOpacity="0.42"
                      strokeWidth="1.2"
                    />
                    <circle
                      cx={x}
                      cy={y}
                      r={Math.max(3.2, country.radius * 0.58)}
                      fill="#f3c95a"
                      fillOpacity="0.9"
                      stroke="#070706"
                      strokeWidth="1.3"
                    />
                  </g>
                );
              })}
            </g>
          </svg>

          {countries.length === 0 && (
            <div className="absolute bottom-4 left-4 right-4 rounded-2xl border border-gold/15 bg-black/70 p-4 text-center text-sm text-cream/55 backdrop-blur">
              No country usage recorded for this period yet.
            </div>
          )}

          {tooltipCountry && (
            <div className="absolute bottom-4 left-4 right-4 rounded-2xl border border-gold/15 bg-black/75 p-4 backdrop-blur sm:left-auto sm:w-72">
              <div className="text-sm font-medium text-cream">{countryLabel(tooltipCountry)}</div>
              <div className="mt-3 grid grid-cols-3 gap-2 text-xs">
                <Metric label="Usage" value={tooltipCountry.count} />
                <Metric label="Logins" value={tooltipCountry.login_count} />
                <Metric label="Registers" value={tooltipCountry.registration_count} />
              </div>
            </div>
          )}
        </div>
      </div>

      <div className="mt-4">
        <AdminMapLegend />
      </div>
    </div>
  );
}

function Metric({ label, value }) {
  return (
    <div className="rounded-xl border border-white/5 bg-white/[0.03] px-3 py-2">
      <div className="text-cream/35">{label}</div>
      <div className="mt-1 font-medium text-gold">{formatGeoNumber(value)}</div>
    </div>
  );
}

function geometryToPath(geometry) {
  if (!geometry) return '';

  if (geometry.type === 'Polygon') {
    return polygonToPath(geometry.coordinates);
  }

  if (geometry.type === 'MultiPolygon') {
    return geometry.coordinates.map(polygonToPath).join(' ');
  }

  return '';
}

function polygonToPath(polygon) {
  return polygon
    .flatMap(splitWrappedRing)
    .map(ringToPath)
    .join(' ');
}

function splitWrappedRing(ring) {
  const segments = [];
  let current = [];

  ring.forEach((point, index) => {
    const previous = ring[index - 1];

    if (previous && Math.abs(point[0] - previous[0]) > 180) {
      if (current.length > 1) {
        segments.push(current);
      }

      current = [point];
      return;
    }

    current.push(point);
  });

  if (current.length > 1) {
    segments.push(current);
  }

  return segments;
}

function ringToPath(ring) {
  const points = ring.map(project);
  if (points.length === 0) return '';

  const [firstX, firstY] = points[0];
  const rest = points.slice(1).map(([x, y]) => `L${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');

  return `M${firstX.toFixed(1)} ${firstY.toFixed(1)} ${rest} Z`;
}

function project([longitude, latitude]) {
  const clampedLatitude = Math.max(MIN_LATITUDE, Math.min(MAX_LATITUDE, latitude));
  const x = X_PADDING + ((longitude + 180) / 360) * PROJECTED_WIDTH;
  const y = Y_PADDING + ((MAX_LATITUDE - clampedLatitude) / (MAX_LATITUDE - MIN_LATITUDE)) * PROJECTED_HEIGHT;

  return [x, y];
}
