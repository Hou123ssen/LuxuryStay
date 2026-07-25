import { useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { addDays, differenceInCalendarDays } from 'date-fns';
import { bookingService } from '../../services/api';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { FiCalendar, FiMinus, FiPlus } from 'react-icons/fi';

export default function BookingCalendar({ property }) {
  const { isAuth } = useAuth();
  const navigate   = useNavigate();
  const [startDate, setStart] = useState(null);
  const [endDate,   setEnd]   = useState(null);
  const [loading,   setLoad]  = useState(false);

  const nights = startDate && endDate ? differenceInCalendarDays(endDate, startDate) : 0;
  const total  = nights * Number(property.price_per_night);

  const handleChange = ([start, end]) => { setStart(start); setEnd(end); };

  const handleBook = async () => {
    if (!isAuth) { toast.error('Please sign in to book'); navigate('/login'); return; }
    if (!startDate || !endDate) { toast.error('Please select check-in and check-out dates'); return; }
    setLoad(true);
    try {
      await bookingService.create({
        property_id: property.id,
        start_date:  startDate.toISOString().split('T')[0],
        end_date:    endDate.toISOString().split('T')[0],
      });
      toast.success('Booking confirmed! 🎉');
      navigate('/bookings');
    } catch (err) {
      toast.error(err.response?.data?.message || 'Booking failed. Please try again.');
    }
    setLoad(false);
  };

  return (
    <div className="luxury-card rounded-2xl p-6">
      <div className="flex items-center justify-between mb-5">
        <div>
          <span className="text-2xl font-display text-gold">${Number(property.price_per_night).toLocaleString()}</span>
          <span className="text-cream/40 text-sm"> / night</span>
        </div>
        {property.rating && (
          <span className="text-xs text-cream/50">★ {Number(property.rating).toFixed(1)}</span>
        )}
      </div>

      {/* Date picker */}
      <div className="mb-4">
        <label className="block text-xs text-cream/45 mb-2 uppercase tracking-wider flex items-center gap-1.5">
          <FiCalendar size={11} /> Check-in — Check-out
        </label>
        <DatePicker
          selected={startDate}
          onChange={handleChange}
          startDate={startDate}
          endDate={endDate}
          selectsRange
          minDate={new Date()}
          inline
          monthsShown={1}
        />
      </div>

      {/* Summary */}
      {nights > 0 && (
        <div className="border-t border-gold/10 pt-4 mb-4 space-y-2 text-sm">
          <div className="flex justify-between text-cream/60">
            <span>${Number(property.price_per_night).toLocaleString()} × {nights} night{nights > 1 ? 's' : ''}</span>
            <span>${total.toLocaleString()}</span>
          </div>
          <div className="flex justify-between font-medium text-cream pt-1 border-t border-gold/10">
            <span>Total</span>
            <span className="text-gold">${total.toLocaleString()}</span>
          </div>
        </div>
      )}

      <button onClick={handleBook} disabled={loading || !startDate || !endDate}
        className={`btn-gold w-full py-3.5 rounded-xl font-medium text-sm tracking-wide transition-all ${
          (!startDate || !endDate) ? 'opacity-40 cursor-not-allowed' : ''
        }`}>
        {loading ? (
          <span className="flex items-center justify-center gap-2">
            <span className="w-4 h-4 border-2 border-obsidian/40 border-t-obsidian rounded-full animate-spin" />
            Confirming…
          </span>
        ) : 'Reserve Now'}
      </button>

      <p className="text-center text-xs text-cream/30 mt-3">You won't be charged yet</p>
    </div>
  );
}
