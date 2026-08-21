import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',
  ],

  theme: {
    extend: {
      fontFamily: {
        // Use Inter first, fallback to Figtree, then system sans
        sans: [
          'Inter',        // Primary: clean, modern
          'Figtree',      // Secondary: fallback from Laravel Breeze
          'system-ui',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'Noto Sans',
          'sans-serif',
        ],
      },
      colors: {
        primary: {
          50: '#EFF4FD',
          100: '#DBE7FB',
          200: '#B8CFF7',
          300: '#8AAEF0',
          400: '#5784E6',
          500: '#2F5FD6',
          600: '#1447C0',
          700: '#10399B',
          800: '#0C2C77',
          900: '#091F54',
          950: '#050F2E',
        },
        success: {
          50: '#ECFDF5',
          100: '#D1FAE5',
          500: '#10B981',
          600: '#059669',
        },
        warning: {
          50: '#FFFBEB',
          100: '#FEF3C7',
          500: '#F59E0B',
          600: '#D97706',
        },
        danger: {
          50: '#FEF2F2',
          100: '#FEE2E2',
          500: '#EF4444',
          600: '#DC2626',
        },
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
        soft: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
      },
      borderRadius: {
        xl: '1rem',
        '2xl': '1.5rem',
      },
    },
  },

  plugins: [forms, typography],
  safelist: [
  'dataTable-selector',
  'dataTable-input',
  'dataTable-pagination',
],
}
