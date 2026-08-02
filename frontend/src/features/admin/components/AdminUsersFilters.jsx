import { FiRefreshCw, FiSearch, FiX } from 'react-icons/fi';
import {
  booleanOptions,
  perPageOptions,
  roleOptions,
  sortOptions,
} from '../utils/adminUserFormatters';

function SelectField({ label, value, onChange, children }) {
  return (
    <label className="block">
      <span className="text-[11px] uppercase tracking-[0.16em] text-cream/35">{label}</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="mt-1 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-cream outline-none transition-colors focus:border-gold/45"
      >
        {children}
      </select>
    </label>
  );
}

export default function AdminUsersFilters({
  filters,
  onFilterChange,
  onClear,
  onRefresh,
  refreshing,
}) {
  return (
    <section className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <div className="grid gap-3 lg:grid-cols-[minmax(220px,1.4fr)_repeat(4,minmax(130px,1fr))]">
        <label className="block">
          <span className="text-[11px] uppercase tracking-[0.16em] text-cream/35">Search</span>
          <div className="mt-1 flex items-center gap-2 rounded-xl border border-white/10 bg-black/30 px-3 py-2 transition-colors focus-within:border-gold/45">
            <FiSearch className="text-cream/35" size={15} />
            <input
              value={filters.search}
              onChange={(event) => onFilterChange('search', event.target.value)}
              placeholder="Name or email"
              className="min-w-0 flex-1 bg-transparent text-sm text-cream outline-none placeholder:text-cream/25"
            />
          </div>
        </label>

        <SelectField label="Role" value={filters.role} onChange={(value) => onFilterChange('role', value)}>
          {roleOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <label className="block">
          <span className="text-[11px] uppercase tracking-[0.16em] text-cream/35">Country code</span>
          <input
            value={filters.country_code}
            onChange={(event) => onFilterChange('country_code', event.target.value.toUpperCase())}
            placeholder="MA"
            maxLength={2}
            className="mt-1 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm uppercase text-cream outline-none transition-colors placeholder:text-cream/25 focus:border-gold/45"
          />
        </label>

        <label className="block">
          <span className="text-[11px] uppercase tracking-[0.16em] text-cream/35">City</span>
          <input
            value={filters.city}
            onChange={(event) => onFilterChange('city', event.target.value)}
            placeholder="Casablanca"
            className="mt-1 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-cream outline-none transition-colors placeholder:text-cream/25 focus:border-gold/45"
          />
        </label>

        <SelectField label="Sort" value={filters.sort} onChange={(value) => onFilterChange('sort', value)}>
          {sortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>
      </div>

      <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(130px,1fr))_auto_auto] lg:items-end">
        <SelectField label="Has properties" value={filters.has_properties} onChange={(value) => onFilterChange('has_properties', value)}>
          {booleanOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <SelectField label="Has bookings" value={filters.has_bookings} onChange={(value) => onFilterChange('has_bookings', value)}>
          {booleanOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <SelectField label="Demo users" value={filters.demo} onChange={(value) => onFilterChange('demo', value)}>
          {booleanOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <SelectField label="Per page" value={String(filters.per_page)} onChange={(value) => onFilterChange('per_page', Number(value))}>
          {perPageOptions.map((value) => <option key={value} value={value}>{value}</option>)}
        </SelectField>

        <button
          type="button"
          onClick={onRefresh}
          disabled={refreshing}
          className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-gold/25 px-3 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <FiRefreshCw size={15} className={refreshing ? 'animate-spin' : ''} />
          Refresh
        </button>

        <button
          type="button"
          onClick={onClear}
          className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/10 px-3 text-sm text-cream/60 transition-colors hover:border-gold/25 hover:text-cream"
        >
          <FiX size={15} />
          Clear
        </button>
      </div>
    </section>
  );
}
