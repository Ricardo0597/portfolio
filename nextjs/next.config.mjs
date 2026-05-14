/** @type {import('next').NextConfig} */
const nextConfig = {
  basePath: '/headless',
  output: 'standalone',

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
