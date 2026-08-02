export default function AdminPropertyOwnerBadge({ owner = {} }) {
  if (!owner?.id) {
    return (
      <div className="text-sm text-cream/45">
        Owner not available
      </div>
    );
  }

  return (
    <div className="min-w-0">
      <div className="truncate text-sm font-medium text-cream">{owner.name || `Owner #${owner.id}`}</div>
      <div className="mt-1 truncate text-xs text-cream/45">{owner.email || 'Email not available'}</div>
      <div className="mt-1 text-[11px] uppercase tracking-[0.14em] text-gold/50">{owner.role || 'unknown'}</div>
    </div>
  );
}
