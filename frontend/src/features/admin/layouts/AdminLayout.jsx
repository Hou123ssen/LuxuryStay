import { Outlet } from 'react-router-dom';
import AdminSidebar from '../components/AdminSidebar';

export default function AdminLayout() {
  return (
    <div className="min-h-screen px-4 py-8 sm:py-10">
      <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        <AdminSidebar />
        <div className="min-w-0">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
