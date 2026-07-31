import { Link } from 'react-router-dom';
import { FiAlertTriangle, FiShield, FiStar } from 'react-icons/fi';

const ADMIN_CARDS = [
  {
    to: '/admin/reports',
    title: 'Reports Moderation',
    description: 'Review guest reports and resolve platform safety issues.',
    icon: FiAlertTriangle,
  },
  {
    to: '/admin/reviews',
    title: 'Reviews Moderation',
    description: 'Publish verified reviews or reject harmful submissions.',
    icon: FiStar,
  },
];

export default function AdminDashboard() {
  return (
    <div className="min-h-screen px-4 py-10">
      <div className="mx-auto max-w-5xl">
        <div className="mb-8 fade-up">
          <div className="ornament-divider mb-3 max-w-sm">
            <span className="text-xs uppercase tracking-[0.3em] text-gold/55">Admin</span>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="font-display text-4xl font-light text-cream sm:text-5xl">
                Admin <span className="text-gold-gradient italic">Dashboard</span>
              </h1>
              <p className="mt-3 text-sm leading-6 text-cream/45">
                Platform control center
              </p>
            </div>
            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/15 bg-gold/5 px-3 py-2 text-sm text-gold/80">
              <FiShield size={15} />
              Admin only
            </div>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          {ADMIN_CARDS.map((card) => {
            const Icon = card.icon;

            return (
              <Link
                key={card.to}
                to={card.to}
                className="group rounded-2xl border border-gold/10 bg-white/[0.03] p-5 transition-all hover:border-gold/35 hover:bg-gold/[0.05]"
              >
                <div className="mb-5 inline-flex rounded-2xl border border-gold/20 bg-gold/5 p-3 text-gold">
                  <Icon size={22} />
                </div>
                <h2 className="font-display text-2xl text-cream transition-colors group-hover:text-gold">
                  {card.title}
                </h2>
                <p className="mt-2 text-sm leading-6 text-cream/45">
                  {card.description}
                </p>
              </Link>
            );
          })}
        </div>
      </div>
    </div>
  );
}
