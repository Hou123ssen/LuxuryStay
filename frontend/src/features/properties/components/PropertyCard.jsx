import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { FiHeart, FiStar, FiMapPin } from "react-icons/fi";
import { favoriteService, STORAGE_URL } from "../../../shared/api/api";
import { useAuth } from "../../../app/providers/AuthContext";
import toast from "react-hot-toast";

function resolveImage(raw) {
  if (!raw) return null;
  if (typeof raw === "string") {
    if (raw.startsWith("http")) return raw;
    return `${STORAGE_URL}/storage/${raw.replace(/^\//, "")}`;
  }
  const val = raw.url || raw.path || raw.src || raw.image_url;
  if (!val) return null;
  if (val.startsWith("http")) return val;
  return `${STORAGE_URL}/storage/${val.replace(/^\//, "")}`;
}

const FALLBACK =
  "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&q=80";

// يتعامل مع كل الصيغ الممكنة من Laravel
function getMainImage(property) {
  const img = property.images?.[0];

  if (img) {
    let url =
      typeof img === "string"
        ? img
        : img.url ||
          img.path ||
          img.src ||
          img.image_url ||
          img.original_url ||
          img.full_url;

    if (!url) return null;

    if (!url.startsWith("http")) {
      url = `${STORAGE_URL}/storage/${url}`;
    }

    return url;
  }

  let legacy =
    property.image_url ||
    property.image ||
    property.thumbnail ||
    property.featured_image;

  if (legacy && !legacy.startsWith("http")) {
    legacy = `${STORAGE_URL}/storage/${legacy}`;
  }

  return legacy || null;
}
export default function PropertyCard({
  property,
  isFavorited: initFav = false,
}) {
  const { isAuth } = useAuth();
  const navigate = useNavigate();
 const [fav, setFav] = useState(property.is_favorite);
  const [loading, setLoading] = useState(false);
  const [imgError, setImgError] = useState(false);
  // here
  const mainImage = !imgError && getMainImage(property);
  const propertyTitle = property.title || property.name || "Property";

  const handleFav = async (e) => {
    e.stopPropagation();

    if (!isAuth) {
      toast.error("Please sign in to save favorites");
      navigate("/login");
      return;
    }

    setLoading(true);

    try {
      const res = await favoriteService.toggle(property.id);

      // 🔥 الأفضل
      setFav((prev) => !prev);

      toast.success("Updated favorites");
    } catch {
      toast.error("Something went wrong");
    }

    setLoading(false);
  };

  const typeColors = {
    hotel: "bg-blue-500/15 text-blue-300",
    apartment: "bg-purple-500/15 text-purple-300",
    residence: "bg-gold/15 text-gold",
  };

  return (
    <div
      className="luxury-card rounded-2xl overflow-hidden cursor-pointer group"
      onClick={() => navigate(`/properties/${property.id}`)}
    >
      {/* ── صورة واحدة فقط ── */}
      <div className="relative h-52 overflow-hidden">
        <img
          src={mainImage}
          alt={propertyTitle}
          className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
          onError={() => setImgError(true)}
        />
        <div
          className="absolute inset-0"
          style={{
            background:
              "linear-gradient(to top, rgba(10,10,15,0.6) 0%, transparent 50%)",
          }}
        />

        {/* Type badge */}
        {property.type && (
          <span
            className={`absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-medium capitalize ${typeColors[property.type] || typeColors.residence}`}
          >
            {property.type}
          </span>
        )}

        {/* Favorite */}
        <button
          onClick={handleFav}
          disabled={loading}
          className={`absolute top-3 right-3 p-2 rounded-full transition-all duration-300 backdrop-blur-sm ${
            fav
              ? "bg-red-500 text-white scale-110"
              : "bg-black/40 text-white/70 hover:bg-black/60 hover:text-white"
          }`}
        >
          <FiHeart size={15} fill={fav ? "currentColor" : "none"} />
        </button>
      </div>

      {/* ── Content ── */}
      <div className="p-4">
        <div className="flex items-start justify-between gap-2 mb-1.5">
          <h3 className="font-display text-lg text-cream leading-tight line-clamp-1">
            {propertyTitle}
          </h3>
          {property.rating && (
            <span className="flex items-center gap-1 text-xs text-gold shrink-0 mt-1">
              <FiStar size={11} fill="currentColor" />{" "}
              {Number(property.rating).toFixed(1)}
            </span>
          )}
        </div>

        <div className="flex items-center gap-1 text-cream/45 text-xs mb-3">
          <FiMapPin size={11} />
          <span>
            {property.city}
            {property.country ? `, ${property.country}` : ""}
          </span>
        </div>

        <div className="flex items-end justify-between">
          <div>
            <span className="text-gold font-medium">
              ${Number(property.price_per_night).toLocaleString()}
            </span>
            <span className="text-cream/40 text-xs"> / night</span>
          </div>
          <span className="text-xs px-3 py-1.5 rounded-full border border-gold/25 text-gold/75 hover:border-gold/60 transition-colors">
            View Details
          </span>
        </div>
      </div>
    </div>
  );
}
