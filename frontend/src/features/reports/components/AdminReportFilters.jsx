import {
  REPORT_CATEGORY_OPTIONS,
  REPORT_SEVERITY_OPTIONS,
  REPORT_STATUS_OPTIONS,
} from '../utils/reportAdminOptions';

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

export default function AdminReportFilters({ filters, onFilterChange, onReset, disabled = false }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-4">
      <div className="grid gap-3 md:grid-cols-4">
        <FilterSelect
          label="Status"
          value={filters.status}
          options={REPORT_STATUS_OPTIONS}
          onChange={(value) => onFilterChange('status', value)}
        />
        <FilterSelect
          label="Severity"
          value={filters.severity}
          options={REPORT_SEVERITY_OPTIONS}
          onChange={(value) => onFilterChange('severity', value)}
        />
        <FilterSelect
          label="Category"
          value={filters.category}
          options={REPORT_CATEGORY_OPTIONS}
          onChange={(value) => onFilterChange('category', value)}
        />
        <div className="flex items-end">
          <button
            type="button"
            onClick={onReset}
            disabled={disabled}
            className="w-full rounded-xl border border-gold/20 px-4 py-2.5 text-sm text-cream/65 transition-colors hover:border-gold/50 hover:text-cream disabled:opacity-50"
          >
            Reset filters
          </button>
        </div>
      </div>
    </div>
  );
}
