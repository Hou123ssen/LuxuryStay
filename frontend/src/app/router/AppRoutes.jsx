import { Routes, Route } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';

// Pages
import Home           from '../../features/home/pages/Home';
import Login          from '../../features/auth/pages/Login';
import Register       from '../../features/auth/pages/Register';
import Properties     from '../../features/properties/pages/Properties';
import PropertyDetail from '../../features/properties/pages/PropertyDetail';
import Bookings       from '../../features/bookings/pages/Bookings';
import Favorites      from '../../features/favorites/pages/Favorites';
import Chat           from '../../features/chat/pages/Chat';
import AudioCall      from '../../features/chat/pages/AudioCall';
import Notifications  from '../../features/notifications/pages/Notifications';
import Profile        from '../../features/profile/pages/Profile';
import AdminLayout    from '../../features/admin/layouts/AdminLayout';
import AdminDashboard from '../../features/admin/pages/AdminDashboard';
import AdminGeography from '../../features/admin/pages/AdminGeography';
import AdminReports   from '../../features/reports/pages/AdminReports';
import AdminReviews   from '../../features/reviews/pages/AdminReviews';
import AddProperty    from '../../features/properties/pages/AddProperty';
import EditProperty   from '../../features/properties/pages/EditProperty';
import NotFound       from '../../pages/NotFound';

export default function AppRoutes() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/"               element={<Home />} />
      <Route path="/login"          element={<Login />} />
      <Route path="/register"       element={<Register />} />
      <Route path="/properties"     element={<Properties />} />
      <Route path="/properties/:id" element={<PropertyDetail />} />

      {/* Protected */}
      <Route path="/properties/new" element={
        <ProtectedRoute><AddProperty /></ProtectedRoute>
      } />
      <Route path="/properties/:id/edit" element={
        <ProtectedRoute><EditProperty /></ProtectedRoute>
      } />
      <Route path="/bookings" element={
        <ProtectedRoute><Bookings /></ProtectedRoute>
      } />
      <Route path="/favorites" element={
        <ProtectedRoute><Favorites /></ProtectedRoute>
      } />
      <Route path="/chat" element={
        <ProtectedRoute><Chat /></ProtectedRoute>
      } />
      <Route path="/call" element={
        <ProtectedRoute><AudioCall /></ProtectedRoute>
      } />
      <Route path="/notifications" element={
        <ProtectedRoute><Notifications /></ProtectedRoute>
      } />
      <Route path="/profile" element={
        <ProtectedRoute><Profile /></ProtectedRoute>
      } />
      <Route element={
        <ProtectedRoute roles={['admin']}><AdminLayout /></ProtectedRoute>
      }>
        <Route path="/admin" element={<AdminDashboard />} />
        <Route path="/admin/geography" element={<AdminGeography />} />
        <Route path="/admin/reports" element={<AdminReports />} />
        <Route path="/admin/reviews" element={<AdminReviews />} />
      </Route>

      {/* 404 */}
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
