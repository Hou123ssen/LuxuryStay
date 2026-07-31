import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiCalendar } from 'react-icons/fi';
import toast from 'react-hot-toast';
import Pagination from '../../../shared/components/common/Pagination';
import { useAuth } from '../../../app/providers/AuthContext';
import { bookingService } from '../api/bookingApi';
import BookingCard from '../components/BookingCard';
import CancelBookingModal from '../components/CancelBookingModal';
import { useBookingsPagination } from '../hooks/useBookingsPagination';
import { canCancelBooking } from '../utils/bookingCancellation';

const TABS = [
  { key: 'upcoming', label: 'Upcoming' },
  { key: 'past', label: 'Past' },
  { key: 'owner', label: "My Properties' Bookings" },
];

export default function Bookings() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const {
    items: shown,
    setItems: setShown,
    meta,
    loading,
    tab,
    page,
    bookingRefs,
    highlightedBookingId,
    refresh,
    handleTabChange,
    goToPage,
  } = useBookingsPagination();
  const [actionLoad, setActionLoad] = useState(null);
  const [cancelTarget, setCancelTarget] = useState(null);

  const handleAccept = async (id) => {
    setActionLoad(id);
    try {
      await bookingService.accept(id);
      setShown((prev) => prev.map((booking) => (
        booking.id === id ? { ...booking, status: 'accepted' } : booking
      )));
      toast.success('Booking accepted!');
    } catch (err) {
      toast.error(err.response?.data?.message || err.response?.data?.error || 'Failed to accept booking');
    } finally {
      setActionLoad(null);
    }
  };

  const handleReject = async (id) => {
    setActionLoad(id);
    try {
      await bookingService.reject(id);
      setShown((prev) => prev.map((booking) => (
        booking.id === id ? { ...booking, status: 'rejected' } : booking
      )));
      toast.success('Booking rejected');
    } catch (err) {
      toast.error(err.response?.data?.message || err.response?.data?.error || 'Failed to reject booking');
    } finally {
      setActionLoad(null);
    }
  };

  const handleCancel = async (reason) => {
    if (!cancelTarget) return;

    setActionLoad(cancelTarget.id);
    try {
      await bookingService.cancel(cancelTarget.id, { reason });
      toast.success('Booking cancelled');
      setCancelTarget(null);
      await refresh();
    } catch (err) {
      toast.error(err.response?.data?.message || err.response?.data?.error || 'Failed to cancel booking');
    } finally {
      setActionLoad(null);
    }
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

      <div
        className="flex gap-1 mb-8 p-1 rounded-xl w-fit max-w-full overflow-x-auto"
        style={{ background: 'rgba(28,28,46,0.8)', border: '1px solid rgba(201,168,76,0.1)' }}
      >
        {TABS.map((item) => (
          <button
            key={item.key}
            type="button"
            onClick={() => handleTabChange(item.key)}
            className={`px-4 py-2 rounded-lg text-sm transition-all whitespace-nowrap ${
              tab === item.key ? 'bg-gold text-obsidian font-medium' : 'text-cream/50 hover:text-cream'
            }`}
          >
            {item.label}
            {tab === item.key && meta?.total !== undefined ? ` (${meta.total})` : ''}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="space-y-4">
          {[1, 2].map((item) => (
            <div key={item} className="h-40 shimmer rounded-2xl" />
          ))}
        </div>
      ) : shown.length === 0 ? (
        <div className="text-center py-24">
          <FiCalendar size={40} className="mx-auto text-cream/15 mb-4" />
          <h3 className="font-display text-2xl text-cream/40 mb-2">No bookings here</h3>
          {tab !== 'owner' && (
            <button
              type="button"
              onClick={() => navigate('/properties')}
              className="btn-gold px-6 py-2.5 rounded-full text-sm mt-4"
            >
              Explore Properties
            </button>
          )}
        </div>
      ) : (
        <>
          <div className="space-y-4">
            {shown.map((booking, index) => (
              <div
                key={booking.id}
                ref={(element) => {
                  bookingRefs.current[booking.id] = element;
                }}
                className={`fade-up fade-up-${Math.min(index + 1, 4)} rounded-2xl transition-all duration-500 ${
                  String(highlightedBookingId) === String(booking.id)
                    ? 'ring-2 ring-gold/70 shadow-[0_0_32px_rgba(201,168,76,0.22)]'
                    : 'ring-0'
                }`}
              >
                <BookingCard
                  booking={booking}
                  isOwnerView={tab === 'owner'}
                  actionLoad={actionLoad}
                  onAccept={handleAccept}
                  onReject={handleReject}
                  onCancel={setCancelTarget}
                  canCancel={canCancelBooking(booking, user, tab === 'owner')}
                  onNavigate={navigate}
                />
              </div>
            ))}
          </div>

          <Pagination meta={meta} currentPage={page} onPageChange={goToPage} />
        </>
      )}

      <CancelBookingModal
        booking={cancelTarget}
        isOpen={!!cancelTarget}
        loading={actionLoad === cancelTarget?.id}
        onClose={() => {
          if (!actionLoad) setCancelTarget(null);
        }}
        onConfirm={handleCancel}
      />
    </div>
  );
}
