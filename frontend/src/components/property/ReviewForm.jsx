import { useState } from "react";
import { reviewService } from "../../services/api";
import { useAuth } from "../../context/AuthContext";
import toast from "react-hot-toast";
import { FiStar } from "react-icons/fi";

export default function ReviewForm({ propertyId, onSuccess }) {
  const { isAuth } = useAuth();
  const [rating, setRating] = useState(0);
  const [hover, setHover] = useState(0);
  const [comment, setComment] = useState("");
  const [loading, setLoad] = useState(false);

  if (!isAuth) return null;

  const submit = async () => {
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
      await reviewService.create({ property_id: propertyId, rating, comment });
      toast.success("Review submitted!");
      setRating(0);
      setComment("");
      onSuccess?.();
    } catch (err) {
      if (err.response?.status === 403) {
        toast.error(err.response.data.message || "You must book first");
        return;
      }

      toast.error("Could not submit review");
    }
    setLoad(false);
  };

  return (
    <div className="luxury-card rounded-2xl p-5">
      <h3 className="font-display text-xl text-cream mb-4">Leave a Review</h3>
      <div className="flex gap-1 mb-4">
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            onMouseEnter={() => setHover(n)}
            onMouseLeave={() => setHover(0)}
            onClick={() => setRating(n)}
            className={`text-2xl transition-all ${(hover || rating) >= n ? "text-gold scale-110" : "text-cream/20"}`}
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
        className="luxury-input w-full px-4 py-3 rounded-xl text-sm resize-none mb-4"
      />
      <button
        onClick={submit}
        disabled={loading}
        className="btn-gold px-6 py-2.5 rounded-xl text-sm font-medium"
      >
        {loading ? "Submitting…" : "Submit Review"}
      </button>
    </div>
  );
}
