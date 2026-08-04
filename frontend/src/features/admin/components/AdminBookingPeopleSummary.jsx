function Person({ label, person = {} }) {
  if (!person?.id) {
    return (
      <div>
        <p className="text-[11px] uppercase tracking-[0.14em] text-cream/35">{label}</p>
        <p className="mt-1 text-sm text-cream/45">Not available</p>
      </div>
    );
  }

  return (
    <div className="min-w-0">
      <p className="text-[11px] uppercase tracking-[0.14em] text-cream/35">{label}</p>
      <p className="mt-1 truncate text-sm font-medium text-cream">{person.name || `User #${person.id}`}</p>
      <p className="mt-1 truncate text-xs text-cream/45">{person.email || 'Email not available'}</p>
      <p className="mt-1 text-[11px] uppercase tracking-[0.12em] text-gold/45">{person.role || 'unknown'}</p>
    </div>
  );
}

export default function AdminBookingPeopleSummary({ guest, owner, compact = false }) {
  return (
    <div className={`grid gap-3 ${compact ? 'grid-cols-1' : 'sm:grid-cols-2'}`}>
      <Person label="Guest" person={guest} />
      <Person label="Owner" person={owner} />
    </div>
  );
}
