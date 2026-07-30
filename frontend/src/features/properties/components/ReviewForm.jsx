import { useEffect, useState } from "react";
import { reviewService } from "../api/propertyApi";
import { useAuth } from "../../../app/providers/AuthContext";
import toast from "react-hot-toast";
import { FiStar } from "react-icons/fi";

export default function ReviewForm({ propertyId, eligibleBookings = [], onSuccess }) {
  const { isAuth } = useAuth();
  const [rating, setRating] = useState(0);
  const [hover, setHover] = useState(0);
  const [comment, setComment] = useState("");
  const [bookingId, setBookingId] = useState("");
  const [loading, setLoad] = useState(false);

  useEffect(() => {
    if (eligibleBookings.length === 1) {
      setBookingId(String(eligibleBookings[0].id));
    }
  }, [eligibleBookings]);

  if (!isAuth) return null;
  if (eligibleBookings.length === 0) return null;

  const submit = async () => {
    if (loading) return;

    if (!bookingId) {
      toast.error("Please select the completed stay to review");
      return;
    }
    if (!rating) {
      toast.error("Please select a rating");
      return;
    }
    if (!comment.trim()) {
      toast.error("Please write a review");
      return;
    }
    setLoad(true);
    try {
      const res = await reviewService.create({
        property_id: propertyId,
        booking_id: bookingId,
        rating,
        comment,
      });
      toast.success(res.data?.message || "Review submitted!");
      setRating(0);
      setComment("");
      setBookingId("");
      onSuccess?.();
    } catch (err) {
      const message = err.response?.status === 409
        ? "You already reviewed this stay."
        : err.response?.data?.message || "Could not submit review";

      toast.error(message);
    } finally {
      setLoad(false);
    }
  };

  return (
    <div className="luxury-card rounded-2xl p-5">
      <h3 className="font-display text-xl text-cream mb-4">Leave a Review</h3>
      {eligibleBookings.length > 1 && (
        <select
          value={bookingId}
          onChange={(e) => setBookingId(e.target.value)}
          disabled={loading}
          className="luxury-input w-full px-4 py-3 rounded-xl text-sm mb-4"
        >
          <option value="">Select completed stay</option>
          {eligibleBookings.map((booking) => (
            <option key={booking.id} value={booking.id}>
              {booking.start_date?.slice(0, 10)} - {booking.end_date?.slice(0, 10)}
            </option>
          ))}
        </select>
      )}
      <div className="flex gap-1 mb-4">
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            onMouseEnter={() => setHover(n)}
            onMouseLeave={() => setHover(0)}
            onClick={() => !loading && setRating(n)}
            disabled={loading}
            className={`text-2xl transition-all disabled:cursor-not-allowed disabled:opacity-60 ${(hover || rating) >= n ? "text-gold scale-110" : "text-cream/20"}`}
          >
            <FiStar fill={(hover || rating) >= n ? "currentColor" : "none"} />
          </button>
        ))}
      </div>
      <textarea
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        placeholder="Share your experience…"
        rows={3}
        disabled={loading}
        className="luxury-input w-full px-4 py-3 rounded-xl text-sm resize-none mb-4"
      />
      <button
        onClick={submit}
        disabled={loading || !bookingId}
        className="btn-gold px-6 py-2.5 rounded-xl text-sm font-medium disabled:cursor-not-allowed disabled:opacity-60"
      >
        {loading ? "Submitting…" : "Submit Review"}
      </button>
    </div>
  );
}
