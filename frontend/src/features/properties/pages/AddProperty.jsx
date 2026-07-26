import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { propertyService, imageService } from '../../../shared/api/api';
import PropertyForm from '../components/PropertyForm';
import toast from 'react-hot-toast';

function SuccessScreen() {
  return (
    <div className="min-h-screen flex items-center justify-center px-4">
      <div className="luxury-card rounded-3xl p-12 text-center max-w-sm w-full fade-up">
        <div className="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center text-4xl"
          style={{
            background: 'linear-gradient(135deg,rgba(201,168,76,.25) 0%,rgba(201,168,76,.05) 100%)',
            border: '2px solid rgba(201,168,76,.35)',
          }}>
          ✓
        </div>
        <h2 className="font-display text-3xl text-cream mb-2">Listed!</h2>
        <p className="text-cream/45 text-sm mb-6">
          Your property has been published. Redirecting to listings…
        </p>
        <div className="w-full h-1 rounded-full overflow-hidden" style={{ background: 'rgba(201,168,76,.1)' }}>
          <div className="h-full bg-gold rounded-full" style={{ animation: 'grow 2.2s linear forwards' }} />
        </div>
        <style>{`@keyframes grow { from { width:0 } to { width:100% } }`}</style>
      </div>
    </div>
  );
}

export default function AddProperty() {
  const navigate            = useNavigate();
  const [done, setDone]     = useState(false);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (payload, newFiles, _removedIds, setErrors) => {
    setLoading(true);

    try {
      // ── Step 1: Create property ──────────────────────────────────────────────
      const res      = await propertyService.create(payload);
      const property = res.data?.data || res.data;
      const propId   = property?.id;

      console.log('✅ Property created, id:', propId);

      // ── Step 2: Upload images ────────────────────────────────────────────────
      if (newFiles.length > 0) {
        if (!propId) {
          toast.error('Property created but ID missing — cannot upload images.');
        } else {
          try {
            await imageService.uploadMultiple(newFiles, propId);
            console.log('✅ Images uploaded');
          } catch (imgErr) {
            console.error('❌ Image upload failed:', imgErr.response?.status, imgErr.response?.data?.message);
            toast.error('Property created, but images failed to upload.');
          }
        }
      }

      toast.success('Property listed successfully! 🎉');
      setDone(true);
      setTimeout(() => navigate('/properties'), 2200);

    } catch (err) {
      console.error('❌ Property error:', err.response?.status, err.response?.data);
      const status = err.response?.status;
      if (status === 422) {
        const serverErrors = err.response.data?.errors || {};
        const mapped = {};
        Object.entries(serverErrors).forEach(([field, msgs]) => {
          mapped[field] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
        setErrors(mapped);
        toast.error('Please fix the validation errors');
      } else if (status === 401) {
        toast.error('Session expired — please sign in again');
        navigate('/login');
      } else {
        toast.error(err.response?.data?.message || 'Something went wrong.');
      }
    } finally {
      setLoading(false);
    }
  };

  if (done) return <SuccessScreen />;

  return (
    <PropertyForm
      onSubmit={handleSubmit}
      submitLabel="Publish Property"
      isEdit={false}
      loading={loading}
    />
  );
}
