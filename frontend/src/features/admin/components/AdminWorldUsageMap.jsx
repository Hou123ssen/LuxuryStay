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

const MAP_WIDTH = 1000;
const MAP_HEIGHT = 520;
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
            <g opacity="0.11" stroke="#f5ead2" strokeWidth="0.5">
              {Array.from({ length: 7 }).map((_, index) => (
                <line key={`lat-${index}`} x1="0" x2={MAP_WIDTH} y1={70 + index * 62} y2={70 + index * 62} />
              ))}
              {Array.from({ length: 11 }).map((_, index) => (
                <line key={`lng-${index}`} x1={80 + index * 84} x2={80 + index * 84} y1="0" y2={MAP_HEIGHT} />
              ))}
            </g>

            <g>
              {mapCountries.map((geo) => {
                const country = countryByNumericCode.get(geo.id);
                const hasUsage = Boolean(country);
                const opacity = hasUsage ? 0.26 + usageIntensity(country.count, maxCount) * 0.54 : 0.12;

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
                    fill={hasUsage ? '#d4af37' : '#f5ead2'}
                    fillOpacity={opacity}
                    stroke={hasUsage ? '#d4af37' : '#f5ead2'}
                    strokeOpacity={hasUsage ? 0.42 : 0.12}
                    strokeWidth={hasUsage ? 1.1 : 0.55}
                    className="outline-none transition-colors focus-visible:stroke-[2]"
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
                      r={country.radius * 2.1}
                      fill="#d4af37"
                      fillOpacity="0.14"
                      stroke="#d4af37"
                      strokeOpacity="0.38"
                      strokeWidth="1.4"
                    />
                    <circle
                      cx={x}
                      cy={y}
                      r={Math.max(4, country.radius * 0.65)}
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
    .map((ring) => {
      const points = ring.map(project);
      if (points.length === 0) return '';

      const [firstX, firstY] = points[0];
      const rest = points.slice(1).map(([x, y]) => `L${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');

      return `M${firstX.toFixed(1)} ${firstY.toFixed(1)} ${rest} Z`;
    })
    .join(' ');
}

function project([longitude, latitude]) {
  const x = ((longitude + 180) / 360) * MAP_WIDTH;
  const y = ((84 - latitude) / 168) * MAP_HEIGHT;

  return [x, y];
}
