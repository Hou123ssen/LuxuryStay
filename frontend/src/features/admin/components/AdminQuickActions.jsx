import { Link } from 'react-router-dom';
import { FiAlertTriangle, FiStar } from 'react-icons/fi';

const ACTIONS = [
  {
    to: '/admin/reports',
    title: 'Reports Moderation',
    description: 'Review guest reports and resolve platform safety issues.',
    icon: FiAlertTriangle,
  },
  {
    to: '/admin/reviews',
    title: 'Reviews Moderation',
    description: 'Publish valid reviews or reject harmful submissions.',
    icon: FiStar,
  },
];

export default function AdminQuickActions() {
  return (
    <section className="grid gap-4 md:grid-cols-2">
      {ACTIONS.map((action) => {
        const Icon = action.icon;

        return (
          <Link
            key={action.to}
            to={action.to}
            className="group rounded-2xl border border-gold/10 bg-white/[0.03] p-5 transition-all hover:border-gold/35 hover:bg-gold/[0.05]"
          >
            <div className="mb-4 inline-flex rounded-2xl border border-gold/20 bg-gold/5 p-3 text-gold">
              <Icon size={21} />
            </div>
            <h2 className="font-display text-2xl text-cream transition-colors group-hover:text-gold">
              {action.title}
            </h2>
            <p className="mt-2 text-sm leading-6 text-cream/45">{action.description}</p>
          </Link>
        );
      })}
    </section>
  );
}
