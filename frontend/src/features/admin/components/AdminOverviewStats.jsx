import {
  FiAlertTriangle,
  FiBriefcase,
  FiFlag,
  FiHome,
  FiMessageSquare,
  FiStar,
  FiUsers,
} from 'react-icons/fi';
import AdminStatCard from './AdminStatCard';

export default function AdminOverviewStats({ totals = {}, moderation = {} }) {
  const cards = [
    { label: 'Users', value: totals.users_count, icon: FiUsers },
    { label: 'Properties', value: totals.properties_count, icon: FiHome },
    { label: 'Bookings', value: totals.bookings_count, icon: FiBriefcase },
    { label: 'Reports', value: totals.reports_count, icon: FiFlag },
    { label: 'Reviews', value: totals.reviews_count, icon: FiMessageSquare },
    { label: 'Pending Reports', value: moderation.pending_reports_count, icon: FiAlertTriangle, tone: 'warning' },
    { label: 'Pending Reviews', value: moderation.pending_reviews_count, icon: FiStar, tone: 'warning' },
    { label: 'High Risk Reviews', value: moderation.high_risk_reviews_count, icon: FiAlertTriangle, tone: 'critical' },
  ];

  return (
    <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map((card) => (
        <AdminStatCard key={card.label} {...card} />
      ))}
    </section>
  );
}
