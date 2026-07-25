import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import Layout from './components/layout/Layout';
import ProtectedRoute from './components/common/ProtectedRoute';

// Pages
import Home           from './pages/Home';
import Login          from './pages/Login';
import Register       from './pages/Register';
import Properties     from './pages/Properties';
import PropertyDetail from './pages/PropertyDetail';
import Bookings       from './pages/Bookings';
import Favorites      from './pages/Favorites';
import Chat           from './pages/Chat';
import Notifications  from './pages/Notifications';
import Profile        from './pages/Profile';
import AddProperty    from './pages/AddProperty';
import EditProperty   from './pages/Editproperty';   // ← NEW
import NotFound       from './pages/NotFound';

export default function App() {
  return (
    <AuthProvider>
      <Layout>
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
          {/* ← NEW */}
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
          <Route path="/notifications" element={
            <ProtectedRoute><Notifications /></ProtectedRoute>
          } />
          <Route path="/profile" element={
            <ProtectedRoute><Profile /></ProtectedRoute>
          } />

          {/* 404 */}
          <Route path="*" element={<NotFound />} />
        </Routes>
      </Layout>
    </AuthProvider>
  );
}