import { statusLabel } from '../utils/adminPropertyFormatters';

export default function AdminPropertyStatusBadge({ status }) {
  const hasStatus = Boolean(status);

  return (
    <span
      className={`inline-flex w-fit rounded-full border px-2.5 py-1 text-xs ${
        hasStatus
          ? 'border-gold/25 bg-gold/10 text-gold'
          : 'border-white/10 bg-white/[0.03] text-cream/45'
      }`}
    >
      {statusLabel(status)}
    </span>
  );
}
