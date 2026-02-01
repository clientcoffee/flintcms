module.exports = {
  content: [
    './app/**/*.php',
    './app/assets/js/**/*.js',
    './site/**/*.php',
    './site/**/*.md',
    './site/**/*.mdx'
  ],
  theme: {
    extend: {}
  },
  plugins: [require('@tailwindcss/typography')]
};
