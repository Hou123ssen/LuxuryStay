import { useNavigate } from 'react-router-dom';
import { FiArrowLeft } from 'react-icons/fi';

export default function NotFound() {
  const navigate = useNavigate();
  return (
    <div className="min-h-screen flex flex-col items-center justify-center text-center px-4">
      <div className="font-display text-[8rem] leading-none text-gold/10 mb-4 select-none">404</div>
      <h1 className="font-display text-3xl text-cream mb-2">Page not found</h1>
      <p className="text-cream/35 text-sm mb-8 max-w-sm">
        The page you're looking for has checked out. Let's get you back on track.
      </p>
      <button onClick={() => navigate('/')}
        className="btn-gold px-8 py-3 rounded-full flex items-center gap-2 text-sm">
        <FiArrowLeft size={15} /> Back to Home
      </button>
    </div>
  );
}
