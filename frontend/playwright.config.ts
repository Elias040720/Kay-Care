import { defineConfig } from '@playwright/test'
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 45_000,
  expect: { timeout: 8_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    browserName: 'chromium',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1440, height: 1000 },
    screenshot: 'only-on-failure',
    trace: 'off',
  },
  projects: [
    { name: 'demo', testMatch: '**/demo.spec.ts', use: { baseURL: 'http://localhost:5173' } },
    {
      name: 'real-contract',
      testMatch: '**/real.spec.ts',
      use: { baseURL: 'http://localhost:5174' },
    },
  ],
  webServer: [
    {
      command: 'node node_modules/vite/bin/vite.js --host localhost --port 5173 --strictPort',
      url: 'http://localhost:5173',
      reuseExistingServer: false,
      env: { VITE_API_MODE: 'mock' },
    },
    {
      command: 'node node_modules/vite/bin/vite.js --host localhost --port 5174 --strictPort',
      url: 'http://localhost:5174',
      reuseExistingServer: false,
      env: { VITE_API_MODE: 'real', VITE_API_BASE_URL: 'http://localhost:8000' },
    },
  ],
})
