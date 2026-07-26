import { useState } from 'react';
import { FiSearch, FiSliders, FiX } from 'react-icons/fi';

const TYPES = ['apartment', 'hotel', 'residence'];
const SORTS = [
  { value: 'latest',     label: 'Latest' },
  { value: 'price_asc',  label: 'Price: Low → High' },
  { value: 'price_desc', label: 'Price: High → Low' },
  { value: 'rating',     label: 'Top Rated' },
];

export default function PropertyFilters({ filters, onChange, onReset }) {
  const [show, setShow] = useState(false);

  const set = (key, val) => onChange({ ...filters, [key]: val });

  const activeCount = [filters.city, filters.type, filters.min_price, filters.max_price, filters.sort !== 'latest']
    .filter(Boolean).length;

  return (
    <>
      {/* Search bar + filter toggle */}
      <div className="flex gap-3 items-center mb-6">
        <div className="relative flex-1">
          <FiSearch size={15} className="absolute left-4 top-1/2 -translate-y-1/2 text-cream/35" />
          <input
            type="text" placeholder="Search by city…"
            value={filters.city || ''}
            onChange={e => set('city', e.target.value)}
            className="luxury-input w-full pl-10 pr-4 py-3 rounded-xl text-sm"
          />
        </div>
        <button onClick={() => setShow(!show)}
          className={`flex items-center gap-2 px-4 py-3 rounded-xl border text-sm transition-colors ${
            show || activeCount > 0
              ? 'border-gold text-gold bg-gold/5' : 'border-gold/20 text-cream/60 hover:border-gold/40'
          }`}>
          <FiSliders size={14} />
          Filters
          {activeCount > 0 && (
            <span className="ml-1 w-4 h-4 rounded-full bg-gold text-obsidian text-[10px] flex items-center justify-center font-medium">
              {activeCount}
            </span>
          )}
        </button>
        {activeCount > 0 && (
          <button onClick={onReset} className="p-3 rounded-xl border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors">
            <FiX size={15} />
          </button>
        )}
      </div>

      {/* Expanded filters */}
      {show && (
        <div className="luxury-card rounded-2xl p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Type */}
          <div>
            <label className="block text-xs text-cream/45 mb-2 uppercase tracking-wider">Property Type</label>
            <div className="flex flex-wrap gap-2">
              {TYPES.map(t => (
                <button key={t} onClick={() => set('type', filters.type === t ? '' : t)}
                  className={`px-3 py-1.5 rounded-full text-xs capitalize transition-colors border ${
                    filters.type === t ? 'border-gold bg-gold/10 text-gold' : 'border-white/10 text-cream/55 hover:border-gold/30'
                  }`}>
                  {t}
                </button>
              ))}
            </div>
          </div>

          {/* Min price */}
          <div>
            <label className="block text-xs text-cream/45 mb-2 uppercase tracking-wider">Min Price / Night</label>
            <input type="number" placeholder="$0" value={filters.min_price || ''}
              onChange={e => set('min_price', e.target.value)}
              className="luxury-input w-full px-3 py-2 rounded-lg text-sm" />
          </div>

          {/* Max price */}
          <div>
            <label className="block text-xs text-cream/45 mb-2 uppercase tracking-wider">Max Price / Night</label>
            <input type="number" placeholder="No limit" value={filters.max_price || ''}
              onChange={e => set('max_price', e.target.value)}
              className="luxury-input w-full px-3 py-2 rounded-lg text-sm" />
          </div>

          {/* Sort */}
          <div>
            <label className="block text-xs text-cream/45 mb-2 uppercase tracking-wider">Sort By</label>
            <select value={filters.sort || 'latest'} onChange={e => set('sort', e.target.value)}
              className="luxury-input w-full px-3 py-2 rounded-lg text-sm cursor-pointer">
              {SORTS.map(s => (
                <option key={s.value} value={s.value} style={{ background: '#1c1c2e' }}>{s.label}</option>
              ))}
            </select>
          </div>
        </div>
      )}
    </>
  );
}
