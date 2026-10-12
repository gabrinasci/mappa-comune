/** @type {import('tailwindcss').Config} */
module.exports = {
  // Classi lette anche dal JS e dalle librerie PHP che generano markup (es. icone degli avvisi).
  content: ['./public/**/*.php', './public/assets/js/**/*.js', './app/lib/**/*.php'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Titillium Web"', 'Arial', 'sans-serif'],
      },
      colors: {
        navy: '#00194B',
        primary: '#0066CC',
        'primary-dark': '#003399',
      },
    },
  },
  plugins: [],
};
