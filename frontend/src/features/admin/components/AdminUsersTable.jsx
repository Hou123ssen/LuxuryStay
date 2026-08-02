import { FiEye } from 'react-icons/fi';
import AdminUserCounts from './AdminUserCounts';
import AdminUserGeoBadge from './AdminUserGeoBadge';
import AdminUserRoleBadge from './AdminUserRoleBadge';
import { formatAdminUserDate } from '../utils/adminUserFormatters';

export default function AdminUsersTable({ users = [], loading = false, onViewUser }) {
  if (loading) {
    return (
      <div className="grid gap-3">
        {[1, 2, 3].map((item) => (
          <div key={item} className="h-28 animate-pulse rounded-2xl border border-gold/10 bg-white/[0.03]" />
        ))}
      </div>
    );
  }

  if (users.length === 0) {
    return (
      <div className="rounded-2xl border border-gold/10 bg-white/[0.03] p-8 text-center text-sm text-cream/45">
        No users found for these filters.
      </div>
    );
  }

  return (
    <section className="overflow-hidden rounded-2xl border border-gold/10 bg-white/[0.03]">
      <div className="hidden overflow-x-auto xl:block">
        <table className="min-w-full divide-y divide-white/5">
          <thead className="bg-black/20">
            <tr className="text-left text-[11px] uppercase tracking-[0.16em] text-cream/35">
              <th className="px-4 py-3">User</th>
              <th className="px-4 py-3">Role</th>
              <th className="px-4 py-3">Registered</th>
              <th className="px-4 py-3">Last seen</th>
              <th className="px-4 py-3">Counts</th>
              <th className="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5">
            {users.map((user) => (
              <tr key={user.id} className="transition-colors hover:bg-white/[0.025]">
                <td className="px-4 py-4">
                  <div className="font-medium text-cream">{user.name}</div>
                  <div className="mt-1 text-sm text-cream/45">{user.email}</div>
                  {user.is_demo_user && (
                    <span className="mt-2 inline-flex rounded-full border border-amber-300/25 bg-amber-300/10 px-2 py-0.5 text-[11px] text-amber-100">
                      Demo
                    </span>
                  )}
                </td>
                <td className="px-4 py-4"><AdminUserRoleBadge role={user.role} /></td>
                <td className="px-4 py-4">
                  <AdminUserGeoBadge
                    label="Registered"
                    countryCode={user.registered_country_code}
                    countryName={user.registered_country_name}
                    regionName={user.registered_region_name}
                    cityName={user.registered_city_name}
                  />
                </td>
                <td className="px-4 py-4">
                  <AdminUserGeoBadge
                    label={formatAdminUserDate(user.last_seen_at)}
                    countryCode={user.last_seen_country_code}
                    countryName={user.last_seen_country_name}
                    regionName={user.last_seen_region_name}
                    cityName={user.last_seen_city_name}
                  />
                </td>
                <td className="px-4 py-4 min-w-[280px]"><AdminUserCounts counts={user.counts} /></td>
                <td className="px-4 py-4 text-right">
                  <button
                    type="button"
                    onClick={() => onViewUser(user.id)}
                    className="inline-flex items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-sm text-gold transition-colors hover:border-gold hover:bg-gold/10"
                  >
                    <FiEye size={15} />
                    View
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="grid gap-3 p-3 xl:hidden">
        {users.map((user) => (
          <article key={user.id} className="rounded-2xl border border-white/5 bg-black/15 p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="truncate font-medium text-cream">{user.name}</h3>
                  <AdminUserRoleBadge role={user.role} />
                  {user.is_demo_user && <span className="rounded-full border border-amber-300/25 bg-amber-300/10 px-2 py-0.5 text-[11px] text-amber-100">Demo</span>}
                </div>
                <p className="mt-1 truncate text-sm text-cream/45">{user.email}</p>
              </div>
              <button
                type="button"
                onClick={() => onViewUser(user.id)}
                className="inline-flex w-fit items-center gap-2 rounded-full border border-gold/25 px-3 py-2 text-sm text-gold"
              >
                <FiEye size={15} />
                View
              </button>
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <AdminUserGeoBadge
                label="Registered"
                countryCode={user.registered_country_code}
                countryName={user.registered_country_name}
                regionName={user.registered_region_name}
                cityName={user.registered_city_name}
              />
              <AdminUserGeoBadge
                label={`Last seen ${formatAdminUserDate(user.last_seen_at)}`}
                countryCode={user.last_seen_country_code}
                countryName={user.last_seen_country_name}
                regionName={user.last_seen_region_name}
                cityName={user.last_seen_city_name}
              />
            </div>
            <div className="mt-4"><AdminUserCounts counts={user.counts} compact /></div>
          </article>
        ))}
      </div>
    </section>
  );
}
