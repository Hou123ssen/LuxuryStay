import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../app/providers/AuthContext';
import toast from 'react-hot-toast';
import { FiUser, FiMail, FiLock, FiArrowRight } from 'react-icons/fi';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [loading, setLoad] = useState(false);
  const [errors,  setErrors] = useState({});

  const set = (k, v) => { setForm(p => ({...p, [k]: v})); setErrors(p => ({...p, [k]: ''})); };

  const validate = () => {
    const e = {};
    if (!form.name)     e.name     = 'Name is required';
    if (!form.email)    e.email    = 'Email is required';
    if (!form.password) e.password = 'Password is required';
    if (form.password.length < 8) e.password = 'Minimum 8 characters';
    if (form.password !== form.password_confirmation) e.password_confirmation = 'Passwords do not match';
    setErrors(e);
    return !Object.keys(e).length;
  };

  const submit = async (e) => {
    e.preventDefault();
    if (!validate()) return;
    setLoad(true);
    try {
      await register(form);
      toast.success('Account created! Welcome to LuxuryStay.');
      navigate('/properties');
    } catch (err) {
      toast.error(err.response?.data?.message || 'Registration failed');
      if (err.response?.data?.errors) setErrors(err.response.data.errors);
    }
    setLoad(false);
  };

  const fields = [
    { key: 'name',                  type: 'text',     placeholder: 'Your full name',   icon: <FiUser size={14} />,  label: 'Full Name' },
    { key: 'email',                 type: 'email',    placeholder: 'you@example.com',  icon: <FiMail size={14} />,  label: 'Email' },
    { key: 'password',              type: 'password', placeholder: '8+ characters',    icon: <FiLock size={14} />,  label: 'Password' },
    { key: 'password_confirmation', type: 'password', placeholder: 'Repeat password',  icon: <FiLock size={14} />,  label: 'Confirm Password' },
  ];

  return (
    <div className="min-h-screen flex items-center justify-center px-4 py-10 relative overflow-hidden">
      <div className="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=1400&q=80" alt=""
             className="w-full h-full object-cover opacity-10" />
      </div>

      <div className="relative z-10 w-full max-w-md">
        <Link to="/" className="block text-center mb-8">
          <span className="font-display text-3xl tracking-widest text-gold-gradient">LUXURYSTAY</span>
        </Link>

        <div className="luxury-card rounded-3xl p-8">
          <h1 className="font-display text-3xl text-cream mb-1">Create account</h1>
          <p className="text-cream/40 text-sm mb-7">Begin your luxury experience today</p>

          <form onSubmit={submit} className="space-y-4">
            {fields.map(f => (
              <div key={f.key}>
                <label className="block text-xs text-cream/45 mb-1.5 uppercase tracking-wider">{f.label}</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-cream/30">{f.icon}</span>
                  <input type={f.type} value={form[f.key]} onChange={e => set(f.key, e.target.value)}
                    placeholder={f.placeholder}
                    className={`luxury-input w-full pl-10 pr-4 py-3 rounded-xl text-sm ${errors[f.key] ? 'border-red-500/60' : ''}`} />
                </div>
                {errors[f.key] && <p className="text-red-400 text-xs mt-1">{errors[f.key]}</p>}
              </div>
            ))}

            <button type="submit" disabled={loading}
              className="btn-gold w-full py-3.5 rounded-xl font-medium flex items-center justify-center gap-2 mt-6">
              {loading
                ? <span className="w-4 h-4 border-2 border-obsidian/40 border-t-obsidian rounded-full animate-spin" />
                : <><span>Create Account</span><FiArrowRight size={15} /></>}
            </button>
          </form>

          <div className="ornament-divider my-6">
            <span className="text-xs text-cream/25">or</span>
          </div>

          <p className="text-center text-sm text-cream/40">
            Already have an account?{' '}
            <Link to="/login" className="text-gold hover:text-gold-light transition-colors">Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
