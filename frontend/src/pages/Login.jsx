import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../app/providers/AuthContext';
import toast from 'react-hot-toast';
import { FiMail, FiLock, FiArrowRight } from 'react-icons/fi';

export default function Login() {
  const { login } = useAuth();
  const navigate  = useNavigate();
  const [form, setForm] = useState({ email: '', password: '' });
  const [loading, setLoad] = useState(false);
  const [errors, setErrors] = useState({});

  const set = (k, v) => { setForm(p => ({ ...p, [k]: v })); setErrors(p => ({ ...p, [k]: '' })); };

  const validate = () => {
    const e = {};
    if (!form.email)    e.email    = 'Email is required';
    if (!form.password) e.password = 'Password is required';
    setErrors(e);
    return !Object.keys(e).length;
  };

  const submit = async (e) => {
    e.preventDefault();
    if (!validate()) return;
    setLoad(true);
    try {
      await login(form);
      toast.success('Welcome back!');
      navigate('/properties');
    } catch (err) {
      const msg = err.response?.data?.message || 'Invalid credentials';
      toast.error(msg);
      if (err.response?.status === 422) setErrors(err.response.data.errors || {});
    }
    setLoad(false);
  };

  return (
    <div className="min-h-screen flex items-center justify-center px-4 relative overflow-hidden">
      {/* BG */}
      <div className="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1400&q=80" alt=""
             className="w-full h-full object-cover opacity-10" />
        <div className="absolute inset-0" style={{ background: 'radial-gradient(circle at 30% 50%, rgba(201,168,76,0.05) 0%, transparent 60%)' }} />
      </div>

      <div className="relative z-10 w-full max-w-md">
        {/* Logo */}
        <Link to="/" className="block text-center mb-8">
          <span className="font-display text-3xl tracking-widest text-gold-gradient">LUXURYSTAY</span>
        </Link>

        <div className="luxury-card rounded-3xl p-8">
          <h1 className="font-display text-3xl text-cream mb-1">Welcome back</h1>
          <p className="text-cream/40 text-sm mb-7">Sign in to continue your journey</p>

          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="block text-xs text-cream/45 mb-1.5 uppercase tracking-wider">Email</label>
              <div className="relative">
                <FiMail size={14} className="absolute left-4 top-1/2 -translate-y-1/2 text-cream/30" />
                <input type="email" value={form.email} onChange={e => set('email', e.target.value)}
                  placeholder="you@example.com"
                  className={`luxury-input w-full pl-10 pr-4 py-3 rounded-xl text-sm ${errors.email ? 'border-red-500/60' : ''}`} />
              </div>
              {errors.email && <p className="text-red-400 text-xs mt-1">{errors.email}</p>}
            </div>

            <div>
              <label className="block text-xs text-cream/45 mb-1.5 uppercase tracking-wider">Password</label>
              <div className="relative">
                <FiLock size={14} className="absolute left-4 top-1/2 -translate-y-1/2 text-cream/30" />
                <input type="password" value={form.password} onChange={e => set('password', e.target.value)}
                  placeholder="••••••••"
                  className={`luxury-input w-full pl-10 pr-4 py-3 rounded-xl text-sm ${errors.password ? 'border-red-500/60' : ''}`} />
              </div>
              {errors.password && <p className="text-red-400 text-xs mt-1">{errors.password}</p>}
            </div>

            <button type="submit" disabled={loading}
              className="btn-gold w-full py-3.5 rounded-xl font-medium flex items-center justify-center gap-2 mt-6">
              {loading ? (
                <span className="w-4 h-4 border-2 border-obsidian/40 border-t-obsidian rounded-full animate-spin" />
              ) : (
                <><span>Sign In</span><FiArrowRight size={15} /></>
              )}
            </button>
          </form>

          <div className="ornament-divider my-6">
            <span className="text-xs text-cream/25">or</span>
          </div>

          <p className="text-center text-sm text-cream/40">
            Don't have an account?{' '}
            <Link to="/register" className="text-gold hover:text-gold-light transition-colors">Create one</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
