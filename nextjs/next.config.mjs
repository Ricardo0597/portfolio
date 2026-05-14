/** @type {import('next').NextConfig} */
const nextConfig = {
  basePath: '/headless',
  output: 'standalone',
  turbopack: {},
  allowedDevOrigins: ['portfolio.ddev.site', '*.ngrok-free.dev', '*.ngrok-free.app'],

  webpack: (config) => {
    config.resolve.alias = {
      ...config.resolve.alias,
      sharp$: false,
      'onnxruntime-node$': false,
    };
    return config;
  },
};

export default nextConfig;
