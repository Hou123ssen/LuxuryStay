import Pagination from '../../../shared/components/common/Pagination';
import PropertyCard from '../components/PropertyCard';
import PropertyFilters from '../components/PropertyFilters';
import { usePropertiesPagination } from '../hooks/usePropertiesPagination';

const SKELETON = Array(6).fill(0);

export default function Properties() {
  const { properties, meta, loading, filters, setFilters, resetFilters, goToPage } =
    usePropertiesPagination();

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

          <Pagination meta={meta} currentPage={filters.page} onPageChange={goToPage} />
        </>
      )}
    </div>
  );
}
