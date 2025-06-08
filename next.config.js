// next.config.js
const path = require('path');

/** @type {import('next').NextConfig} */
module.exports = {
  experimental: { appDir: true },
  webpack(config) {
    config.resolve.alias = {
      ...(config.resolve.alias || {}),
      '@components': path.join(__dirname, 'components'),
      '@ui':         path.join(__dirname, 'components', 'ui'),
      '@':           path.join(__dirname),
    };
    return config;
  },
};