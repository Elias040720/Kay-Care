import { defineConfig } from '@playwright/test'

// scripts/test-integration.ps1 owns the real servers and isolated SQLite database.
export default defineConfig({
  testDir: './tests/integration',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  workers: 1,
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: 'http://localhost:5175',
    browserName: 'chromium',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1440, height: 1000 },
    screenshot: 'only-on-failure',
    trace: 'off',
  },
})
