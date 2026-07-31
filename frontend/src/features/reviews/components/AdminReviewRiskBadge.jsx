export default function AdminReviewRiskBadge({ score }) {
  const value = Number(score || 0);
  const high = value >= 70;
  const medium = value >= 35 && value < 70;
  const style = high
    ? 'border-red-300/35 bg-red-300/10 text-red-100'
    : medium
      ? 'border-amber-300/30 bg-amber-300/10 text-amber-100'
      : 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100';
  const label = high ? 'High risk' : medium ? 'Review signal' : 'Low risk';

  return (
    <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${style}`}>
      {label}: {value}
    </span>
  );
}
