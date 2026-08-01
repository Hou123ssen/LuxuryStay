export const formatNumber = (value) => {
  const number = Number(value || 0);
  return new Intl.NumberFormat().format(number);
};

export const formatDateTime = (value) => {
  if (!value) return 'Not available';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Not available';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

export const formatStatusLabel = (value) => {
  if (!value) return 'Unknown';

  return String(value)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const alertSeverityStyles = {
  info: 'border-gold/15 bg-gold/5 text-gold',
  warning: 'border-amber-400/25 bg-amber-400/10 text-amber-100',
  critical: 'border-red-400/25 bg-red-500/10 text-red-100',
};

export const statusToneClasses = {
  pending: 'border-amber-400/25 bg-amber-400/10 text-amber-100',
  pending_review: 'border-amber-400/25 bg-amber-400/10 text-amber-100',
  reviewed: 'border-blue-300/20 bg-blue-300/10 text-blue-100',
  accepted: 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100',
  published: 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100',
  completed: 'border-gold/20 bg-gold/10 text-gold',
  resolved: 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100',
  rejected: 'border-red-300/20 bg-red-400/10 text-red-100',
  cancelled: 'border-white/10 bg-white/5 text-cream/60',
};

export const statusClasses = (status) => (
  statusToneClasses[status] || 'border-white/10 bg-white/5 text-cream/60'
);
