import { format } from 'date-fns';
import {
  FiArrowRight,
  FiCalendar,
  FiCheck,
  FiClock,
  FiMapPin,
  FiMessageCircle,
  FiX,
} from 'react-icons/fi';
import { STORAGE_URL } from '../../../shared/api/api';

const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=70';

const STATUS_STYLES = {
  accepted: 'bg-green-500/10 text-green-400 border-green-500/20',
  confirmed: 'bg-green-500/10 text-green-400 border-green-500/20',
  pending: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
  rejected: 'bg-red-500/10 text-red-400 border-red-500/20',
  completed: 'bg-cream/10 text-cream/60 border-cream/10',
};

function nights(booking) {
  return Math.max(1, Math.round(
    (new Date(booking.end_date) - new Date(booking.start_date)) / 86400000,
  ));
}

export default function BookingCard({
  booking,
  isOwnerView = false,
  actionLoad = null,
  onAccept,
  onReject,
  onNavigate,
}) {
  const property = booking.property || {};
  const image = property.images?.[0]?.path
    ? `${STORAGE_URL}/storage/${property.images[0].path}`
    : FALLBACK_IMAGE;
  const nightsCount = nights(booking);
  const total = nightsCount * Number(property.price_per_night || 0);

  return (
    <div className="luxury-card rounded-2xl overflow-hidden flex flex-col sm:flex-row">
      <div className="w-full sm:w-48 h-40 sm:h-auto shrink-0 overflow-hidden">
        <img
          src={image}
          className="w-full h-full object-cover"
          onError={(event) => {
            event.currentTarget.src = FALLBACK_IMAGE;
          }}
          alt={property.title || property.name || 'Property'}
        />
      </div>

      <div className="flex-1 p-5 flex flex-col justify-between">
        <div>
          <div className="flex items-start justify-between gap-3 mb-2 flex-wrap">
            <div>
              <h3 className="font-display text-xl text-cream">
                {property.title || property.name || 'Property'}
              </h3>
              <div className="flex items-center gap-1 text-cream/40 text-xs mt-0.5">
                <FiMapPin size={10} />
                <span>{property.city || '-'}</span>
              </div>
              {isOwnerView && booking.user && (
                <p className="text-xs text-gold/70 mt-1">
                  {booking.user.name} - {booking.user.email}
                </p>
              )}
            </div>
            {booking.status && (
              <span className={`px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider border ${STATUS_STYLES[booking.status] || STATUS_STYLES.pending}`}>
                {booking.status}
              </span>
            )}
          </div>

          <div className="flex flex-wrap gap-4 mt-3 text-sm text-cream/55">
            <div className="flex items-center gap-1.5">
              <FiCalendar size={12} className="text-gold/60" />
              <span>
                {format(new Date(booking.start_date), 'MMM d')} - {format(new Date(booking.end_date), 'MMM d, yyyy')}
              </span>
            </div>
            <div className="flex items-center gap-1.5">
              <FiClock size={12} className="text-gold/60" />
              <span>{nightsCount} night{nightsCount > 1 ? 's' : ''}</span>
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

          <div className="flex items-center gap-2 ml-auto flex-wrap justify-end">
            <button
              type="button"
              onClick={() => onNavigate(`/chat?property_id=${property.id}`)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gold/25 text-gold/70 hover:border-gold hover:text-gold transition-colors text-xs"
            >
              <FiMessageCircle size={13} /> Chat
            </button>

            {isOwnerView && booking.status === 'pending' && (
              <>
                <button
                  type="button"
                  onClick={() => onReject(booking.id)}
                  disabled={actionLoad === booking.id}
                  className="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs disabled:opacity-50"
                >
                  {actionLoad === booking.id ? '...' : <><FiX size={13} /> Reject</>}
                </button>
                <button
                  type="button"
                  onClick={() => onAccept(booking.id)}
                  disabled={actionLoad === booking.id}
                  className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-green-500/15 border border-green-500/30 text-green-400 hover:bg-green-500/25 transition-colors text-xs disabled:opacity-50"
                >
                  {actionLoad === booking.id ? '...' : <><FiCheck size={13} /> Accept</>}
                </button>
              </>
            )}

            <button
              type="button"
              onClick={() => onNavigate(`/properties/${property.id}`)}
              className="flex items-center gap-1.5 text-xs text-gold/70 hover:text-gold transition-colors"
            >
              View <FiArrowRight size={11} />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
