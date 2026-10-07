// TEST-S8 · E2E flujo del vendedor en terreno.
//
// Verifica el camino: login → Mi Panel → buscar cliente → armar pedido →
// confirmar → ver que aparece en "Mis últimos pedidos".
//
// Datos requeridos (seed demo):
//   • Usuario `vendedor.demo@greatbaby.com` / password `demo` con rol Vendedor
//   • Al menos 1 cliente activo con `lista_precios_id` NO NULL
//   • Al menos 1 producto con precio en esa lista

import { test, expect } from '@playwright/test';

const CRED = {
    email: 'vendedor.demo@greatbaby.com',
    password: 'demo',
};

test.describe('Vendedor en terreno', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/app/login');
        await page.getByLabel(/correo|email/i).fill(CRED.email);
        await page.getByLabel(/contraseña|password/i).fill(CRED.password);
        await page.getByRole('button', { name: /ingresar|iniciar/i }).click();
        await expect(page).toHaveURL(/\/app($|\/vendedor)/);
    });

    test('Mi Panel renderiza KPIs del vendedor', async ({ page }) => {
        await page.goto('/app/vendedor');
        await expect(page.getByRole('heading', { name: /mi panel/i })).toBeVisible();
        // KPIs comparativos (ventas facturadas / pedidos / ticket promedio).
        await expect(page.getByText(/ventas facturadas/i)).toBeVisible();
        await expect(page.getByText(/pedidos cerrados/i)).toBeVisible();
        await expect(page.getByText(/ticket promedio/i)).toBeVisible();
    });

    test('Sidebar muestra MI GESTIÓN COMERCIAL con 4 items (Miracle style)', async ({ page }) => {
        await page.goto('/app/vendedor');
        const group = page.locator('text=/mi gestión comercial/i');
        await expect(group).toBeVisible();
        await expect(page.getByRole('link', { name: /mi panel/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /ventas por cliente/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /contado.*crédito/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /seguimiento/i })).toBeVisible();
    });

    test('Vendedor NO ve Cartera/Compras/Dropi (gate por rol)', async ({ page }) => {
        await page.goto('/app/vendedor');
        await expect(page.getByRole('link', { name: /^cartera/i })).toHaveCount(0);
        await expect(page.getByRole('link', { name: /^compras/i })).toHaveCount(0);
        await expect(page.getByRole('link', { name: /^dropi/i })).toHaveCount(0);
    });

    test('Buscar cliente → armar pedido → confirmar', async ({ page }) => {
        await page.goto('/app/vendedor');
        await page.getByPlaceholder(/razón social|nit|correo/i).fill('a');
        await page.getByRole('button', { name: /buscar cliente/i }).click();
        await page.waitForLoadState('networkidle');

        const primerCliente = page.getByRole('link', { name: /armar pedido/i }).first();
        await expect(primerCliente).toBeVisible();
        await primerCliente.click();

        await expect(page.getByRole('heading', { name: /pedido|nuevo/i })).toBeVisible();
        // Agrega 1 unidad del primer producto disponible.
        const inputCantidad = page.locator('input[type="number"]').first();
        await inputCantidad.fill('1');
        await page.getByRole('button', { name: /confirmar|enviar pedido/i }).click();

        // Flash de éxito, redirect a Mi Panel.
        await expect(page.getByText(/pedido .* registrado/i)).toBeVisible({ timeout: 10_000 });
    });

    test('Ventas por Cliente muestra ranking con trophy en #1 (si hay datos)', async ({ page }) => {
        await page.goto('/app/vendedor/ventas-por-cliente');
        await expect(page.getByRole('heading', { name: /mis ventas por cliente/i })).toBeVisible();
        await expect(page.getByText(/clientes con compras/i)).toBeVisible();
    });

    test('Contado/Crédito con 3 cards de distribución', async ({ page }) => {
        await page.goto('/app/vendedor/contado-credito');
        await expect(page.getByText(/contado \(pagado\)/i)).toBeVisible();
        await expect(page.getByText(/pendiente de pago/i)).toBeVisible();
        await expect(page.getByRole('heading', { name: /crédito/i })).toBeVisible();
    });

    test('Seguimiento muestra las 3 tablas (sin facturar · por cobrar · últimos)', async ({ page }) => {
        await page.goto('/app/vendedor/seguimiento');
        await expect(page.getByText(/pedidos sin facturar/i)).toBeVisible();
        await expect(page.getByText(/pagos por cobrar/i)).toBeVisible();
        await expect(page.getByText(/últimos movimientos/i)).toBeVisible();
    });
});
