import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../app/providers/AuthContext';
import { FiMenu, FiX, FiHeart, FiMessageCircle, FiBell, FiUser, FiLogOut, FiCalendar, FiPlusCircle } from 'react-icons/fi'; // ← FiPlusCircle added

import { FiShield } from 'react-icons/fi';
import { navbarCountsService } from '../api/navbarCountsApi';
import { NAVBAR_COUNTS_REFRESH_EVENT } from '../utils/navbarCountsEvents';

const formatBadgeCount = (count) => {
  const value = Number(count || 0);
  return value > 99 ? '99+' : String(value);
};

const CountBadge = ({ count, className = '' }) => {
  if (Number(count || 0) <= 0) return null;

  return (
    <span className={`inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full border border-gold/60 bg-gold px-1.5 text-[10px] font-semibold leading-none text-[#0a0a0f] shadow-[0_0_18px_rgba(201,168,76,0.28)] ${className}`}>
      {formatBadgeCount(count)}
    </span>
  );
};

export default function Navbar() {
  const { user, logout, isAuth } = useAuth();
  const navigate  = useNavigate();
  const location  = useLocation();
  const [open, setOpen]         = useState(false);
  const [dropdown, setDropdown] = useState(false);
  const [counts, setCounts] = useState({
    unread_messages_count: 0,
    unread_notifications_count: 0,
  });

  const loadCounts = useCallback(async () => {
    if (!isAuth) {
      setCounts({
        unread_messages_count: 0,
        unread_notifications_count: 0,
      });
      return;
    }

    try {
      const res = await navbarCountsService.getCounts();
      setCounts(res.data?.data || {
        unread_messages_count: 0,
        unread_notifications_count: 0,
      });
    } catch {
      // Keep the previous count on transient polling/focus failures.
    }
  }, [isAuth]);

  useEffect(() => {
    if (!isAuth) {
      setCounts({
        unread_messages_count: 0,
        unread_notifications_count: 0,
      });
      return undefined;
    }

    loadCounts();
    const intervalId = setInterval(loadCounts, 20000);
    const handleFocus = () => loadCounts();
    const handleRefresh = (event) => {
      if (event.detail?.counts) {
        setCounts(prev => ({ ...prev, ...event.detail.counts }));
      }

      loadCounts();
    };

    window.addEventListener('focus', handleFocus);
    window.addEventListener(NAVBAR_COUNTS_REFRESH_EVENT, handleRefresh);

    return () => {
      clearInterval(intervalId);
      window.removeEventListener('focus', handleFocus);
      window.removeEventListener(NAVBAR_COUNTS_REFRESH_EVENT, handleRefresh);
    };
  }, [isAuth, loadCounts]);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const navLinks = isAuth
    ? [
        { to: '/properties', label: 'Explore' },
        { to: '/bookings',   label: 'My Stays',  icon: <FiCalendar size={14} /> },
        { to: '/favorites',  label: 'Favorites',  icon: <FiHeart size={14} /> },
        { to: '/chat',       label: 'Messages',   icon: <FiMessageCircle size={14} />, badge: counts.unread_messages_count },
        ...(user?.role === 'admin'
          ? [{ to: '/admin/reports', label: 'Reports', icon: <FiShield size={14} /> }]
          : []),
      ]
    : [
        { to: '/properties', label: 'Explore' },
      ];

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 h-16"
         style={{ background: 'rgba(10,10,15,0.85)', backdropFilter: 'blur(20px)', borderBottom: '1px solid rgba(201,168,76,0.1)' }}>
      <div className="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
        {/* Logo */}
        <Link to="/" className="flex items-center gap-2 group">
          <span className="text-2xl font-display font-light tracking-widest text-gold-gradient group-hover:opacity-90 transition-opacity">
            LUXURYSTAY
          </span>
        </Link>

        {/* Desktop links */}
        <div className="hidden md:flex items-center gap-6">
          {navLinks.map(l => (
            <Link key={l.to} to={l.to}
              className={`flex items-center gap-1.5 text-sm tracking-wide transition-colors ${
                location.pathname.startsWith(l.to)
                  ? 'text-gold' : 'text-cream/60 hover:text-cream'
              }`}>
              {l.icon}{l.label}
              <CountBadge count={l.badge} />
            </Link>
          ))}
        </div>

        {/* Right side */}
        <div className="hidden md:flex items-center gap-3">
          {isAuth ? (
            <>
              <Link to="/notifications" className="p-2 text-cream/50 hover:text-gold transition-colors relative">
                <FiBell size={18} />
                <CountBadge count={counts.unread_notifications_count} className="absolute -right-1 -top-1" />
              </Link>

              {/* ← NEW: List Property button */}
              <Link to="/properties/new"
                className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border border-gold/30 text-gold/80 hover:border-gold hover:text-gold hover:bg-gold/5 transition-all text-sm">
                <FiPlusCircle size={14} />
                <span>List Property</span>
              </Link>

              <div className="relative">
                <button onClick={() => setDropdown(!dropdown)}
                  className="flex items-center gap-2 px-3 py-1.5 rounded-full border border-gold/20 hover:border-gold/50 transition-colors">
                  <FiUser size={15} className="text-gold" />
                  <span className="text-sm text-cream/80">{user?.name?.split(' ')[0]}</span>
                </button>
                {dropdown && (
                  <div className="absolute right-0 mt-2 w-44 rounded-xl overflow-hidden"
                       style={{ background: '#1c1c2e', border: '1px solid rgba(201,168,76,0.2)', boxShadow: '0 20px 60px rgba(0,0,0,0.5)' }}>
                    <Link to="/profile" onClick={() => setDropdown(false)}
                      className="flex items-center gap-2 px-4 py-3 text-sm text-cream/70 hover:text-cream hover:bg-white/5 transition-colors">
                      <FiUser size={14} /> Profile
                    </Link>
                    <button onClick={() => { setDropdown(false); handleLogout(); }}
                      className="w-full flex items-center gap-2 px-4 py-3 text-sm text-red-400/80 hover:text-red-400 hover:bg-white/5 transition-colors">
                      <FiLogOut size={14} /> Sign Out
                    </button>
                  </div>
                )}
              </div>
            </>
          ) : (
            <div className="flex items-center gap-3">
              <Link to="/login" className="text-sm text-cream/60 hover:text-cream transition-colors">Sign In</Link>
              <Link to="/register" className="btn-gold px-4 py-2 rounded-full text-sm font-medium">
                Get Started
              </Link>
            </div>
          )}
        </div>

        {/* Mobile hamburger */}
        <button className="md:hidden text-cream/70" onClick={() => setOpen(!open)}>
          {open ? <FiX size={22} /> : <FiMenu size={22} />}
        </button>
      </div>

      {/* Mobile menu */}
      {open && (
        <div className="md:hidden px-4 pb-4 space-y-1"
             style={{ background: 'rgba(10,10,15,0.98)', borderBottom: '1px solid rgba(201,168,76,0.1)' }}>
          {navLinks.map(l => (
            <Link key={l.to} to={l.to} onClick={() => setOpen(false)}
              className="flex items-center gap-2 py-3 text-cream/70 hover:text-cream border-b border-white/5 text-sm">
              {l.icon}{l.label}
              <CountBadge count={l.badge} className="ml-auto" />
            </Link>
          ))}
          {isAuth ? (
            <>
              {/* ← NEW: mobile List Property link */}
              <Link to="/notifications" onClick={() => setOpen(false)}
                className="flex items-center gap-2 py-3 text-cream/70 hover:text-cream border-b border-white/5 text-sm">
                <FiBell size={14} /> Notifications
                <CountBadge count={counts.unread_notifications_count} className="ml-auto" />
              </Link>
              <Link to="/properties/new" onClick={() => setOpen(false)}
                className="flex items-center gap-2 py-3 text-gold/80 border-b border-white/5 text-sm">
                <FiPlusCircle size={14} /> List a Property
              </Link>
              <button onClick={() => { setOpen(false); handleLogout(); }}
                className="flex items-center gap-2 py-3 text-red-400/80 text-sm w-full">
                <FiLogOut size={14} /> Sign Out
              </button>
            </>
          ) : (
            <div className="flex gap-3 pt-2">
              <Link to="/login" onClick={() => setOpen(false)} className="flex-1 text-center py-2.5 border border-gold/30 rounded-full text-sm text-cream/70">Sign In</Link>
              <Link to="/register" onClick={() => setOpen(false)} className="flex-1 text-center py-2.5 btn-gold rounded-full text-sm">Register</Link>
            </div>
          )}
        </div>
      )}
    </nav>
  );
}
