export const NAVBAR_COUNTS_REFRESH_EVENT = 'luxurrstay:navbar-counts-refresh';

export const notifyNavbarCountsChanged = (counts = null) => {
  window.dispatchEvent(new CustomEvent(NAVBAR_COUNTS_REFRESH_EVENT, {
    detail: counts ? { counts } : undefined,
  }));
};
