import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { favoriteService } from '../shared/api/api';
import PropertyCard from '../features/properties/components/PropertyCard';
import { FiHeart } from 'react-icons/fi';

export default function Favorites() {
  const navigate = useNavigate();
  const [favorites, setFavorites] = useState([]);
  const [loading,   setLoad]      = useState(true);

useEffect(() => {
  (async () => {
    try {
      const res = await favoriteService.list();
      const data = res.data;

      const list = Array.isArray(data)
        ? data.map(f => f.property)
        : data.data?.map(f => f.property) || [];

      setFavorites(list.filter(Boolean));
    } catch (err) {
      console.error(err);
      setFavorites([]);
    }
    setLoad(false);
  })();
}, []);

  return (
    <div className="min-h-screen px-4 py-10 max-w-7xl mx-auto">
      <div className="mb-8 fade-up">
        <div className="ornament-divider mb-3 max-w-xs">
          <span className="text-xs tracking-[0.3em] text-gold/55 uppercase">Saved</span>
        </div>
        <h1 className="font-display text-4xl sm:text-5xl font-light text-cream">
          My <span className="text-gold-gradient italic">Favorites</span>
        </h1>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {[1,2,3].map(i => (
            <div key={i} className="h-72 shimmer rounded-2xl" />
          ))}
        </div>
      ) : favorites.length === 0 ? (
        <div className="text-center py-24">
          <FiHeart size={40} className="mx-auto text-cream/15 mb-4" />
          <h3 className="font-display text-2xl text-cream/40 mb-2">No saved properties yet</h3>
          <p className="text-cream/25 text-sm mb-6">Tap the heart icon on any property to save it here</p>
          <button onClick={() => navigate('/properties')} className="btn-gold px-6 py-2.5 rounded-full text-sm">
            Explore Properties
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {favorites.map((p, i) => (
            <div key={p.id} className={`fade-up fade-up-${Math.min(i%3+1,4)}`}>
              <PropertyCard property={p} isFavorited={true} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
