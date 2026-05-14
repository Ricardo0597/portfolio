/** @type {import('next').NextConfig} */
const nextConfig = {
  basePath: '/headless',
  output: 'standalone',
  turbopack: {
    resolveAlias: {
      sharp: { browser: false },
      'onnxruntime-node': { browser: false },
    },
  },
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
