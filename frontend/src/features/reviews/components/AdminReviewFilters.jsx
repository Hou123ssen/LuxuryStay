import {
  REVIEW_RATING_OPTIONS,
  REVIEW_RISK_LEVEL_OPTIONS,
  REVIEW_STATUS_OPTIONS,
} from '../utils/reviewAdminOptions';

function FilterSelect({ label, value, options, onChange }) {
  return (
    <label className="block text-xs uppercase tracking-[0.22em] text-gold/55">
      {label}
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="mt-2 w-full rounded-xl border border-gold/15 bg-[#101018] px-3 py-2.5 text-sm normal-case tracking-normal text-cream outline-none focus:border-gold/60"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function FilterInput({ label, value, placeholder, onChange }) {
  return (
    <label className="block text-xs uppercase tracking-[0.22em] text-gold/55">
      {label}
      <input
        value={value}
        inputMode="numeric"
        onChange={(event) => onChange(event.target.value.replace(/[^\d]/g, ''))}
        placeholder={placeholder}
        className="mt-2 w-full rounded-xl border border-gold/15 bg-[#101018] px-3 py-2.5 text-sm normal-case tracking-normal text-cream outline-none placeholder:text-cream/25 focus:border-gold/60"
      />
    </label>
  );
}

export default function AdminReviewFilters({ filters, onFilterChange, onReset, disabled = false }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-4">
      <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <FilterSelect
          label="Status"
          value={filters.status}
          options={REVIEW_STATUS_OPTIONS}
          onChange={(value) => onFilterChange('status', value)}
        />
        <FilterSelect
          label="Rating"
          value={filters.rating}
          options={REVIEW_RATING_OPTIONS}
          onChange={(value) => onFilterChange('rating', value)}
        />
        <FilterSelect
          label="Risk"
          value={filters.risk_level}
          options={REVIEW_RISK_LEVEL_OPTIONS}
          onChange={(value) => onFilterChange('risk_level', value)}
        />
        <FilterInput
          label="Property ID"
          value={filters.property_id}
          placeholder="Any"
          onChange={(value) => onFilterChange('property_id', value)}
        />
        <FilterInput
          label="User ID"
          value={filters.user_id}
          placeholder="Any"
          onChange={(value) => onFilterChange('user_id', value)}
        />
        <div className="flex items-end">
          <button
            type="button"
            onClick={onReset}
            disabled={disabled}
            className="w-full rounded-xl border border-gold/20 px-4 py-2.5 text-sm text-cream/65 transition-colors hover:border-gold/50 hover:text-cream disabled:opacity-50"
          >
            Reset
          </button>
        </div>
      </div>
    </div>
  );
}
