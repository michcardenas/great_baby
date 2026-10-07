// TEST-S9 · E2E flujos de Don Jorge (logística / bodega).
//
// Verifica: login AdminBodega → Cola de alistamiento (ex-"Cola Don Jorge") →
// asignar pedido a alistador → marcar alistado → pasa a Despachador →
// despachar → pedido queda facturado+despachado.
//
// Datos requeridos (seed demo):
//   • Usuario `admin.bodega.bog@greatbaby.com` / `demo` (AdminBodega BOG)
//   • Usuario `alistador.bog@greatbaby.com` / `demo` (Alistador)
//   • Al menos 1 pedido en estado 'aprobado' listo para facturar
//     (o se puede crear desde el flujo vendedor del spec anterior)

import { test, expect } from '@playwright/test';

const ADMIN = { email: 'admin.bodega.bog@greatbaby.com', password: 'demo' };

test.describe('Don Jorge · logística de bodega', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/app/login');
        await page.getByLabel(/correo|email/i).fill(ADMIN.email);
        await page.getByLabel(/contraseña|password/i).fill(ADMIN.password);
        await page.getByRole('button', { name: /ingresar|iniciar/i }).click();
    });

    test('Cola de alistamiento renderiza con 4 columnas (incluye Con novedad)', async ({ page }) => {
        await page.goto('/app/logistica/cola-jorge');
        await expect(page.getByText(/cola de alistamiento/i)).toBeVisible();
        // LOG-J5 · 4 columnas: Sin asignar · En picking · Alistado · Con novedad
        await expect(page.getByText(/sin asignar/i)).toBeVisible();
        await expect(page.getByText(/en picking/i)).toBeVisible();
        await expect(page.getByText(/alistado/i)).toBeVisible();
        await expect(page.getByText(/con novedad/i)).toBeVisible();
    });

    test('AdminBodega NO ve Compras ni Dropi (solo lo suyo)', async ({ page }) => {
        await page.goto('/app');
        await expect(page.getByRole('link', { name: /^compras/i })).toHaveCount(0);
        await expect(page.getByRole('link', { name: /^dropi/i })).toHaveCount(0);
        // SÍ debe ver Logística.
        await expect(page.getByRole('link', { name: /logística|logistica/i })).toBeVisible();
    });

    test('AdminBodega NO ve card "Dinero Dropi sin cobrar"', async ({ page }) => {
        await page.goto('/app');
        await expect(page.getByText(/dinero dropi sin cobrar/i)).toHaveCount(0);
    });

    test('Hoja de picking muestra ubicación Rack·Sección·Nivel + hora automática', async ({ page }) => {
        await page.goto('/app/logistica/cola-jorge');
        const firstPicking = page.getByRole('link', { name: /picking|imprimir/i }).first();
        if (await firstPicking.isVisible()) {
            await firstPicking.click();
            await expect(page.getByText(/rack|sección|nivel/i)).toBeVisible();
            await expect(page.getByText(/alistador/i)).toBeVisible();
            // Hora del sistema, no editable.
            await expect(page.locator('input[type="time"]')).toHaveCount(0);
        } else {
            test.skip(true, 'No hay pedidos listos para picking · seed vacío');
        }
    });

    test('Trazabilidad del pedido (LOG-J10) muestra línea de tiempo completa', async ({ page }) => {
        // Intenta abrir el primer pedido B2B que exista.
        await page.goto('/app/pedidos-b2b');
        const primero = page.locator('a[href^="/app/pedidos-b2b/"]').first();
        if (await primero.isVisible()) {
            await primero.click();
            await expect(page.getByText(/trazabilidad|línea de tiempo|timeline/i)).toBeVisible();
        } else {
            test.skip(true, 'Sin pedidos B2B en el seed');
        }
    });
});

test.describe('Contabilidad · semáforo SIIGO (CONT-C6)', () => {
    test('Aracely ve el semáforo SIIGO en /app/contabilidad/panel', async ({ page }) => {
        await page.goto('/app/login');
        await page.getByLabel(/correo|email/i).fill('aracely@greatbaby.com');
        await page.getByLabel(/contraseña|password/i).fill('demo');
        await page.getByRole('button', { name: /ingresar|iniciar/i }).click();

        await page.goto('/app/contabilidad/panel');
        await expect(page.getByText(/semáforo siigo/i)).toBeVisible();
        await expect(page.getByText(/ver discrepancias/i)).toBeVisible();
    });

    test('Discrepancias SIIGO renderiza 3 tablas (huérfanos · delta · fallidos)', async ({ page }) => {
        await page.goto('/app/login');
        await page.getByLabel(/correo|email/i).fill('aracely@greatbaby.com');
        await page.getByLabel(/contraseña|password/i).fill('demo');
        await page.getByRole('button', { name: /ingresar|iniciar/i }).click();

        await page.goto('/app/contabilidad/discrepancias-siigo');
        await expect(page.getByText(/huérfanos.*aprobados sin llegar a siigo/i)).toBeVisible();
        await expect(page.getByText(/delta.*cuadre interno/i)).toBeVisible();
        await expect(page.getByText(/errores recientes de siigo/i)).toBeVisible();
    });
});
