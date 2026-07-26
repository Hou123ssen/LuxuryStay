/**
 * ImageUploader
 * ─────────────────────────────────────────────────────────────────────────────
 * Props
 *   existingImages  – array of already-saved images { id, url } (for Edit mode)
 *   onImagesChange  – callback(newLocalFiles, removedExistingIds)
 *                     called whenever local queue or remove-list changes
 *   maxFiles        – max total images allowed (default 8)
 *   disabled        – grey-out the zone
 */
import { useState, useRef, useCallback } from 'react';
import { FiUploadCloud, FiX, FiImage, FiAlertCircle } from 'react-icons/fi';

const MAX_FILE_SIZE_MB = 5;
const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

export default function ImageUploader({
  existingImages = [],
  onImagesChange,
  maxFiles = 8,
  disabled = false,
}) {
  const inputRef  = useRef(null);
  const [dragging, setDragging] = useState(false);

  // Local new files selected by user (not yet uploaded to server)
  const [localFiles, setLocalFiles]       = useState([]); // [{ file, preview, id }]
  // IDs of existing server images marked for removal
  const [removedIds, setRemovedIds]       = useState([]); // number[]
  // Per-file validation errors
  const [fileErrors, setFileErrors]       = useState([]);

  const totalCount = (existingImages.length - removedIds.length) + localFiles.length;
  const slotsLeft  = maxFiles - totalCount;

  /* ── notify parent ───────────────────────────────────────────────────────── */
  const notify = useCallback((files, removed) => {
    onImagesChange?.(files.map((f) => f.file), removed);
  }, [onImagesChange]);

  /* ── process dropped/selected files ─────────────────────────────────────── */
  const processFiles = (rawFiles) => {
    const errs  = [];
    const valid = [];
    const available = maxFiles - totalCount;

    Array.from(rawFiles).slice(0, available).forEach((file, i) => {
      if (!ACCEPTED.includes(file.type)) {
        errs.push(`"${file.name}" is not a supported image format`);
        return;
      }
      if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
        errs.push(`"${file.name}" exceeds ${MAX_FILE_SIZE_MB} MB`);
        return;
      }
      valid.push({
        id:      `local_${Date.now()}_${i}`,
        file,
        preview: URL.createObjectURL(file),
      });
    });

    setFileErrors(errs);
    const next = [...localFiles, ...valid];
    setLocalFiles(next);
    notify(next, removedIds);
  };

  /* ── drag events ─────────────────────────────────────────────────────────── */
  const onDragOver  = (e) => { e.preventDefault(); if (!disabled) setDragging(true); };
  const onDragLeave = ()  => setDragging(false);
  const onDrop      = (e) => {
    e.preventDefault();
    setDragging(false);
    if (disabled) return;
    processFiles(e.dataTransfer.files);
  };

  /* ── input change ────────────────────────────────────────────────────────── */
  const onInputChange = (e) => {
    processFiles(e.target.files);
    e.target.value = ''; // allow re-selecting same file
  };

  /* ── remove local file ───────────────────────────────────────────────────── */
  const removeLocal = (id) => {
    const next = localFiles.filter((f) => f.id !== id);
    setLocalFiles(next);
    notify(next, removedIds);
  };

  /* ── remove existing server image ────────────────────────────────────────── */
  const removeExisting = (imgId) => {
    const next = [...removedIds, imgId];
    setRemovedIds(next);
    notify(localFiles, next);
  };

  /* ── restore removed existing image ─────────────────────────────────────── */
  const restoreExisting = (imgId) => {
    const next = removedIds.filter((id) => id !== imgId);
    setRemovedIds(next);
    notify(localFiles, next);
  };

  const isRemoved = (imgId) => removedIds.includes(imgId);

  return (
    <div className="space-y-4">

      {/* ── Drop zone ── */}
      <div
        onDragOver={onDragOver}
        onDragLeave={onDragLeave}
        onDrop={onDrop}
        onClick={() => !disabled && slotsLeft > 0 && inputRef.current?.click()}
        className={`relative flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-all duration-200 ${
          disabled
            ? 'border-white/8 opacity-50 cursor-not-allowed'
            : slotsLeft <= 0
            ? 'border-white/8 opacity-50 cursor-not-allowed'
            : dragging
            ? 'border-gold bg-gold/8 scale-[1.01] cursor-copy'
            : 'border-white/15 hover:border-gold/50 hover:bg-gold/3 cursor-pointer'
        }`}
      >
        <div
          className={`w-14 h-14 rounded-full flex items-center justify-center transition-colors ${
            dragging ? 'bg-gold/20' : 'bg-white/5'
          }`}
        >
          <FiUploadCloud size={24} className={dragging ? 'text-gold' : 'text-cream/40'} />
        </div>

        <div>
          <p className={`text-sm font-medium ${dragging ? 'text-gold' : 'text-cream/70'}`}>
            {dragging ? 'Drop images here' : slotsLeft <= 0 ? 'Maximum images reached' : 'Drag & drop images here'}
          </p>
          <p className="text-xs text-cream/30 mt-1">
            {slotsLeft > 0
              ? `or click to browse · JPG, PNG, WebP · max ${MAX_FILE_SIZE_MB} MB each · ${slotsLeft} slot${slotsLeft !== 1 ? 's' : ''} left`
              : `${maxFiles} of ${maxFiles} images added`}
          </p>
        </div>

        <input
          ref={inputRef}
          type="file"
          multiple
          accept={ACCEPTED.join(',')}
          onChange={onInputChange}
          className="hidden"
          disabled={disabled || slotsLeft <= 0}
        />
      </div>

      {/* ── File errors ── */}
      {fileErrors.length > 0 && (
        <div className="space-y-1">
          {fileErrors.map((e, i) => (
            <p key={i} className="flex items-center gap-2 text-xs text-red-400">
              <FiAlertCircle size={11} /> {e}
            </p>
          ))}
        </div>
      )}

      {/* ── Existing server images ── */}
      {existingImages.length > 0 && (
        <div>
          <p className="text-xs text-cream/35 uppercase tracking-wider mb-2">Current Images</p>
          <div className="grid grid-cols-3 sm:grid-cols-4 gap-2">
            {existingImages.map((img) => {
              const removed = isRemoved(img.id);
              return (
                <div key={img.id} className="relative group aspect-square rounded-xl overflow-hidden">
                  <img
                    src={img.url}
                    alt=""
                    className={`w-full h-full object-cover transition-all duration-300 ${removed ? 'opacity-25 grayscale' : 'opacity-100'}`}
                  />
                  {/* Overlay */}
                  <div className={`absolute inset-0 transition-opacity duration-200 ${removed ? 'bg-black/60' : 'bg-black/0 group-hover:bg-black/30'}`} />

                  {removed ? (
                    /* Restore button */
                    <button
                      type="button"
                      onClick={() => restoreExisting(img.id)}
                      title="Restore image"
                      className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-cream/80 hover:text-cream transition-colors"
                    >
                      <FiImage size={18} />
                      <span className="text-[10px] font-medium">Restore</span>
                    </button>
                  ) : (
                    /* Remove button */
                    <button
                      type="button"
                      onClick={() => removeExisting(img.id)}
                      title="Remove image"
                      className="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-500 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
                    >
                      <FiX size={12} className="text-white" />
                    </button>
                  )}

                  {/* "Will be removed" badge */}
                  {removed && (
                    <div className="absolute bottom-1.5 left-1.5 right-1.5 text-center">
                      <span className="text-[9px] uppercase tracking-wider text-red-400 bg-black/70 rounded px-1.5 py-0.5">
                        Will be removed
                      </span>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ── New local previews ── */}
      {localFiles.length > 0 && (
        <div>
          <p className="text-xs text-cream/35 uppercase tracking-wider mb-2">
            New Images <span className="text-gold/60 normal-case">({localFiles.length} queued for upload)</span>
          </p>
          <div className="grid grid-cols-3 sm:grid-cols-4 gap-2">
            {localFiles.map((f, idx) => (
              <div key={f.id} className="relative group aspect-square rounded-xl overflow-hidden"
                   style={{ border: '1px solid rgba(201,168,76,0.25)' }}>
                <img src={f.preview} alt="" className="w-full h-full object-cover" />
                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors" />

                {/* Index badge */}
                <span className="absolute top-1.5 left-1.5 w-5 h-5 rounded-full bg-gold text-obsidian text-[10px] font-bold flex items-center justify-center">
                  {idx + 1}
                </span>

                {/* Remove */}
                <button
                  type="button"
                  onClick={() => removeLocal(f.id)}
                  title="Remove"
                  className="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-500 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
                >
                  <FiX size={12} className="text-white" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ── Empty state hint ── */}
      {totalCount === 0 && (
        <p className="flex items-center gap-1.5 text-xs text-cream/25 pl-1">
          <FiImage size={11} /> No images added yet — at least one is recommended
        </p>
      )}
    </div>
  );
}


