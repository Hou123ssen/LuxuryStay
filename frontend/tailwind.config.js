/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,jsx}"],
  theme: {
    extend: {
      fontFamily: {
        display: ['Cormorant Garamond', 'Georgia', 'serif'],
        sans: ['DM Sans', 'sans-serif'],
        mono: ['DM Mono', 'monospace'],
      },
      colors: {
        obsidian: {
          DEFAULT: '#0a0a0f',
          50: '#f5f5f7',
          100: '#e8e8ed',
          200: '#c8c8d4',
          300: '#9898aa',
          400: '#666680',
          500: '#3d3d55',
          600: '#25253a',
          700: '#16162a',
          800: '#0e0e1c',
          900: '#0a0a0f',
        },
        gold: {
          DEFAULT: '#c9a84c',
          light: '#e8cc7e',
          dark: '#9a7a2e',
        },
        cream: '#faf8f2',
      },
      animation: {
        'fade-up': 'fadeUp 0.6s ease forwards',
        'shimmer': 'shimmer 2s infinite',
      },
      keyframes: {
        fadeUp: {
          from: { opacity: 0, transform: 'translateY(20px)' },
          to: { opacity: 1, transform: 'translateY(0)' },
        },
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
      },
    },
  },
  plugins: [],
}
