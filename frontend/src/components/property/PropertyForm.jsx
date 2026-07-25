/**
 * PropertyForm
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared form shell used by both AddProperty and EditProperty.
 *
 * Props
 *   initialData   – pre-filled values (for edit mode)
 *   existingImages– existing server images [{ id, url }]
 *   onSubmit      – async fn(payload, newFiles, removedImageIds) → void
 *   submitLabel   – text on the submit button
 *   isEdit        – boolean, adjusts breadcrumb / heading copy
 *   loading       – disable inputs & show spinner
 */
import { useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import ImageUploader from './ImageUploader';
import {
  FiArrowLeft, FiHome, FiMapPin, FiDollarSign,
  FiTag, FiAlignLeft, FiCheckCircle,
} from 'react-icons/fi';

/* ── Constants ──────────────────────────────────────────────────────────────── */
export const PROPERTY_TYPES = [
  { value: 'apartment', label: 'Apartment', desc: 'Urban flat or suite',       icon: '🏢' },
  { value: 'hotel',     label: 'Hotel',     desc: 'Full-service accommodation', icon: '🏨' },
  { value: 'residence', label: 'Residence', desc: 'Private villa or house',     icon: '🏡' },
];

const BLANK = {
  title: '', description: '', type: '', price_per_night: '', city: '', address: '',
};

/* ── Field wrapper ───────────────────────────────────────────────────────────── */
export function Field({ label, icon, error, hint, children }) {
  return (
    <div className="space-y-1.5">
      <label className="flex items-center gap-1.5 text-xs text-cream/45 uppercase tracking-wider">
        <span className="text-gold/60">{icon}</span>
        {label}
      </label>
      {children}
      {hint && !error && <p className="text-[11px] text-cream/25 pl-0.5">{hint}</p>}
      {error && (
        <p className="flex items-center gap-1 text-[11px] text-red-400 pl-0.5">
          <span className="inline-block w-1 h-1 rounded-full bg-red-400" />
          {error}
        </p>
      )}
    </div>
  );
}

/* ── Validation ─────────────────────────────────────────────────────────────── */
function validate(form) {
  const e = {};
  if (!form.title.trim())                        e.title           = 'Title is required';
  else if (form.title.trim().length < 5)         e.title           = 'At least 5 characters';
  if (!form.description.trim())                  e.description     = 'Description is required';
  else if (form.description.trim().length < 20)  e.description     = 'At least 20 characters';
  if (!form.type)                                e.type            = 'Please select a property type';
  if (!form.price_per_night)                     e.price_per_night = 'Price is required';
  else if (isNaN(form.price_per_night) || Number(form.price_per_night) <= 0)
                                                 e.price_per_night = 'Enter a valid price > 0';
  if (!form.city.trim())                         e.city            = 'City is required';
  if (!form.address.trim())                      e.address         = 'Address is required';
  return e;
}

/* ── Main component ─────────────────────────────────────────────────────────── */
export default function PropertyForm({
  initialData    = BLANK,
  existingImages = [],
  onSubmit,
  submitLabel    = 'Publish Property',
  isEdit         = false,
  loading        = false,
}) {
  const navigate = useNavigate();
  const [form,   setForm]   = useState({ ...BLANK, ...initialData });
  const [errors, setErrors] = useState({});

  // Image upload state – managed here, passed to ImageUploader
  const [newFiles,       setNewFiles]       = useState([]);   // File[]
  const [removedImgIds,  setRemovedImgIds]  = useState([]);   // number[]

  /* setters */
  const set = (key, val) => {
    setForm((p) => ({ ...p, [key]: val }));
    setErrors((p) => ({ ...p, [key]: '' }));
  };

  const handleImagesChange = useCallback((files, removedIds) => {
    setNewFiles(files);
    setRemovedImgIds(removedIds);
  }, []);

  /* submit */
  const handleSubmit = async (e) => {
    e.preventDefault();
    const errs = validate(form);
    if (Object.keys(errs).length) {
      setErrors(errs);
      return;
    }
    const payload = {
      title:           form.title.trim(),
      description:     form.description.trim(),
      type:            form.type,
      price_per_night: Number(form.price_per_night),
      city:            form.city.trim(),
      address:         form.address.trim(),
    };
    await onSubmit(payload, newFiles, removedImgIds, setErrors);
  };

  const stepNum = (n) => (
    <span className="w-6 h-6 rounded-full bg-gold text-obsidian text-[11px] font-medium flex items-center justify-center shrink-0">
      {n}
    </span>
  );

  return (
    <div className="min-h-screen px-4 py-10">
      <div className="max-w-2xl mx-auto">

        {/* ── Breadcrumb ── */}
        <div className="flex items-center gap-3 mb-8 fade-up">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="p-2 rounded-xl border border-gold/15 text-cream/40 hover:text-cream hover:border-gold/40 transition-colors"
          >
            <FiArrowLeft size={16} />
          </button>
          <nav className="text-xs text-cream/30 flex items-center gap-1.5">
            <Link to="/properties" className="hover:text-gold transition-colors">Properties</Link>
            <span>/</span>
            <span className="text-cream/60">{isEdit ? 'Edit Property' : 'Add New'}</span>
          </nav>
        </div>

        {/* ── Header ── */}
        <div className="mb-10 fade-up fade-up-1">
          <div className="ornament-divider mb-3 max-w-xs">
            <span className="text-xs tracking-[0.3em] text-gold/55 uppercase">
              {isEdit ? 'Manage' : 'Host'}
            </span>
          </div>
          <h1 className="font-display text-4xl sm:text-5xl font-light text-cream leading-tight">
            {isEdit ? (
              <>Edit Your <span className="text-gold-gradient italic">Property</span></>
            ) : (
              <>List Your <span className="text-gold-gradient italic">Property</span></>
            )}
          </h1>
          <p className="text-cream/35 text-sm mt-2">
            {isEdit
              ? 'Update the details below. Changes will go live immediately.'
              : 'Fill in the details below and your property will be live instantly.'}
          </p>
        </div>

        {/* ── Form ── */}
        <form onSubmit={handleSubmit} noValidate>
          <div className="space-y-8">

            {/* ═══ Section 1: Basic info ═══ */}
            <section className="luxury-card rounded-2xl p-6 sm:p-8 space-y-6 fade-up fade-up-2">
              <div className="flex items-center gap-2">{stepNum(1)}<h2 className="font-display text-lg text-cream">Basic Information</h2></div>

              <Field label="Property Title" icon={<FiTag size={11} />} error={errors.title}>
                <input
                  type="text"
                  value={form.title}
                  onChange={(e) => set('title', e.target.value)}
                  placeholder="e.g. Elegant Penthouse in Marrakech Medina"
                  maxLength={120}
                  disabled={loading}
                  className={`luxury-input w-full px-4 py-3 rounded-xl text-sm ${errors.title ? 'border-red-500/60' : ''}`}
                />
                <div className="flex justify-end">
                  <span className="text-[10px] text-cream/20">{form.title.length}/120</span>
                </div>
              </Field>

              <Field
                label="Description"
                icon={<FiAlignLeft size={11} />}
                error={errors.description}
                hint="Minimum 20 characters — describe what makes your property special"
              >
                <textarea
                  value={form.description}
                  onChange={(e) => set('description', e.target.value)}
                  placeholder="Describe the ambiance, highlights, nearby attractions…"
                  rows={4}
                  maxLength={2000}
                  disabled={loading}
                  className={`luxury-input w-full px-4 py-3 rounded-xl text-sm resize-none ${errors.description ? 'border-red-500/60' : ''}`}
                />
                <div className="flex justify-end">
                  <span className={`text-[10px] ${form.description.length < 20 && form.description.length > 0 ? 'text-amber-400/70' : 'text-cream/20'}`}>
                    {form.description.length}/2000
                  </span>
                </div>
              </Field>
            </section>

            {/* ═══ Section 2: Property type ═══ */}
            <section className="luxury-card rounded-2xl p-6 sm:p-8 space-y-5 fade-up fade-up-3">
              <div className="flex items-center gap-2">{stepNum(2)}<h2 className="font-display text-lg text-cream">Property Type</h2></div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {PROPERTY_TYPES.map((t) => {
                  const active = form.type === t.value;
                  return (
                    <button
                      key={t.value}
                      type="button"
                      disabled={loading}
                      onClick={() => set('type', t.value)}
                      className={`relative flex flex-col items-center gap-2 p-5 rounded-xl border-2 text-center transition-all duration-200 ${
                        active
                          ? 'border-gold bg-gold/8 scale-[1.02]'
                          : 'border-white/8 hover:border-gold/30 hover:bg-white/3'
                      }`}
                    >
                      {active && <FiCheckCircle size={14} className="absolute top-3 right-3 text-gold" />}
                      <span className="text-2xl">{t.icon}</span>
                      <div>
                        <p className={`text-sm font-medium ${active ? 'text-gold' : 'text-cream/80'}`}>{t.label}</p>
                        <p className="text-[11px] text-cream/35 mt-0.5">{t.desc}</p>
                      </div>
                    </button>
                  );
                })}
              </div>
              {errors.type && (
                <p className="flex items-center gap-1 text-[11px] text-red-400">
                  <span className="inline-block w-1 h-1 rounded-full bg-red-400" />{errors.type}
                </p>
              )}
            </section>

            {/* ═══ Section 3: Pricing & Location ═══ */}
            <section className="luxury-card rounded-2xl p-6 sm:p-8 space-y-6 fade-up fade-up-4">
              <div className="flex items-center gap-2">{stepNum(3)}<h2 className="font-display text-lg text-cream">Pricing & Location</h2></div>

              <Field label="Price Per Night (USD)" icon={<FiDollarSign size={11} />} error={errors.price_per_night}>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gold/60 text-sm font-mono">$</span>
                  <input
                    type="number"
                    value={form.price_per_night}
                    onChange={(e) => set('price_per_night', e.target.value)}
                    placeholder="0"
                    min="1"
                    step="0.01"
                    disabled={loading}
                    className={`luxury-input w-full pl-8 pr-4 py-3 rounded-xl text-sm ${errors.price_per_night ? 'border-red-500/60' : ''}`}
                  />
                </div>
              </Field>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <Field label="City" icon={<FiMapPin size={11} />} error={errors.city}>
                  <input
                    type="text"
                    value={form.city}
                    onChange={(e) => set('city', e.target.value)}
                    placeholder="e.g. Marrakech"
                    disabled={loading}
                    className={`luxury-input w-full px-4 py-3 rounded-xl text-sm ${errors.city ? 'border-red-500/60' : ''}`}
                  />
                </Field>

                <Field label="Full Address" icon={<FiHome size={11} />} error={errors.address}>
                  <input
                    type="text"
                    value={form.address}
                    onChange={(e) => set('address', e.target.value)}
                    placeholder="e.g. 12 Rue Riad Zitoun"
                    disabled={loading}
                    className={`luxury-input w-full px-4 py-3 rounded-xl text-sm ${errors.address ? 'border-red-500/60' : ''}`}
                  />
                </Field>
              </div>
            </section>

            {/* ═══ Section 4: Images ═══ */}
            <section className="luxury-card rounded-2xl p-6 sm:p-8 space-y-5 fade-up fade-up-4">
              <div className="flex items-center gap-2">
                {stepNum(4)}
                <div>
                  <h2 className="font-display text-lg text-cream">Photos</h2>
                  <p className="text-[11px] text-cream/30">Upload up to 8 high-quality photos</p>
                </div>
              </div>
              <ImageUploader
                existingImages={existingImages}
                onImagesChange={handleImagesChange}
                maxFiles={8}
                disabled={loading}
              />
            </section>

            {/* ═══ Preview bar ═══ */}
            {(form.title || form.city || form.price_per_night) && (
              <div
                className="rounded-2xl px-6 py-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm fade-up"
                style={{ background: 'rgba(201,168,76,0.05)', border: '1px solid rgba(201,168,76,0.15)' }}
              >
                <span className="text-gold/60 text-xs uppercase tracking-wider">Preview</span>
                {form.title && <span className="text-cream/70 font-medium">{form.title}</span>}
                {form.city  && <span className="text-cream/35">📍 {form.city}</span>}
                {form.type  && <span className="text-cream/35 capitalize">· {form.type}</span>}
                {form.price_per_night && (
                  <span className="text-gold ml-auto font-medium">
                    ${Number(form.price_per_night).toLocaleString()}
                    <span className="text-cream/30 font-normal"> / night</span>
                  </span>
                )}
              </div>
            )}

            {/* ═══ Submit row ═══ */}
            <div className="flex flex-col sm:flex-row gap-3 pb-6 fade-up">
              <button
                type="button"
                onClick={() => navigate(-1)}
                disabled={loading}
                className="flex-1 sm:flex-none px-6 py-3.5 rounded-xl border border-gold/20 text-cream/50 hover:text-cream hover:border-gold/40 transition-colors text-sm"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={loading}
                className={`flex-1 btn-gold py-3.5 rounded-xl font-medium text-sm tracking-wide flex items-center justify-center gap-2 ${loading ? 'opacity-70 cursor-not-allowed' : ''}`}
              >
                {loading ? (
                  <>
                    <span className="w-4 h-4 border-2 border-obsidian/40 border-t-obsidian rounded-full animate-spin" />
                    {isEdit ? 'Saving…' : 'Publishing…'}
                  </>
                ) : (
                  <><FiCheckCircle size={15} />{submitLabel}</>
                )}
              </button>
            </div>

          </div>
        </form>
      </div>
    </div>
  );
}