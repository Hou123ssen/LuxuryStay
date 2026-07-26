import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { STORAGE_URL } from '../../../shared/api/api';
import { chatService } from '../../chat/api/chatApi';
import { favoriteService } from '../../favorites/api/favoriteApi';
import { propertyService } from '../api/propertyApi';
import BookingCalendar from '../../bookings/components/BookingCalendar';
import ReviewForm from '../components/ReviewForm';
import DeleteConfirmModal from '../../../shared/components/common/DeleteConfirmModal';
import { useAuth } from '../../../app/providers/AuthContext';
import toast from 'react-hot-toast';
import {
  FiMapPin, FiStar, FiHeart, FiMessageCircle,
  FiArrowLeft, FiArrowRight, FiWifi, FiDroplet,
  FiTv, FiCoffee, FiEdit2, FiTrash2, FiMaximize2,
} from 'react-icons/fi';

function resolveImage(raw) {
  if (!raw) return null;
  if (typeof raw === 'string') {
    if (raw.startsWith('http')) return raw;
    return `${STORAGE_URL}/storage/${raw.replace(/^\//, '')}`;
  }
  const candidates = [raw.url, raw.full_url, raw.original_url, raw.path, raw.src, raw.image_url];
  for (const c of candidates) {
    if (c) return resolveImage(c);
  }
  return null;
}

function getImages(property) {
  const FALLBACK = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&q=80';

  if (property.images?.length) {
    const resolved = property.images.map(resolveImage).filter(Boolean);
    if (resolved.length) return resolved;
  }

  const single = property.image_url || property.image || property.thumbnail;
  if (single) return [resolveImage(single)].filter(Boolean);

  return [FALLBACK];
}

// ─── Carousel Component ────────────────────────────────────────────────────────
function ImageCarousel({ images }) {
  const [current,    setCurrent]    = useState(0);
  const [lightbox,   setLightbox]   = useState(false);
  const [imgErrors,  setImgErrors]  = useState({});

  const FALLBACK = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&q=80';

  const prev = useCallback(() => setCurrent(i => (i - 1 + images.length) % images.length), [images.length]);
  const next = useCallback(() => setCurrent(i => (i + 1) % images.length), [images.length]);

  // keyboard navigation
  useEffect(() => {
    if (!lightbox) return;
    const handler = (e) => {
      if (e.key === 'ArrowLeft')  prev();
      if (e.key === 'ArrowRight') next();
      if (e.key === 'Escape')     setLightbox(false);
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [lightbox, prev, next]);

  const imgSrc = (i) => imgErrors[i] ? FALLBACK : images[i];

  return (
    <>
      {/* ── الـ Carousel الرئيسي ── */}
      <div className="relative h-72 sm:h-96 lg:h-[520px] overflow-hidden bg-obsidian-800 group">

        {/* الصورة الحالية */}
        <img
          key={current}
          src={imgSrc(current)}
          alt={`Photo ${current + 1}`}
          className="w-full h-full object-cover transition-all duration-700"
          onError={() => setImgErrors(p => ({ ...p, [current]: true }))}
          style={{ animation: 'fadeIn 0.4s ease' }}
        />

        {/* gradient overlay */}
        <div className="absolute inset-0"
          style={{ background: 'linear-gradient(to bottom, rgba(10,10,15,0.25) 0%, transparent 40%, rgba(10,10,15,0.65) 100%)' }} />

        {/* أسهم التنقل — تظهر فقط إذا يوجد أكثر من صورة */}
        {images.length > 1 && (
          <>
            <button onClick={prev}
              className="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full flex items-center justify-center backdrop-blur-md border border-white/20 text-white opacity-0 group-hover:opacity-100 transition-all hover:border-gold/60 hover:text-gold">
              <FiArrowLeft size={18} />
            </button>
            <button onClick={next}
              className="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full flex items-center justify-center backdrop-blur-md border border-white/20 text-white opacity-0 group-hover:opacity-100 transition-all hover:border-gold/60 hover:text-gold">
              <FiArrowRight size={18} />
            </button>
          </>
        )}

        {/* عداد الصور + زر Lightbox */}
        <div className="absolute top-4 right-4 flex items-center gap-2">
          {images.length > 1 && (
            <span className="px-2.5 py-1 rounded-full text-xs text-cream/80 backdrop-blur-md"
              style={{ background: 'rgba(0,0,0,0.45)' }}>
              {current + 1} / {images.length}
            </span>
          )}
          <button onClick={() => setLightbox(true)}
            className="p-2 rounded-full backdrop-blur-md border border-white/15 text-white hover:border-gold/50 transition-colors">
            <FiMaximize2 size={15} />
          </button>
        </div>

        {/* نقاط التنقل في الأسفل */}
        {images.length > 1 && (
          <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 items-center">
            {images.map((_, i) => (
              <button key={i} onClick={() => setCurrent(i)}
                className={`rounded-full transition-all duration-300 ${
                  i === current
                    ? 'w-6 h-2 bg-gold'
                    : 'w-2 h-2 bg-white/40 hover:bg-white/70'
                }`} />
            ))}
          </div>
        )}

        {/* Thumbnail strip للشاشات الكبيرة */}
        {images.length > 1 && (
          <div className="absolute bottom-4 right-4 hidden lg:flex gap-1.5">
            {images.slice(0, 5).map((img, i) => (
              <button key={i} onClick={() => setCurrent(i)}
                className={`w-14 h-10 rounded-lg overflow-hidden border-2 transition-all ${
                  i === current ? 'border-gold scale-105' : 'border-white/20 opacity-60 hover:opacity-100'
                }`}>
                <img src={imgErrors[i] ? FALLBACK : img} className="w-full h-full object-cover" alt="" />
              </button>
            ))}
          </div>
        )}
      </div>

      {/* ── Lightbox ── */}
      {lightbox && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center"
          style={{ background: 'rgba(0,0,0,0.95)' }}
          onClick={() => setLightbox(false)}>

          <img
            src={imgSrc(current)}
            alt=""
            className="max-w-[90vw] max-h-[90vh] object-contain rounded-xl"
            onClick={e => e.stopPropagation()}
          />

          {images.length > 1 && (
            <>
              <button onClick={(e) => { e.stopPropagation(); prev(); }}
                className="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full flex items-center justify-center border border-white/20 text-white hover:border-gold hover:text-gold transition-colors">
                <FiArrowLeft size={20} />
              </button>
              <button onClick={(e) => { e.stopPropagation(); next(); }}
                className="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full flex items-center justify-center border border-white/20 text-white hover:border-gold hover:text-gold transition-colors">
                <FiArrowRight size={20} />
              </button>
            </>
          )}

          <button onClick={() => setLightbox(false)}
            className="absolute top-4 right-4 text-white/60 hover:text-white text-sm px-3 py-1.5 border border-white/20 rounded-full">
            ESC / Close
          </button>

          {/* thumbnails في الـ Lightbox */}
          {images.length > 1 && (
            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
              {images.map((img, i) => (
                <button key={i} onClick={(e) => { e.stopPropagation(); setCurrent(i); }}
                  className={`w-12 h-8 rounded overflow-hidden border-2 transition-all ${
                    i === current ? 'border-gold' : 'border-white/20 opacity-50 hover:opacity-100'
                  }`}>
                  <img src={imgErrors[i] ? FALLBACK : img} className="w-full h-full object-cover" alt="" />
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      <style>{`@keyframes fadeIn { from { opacity:0 } to { opacity:1 } }`}</style>
    </>
  );
}

// ─── Amenity icons ─────────────────────────────────────────────────────────────
const AMENITY_ICONS = {
  wifi:   <FiWifi size={14} />,
  pool:   <FiDroplet size={14} />,
  tv:     <FiTv size={14} />,
  coffee: <FiCoffee size={14} />,
};

// ─── PropertyDetail ────────────────────────────────────────────────────────────
export default function PropertyDetail() {
  const { id }           = useParams();
  const navigate         = useNavigate();
  const { user, isAuth } = useAuth();

  const [property,      setProperty]      = useState(null);
  const [loading,       setLoad]          = useState(true);
  const [fav,           setFav]           = useState(false);
  const [isOwner,       setIsOwner]       = useState(false);
  const [deleteOpen,    setDeleteOpen]    = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [contactLoading, setContactLoading] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await propertyService.get(id);
        const p   = res.data?.data || res.data;
        setProperty(p);
        setFav(res.data.is_favorited || false);
        const ownerId = p?.user_id || p?.host?.id || p?.owner?.id;
        setIsOwner(!!user && String(ownerId) === String(user?.id));
      } catch { navigate('/properties'); }
      setLoad(false);
    })();
  }, [id, user]);

  const handleFav = async () => {
    if (!isAuth) { toast.error('Please sign in'); navigate('/login'); return; }
    try {
      await favoriteService.toggle(id);
      setFav(!fav);
      toast.success(fav ? 'Removed from favorites' : 'Saved to favorites');
    } catch { toast.error('Something went wrong'); }
  };

  const handleDelete = async () => {
    setDeleteLoading(true);
    try {
      await propertyService.delete(id);
      toast.success('Property deleted');
      setDeleteOpen(false);
      navigate('/properties');
    } catch (err) {
      toast.error(err.response?.data?.message || 'Could not delete');
    } finally { setDeleteLoading(false); }
  };

  const handleContactHost = async () => {
    if (!isAuth) {
      toast.error('Please sign in');
      navigate('/login');
      return;
    }

    setContactLoading(true);
    try {
      const res = await chatService.createConversation({ property_id: property.id });
      const conversation = res.data?.data || res.data;

      if (conversation?.id) {
        navigate(`/chat?conversation_id=${conversation.id}`);
      } else {
        navigate(`/chat?property_id=${property.id}`);
      }
    } catch (err) {
      toast.error(err.response?.data?.message || 'Could not start conversation');
    } finally {
      setContactLoading(false);
    }
  };

  if (loading) return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="w-10 h-10 border-2 border-gold/30 border-t-gold rounded-full animate-spin" />
    </div>
  );
  if (!property) return null;

  const images  = getImages(property);
  const reviews = property.reviews   || [];
  const amenities = property.amenities || [];

  return (
    <div className="min-h-screen pb-20">

      <DeleteConfirmModal
        isOpen={deleteOpen}
        title="Delete Property"
        message={`Are you sure you want to delete "${property.title || property.name}"?`}
        danger="Yes, Delete Property"
        loading={deleteLoading}
        onConfirm={handleDelete}
        onCancel={() => setDeleteOpen(false)}
      />

      {/* ══ Carousel في الأعلى ══ */}
      <div className="relative">
        <ImageCarousel images={images} />

        {/* زر الرجوع — فوق الـ carousel */}
        <button onClick={() => navigate(-1)}
          className="absolute top-4 left-4 z-10 p-2.5 rounded-full backdrop-blur-md border border-white/15 text-white hover:border-gold/50 transition-colors">
          <FiArrowLeft size={18} />
        </button>

        {/* أزرار المالك أو زر المفضلة */}
        {isOwner ? (
          <div className="absolute top-4 right-16 z-10 flex gap-2">
            <button onClick={() => navigate(`/properties/${id}/edit`)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-full backdrop-blur-md border border-gold/40 text-gold hover:bg-gold/10 transition-all text-xs font-medium">
              <FiEdit2 size={13} /> Edit
            </button>
            <button onClick={() => setDeleteOpen(true)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-full backdrop-blur-md border border-red-500/40 text-red-400 hover:bg-red-500/10 transition-all text-xs font-medium">
              <FiTrash2 size={13} /> Delete
            </button>
          </div>
        ) : (
          <button onClick={handleFav}
            className={`absolute top-4 right-16 z-10 p-2.5 rounded-full backdrop-blur-md border transition-all ${
              fav ? 'bg-red-500 border-red-500 text-white' : 'border-white/15 text-white hover:border-gold/50'
            }`}>
            <FiHeart size={18} fill={fav ? 'currentColor' : 'none'} />
          </button>
        )}
      </div>

      {/* ══ المحتوى ══ */}
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

          {/* اليسار: التفاصيل */}
          <div className="lg:col-span-2 space-y-8">

            {/* العنوان */}
            <div className="fade-up">
              <div className="flex items-start justify-between gap-4 flex-wrap">
                <div>
                  {property.type && (
                    <span className="text-xs uppercase tracking-widest text-gold/60 mb-2 block">{property.type}</span>
                  )}
                  <h1 className="font-display text-3xl sm:text-4xl font-light text-cream leading-tight">
                    {property.title || property.name}
                  </h1>
                  <div className="flex items-center gap-2 mt-2 text-cream/45 text-sm flex-wrap">
                    <FiMapPin size={12} />
                    <span>{property.address || property.city}</span>
                    {property.rating && (
                      <>
                        <span className="text-gold/30">·</span>
                        <FiStar size={12} className="text-gold" fill="currentColor" />
                        <span className="text-gold">{Number(property.rating).toFixed(1)}</span>
                        <span className="text-cream/30">({reviews.length} reviews)</span>
                      </>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                  {isOwner ? (
                    <>
                      <button onClick={() => navigate(`/properties/${id}/edit`)}
                        className="flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-gold/25 text-gold/80 hover:border-gold hover:text-gold transition-colors text-sm">
                        <FiEdit2 size={13} /> Edit Property
                      </button>
                      <button onClick={() => setDeleteOpen(true)}
                        className="flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-red-500/25 text-red-400/80 hover:border-red-500 hover:text-red-400 transition-colors text-sm">
                        <FiTrash2 size={13} /> Delete
                      </button>
                    </>
                  ) : (
                    <button onClick={handleContactHost} disabled={contactLoading}
                      className="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gold/25 text-gold/80 hover:border-gold hover:text-gold transition-colors text-sm">
                      <FiMessageCircle size={14} /> {contactLoading ? 'Opening...' : 'Message Host'}
                    </button>
                  )}
                </div>
              </div>
            </div>

            <div className="border-t border-gold/8" />

            {/* الوصف */}
            {property.description && (
              <div className="fade-up fade-up-1">
                <h2 className="font-display text-xl text-cream mb-3">About this place</h2>
                <p className="text-cream/55 leading-relaxed text-sm">{property.description}</p>
              </div>
            )}

            {/* المرافق */}
            {amenities.length > 0 && (
              <div className="fade-up fade-up-2">
                <h2 className="font-display text-xl text-cream mb-4">Amenities</h2>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  {amenities.map((a, i) => (
                    <div key={i} className="flex items-center gap-2.5 px-3 py-2.5 rounded-xl"
                      style={{ background: 'rgba(201,168,76,.05)', border: '1px solid rgba(201,168,76,.1)' }}>
                      <span className="text-gold">{AMENITY_ICONS[a.toLowerCase()] || '◆'}</span>
                      <span className="text-cream/65 text-sm capitalize">{a}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* التفاصيل */}
            <div className="fade-up fade-up-2">
              <h2 className="font-display text-xl text-cream mb-4">Property Details</h2>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                {[
                  { label: 'Price / Night', value: `$${Number(property.price_per_night).toLocaleString()}` },
                  { label: 'City',          value: property.city },
                  { label: 'Type',          value: property.type },
                  { label: 'Guests',        value: property.max_guests ? `${property.max_guests} guests` : null },
                  { label: 'Bedrooms',      value: property.bedrooms   ? `${property.bedrooms} beds`    : null },
                  { label: 'Bathrooms',     value: property.bathrooms  ? `${property.bathrooms} baths`  : null },
                ].filter(d => d.value).map(d => (
                  <div key={d.label} className="p-4 rounded-xl"
                    style={{ background: 'rgba(28,28,46,.6)', border: '1px solid rgba(201,168,76,.08)' }}>
                    <span className="block text-xs text-cream/35 uppercase tracking-wider mb-1">{d.label}</span>
                    <span className="text-cream font-medium capitalize">{d.value}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* تنبيه للمالك */}
            {isOwner && (
              <div className="rounded-2xl px-5 py-4 flex items-center gap-3 fade-up"
                style={{ background: 'rgba(201,168,76,.06)', border: '1px solid rgba(201,168,76,.18)' }}>
                <span className="text-gold text-lg">◆</span>
                <div>
                  <p className="text-sm text-gold/80 font-medium">You own this property</p>
                  <p className="text-xs text-cream/35 mt-0.5">Guests can book, but you cannot book your own property.</p>
                </div>
              </div>
            )}

            {/* التقييمات */}
            <div className="fade-up fade-up-3">
              <h2 className="font-display text-xl text-cream mb-5">
                Reviews {reviews.length > 0 && <span className="text-gold/50 text-base font-sans">({reviews.length})</span>}
              </h2>
              {reviews.length > 0 ? (
                <div className="space-y-4 mb-6">
                  {reviews.map((r, i) => (
                    <div key={i} className="luxury-card rounded-2xl p-5">
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                          <div className="w-8 h-8 rounded-full bg-gold/20 flex items-center justify-center text-gold text-xs font-medium">
                            {r.user?.name?.[0]?.toUpperCase() || '?'}
                          </div>
                          <span className="text-sm text-cream/80">{r.user?.name || 'Guest'}</span>
                        </div>
                        <div className="flex gap-0.5">
                          {[1,2,3,4,5].map(n => (
                            <FiStar key={n} size={11}
                              className={n <= r.rating ? 'text-gold' : 'text-cream/15'}
                              fill={n <= r.rating ? 'currentColor' : 'none'} />
                          ))}
                        </div>
                      </div>
                      <p className="text-cream/55 text-sm leading-relaxed">{r.comment}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-cream/30 text-sm mb-6">No reviews yet. Be the first!</p>
              )}
              {!isOwner && (
                <ReviewForm
                  propertyId={property.id}
                  onSuccess={() => propertyService.get(id).then(r => setProperty(r.data?.data || r.data))}
                />
              )}
            </div>
          </div>

          {/* اليمين: الحجز أو إدارة */}
          <div className="lg:col-span-1">
            <div className="sticky top-24">
              {isOwner ? (
                <div className="luxury-card rounded-2xl p-6 text-center space-y-4">
                  <span className="text-3xl font-display text-gold">
                    ${Number(property.price_per_night).toLocaleString()}
                    <span className="text-cream/35 text-base font-sans font-normal"> / night</span>
                  </span>
                  <p className="text-cream/35 text-sm">Manage your listing</p>
                  <button onClick={() => navigate(`/properties/${id}/edit`)}
                    className="btn-gold w-full py-3 rounded-xl text-sm font-medium flex items-center justify-center gap-2">
                    <FiEdit2 size={14} /> Edit Property Details
                  </button>
                  <button onClick={() => setDeleteOpen(true)}
                    className="w-full py-3 rounded-xl text-sm border border-red-500/25 text-red-400/70 hover:border-red-500 hover:text-red-400 transition-colors">
                    <FiTrash2 size={13} className="inline mr-1.5" /> Delete Property
                  </button>
                </div>
              ) : (
                <BookingCalendar property={property} />
              )}
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
