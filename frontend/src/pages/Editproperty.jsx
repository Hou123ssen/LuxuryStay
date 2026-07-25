import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { propertyService, imageService } from '../services/api';
import PropertyForm from '../components/property/PropertyForm';
import { useAuth } from '../context/AuthContext';
import toast from 'react-hot-toast';
import { FiLock } from 'react-icons/fi';

export default function EditProperty() {
  const { id }         = useParams();
  const navigate       = useNavigate();
  const { user }       = useAuth();

  const [property,  setProperty]  = useState(null);
  const [fetching,  setFetching]  = useState(true);
  const [loading,   setLoading]   = useState(false);
  const [isOwner,   setIsOwner]   = useState(false);

  /* ── Fetch property ────────────────────────────────────────────────────────── */
  useEffect(() => {
    (async () => {
      try {
        const res = await propertyService.get(id);
        const p   = res.data?.data || res.data;
        setProperty(p);
        // Check ownership: compare user_id or host.id against logged-in user
        const ownerId = p?.user_id || p?.host?.id || p?.owner?.id;
        setIsOwner(String(ownerId) === String(user?.id));
      } catch {
        toast.error('Property not found');
        navigate('/properties');
      } finally {
        setFetching(false);
      }
    })();
  }, [id, user]);

  /* ── Submit handler ──────────────────────────────────────────────────────── */
  const handleSubmit = async (payload, newFiles, removedImageIds, setErrors) => {
    setLoading(true);
    try {
      // 1. Update property fields
      await propertyService.update(id, payload);

      // 2. Upload new images
      if (newFiles.length > 0) {
        try {
          await imageService.uploadMany(newFiles, Number(id));
        } catch {
          toast.error('Property updated, but some images failed to upload.');
        }
      }

      // 3. Delete removed images
      //    Laravel typically exposes DELETE /api/images/{id}
      //    We call it for each removed id — fire-and-forget if no endpoint exists
      if (removedImageIds.length > 0) {
        await Promise.allSettled(
          removedImageIds.map((imgId) =>
            fetch(
              `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/images/${imgId}`,
              {
                method: 'DELETE',
                headers: {
                  Authorization: `Bearer ${localStorage.getItem('luxurystay_token')}`,
                  Accept: 'application/json',
                },
              }
            )
          )
        );
      }

      toast.success('Property updated successfully! ✨');
      navigate(`/properties/${id}`);
    } catch (err) {
      const status = err.response?.status;
      if (status === 422) {
        const serverErrors = err.response.data?.errors || {};
        const mapped = {};
        Object.entries(serverErrors).forEach(([field, msgs]) => {
          mapped[field] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
        setErrors(mapped);
        toast.error('Please fix the validation errors');
      } else if (status === 403) {
        toast.error('You are not authorized to edit this property');
        navigate(`/properties/${id}`);
      } else {
        toast.error(err.response?.data?.message || 'Update failed. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  /* ── Loading state ───────────────────────────────────────────────────────── */
  if (fetching) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="w-10 h-10 border-2 border-gold/30 border-t-gold rounded-full animate-spin" />
      </div>
    );
  }

  /* ── Not owner guard ─────────────────────────────────────────────────────── */
  if (!isOwner) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <div className="luxury-card rounded-3xl p-12 text-center max-w-sm w-full fade-up">
          <div
            className="w-16 h-16 rounded-full mx-auto mb-5 flex items-center justify-center"
            style={{
              background: 'rgba(239,68,68,0.1)',
              border: '1px solid rgba(239,68,68,0.25)',
            }}
          >
            <FiLock size={24} className="text-red-400" />
          </div>
          <h2 className="font-display text-2xl text-cream mb-2">Access Denied</h2>
          <p className="text-cream/40 text-sm mb-6">
            You can only edit properties that you own.
          </p>
          <button
            onClick={() => navigate(`/properties/${id}`)}
            className="btn-gold px-6 py-2.5 rounded-full text-sm font-medium"
          >
            View Property
          </button>
        </div>
      </div>
    );
  }

  /* ── Derive initial values from fetched property ─────────────────────────── */
  const initialData = {
    title:           property?.title       || property?.name || '',
    description:     property?.description || '',
    type:            property?.type        || '',
    price_per_night: property?.price_per_night ? String(property.price_per_night) : '',
    city:            property?.city        || '',
    address:         property?.address     || '',
  };

  const existingImages = property?.images || [];

  return (
    <PropertyForm
      initialData={initialData}
      existingImages={existingImages}
      onSubmit={handleSubmit}
      submitLabel="Save Changes"
      isEdit={true}
      loading={loading}
    />
  );
}