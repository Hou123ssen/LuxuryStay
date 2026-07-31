import { useEffect, useMemo, useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { addDays, differenceInCalendarDays } from 'date-fns';
import { bookingService } from '../api/bookingApi';
import { propertyService } from '../../properties/api/propertyApi';
import { useAuth } from '../../../app/providers/AuthContext';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { FiCalendar } from 'react-icons/fi';
import RatingDisplay from '../../../shared/components/common/RatingDisplay';

function parseDateStringAsLocalDate(value) {
  const [year, month, day] = value.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function formatDateAsLocalString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default function BookingCalendar({ property }) {
  const { isAuth } = useAuth();
  const navigate   = useNavigate();
  const [startDate, setStart] = useState(null);
  const [endDate,   setEnd]   = useState(null);
  const [loading,   setLoad]  = useState(false);
  const [availabilityLoading, setAvailabilityLoading] = useState(false);
  const [availabilityFailed, setAvailabilityFailed] = useState(false);
  const [unavailableDateStrings, setUnavailableDateStrings] = useState([]);

  const unavailableDateSet = useMemo(
    () => new Set(unavailableDateStrings),
    [unavailableDateStrings]
  );
  const unavailableDates = useMemo(
    () => unavailableDateStrings.map(parseDateStringAsLocalDate),
    [unavailableDateStrings]
  );

  const nights = startDate && endDate ? differenceInCalendarDays(endDate, startDate) : 0;
  const total  = nights * Number(property.price_per_night);

  useEffect(() => {
    if (!property?.id) return;

    let cancelled = false;
    setAvailabilityLoading(true);
    setAvailabilityFailed(false);

    propertyService.availability(property.id)
      .then((res) => {
        if (cancelled) return;
        setUnavailableDateStrings(res.data?.unavailable_dates || []);
      })
      .catch(() => {
        if (cancelled) return;
        setUnavailableDateStrings([]);
        setAvailabilityFailed(true);
      })
      .finally(() => {
        if (!cancelled) setAvailabilityLoading(false);
      });

    return () => { cancelled = true; };
  }, [property?.id]);

  const rangeIncludesUnavailableNight = (start, end) => {
    if (!start || !end) return false;

    for (let date = new Date(start); date < end; date = addDays(date, 1)) {
      if (unavailableDateSet.has(formatDateAsLocalString(date))) return true;
    }

    return false;
  };

  const handleChange = ([start, end]) => {
    if (start && end && rangeIncludesUnavailableNight(start, end)) {
      toast.error('Selected dates include unavailable nights.');
      setStart(start);
      setEnd(null);
      return;
    }

    setStart(start);
    setEnd(end);
  };

  const handleBook = async () => {
    if (!isAuth) { toast.error('Please sign in to book'); navigate('/login'); return; }
    if (!startDate || !endDate) { toast.error('Please select check-in and check-out dates'); return; }
    if (rangeIncludesUnavailableNight(startDate, endDate)) {
      toast.error('Selected dates include unavailable nights.');
      return;
    }
    setLoad(true);
    try {
      await bookingService.create({
        property_id: property.id,
        start_date:  formatDateAsLocalString(startDate),
        end_date:    formatDateAsLocalString(endDate),
      });
      toast.success('Booking request sent.');
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
        <RatingDisplay
          rating={property.average_rating}
          count={property.reviews_count}
          label={property.rating_label}
          ratingState={property.rating_state}
          trustBadge={property.trust_badge}
          trustLabel={property.trust_label}
          showEmpty={false}
        />
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
          excludeDates={unavailableDates}
          inline
          monthsShown={1}
        />
        <p className="text-xs text-cream/35 mt-2">
          {availabilityLoading
            ? 'Checking availability...'
            : availabilityFailed
              ? 'Availability could not be loaded. Booking will still be checked before confirmation.'
              : 'Unavailable dates are disabled.'}
        </p>
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
