import { Link } from 'react-router-dom';
import { FiAlertTriangle, FiCheckCircle } from 'react-icons/fi';
import { alertSeverityStyles, formatStatusLabel } from '../utils/adminDashboardFormatters';

export default function AdminAlertList({ alerts = [] }) {
  if (!alerts.length) {
    return (
      <section className="rounded-2xl border border-emerald-300/15 bg-emerald-300/10 p-5">
        <div className="flex items-center gap-3 text-emerald-100">
          <FiCheckCircle size={18} />
          <div>
            <h2 className="font-display text-2xl text-cream">No urgent platform alerts.</h2>
            <p className="mt-1 text-sm text-cream/50">Moderation and safety queues are calm.</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="rounded-2xl border border-gold/10 bg-white/[0.03] p-5">
      <h2 className="font-display text-2xl text-cream">Alerts</h2>
      <div className="mt-4 grid gap-3">
        {alerts.map((alert) => (
          <div
            key={`${alert.type}-${alert.title}`}
            className={`rounded-xl border p-4 ${alertSeverityStyles[alert.severity] || alertSeverityStyles.info}`}
          >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="flex gap-3">
                <FiAlertTriangle className="mt-0.5 shrink-0" size={18} />
                <div>
                  <div className="text-xs uppercase tracking-[0.18em] opacity-70">
                    {formatStatusLabel(alert.severity)}
                  </div>
                  <h3 className="mt-1 font-medium text-cream">{alert.title}</h3>
                  <p className="mt-1 text-sm leading-6 text-cream/55">{alert.description}</p>
                </div>
              </div>
              {alert.action_url && (
                <Link
                  to={alert.action_url}
                  className="inline-flex w-fit items-center justify-center rounded-full border border-gold/30 px-3 py-1.5 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
                >
                  Open
                </Link>
              )}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
