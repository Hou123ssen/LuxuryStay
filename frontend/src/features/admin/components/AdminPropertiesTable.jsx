import { FiEye } from 'react-icons/fi';
import AdminPropertyCounts from './AdminPropertyCounts';
import AdminPropertyOwnerBadge from './AdminPropertyOwnerBadge';
import AdminPropertyRatingSummary from './AdminPropertyRatingSummary';
import AdminPropertyStatusBadge from './AdminPropertyStatusBadge';
import {
  formatAdminPropertyDate,
  formatAdminPropertyPrice,
  propertyLocationLabel,
} from '../utils/adminPropertyFormatters';

export default function AdminPropertiesTable({ properties = [], loading = false, onViewProperty }) {
  if (loading) {
    return (
      <div className="grid gap-3">
        {[1, 2, 3].map((item) => (
          <div key={item} className="h-32 animate-pulse rounded-2xl border border-gold/10 bg-white/[0.03]" />
        ))}
      </div>
    );
  }

  if (properties.length === 0) {
    return (
      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-8 text-center text-sm text-cream/45">
        No properties found for these filters.
      </div>
    );
  }

  return (
    <section className="overflow-hidden rounded-2xl border border-gold/10 bg-white/[0.03]">
      <div className="hidden overflow-x-auto 2xl:block">
        <table className="min-w-full divide-y divide-white/5">
          <thead className="bg-black/20">
            <tr className="text-left text-[11px] uppercase tracking-[0.16em] text-cream/35">
              <th className="px-4 py-3">Property</th>
              <th className="px-4 py-3">Owner</th>
              <th className="px-4 py-3">Price</th>
              <th className="px-4 py-3">Rating</th>
              <th className="px-4 py-3">Counts</th>
              <th className="px-4 py-3">Created</th>
              <th className="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5">
            {properties.map((property) => (
              <tr key={property.id} className="transition-colors hover:bg-white/[0.025]">
                <td className="px-4 py-4">
                  <div className="max-w-xs">
                    <div className="truncate font-medium text-cream">{property.title}</div>
                    <div className="mt-1 text-sm text-cream/45">{propertyLocationLabel(property)}</div>
                    <div className="mt-2 flex flex-wrap gap-2">
                      <AdminPropertyStatusBadge status={property.status} />
                      {property.type && <span className="rounded-full border border-white/10 px-2 py-0.5 text-xs text-cream/45">{property.type}</span>}
                    </div>
                  </div>
                </td>
                <td className="px-4 py-4 min-w-[190px]"><AdminPropertyOwnerBadge owner={property.owner} /></td>
                <td className="px-4 py-4 text-sm text-gold/80">{formatAdminPropertyPrice(property.price_per_night)}</td>
                <td className="px-4 py-4 min-w-[190px]"><AdminPropertyRatingSummary rating={property.rating} /></td>
                <td className="px-4 py-4 min-w-[280px]"><AdminPropertyCounts counts={property.counts} /></td>
                <td className="px-4 py-4 text-sm text-cream/45">{formatAdminPropertyDate(property.created_at)}</td>
                <td className="px-4 py-4 text-right">
                  <button
                    type="button"
                    onClick={() => onViewProperty(property.id)}
                    className="inline-flex items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
                  >
                    <FiEye size={15} />
                    View
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="grid gap-3 p-3 2xl:hidden">
        {properties.map((property) => (
          <article key={property.id} className="rounded-2xl border border-white/5 bg-black/15 p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="truncate font-medium text-cream">{property.title}</h3>
                  <AdminPropertyStatusBadge status={property.status} />
                </div>
                <p className="mt-1 text-sm text-cream/45">{propertyLocationLabel(property)}</p>
                <p className="mt-1 text-sm text-gold/75">{formatAdminPropertyPrice(property.price_per_night)}</p>
              </div>
              <button
                type="button"
                onClick={() => onViewProperty(property.id)}
                className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-sm text-gold"
              >
                <FiEye size={15} />
                View
              </button>
            </div>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <AdminPropertyOwnerBadge owner={property.owner} />
              <AdminPropertyRatingSummary rating={property.rating} />
            </div>
            <div className="mt-4"><AdminPropertyCounts counts={property.counts} compact /></div>
          </article>
        ))}
      </div>
    </section>
  );
}
