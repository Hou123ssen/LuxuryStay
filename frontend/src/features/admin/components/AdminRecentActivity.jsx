import { formatDateTime, formatStatusLabel, statusClasses } from '../utils/adminDashboardFormatters';

function ActivityList({ title, items, renderPrimary, renderMeta }) {
  return (
    <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <h3 className="font-display text-2xl text-cream">{title}</h3>
      <div className="mt-4 space-y-3">
        {items?.length ? items.map((item) => (
          <div key={`${title}-${item.id}`} className="rounded-xl border border-white/5 bg-black/15 px-4 py-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-sm text-cream">{renderPrimary(item)}</div>
                <div className="mt-1 text-xs text-cream/40">{renderMeta(item)}</div>
              </div>
              <span className={`inline-flex w-fit rounded-full border px-2.5 py-1 text-xs ${statusClasses(item.status)}`}>
                {formatStatusLabel(item.status)}
              </span>
            </div>
            <div className="mt-2 text-xs text-cream/35">{formatDateTime(item.created_at)}</div>
          </div>
        )) : (
          <div className="rounded-xl border border-white/5 bg-black/15 px-4 py-6 text-sm text-cream/45">
            No recent activity.
          </div>
        )}
      </div>
    </div>
  );
}

export default function AdminRecentActivity({ activity = {} }) {
  return (
    <section>
      <h2 className="mb-4 font-display text-2xl text-cream">Recent Activity</h2>
      <div className="grid gap-4 xl:grid-cols-3">
        <ActivityList
          title="Bookings"
          items={activity.bookings}
          renderPrimary={(item) => item.property_title || `Property #${item.property_id}`}
          renderMeta={(item) => `Booking #${item.id} - Guest #${item.user_id} - ${item.start_date || 'N/A'} to ${item.end_date || 'N/A'}`}
        />
        <ActivityList
          title="Reports"
          items={activity.reports}
          renderPrimary={(item) => item.property_title || `Property #${item.property_id}`}
          renderMeta={(item) => `Report #${item.id} - ${formatStatusLabel(item.category)} - ${formatStatusLabel(item.severity)}`}
        />
        <ActivityList
          title="Reviews"
          items={activity.reviews}
          renderPrimary={(item) => item.property_title || `Property #${item.property_id}`}
          renderMeta={(item) => `Review #${item.id} - User #${item.user_id} - ${item.rating}/5`}
        />
      </div>
    </section>
  );
}
