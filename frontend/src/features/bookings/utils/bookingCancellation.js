function dateOnly(value) {
  if (!value) return null;

  const [datePart] = String(value).split('T');
  const [year, month, day] = datePart.split('-').map(Number);

  if (!year || !month || !day) return null;
  return new Date(year, month - 1, day);
}

export function hasStayStarted(booking) {
  const startDate = dateOnly(booking?.start_date);
  if (!startDate) return true;

  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  return today >= startDate;
}

export function canCancelBooking(booking, user, isOwnerView = false) {
  if (!booking || !user?.id || hasStayStarted(booking)) return false;

  const status = booking.status;
  const isGuest = String(booking.user_id) === String(user.id);
  const ownerId = booking.property?.user_id;
  const ownsProperty = ownerId ? String(ownerId) === String(user.id) : isOwnerView;

  if (isGuest) {
    return ['pending', 'accepted'].includes(status);
  }

  if (ownsProperty || isOwnerView) {
    return status === 'accepted';
  }

  return false;
}
