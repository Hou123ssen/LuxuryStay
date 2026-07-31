import { reportSeverityLabel, reportStatusLabel } from '../utils/reportAdminOptions';

const STATUS_STYLES = {
  pending: 'border-amber-300/35 bg-amber-300/10 text-amber-100',
  reviewed: 'border-sky-300/30 bg-sky-300/10 text-sky-100',
  resolved: 'border-emerald-300/30 bg-emerald-300/10 text-emerald-100',
  rejected: 'border-red-300/30 bg-red-300/10 text-red-100',
};

const SEVERITY_STYLES = {
  low: 'border-cream/15 bg-white/5 text-cream/60',
  normal: 'border-gold/20 bg-gold/5 text-gold/80',
  high: 'border-orange-300/35 bg-orange-300/10 text-orange-100',
  critical: 'border-red-300/40 bg-red-300/10 text-red-100',
};

export default function AdminReportStatusBadge({ value, type = 'status' }) {
  const styles = type === 'severity' ? SEVERITY_STYLES : STATUS_STYLES;
  const label = type === 'severity' ? reportSeverityLabel(value) : reportStatusLabel(value);

  return (
    <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${styles[value] || styles.normal || styles.pending}`}>
      {label}
    </span>
  );
}
