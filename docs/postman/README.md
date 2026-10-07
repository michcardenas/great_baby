# TEST-S10 · Postman SIIGO Sandbox

Colección para hacer QA manual contra el sandbox de SIIGO sin tocar el ERP.

## Cómo importar

1. Postman → **Import** → arrastra los dos archivos:
   - `GreatBaby_SIIGO_Sandbox.postman_collection.json`
   - `GreatBaby_SIIGO_Sandbox.postman_environment.json`
2. En la esquina superior derecha, selecciona el env **"GB · SIIGO Sandbox"**.
3. Edita el env y reemplaza:
   - `siigo_access_key` → la access key del sandbox (pedírsela al ejecutivo
     SIIGO; viene cuando activan el partner GreatBaby en sandbox).
   - `siigo_user` → el usuario sandbox del ejecutivo (default
     `sandbox@siigoapi.com`).
   - `partner_id` → debe ser `GreatBaby` (el que firmamos en el convenio).

## Orden de ejecución

Corré las carpetas en orden: `0 · Auth` → `1 · Products` → … → `6 · Journals`.

- **0 · Auth** guarda el `access_token` en la env automáticamente (test
  script). Si falla, revisá `siigo_access_key` y que la IP esté
  whitelisteada en el panel de SIIGO.
- **1 · Products · POST** crea un producto con código `GB-QA-{timestamp}` y
  guarda el ID en `last_product_id` para la siguiente request.

## Qué no está en la colección (porque muta datos reales en sandbox)

- POST a `/v1/invoices` (crear FV) — se puede, pero genera factura
  electrónica en DIAN sandbox que queda registrada. Usar solo cuando el
  ejecutivo lo pida.
- POST a `/v1/credit-notes` — mismo caso.
- POST a `/v1/journals` — mismo caso.

Si necesitás crearlos manualmente, el ERP ya los hace desde los flujos
reales (`/app/cartera/facturas`, `/app/cartera/notas-credito`,
`/app/contabilidad/asientos-manuales`).

## Debugging rápido

- **401 Unauthorized** → token vencido. Volvé a correr `0 · Auth`.
- **403 Forbidden** → el `partner_id` del header no coincide con el
  convenio. Revisá env.
- **422 Unprocessable Entity** → el body no cumple el schema SIIGO. Mirá
  el response, dice qué campo falta.
- **429 Too Many Requests** → esperá 60s, SIIGO limita a 10 req/seg.
