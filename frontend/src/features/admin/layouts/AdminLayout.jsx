import { useCallback, useEffect, useState } from 'react';
import { Outlet } from 'react-router-dom';
import AdminSidebar from '../components/AdminSidebar';

const STORAGE_KEY = 'luxurrstay:admin-sidebar-collapsed';

export default function AdminLayout() {
  const [collapsed, setCollapsed] = useState(() => {
    if (typeof window === 'undefined') return false;

    return window.localStorage.getItem(STORAGE_KEY) === 'true';
  });

  useEffect(() => {
    window.localStorage.setItem(STORAGE_KEY, String(collapsed));
  }, [collapsed]);

  const toggleCollapsed = useCallback(() => {
    setCollapsed((current) => !current);
  }, []);

  return (
    <div className="min-h-[calc(100vh-4rem)] bg-[radial-gradient(circle_at_top_left,rgba(201,168,76,0.08),transparent_34%),var(--obsidian)]">
      <div className="md:hidden">
        <AdminSidebar variant="mobile" />
      </div>

      <div className="flex min-h-[calc(100vh-4rem)]">
        <div className="hidden md:block">
          <AdminSidebar collapsed={collapsed} onToggle={toggleCollapsed} />
        </div>

        <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
