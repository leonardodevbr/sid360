/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{vue,js,ts,jsx,tsx}',
  ],
  safelist: [
    'bg-sid-cream',
    'bg-sid-cream-dark',
    'bg-sid-accent',
    'bg-sid-accent/10',
    'bg-sid-accent-light',
    'bg-primary-50',
    'text-sid-dark',
    'text-sid-secondary',
    'text-sid-accent',
    'text-sid-accent-light',
    'text-sid-gold',
    'text-secondary-600',
    'hover:bg-sid-accent/10',
    'hover:bg-primary-50',
    'hover:text-sid-accent-light',
    'focus:ring-sid-accent',
    'border-sid-accent',
  ],
  theme: {
    extend: {
      colors: {
        sid: {
          accent: '#C23028',
          'accent-light': '#D44840',
          gold: '#C9A84C',
          cream: '#FAF5EE',
          'cream-dark': '#F0E8DB',
          dark: '#1C0A06',
          secondary: '#7A4535',
        },
        primary: {
          50: '#fdf3f2',
          100: '#fbe4e2',
          500: '#C23028',
          600: '#C23028',
          700: '#a82822',
        },
        secondary: {
          500: '#C9A84C',
          600: '#a07a28',
        },
        muted: {
          100: '#F0E8DB',
          200: '#e8e4d8',
          700: '#7A4535',
        },
      },
      boxShadow: {
        card: '0 10px 25px rgba(15, 23, 42, 0.08)',
      },
      borderRadius: {
        xl: '1rem',
      },
    },
  },
  plugins: [],
};

