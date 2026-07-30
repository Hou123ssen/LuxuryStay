import { FiChevronLeft, FiChevronRight } from 'react-icons/fi';
import { paginationItems } from '../../utils/pagination';

export default function Pagination({
  meta,
  currentPage,
  onPageChange,
  disabled = false,
  className = '',
}) {
  const page = currentPage || meta?.current_page || 1;
  const lastPage = meta?.last_page || 1;

  if (!meta || lastPage <= 1) return null;

  const goTo = (nextPage) => {
    if (disabled || nextPage < 1 || nextPage > lastPage || nextPage === page) return;
    onPageChange(nextPage);
  };

  return (
    <nav
      className={`flex items-center justify-center gap-2 mt-10 ${className}`}
      aria-label="Pagination"
    >
      <button
        type="button"
        onClick={() => goTo(page - 1)}
        disabled={disabled || page <= 1}
        className="p-2 rounded-xl border border-gold/20 text-cream/50 hover:border-gold/50 hover:text-cream disabled:opacity-30 disabled:hover:border-gold/20 disabled:hover:text-cream/50 transition-colors"
        aria-label="Previous page"
      >
        <FiChevronLeft />
      </button>

      {paginationItems(page, lastPage).map((item) => {
        if (typeof item === 'string') {
          return (
            <span key={item} className="px-1 text-cream/30 text-sm">
              ...
            </span>
          );
        }

        return (
          <button
            key={item}
            type="button"
            onClick={() => goTo(item)}
            disabled={disabled}
            className={`w-9 h-9 rounded-xl text-sm transition-all ${
              item === page
                ? 'bg-gold text-obsidian font-medium'
                : 'border border-gold/20 text-cream/50 hover:border-gold/50 hover:text-cream'
            } disabled:opacity-50`}
            aria-current={item === page ? 'page' : undefined}
          >
            {item}
          </button>
        );
      })}

      <button
        type="button"
        onClick={() => goTo(page + 1)}
        disabled={disabled || page >= lastPage}
        className="p-2 rounded-xl border border-gold/20 text-cream/50 hover:border-gold/50 hover:text-cream disabled:opacity-30 disabled:hover:border-gold/20 disabled:hover:text-cream/50 transition-colors"
        aria-label="Next page"
      >
        <FiChevronRight />
      </button>
    </nav>
  );
}
