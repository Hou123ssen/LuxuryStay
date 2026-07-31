import { createContext, useContext, useState, useEffect } from 'react';
import { authService } from '../../features/auth/api/authApi';
import toast from 'react-hot-toast';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser]       = useState(null);
  const [token, setToken]     = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const savedToken = localStorage.getItem('token');
    const savedUser  = localStorage.getItem('user');
    if (savedToken) {
      setToken(savedToken);

      try {
        if (savedUser) setUser(JSON.parse(savedUser));
      } catch (err) {
        console.error('Failed to parse user data:', err);
        localStorage.removeItem('user');
      }

      authService.me()
        .then((res) => {
          const freshUser = res.data?.user;
          if (freshUser) {
            localStorage.setItem('user', JSON.stringify(freshUser));
            setUser(freshUser);
          }
        })
        .catch(() => {
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          setToken(null);
          setUser(null);
        })
        .finally(() => setLoading(false));

      return;
    }

    setLoading(false);
  }, []);

  const login = async (credentials) => {
    const res = await authService.login(credentials);
    const { token: tk, user: u } = res.data;
    localStorage.setItem('token', tk);
    localStorage.setItem('user', JSON.stringify(u));
    setToken(tk);
    setUser(u);
    return u;
  };

  const register = async (data) => {
    const res = await authService.register(data);
    const { token: tk, user: u } = res.data;
    localStorage.setItem('token', tk);
    localStorage.setItem('user', JSON.stringify(u));
    setToken(tk);
    setUser(u);
    return u;
  };

  const logout = async () => {
    try { await authService.logout(); } catch (_) {}
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    setToken(null);
    setUser(null);
    toast.success('Signed out successfully');
  };

  return (
    <AuthContext.Provider value={{ user, token, loading, login, register, logout, isAuth: !!token }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
