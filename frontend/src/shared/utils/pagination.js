export const DEFAULT_PAGE = 1;

export function parsePage(value, fallback = DEFAULT_PAGE) {
  const page = Number.parseInt(value, 10);
  return Number.isFinite(page) && page > 0 ? page : fallback;
}

export function normalizePaginationMeta(payload) {
  return payload?.meta || payload?.data?.meta || null;
}

export function paginationItems(currentPage, lastPage, siblingCount = 1) {
  if (!lastPage || lastPage <= 1) return [];

  const current = parsePage(currentPage);
  const totalNumbers = siblingCount * 2 + 5;

  if (lastPage <= totalNumbers) {
    return Array.from({ length: lastPage }, (_, index) => index + 1);
  }

  const leftSibling = Math.max(current - siblingCount, 1);
  const rightSibling = Math.min(current + siblingCount, lastPage);
  const showLeftDots = leftSibling > 2;
  const showRightDots = rightSibling < lastPage - 1;

  if (!showLeftDots && showRightDots) {
    const leftRange = Array.from({ length: 3 + siblingCount * 2 }, (_, index) => index + 1);
    return [...leftRange, 'ellipsis-right', lastPage];
  }

  if (showLeftDots && !showRightDots) {
    const start = lastPage - (2 + siblingCount * 2);
    const rightRange = Array.from({ length: lastPage - start + 1 }, (_, index) => start + index);
    return [1, 'ellipsis-left', ...rightRange];
  }

  const middleRange = Array.from(
    { length: rightSibling - leftSibling + 1 },
    (_, index) => leftSibling + index,
  );

  return [1, 'ellipsis-left', ...middleRange, 'ellipsis-right', lastPage];
}
