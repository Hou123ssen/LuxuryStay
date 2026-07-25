import { Navigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function ProtectedRoute({ children }) {
  const { isAuth, loading } = useAuth();
  if (loading) return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" />
    </div>
  );
  return isAuth ? children : <Navigate to="/login" replace />;
}
