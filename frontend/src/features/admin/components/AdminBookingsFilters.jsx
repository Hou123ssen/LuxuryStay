import { FiRefreshCw, FiSearch, FiX } from 'react-icons/fi';
import {
  bookingBooleanOptions,
  bookingPerPageOptions,
  bookingSortOptions,
  bookingStatusOptions,
} from '../utils/adminBookingFormatters';

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

function TextField({ label, value, onChange, placeholder, type = 'text' }) {
  return (
    <label className="block">
      <span className="text-[11px] uppercase tracking-[0.16em] text-cream/35">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className="mt-1 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-cream outline-none transition-colors placeholder:text-cream/25 focus:border-gold/45"
      />
    </label>
  );
}

export default function AdminBookingsFilters({ filters, onFilterChange, onClear, onRefresh, refreshing }) {
  return (
    <section className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <div className="grid gap-3 lg:grid-cols-[minmax(240px,1.4fr)_repeat(4,minmax(130px,1fr))]">
        <label className="block">
          <span className="text-[11px] uppercase tracking-[0.16em] text-cream/35">Search</span>
          <div className="mt-1 flex items-center gap-2 rounded-xl border border-white/10 bg-black/30 px-3 py-2 transition-colors focus-within:border-gold/45">
            <FiSearch className="text-cream/35" size={15} />
            <input
              value={filters.search}
              onChange={(event) => onFilterChange('search', event.target.value)}
              placeholder="Booking, property, guest, owner"
              className="min-w-0 flex-1 bg-transparent text-sm text-cream outline-none placeholder:text-cream/25"
            />
          </div>
        </label>

        <SelectField label="Status" value={filters.status} onChange={(value) => onFilterChange('status', value)}>
          {bookingStatusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <TextField label="Property ID" value={filters.property_id} onChange={(value) => onFilterChange('property_id', value)} placeholder="12" type="number" />
        <TextField label="Guest ID" value={filters.guest_id} onChange={(value) => onFilterChange('guest_id', value)} placeholder="44" type="number" />
        <TextField label="Owner ID" value={filters.owner_id} onChange={(value) => onFilterChange('owner_id', value)} placeholder="8" type="number" />
      </div>

      <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <TextField label="City" value={filters.city} onChange={(value) => onFilterChange('city', value)} placeholder="Marrakech" />
        <TextField label="Start from" value={filters.start_date_from} onChange={(value) => onFilterChange('start_date_from', value)} type="date" />
        <TextField label="Start to" value={filters.start_date_to} onChange={(value) => onFilterChange('start_date_to', value)} type="date" />
        <TextField label="End from" value={filters.end_date_from} onChange={(value) => onFilterChange('end_date_from', value)} type="date" />
        <TextField label="End to" value={filters.end_date_to} onChange={(value) => onFilterChange('end_date_to', value)} type="date" />
        <TextField label="Created from" value={filters.created_from} onChange={(value) => onFilterChange('created_from', value)} type="date" />
        <TextField label="Created to" value={filters.created_to} onChange={(value) => onFilterChange('created_to', value)} type="date" />
        <TextField label="Min total" value={filters.min_total} onChange={(value) => onFilterChange('min_total', value)} placeholder="200" type="number" />
      </div>

      <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(5,minmax(120px,1fr))_auto_auto] xl:items-end">
        <TextField label="Max total" value={filters.max_total} onChange={(value) => onFilterChange('max_total', value)} placeholder="1200" type="number" />

        <SelectField label="Has review" value={filters.has_review} onChange={(value) => onFilterChange('has_review', value)}>
          {bookingBooleanOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <SelectField label="Has report" value={filters.has_report} onChange={(value) => onFilterChange('has_report', value)}>
          {bookingBooleanOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <SelectField label="Sort" value={filters.sort} onChange={(value) => onFilterChange('sort', value)}>
          {bookingSortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </SelectField>

        <SelectField label="Per page" value={String(filters.per_page)} onChange={(value) => onFilterChange('per_page', Number(value))}>
          {bookingPerPageOptions.map((value) => <option key={value} value={value}>{value}</option>)}
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
