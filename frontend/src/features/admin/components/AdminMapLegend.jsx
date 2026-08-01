const LEGEND_ITEMS = [
  {
    swatch: <span className="h-3 w-3 rounded-full bg-gold shadow-[0_0_14px_rgba(212,175,55,0.45)]" />,
    text: 'Gold marker = recorded platform usage in that country.',
  },
  {
    swatch: <span className="h-4 w-4 rounded-full border border-gold/50 bg-gold/15" />,
    text: 'Bigger marker = more usage events in the selected period.',
  },
  {
    swatch: <span className="h-3 w-5 rounded-sm border border-gold/30 bg-gold/45" />,
    text: 'Highlighted country = country appears in usage analytics.',
  },
  {
    swatch: <span className="h-3 w-5 rounded-sm border border-white/10 bg-white/10" />,
    text: 'Gray country = no recorded usage for the selected period.',
  },
];

const NOTES = [
  'Usage events include registrations and logins.',
  'The selected period controls the map: 7 days, 30 days, 90 days, or all time.',
  'Locations come from trusted geography headers when available.',
  'No raw IP, GPS, email, phone, or exact address is displayed.',
];

export default function AdminMapLegend() {
  return (
    <div className="grid gap-3 lg:grid-cols-[1.2fr_1fr]">
      <div className="rounded-2xl border border-white/5 bg-black/15 p-4">
        <h4 className="text-xs uppercase tracking-[0.18em] text-cream/40">Map Key</h4>
        <div className="mt-3 grid gap-2 sm:grid-cols-2">
          {LEGEND_ITEMS.map((item) => (
            <div key={item.text} className="flex items-center gap-3 text-xs leading-5 text-cream/55">
              <span className="flex h-5 w-7 shrink-0 items-center justify-center">{item.swatch}</span>
              <span>{item.text}</span>
            </div>
          ))}
        </div>
        <p className="mt-3 text-xs leading-5 text-cream/40">
          Unknown country data is shown in tables, not placed on the map. Data is aggregated and privacy-safe.
        </p>
      </div>

      <div className="rounded-2xl border border-gold/10 bg-gold/5 p-4">
        <h4 className="text-xs uppercase tracking-[0.18em] text-gold/70">Data Notes</h4>
        <ul className="mt-3 space-y-2 text-xs leading-5 text-cream/55">
          {NOTES.map((note) => (
            <li key={note}>{note}</li>
          ))}
        </ul>
      </div>
    </div>
  );
}
