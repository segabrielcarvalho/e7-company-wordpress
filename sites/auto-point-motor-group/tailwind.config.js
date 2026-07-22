/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./*.php', './inc/**/*.php', './template-parts/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        accent: '#328538',
        ink: '#1e2b54',
        charcoal: '#204089',
        smoke: '#f2f6f9'
      },
      fontFamily: {
        sans: ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
        display: ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif']
      },
      boxShadow: {
        card: '0 14px 35px rgba(0,0,0,.08)'
      }
    }
  },
  plugins: []
};
