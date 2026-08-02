import { roleLabel } from '../utils/adminUserFormatters';

const roleClasses = {
  admin: 'border-gold/35 bg-gold/10 text-gold',
  owner: 'border-blue-300/20 bg-blue-300/10 text-blue-100',
  user: 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100',
  guest: 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100',
};

export default function AdminUserRoleBadge({ role }) {
  return (
    <span className={`inline-flex w-fit rounded-full border px-2.5 py-1 text-xs font-medium ${roleClasses[role] || 'border-white/10 bg-white/5 text-cream/60'}`}>
      {roleLabel(role)}
    </span>
  );
}
