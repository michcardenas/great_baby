# TEST-S8/S9 · E2E Playwright

Suite E2E que valida los dos flujos más críticos del ERP contra el servidor
real (no mocks):

- **S8 · [vendedor.spec.ts](specs/vendedor.spec.ts)** · camino del vendedor
  en terreno: login → Mi Panel (Miracle style) → armar pedido → confirmar.
- **S9 · [don-jorge.spec.ts](specs/don-jorge.spec.ts)** · camino de bodega:
  AdminBodega → Cola de alistamiento (4 columnas) → picking → despacho +
  verificación del semáforo SIIGO en Contabilidad.

## Setup inicial (una vez)

```bash
cd tests/playwright
npm init -y
npm i -D @playwright/test
npx playwright install chromium
```

## Correr

```bash
# ERP debe estar arriba en http://127.0.0.1:8090
# (XAMPP MySQL activo, php artisan serve corriendo, npm run build hecho)

cd tests/playwright
npx playwright test                     # todos los specs
npx playwright test vendedor            # solo S8
npx playwright test don-jorge           # solo S9
npx playwright test --ui                # modo visual (debug)
npx playwright show-report              # reporte HTML del último run
```

## Variables

| var | default | uso |
|---|---|---|
| `ERP_URL` | `http://127.0.0.1:8090` | base URL del ERP |
| `CI` | — | activa retries×2 + `forbidOnly` |

## Datos requeridos (seed demo)

Si el seed demo está cargado, estos usuarios existen con password `demo`:

- `aracely@greatbaby.com` (rol Aracely)
- `vendedor.demo@greatbaby.com` (rol Vendedor)
- `admin.bodega.bog@greatbaby.com` (rol AdminBodega)
- `alistador.bog@greatbaby.com` (rol Alistador)
- `marketing.demo@greatbaby.com` (rol Marketing)

Si falta alguno, cargalo con `php artisan db:seed --class=DemoSeeder`.

## Reportes

HTML en `storage/playwright-report/index.html` (lo abre `npx playwright
show-report`). Trazas, screenshots y videos solo se guardan **al fallar**
(ver `playwright.config.ts`).

## Notas

- `fullyParallel: false` y `workers: 1` porque los specs tocan BD y nos
  pisamos nosotros mismos al paralelizar.
- Los specs usan `test.skip()` si no hay datos de prueba para esa ruta —
  son defensivos, no fallan por seed vacío.
- Para CI (GitHub Actions), basta `npx playwright test --reporter=html` y
  subir `storage/playwright-report` como artefacto.
