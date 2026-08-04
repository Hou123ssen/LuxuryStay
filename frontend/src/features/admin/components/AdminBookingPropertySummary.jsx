export default function AdminBookingPropertySummary({ property = {} }) {
  if (!property?.id) {
    return <p className="text-sm text-cream/45">Property not available</p>;
  }

  return (
    <div className="min-w-0">
      <p className="truncate text-sm font-medium text-cream">{property.title || `Property #${property.id}`}</p>
      <p className="mt-1 text-xs text-cream/45">{property.city || 'Location not available'}</p>
      <p className="mt-1 text-[11px] uppercase tracking-[0.12em] text-gold/45">Property #{property.id}</p>
    </div>
  );
}
