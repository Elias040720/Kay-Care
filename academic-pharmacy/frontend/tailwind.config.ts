import type { Config } from 'tailwindcss'

// Paleta "premium minimalista": blanco cálido, verde salvia y turquesa suave.
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        cream: { 50: '#FDFBF7', 100: '#FAF6EF', 200: '#F2EBDF', 300: '#E6DCCB' },
        sage: { 50: '#F1F6F2', 100: '#DFEBE2', 200: '#C1D8C8', 300: '#9DC0A8', 400: '#7AA78A', 500: '#5E8C70', 600: '#4A7159', 700: '#3B5947', 800: '#2E4638' },
        teal: { 50: '#EEF8F7', 100: '#D3EEEB', 200: '#A9DDD7', 300: '#7CC6BF', 400: '#52ABA4', 500: '#3A8F89', 600: '#2E736F', 700: '#275C59' },
        ink: { 400: '#6B7772', 500: '#4E5A55', 700: '#2B3531', 900: '#18201D' },
        clay: { 100: '#FBE9E1', 500: '#B8583A', 700: '#8A3D26' },
        honey: { 100: '#FBF1D9', 600: '#8A6414' },
      },
      fontFamily: {
        sans: ['"Inter"', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
        display: ['"Iowan Old Style"', '"Palatino Linotype"', 'Palatino', 'Georgia', 'serif'],
      },
      boxShadow: {
        soft: '0 1px 2px rgba(24,32,29,0.04), 0 8px 24px -12px rgba(24,32,29,0.12)',
        lift: '0 2px 4px rgba(24,32,29,0.05), 0 18px 40px -18px rgba(24,32,29,0.22)',
      },
      borderRadius: { xl2: '1.25rem' },
      keyframes: {
        shimmer: { '0%': { transform: 'translateX(-100%)' }, '100%': { transform: 'translateX(100%)' } },
        rise: { '0%': { opacity: '0', transform: 'translateY(8px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
      },
      animation: {
        shimmer: 'shimmer 1.4s ease-in-out infinite',
        rise: 'rise 420ms cubic-bezier(.2,.7,.2,1) both',
      },
    },
  },
  plugins: [],
} satisfies Config
