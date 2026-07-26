import { useAuth } from '../../../app/providers/AuthContext';
import { useNavigate } from 'react-router-dom';
import { FiUser, FiMail, FiCalendar, FiHeart, FiMessageCircle, FiLogOut, FiArrowRight } from 'react-icons/fi';

export default function Profile() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const quickLinks = [
    { icon: <FiCalendar size={16} />, label: 'My Stays',    sub: 'View your bookings',       to: '/bookings' },
    { icon: <FiHeart size={16} />,    label: 'Favorites',   sub: 'Saved properties',          to: '/favorites' },
    { icon: <FiMessageCircle size={16} />, label: 'Messages', sub: 'Your conversations',      to: '/chat' },
  ];

  return (
    <div className="min-h-screen px-4 py-10 max-w-xl mx-auto">
      <div className="mb-8 fade-up">
        <div className="ornament-divider mb-3 max-w-xs">
          <span className="text-xs tracking-[0.3em] text-gold/55 uppercase">Account</span>
        </div>
        <h1 className="font-display text-4xl font-light text-cream">
          My <span className="text-gold-gradient italic">Profile</span>
        </h1>
      </div>

      {/* Avatar card */}
      <div className="luxury-card rounded-3xl p-8 text-center mb-6 fade-up fade-up-1">
        <div className="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center text-3xl font-display"
             style={{ background: 'linear-gradient(135deg, rgba(201,168,76,0.3) 0%, rgba(201,168,76,0.1) 100%)', color: 'var(--gold)', border: '2px solid rgba(201,168,76,0.3)' }}>
          {user?.name?.[0]?.toUpperCase() || '?'}
        </div>
        <h2 className="font-display text-2xl text-cream mb-1">{user?.name}</h2>
        <div className="flex items-center justify-center gap-1.5 text-cream/40 text-sm">
          <FiMail size={12} />
          <span>{user?.email}</span>
        </div>
        {user?.created_at && (
          <p className="text-cream/25 text-xs mt-2">
            Member since {new Date(user.created_at).getFullYear()}
          </p>
        )}
      </div>

      {/* Quick links */}
      <div className="space-y-3 mb-6 fade-up fade-up-2">
        {quickLinks.map(l => (
          <button key={l.to} onClick={() => navigate(l.to)}
            className="luxury-card w-full rounded-2xl px-5 py-4 flex items-center gap-4 text-left hover:border-gold/30 transition-colors">
            <div className="w-10 h-10 rounded-xl flex items-center justify-center text-gold"
                 style={{ background: 'rgba(201,168,76,0.1)' }}>
              {l.icon}
            </div>
            <div className="flex-1">
              <p className="text-sm font-medium text-cream">{l.label}</p>
              <p className="text-xs text-cream/35">{l.sub}</p>
            </div>
            <FiArrowRight size={15} className="text-cream/25" />
          </button>
        ))}
      </div>

      {/* Sign out */}
      <button onClick={handleLogout}
        className="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl border border-red-500/25 text-red-400/80 hover:bg-red-500/8 hover:text-red-400 transition-colors text-sm fade-up fade-up-3">
        <FiLogOut size={15} /> Sign Out
      </button>
    </div>
  );
}
