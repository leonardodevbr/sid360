/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f5f3ff',
          100: '#ede9fe',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
        },
        secondary: {
          500: '#f97316',
          600: '#ea580c',
        },
        muted: {
          100: '#f3f4f6',
          200: '#e5e7eb',
          700: '#374151',
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

