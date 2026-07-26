import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { bookingService, STORAGE_URL } from '../shared/api/api';
import api from '../shared/api/api';
import { useAuth } from '../app/providers/AuthContext';
import { format } from 'date-fns';
import toast from 'react-hot-toast';
import {
  FiCalendar, FiMapPin, FiClock,
  FiArrowRight, FiCheck, FiX, FiMessageCircle
} from 'react-icons/fi';

const STATUS_STYLES = {
  confirmed: 'bg-green-500/10 text-green-400 border-green-500/20',
  pending:   'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
  rejected:  'bg-red-500/10 text-red-400 border-red-500/20',
  completed: 'bg-cream/10 text-cream/60 border-cream/10',
};

export default function Bookings() {
  const navigate     = useNavigate();
  const { user }     = useAuth();
  const [bookings,   setBookings]   = useState([]);
  const [myProps,    setMyProps]    = useState([]); // حجوزات ملكياتي
  const [loading,    setLoad]       = useState(true);
  const [tab,        setTab]        = useState('upcoming');
  const [actionLoad, setActionLoad] = useState(null); // id الحجز الجاري عليه action

  useEffect(() => {
    (async () => {
      try {
        // حجوزاتي كعميل
        const res = await bookingService.list();
        setBookings(res.data?.data || res.data);

        // حجوزات ملكياتي كمالك
        const ownerRes = await api.get('/owner/bookings');
        setMyProps(ownerRes.data?.data || ownerRes.data || []);
      } catch {
        setBookings([]);
      }
      setLoad(false);
    })();
  }, []);

  const handleAccept = async (id) => {
    setActionLoad(id);
    try {
      await bookingService.accept(id);
      setMyProps(prev => prev.map(b => b.id === id ? { ...b, status: 'confirmed' } : b));
      toast.success('Booking confirmed! ✅');
    } catch { toast.error('Failed to confirm booking'); }
    setActionLoad(null);
  };

  const handleReject = async (id) => {
    setActionLoad(id);
    try {
      await bookingService.reject(id);
      setMyProps(prev => prev.map(b => b.id === id ? { ...b, status: 'rejected' } : b));
      toast.success('Booking rejected');
    } catch { toast.error('Failed to reject booking'); }
    setActionLoad(null);
  };

  const now      = new Date();
  const upcoming = bookings.filter(b => new Date(b.end_date) >= now);
  const past     = bookings.filter(b => new Date(b.end_date) < now);
  const shown    = tab === 'upcoming' ? upcoming : tab === 'past' ? past : myProps;

  const nights = (b) => Math.max(1, Math.round(
    (new Date(b.end_date) - new Date(b.start_date)) / 86400000
  ));

  const BookingCard = ({ b, isOwnerView = false }) => {
    const prop  = b.property || {};
    const img   = prop.images?.[0]?.path
      ? `${STORAGE_URL}/storage/${prop.images[0].path}`
      : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=70';
    const n     = nights(b);
    const total = n * Number(prop.price_per_night || 0);

    return (
      <div className="luxury-card rounded-2xl overflow-hidden flex flex-col sm:flex-row">
        {/* Image */}
        <div className="w-full sm:w-48 h-40 sm:h-auto shrink-0 overflow-hidden">
          <img src={img} className="w-full h-full object-cover"
               onError={e => e.target.src = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=70'} />
        </div>

        {/* Details */}
        <div className="flex-1 p-5 flex flex-col justify-between">
          <div>
            <div className="flex items-start justify-between gap-3 mb-2 flex-wrap">
              <div>
                <h3 className="font-display text-xl text-cream">{prop.title || prop.name || 'Property'}</h3>
                <div className="flex items-center gap-1 text-cream/40 text-xs mt-0.5">
                  <FiMapPin size={10} /><span>{prop.city || '—'}</span>
                </div>
                {/* اسم العميل للمالك */}
                {isOwnerView && b.user && (
                  <p className="text-xs text-gold/70 mt-1">👤 {b.user.name} · {b.user.email}</p>
                )}
              </div>
              {b.status && (
                <span className={`px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider border ${STATUS_STYLES[b.status] || STATUS_STYLES.pending}`}>
                  {b.status}
                </span>
              )}
            </div>

            <div className="flex flex-wrap gap-4 mt-3 text-sm text-cream/55">
              <div className="flex items-center gap-1.5">
                <FiCalendar size={12} className="text-gold/60" />
                <span>{format(new Date(b.start_date), 'MMM d')} — {format(new Date(b.end_date), 'MMM d, yyyy')}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <FiClock size={12} className="text-gold/60" />
                <span>{n} night{n > 1 ? 's' : ''}</span>
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between mt-4 pt-4 border-t border-gold/8 flex-wrap gap-3">
            {total > 0 && (
              <div>
                <span className="text-gold font-medium">${total.toLocaleString()}</span>
                <span className="text-cream/30 text-xs"> total</span>
              </div>
            )}

            <div className="flex items-center gap-2 ml-auto">
              {/* زر Chat مع العميل/المالك */}
              <button
                onClick={() => navigate(`/chat?property_id=${prop.id}`)}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gold/25 text-gold/70 hover:border-gold hover:text-gold transition-colors text-xs">
                <FiMessageCircle size={13} /> Chat
              </button>

              {/* أزرار Accept/Reject للمالك فقط على pending */}
              {isOwnerView && b.status === 'pending' && (
                <>
                  <button
                    onClick={() => handleReject(b.id)}
                    disabled={actionLoad === b.id}
                    className="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs disabled:opacity-50">
                    {actionLoad === b.id ? '...' : <><FiX size={13} /> Reject</>}
                  </button>
                  <button
                    onClick={() => handleAccept(b.id)}
                    disabled={actionLoad === b.id}
                    className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-green-500/15 border border-green-500/30 text-green-400 hover:bg-green-500/25 transition-colors text-xs disabled:opacity-50">
                    {actionLoad === b.id ? '...' : <><FiCheck size={13} /> Accept</>}
                  </button>
                </>
              )}

              <button onClick={() => navigate(`/properties/${prop.id}`)}
                className="flex items-center gap-1.5 text-xs text-gold/70 hover:text-gold transition-colors">
                View <FiArrowRight size={11} />
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="min-h-screen px-4 py-10 max-w-4xl mx-auto">
      <div className="mb-8 fade-up">
        <div className="ornament-divider mb-3 max-w-xs">
          <span className="text-xs tracking-[0.3em] text-gold/55 uppercase">My Account</span>
        </div>
        <h1 className="font-display text-4xl sm:text-5xl font-light text-cream">
          My <span className="text-gold-gradient italic">Stays</span>
        </h1>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 mb-8 p-1 rounded-xl w-fit"
           style={{ background: 'rgba(28,28,46,0.8)', border: '1px solid rgba(201,168,76,0.1)' }}>
        {[
          { key: 'upcoming', label: `Upcoming (${upcoming.length})` },
          { key: 'past',     label: `Past (${past.length})` },
          { key: 'owner',    label: `My Properties' Bookings (${myProps.length})` },
        ].map(t => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`px-4 py-2 rounded-lg text-sm transition-all ${
              tab === t.key ? 'bg-gold text-obsidian font-medium' : 'text-cream/50 hover:text-cream'
            }`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* Content */}
      {loading ? (
        <div className="space-y-4">
          {[1,2].map(i => <div key={i} className="h-40 shimmer rounded-2xl" />)}
        </div>
      ) : shown.length === 0 ? (
        <div className="text-center py-24">
          <FiCalendar size={40} className="mx-auto text-cream/15 mb-4" />
          <h3 className="font-display text-2xl text-cream/40 mb-2">No bookings here</h3>
          {tab !== 'owner' && (
            <button onClick={() => navigate('/properties')} className="btn-gold px-6 py-2.5 rounded-full text-sm mt-4">
              Explore Properties
            </button>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          {shown.map((b, i) => (
            <div key={b.id} className={`fade-up fade-up-${Math.min(i+1,4)}`}>
              <BookingCard b={b} isOwnerView={tab === 'owner'} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
