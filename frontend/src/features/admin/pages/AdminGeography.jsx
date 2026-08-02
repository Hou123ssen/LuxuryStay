import AdminGeographySection from '../components/AdminGeographySection';
import { useAdminDemoDataPreference } from '../hooks/useAdminDemoDataPreference';

export default function AdminGeography() {
  const [includeDemo, setIncludeDemo] = useAdminDemoDataPreference(true);

  return (
    <div className="space-y-6">
      <header className="fade-up">
        <div className="ornament-divider mb-3 max-w-sm">
          <span className="text-xs uppercase tracking-[0.3em] text-gold/55">Admin</span>
        </div>
        <h1 className="font-display text-4xl font-light text-cream sm:text-5xl">
          Geography <span className="text-gold-gradient italic">& Usage</span>
        </h1>
        <p className="mt-3 max-w-2xl text-sm leading-6 text-cream/45">
          Country and city-level platform usage for the selected period.
        </p>
      </header>

      <AdminGeographySection includeDemo={includeDemo} onIncludeDemoChange={setIncludeDemo} />
    </div>
  );
}
