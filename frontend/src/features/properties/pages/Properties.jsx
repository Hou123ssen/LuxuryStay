import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { propertyService } from '../api/propertyApi';
import PropertyCard from '../components/PropertyCard';
import PropertyFilters from '../components/PropertyFilters';
import { FiChevronLeft, FiChevronRight } from 'react-icons/fi';

const SKELETON = Array(6).fill(0);

export default function Properties() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [properties, setProperties] = useState([]);
  const [meta,        setMeta]       = useState(null);
  const [loading,     setLoad]       = useState(true);
  const [filters, setFilters] = useState({
    city:      searchParams.get('city')      || '',
    type:      searchParams.get('type')      || '',
    min_price: searchParams.get('min_price') || '',
    max_price: searchParams.get('max_price') || '',
    sort:      searchParams.get('sort')      || 'latest',
    page:      Number(searchParams.get('page')) || 1,
  });

  const fetchProperties = useCallback(async (params) => {
    setLoad(true);
    try {
      const clean = Object.fromEntries(Object.entries(params).filter(([,v]) => v !== '' && v !== null));
      const res   = await propertyService.list(clean);
      const payload = res.data;
      const listSource = payload.data || payload;
      const items = listSource.data || listSource;
      const pageMeta = payload.meta || payload.data?.meta || null;

      setProperties(Array.isArray(items) ? items : []);
      setMeta(pageMeta);
    } catch {
      setProperties([]);
      setMeta(null);
    }
    setLoad(false);
  }, []);

  useEffect(() => {
    fetchProperties(filters);
    const params = {};
    Object.entries(filters).forEach(([k,v]) => { if (v) params[k] = v; });
    setSearchParams(params);
  }, [filters]);

  const resetFilters = () => setFilters({ city: '', type: '', min_price: '', max_price: '', sort: 'latest', page: 1 });
  const goPage = (p) => setFilters(f => ({ ...f, page: p }));

  return (
    <div className="min-h-screen px-4 py-10 max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-8 fade-up">
        <div className="ornament-divider mb-3 max-w-xs">
          <span className="text-xs tracking-[0.3em] text-gold/55 uppercase">Discover</span>
        </div>
        <h1 className="font-display text-4xl sm:text-5xl font-light text-cream">
          Exceptional <span className="text-gold-gradient italic">Properties</span>
        </h1>
        {meta && <p className="text-cream/35 text-sm mt-2">{meta.total} properties available</p>}
      </div>

      {/* Filters */}
      <PropertyFilters filters={filters} onChange={f => setFilters({...f, page:1})} onReset={resetFilters} />

      {/* Grid */}
      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {SKELETON.map((_, i) => (
            <div key={i} className="rounded-2xl overflow-hidden" style={{ border: '1px solid rgba(201,168,76,0.1)' }}>
              <div className="h-52 shimmer" />
              <div className="p-4 space-y-3" style={{ background: '#13131f' }}>
                <div className="h-4 w-3/4 shimmer rounded" />
                <div className="h-3 w-1/2 shimmer rounded" />
                <div className="h-4 w-1/3 shimmer rounded" />
              </div>
            </div>
          ))}
        </div>
      ) : properties.length === 0 ? (
        <div className="text-center py-24">
          <div className="text-6xl mb-4 opacity-20">◈</div>
          <h3 className="font-display text-2xl text-cream/50 mb-2">No properties found</h3>
          <p className="text-cream/30 text-sm">Try adjusting your filters</p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {properties.map((p, i) => (
              <div key={p.id} className={`fade-up fade-up-${Math.min(i%3+1,4)}`}>
                <PropertyCard property={p} />
              </div>
            ))}
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-center gap-2 mt-12">
              <button onClick={() => goPage(filters.page - 1)} disabled={filters.page <= 1}
                className="p-2 rounded-xl border border-gold/20 text-cream/50 hover:border-gold/50 hover:text-cream disabled:opacity-30 transition-colors">
                <FiChevronLeft />
              </button>
              {Array.from({ length: meta.last_page }, (_, i) => i+1).map(n => (
                <button key={n} onClick={() => goPage(n)}
                  className={`w-9 h-9 rounded-xl text-sm transition-all ${
                    n === filters.page
                      ? 'bg-gold text-obsidian font-medium' : 'border border-gold/20 text-cream/50 hover:border-gold/50'
                  }`}>
                  {n}
                </button>
              ))}
              <button onClick={() => goPage(filters.page + 1)} disabled={filters.page >= meta.last_page}
                className="p-2 rounded-xl border border-gold/20 text-cream/50 hover:border-gold/50 hover:text-cream disabled:opacity-30 transition-colors">
                <FiChevronRight />
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
