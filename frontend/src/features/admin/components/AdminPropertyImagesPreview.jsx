import { FiImage } from 'react-icons/fi';

export default function AdminPropertyImagesPreview({ images = [] }) {
  if (!images.length) {
    return (
      <div className="rounded-xl border border-white/5 bg-black/20 p-4 text-sm text-cream/45">
        No property images.
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
      {images.map((image) => (
        <div key={image.id} className="overflow-hidden rounded-xl border border-white/5 bg-black/20">
          {image.url ? (
            <img src={image.url} alt="" className="aspect-[4/3] w-full object-cover" loading="lazy" />
          ) : (
            <div className="flex aspect-[4/3] items-center justify-center text-cream/35">
              <FiImage />
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
