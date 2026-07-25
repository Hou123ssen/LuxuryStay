import Navbar from './Navbar';
import { Toaster } from 'react-hot-toast';

export default function Layout({ children }) {
  return (
    <div className="min-h-screen" style={{ background: 'var(--obsidian)' }}>
      <Navbar />
      <main className="pt-16">{children}</main>
      <Toaster
        position="top-right"
        toastOptions={{
          style: {
            background: '#1c1c2e',
            color: '#faf8f2',
            border: '1px solid rgba(201,168,76,0.2)',
            fontFamily: 'DM Sans, sans-serif',
            fontSize: '0.875rem',
          },
          success: { iconTheme: { primary: '#c9a84c', secondary: '#0a0a0f' } },
          error:   { iconTheme: { primary: '#ef4444', secondary: '#0a0a0f' } },
        }}
      />
    </div>
  );
}
