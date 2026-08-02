import { formatAdminUserDate, locationLabel, statusLabel } from '../utils/adminUserFormatters';

export default function AdminUserRecentActivity({ rows = [] }) {
  if (!rows.length) {
    return <p className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">No recent analytics activity.</p>;
  }

  return (
    <div className="space-y-2">
      {rows.map((activity) => (
        <div key={activity.id} className="rounded-xl border border-white/5 bg-black/20 p-3">
          <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-medium text-cream">{statusLabel(activity.event_type)}</span>
            <span className="text-xs text-cream/35">{activity.country_code || 'Unknown'}</span>
          </div>
          <p className="mt-1 text-xs text-cream/45">
            {locationLabel(activity.city_name, activity.region_name, activity.country_name)}
          </p>
          <p className="mt-1 text-xs text-cream/30">{formatAdminUserDate(activity.occurred_at)}</p>
        </div>
      ))}
    </div>
  );
}
