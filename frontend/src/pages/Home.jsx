import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiSearch, FiArrowRight } from 'react-icons/fi';

const HERO_CITIES = ['Marrakech', 'Paris', 'Dubai', 'Bali', 'New York', 'Tokyo'];
const FEATURES = [
  { icon: '◆', title: 'Curated Selection', desc: 'Only the finest properties pass our rigorous standards' },
  { icon: '◈', title: 'Seamless Booking', desc: 'Reserve your dream stay in seconds' },
  { icon: '◉', title: '24/7 Concierge', desc: 'Dedicated support throughout your journey' },
];

export default function Home() {
  const navigate  = useNavigate();
  const [city, setCity] = useState('');

  const handleSearch = (e) => {
    e.preventDefault();
    navigate(`/properties${city ? `?city=${city}` : ''}`);
  };

  return (
    <div className="min-h-screen" style={{ background: 'var(--obsidian)' }}>
      {/* Hero */}
      <section className="relative min-h-screen flex items-center justify-center overflow-hidden">
        {/* Background */}
        <div className="absolute inset-0">
          <img src="https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1600&q=85"
               alt="" className="w-full h-full object-cover opacity-25" />
          <div className="absolute inset-0" style={{ background: 'linear-gradient(135deg, rgba(10,10,15,0.95) 0%, rgba(10,10,15,0.7) 50%, rgba(10,10,15,0.9) 100%)' }} />
          {/* Decorative orbs */}
          <div className="absolute top-1/3 right-1/4 w-96 h-96 rounded-full opacity-5"
               style={{ background: 'radial-gradient(circle, var(--gold) 0%, transparent 70%)', filter: 'blur(60px)' }} />
          <div className="absolute bottom-1/4 left-1/4 w-64 h-64 rounded-full opacity-5"
               style={{ background: 'radial-gradient(circle, var(--gold-light) 0%, transparent 70%)', filter: 'blur(40px)' }} />
        </div>

        <div className="relative z-10 text-center px-4 max-w-4xl mx-auto">
          <div className="fade-up ornament-divider mb-6 max-w-xs mx-auto">
            <span className="text-xs tracking-[0.35em] text-gold/60 uppercase">Premium Accommodations</span>
          </div>

          <h1 className="fade-up fade-up-1 font-display text-5xl sm:text-7xl lg:text-8xl font-light leading-tight mb-4">
            <span className="text-cream">Where Luxury</span><br/>
            <span className="text-gold-gradient italic">Feels Like Home</span>
          </h1>

          <p className="fade-up fade-up-2 text-cream/50 text-lg sm:text-xl mb-10 font-light max-w-xl mx-auto">
            Discover handpicked villas, apartments and hotels that redefine extraordinary living.
          </p>

          {/* Search */}
          <form onSubmit={handleSearch} className="fade-up fade-up-3 flex flex-col sm:flex-row gap-3 max-w-xl mx-auto">
            <div className="relative flex-1">
              <FiSearch size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-cream/35" />
              <input type="text" placeholder="Where would you like to go?"
                value={city} onChange={e => setCity(e.target.value)}
                className="luxury-input w-full pl-11 pr-4 py-4 rounded-xl text-sm" />
            </div>
            <button type="submit" className="btn-gold px-8 py-4 rounded-xl font-medium flex items-center justify-center gap-2 text-sm">
              Explore <FiArrowRight size={16} />
            </button>
          </form>

          {/* City pills */}
          <div className="fade-up fade-up-4 flex flex-wrap justify-center gap-2 mt-5">
            {HERO_CITIES.map(c => (
              <button key={c} onClick={() => navigate(`/properties?city=${c}`)}
                className="px-4 py-1.5 rounded-full text-xs text-cream/50 border border-white/8 hover:border-gold/30 hover:text-gold/80 transition-all">
                {c}
              </button>
            ))}
          </div>
        </div>

        {/* Scroll indicator */}
        <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2">
          <span className="text-xs tracking-[0.25em] text-cream/25 uppercase">Scroll</span>
          <div className="w-px h-12 overflow-hidden" style={{ background: 'rgba(201,168,76,0.15)' }}>
            <div className="w-full h-1/2 bg-gold/50 animate-bounce" />
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-24 px-4 max-w-5xl mx-auto">
        <div className="text-center mb-16">
          <div className="ornament-divider mb-4 max-w-xs mx-auto">
            <span className="text-xs tracking-[0.35em] text-gold/60 uppercase">Why LuxuryStay</span>
          </div>
          <h2 className="font-display text-4xl sm:text-5xl font-light text-cream">
            A Different Standard<br/><span className="text-gold-gradient italic">of Hospitality</span>
          </h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {FEATURES.map((f, i) => (
            <div key={f.title} className={`luxury-card rounded-2xl p-8 text-center fade-up fade-up-${i+1}`}>
              <div className="text-3xl text-gold mb-5 font-mono">{f.icon}</div>
              <h3 className="font-display text-xl text-cream mb-2">{f.title}</h3>
              <p className="text-cream/45 text-sm leading-relaxed">{f.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="py-16 px-4">
        <div className="max-w-2xl mx-auto text-center luxury-card rounded-3xl p-12">
          <h2 className="font-display text-4xl font-light text-cream mb-3">Ready to Experience Luxury?</h2>
          <p className="text-cream/45 mb-8">Join thousands of discerning travelers</p>
          <button onClick={() => navigate('/properties')}
            className="btn-gold px-10 py-4 rounded-full font-medium tracking-wide">
            Browse Properties
          </button>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-gold/8 py-8 text-center text-cream/25 text-xs tracking-wide">
        © {new Date().getFullYear()} LUXURYSTAY · All rights reserved
      </footer>
    </div>
  );
}
