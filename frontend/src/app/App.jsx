import { AuthProvider } from './providers/AuthContext';
import AppRoutes from './router/AppRoutes';
import Layout from '../shared/layouts/Layout';

export default function App() {
  return (
    <AuthProvider>
      <Layout>
        <AppRoutes />
      </Layout>
    </AuthProvider>
  );
}
