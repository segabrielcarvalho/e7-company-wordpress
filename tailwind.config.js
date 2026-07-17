const colors = require('tailwindcss/colors');

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./*.php', './template-parts/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        brand: colors.blue,
        ink: '#0a0a0a',
      },
      fontFamily: {
        display: ['Inter Tight', 'Inter', 'ui-sans-serif', 'system-ui'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      },
      boxShadow: {
        glow: '0 24px 80px -32px rgba(37, 99, 235, 0.55)',
      },
    },
  },
  plugins: [],
};
