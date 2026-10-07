// TEST-S8/S9 · Config Playwright para flujos E2E del ERP.
//
// Setup:
//   cd tests/playwright
//   npm init -y && npm i -D @playwright/test
//   npx playwright install chromium
//   npx playwright test                 # corre todos
//   npx playwright test vendedor        # solo spec vendedor
//   npx playwright test --ui            # modo visual
//
// Requisitos:
//   • ERP corriendo en http://127.0.0.1:8090
//   • Seeder cargado: Carlos Vendedor Demo + Mia Marketing + Jorge Bodega
//   • MySQL arriba (XAMPP)

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './specs',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    fullyParallel: false,     // los flujos tocan BD, mejor serializados
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: '../../storage/playwright-report' }],
    ],
    use: {
        baseURL: process.env.ERP_URL ?? 'http://127.0.0.1:8090',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        viewport: { width: 1366, height: 820 },
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
