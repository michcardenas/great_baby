# C-F10 · Deploy staging + rollout producción (Hostinger)

**Pre-condición:** C-F9 verde (checklist completo sin bloqueantes).
**Dueña:** Yeniffer (MyTech) con Aracely conectada por WhatsApp.
**Ventana:** martes o miércoles 09:00–11:00 Bogotá (días calmos de ventas).
**Rollback target:** < 5 min desde que algo rompe.

---

## 0 · Preparación local (día antes)

```bash
cd /c/xampp/htdocs/greatbaby-erp
# 1. Rama limpia desde main
git status                                    # debe estar limpio
git fetch origin && git log main..HEAD --oneline

# 2. Build productivo
php artisan optimize:clear
npm ci
npm run build

# 3. Suite tests
php artisan test --parallel

# 4. Snapshot BD de prod (del backup Hostinger)
# descargar último dump desde Hostinger → carpeta backups/prod-YYYY-MM-DD.sql
```

**Checklist pre-deploy:**
- [ ] `.env.production` tiene llaves SIIGO reales (no sandbox).
- [ ] `.env.production` tiene `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] `.env.production` tiene credenciales MySQL de Hostinger.
- [ ] Mail SMTP (SendGrid/Resend) configurado y verificado.
- [ ] WhatsApp provider con número verde activo.
- [ ] Feature flags iniciales: `FEATURE_SIIGO_PUSH_AUTO=false` (apagado al
      inicio para controlar el flujo manualmente las primeras 48h).

---

## 1 · Deploy staging (gokinvoo.com estilo, subdominio)

Subdominio sugerido: `staging-erp.greatbaby.com.co` apuntado al mismo
servidor Hostinger con directorio propio.

### 1.1 · Subir archivos

```bash
# Opción A · SSH + git pull (preferida)
ssh u284700479@host.hostinger.com
cd ~/domains/staging-erp.greatbaby.com.co/public_html
git pull origin main
```

```bash
# Opción B · rsync si no hay git en el server
rsync -avz --exclude='node_modules' --exclude='.git' --exclude='storage/logs/*' \
    ./ u284700479@host.hostinger.com:~/domains/staging-erp.greatbaby.com.co/public_html/
```

### 1.2 · Dependencias y build en el server

```bash
composer install --no-dev --optimize-autoloader
# node_modules: subir build local, NO instalar en server (Hostinger no soporta
# bien npm en share). Vite build local → subir solo `public/build/`.
```

### 1.3 · Migraciones (CUIDADO con prod · siempre con backup fresco)

```bash
# Dry run · lista qué va a correr
php artisan migrate --pretend

# Para STAGING · correr directo
php artisan migrate --force

# Para PROD · solo tras OK de staging + Aracely + backup confirmado
php artisan migrate --force --step
```

### 1.4 · Cache + optimize

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### 1.5 · Permisos de carpetas (Hostinger-safe)

```bash
chmod -R 775 storage bootstrap/cache
chown -R u284700479:u284700479 storage bootstrap/cache
```

### 1.6 · Supervisor para la cola SIIGO

Archivo `/etc/supervisor/conf.d/greatbaby-queue.conf` (pedírselo a Hostinger
support, requiere ticket):

```ini
[program:greatbaby-queue-siigo]
process_name=%(program_name)s_%(process_num)02d
command=php /home/u284700479/.../artisan queue:work --queue=siigo --tries=1 --timeout=90
autostart=true
autorestart=true
user=u284700479
numprocs=2
redirect_stderr=true
stdout_logfile=/home/u284700479/logs/queue-siigo.log
stopwaitsecs=3600
```

Si Hostinger no permite supervisor en el plan, alternativa:
```bash
# Cron cada minuto que lanza el worker (menos robusto, pero funciona)
* * * * * cd /home/u.../public_html && php artisan queue:work --queue=siigo --max-time=55 --once
```

### 1.7 · Cron del scheduler

```bash
# En Hostinger panel · Cron Jobs · agregar:
* * * * * cd /home/u284700479/domains/.../public_html && php artisan schedule:run >> /dev/null 2>&1
```

### 1.8 · Humo (smoke manual)

```bash
curl -I https://staging-erp.greatbaby.com.co                   # 200/302
curl -I https://staging-erp.greatbaby.com.co/app/login         # 200
php artisan siigo:smoke-test                                   # 10/10 OK
```

### 1.9 · Validación con Aracely en staging (3 días)

- Día 1 · navegar los 7 roles con cuentas reales de prod (pedir rol dummy).
- Día 2 · crear 3 pedidos reales + 1 factura + verificar llegan a SIIGO.
- Día 3 · verificar `siigo:comparador-diario` genera reporte sin discrepancias.

Go/no-go para producción → **Aracely firma por WhatsApp.**

---

## 2 · Rollout producción (ventana 09:00–11:00)

### 2.1 · Backup pre-deploy (CRÍTICO)

```bash
# Dump completo BD prod
mysqldump -u u284700479_gb -p u284700479_gb > ~/backups/gb-pre-deploy-$(date +%Y%m%d-%H%M).sql
# Tar de public/ para rollback rápido
tar -czf ~/backups/gb-public-$(date +%Y%m%d-%H%M).tar.gz /home/u.../public_html/
```

### 2.2 · Modo mantenimiento

```bash
php artisan down --refresh=15 --retry=60 \
    --render="errors::503" \
    --secret="aracely-bypass-2026"
# Aracely accede con /aracely-bypass-2026 para probar antes de abrir a todos.
```

### 2.3 · Deploy (idéntico a staging)

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
# Subir public/build/ pre-compilado
php artisan migrate --force --step
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart           # fuerza supervisor a recargar workers
```

### 2.4 · Humo post-deploy (3 min)

```bash
php artisan siigo:smoke-test                              # 10/10
curl -I https://greatbaby.com.co/app/login                # 200
# Aracely entra con bypass secret y:
#   - revisa dashboard
#   - abre 1 pedido real
#   - verifica semáforo SIIGO verde
```

### 2.5 · Levantar mantenimiento

```bash
php artisan up
```

### 2.6 · Monitoreo primeras 2h

- Tail de logs: `tail -f storage/logs/laravel.log`
- Dashboard Hostinger: CPU/RAM/disk.
- `/app/siigo` KPIs del sync no explotan.
- Aracely + Yeniffer online por WhatsApp.

---

## 3 · Rollback (si algo revienta)

Objetivo: **≤ 5 min** desde que se detecta el problema.

```bash
# 1. Modo mantenimiento inmediato
php artisan down

# 2. Revertir código (si fue por archivos)
git reset --hard <commit-anterior-conocido-bueno>

# 3. Si rompió BD → restaurar dump
mysql -u u284700479_gb -p u284700479_gb < ~/backups/gb-pre-deploy-YYYYMMDD-HHMM.sql

# 4. Cache + up
php artisan optimize:clear
php artisan up
```

**Comunicación:**
- WhatsApp a Aracely: "revirtiendo deploy, 5 min para reabrir".
- Nota post-mortem en `docs/incidentes/YYYY-MM-DD.md` dentro de 24h.

---

## 4 · Post-deploy (primeras 72h)

- [ ] Monitoreo cada 2h el primer día (eyeball + logs).
- [ ] Siigo:comparador-diario corrió a las 03:45 y reporte en 0.
- [ ] WhatsApp a los 7 roles con "¿todo OK?"
- [ ] Pasar `FEATURE_SIIGO_PUSH_AUTO=true` cuando Aracely dé OK (día 3).
- [ ] Cerrar C-F10 en el plan + abrir C-F11 (monitoreo semana 1).

---

## 5 · Credenciales que faltan por confirmar con el cliente

- [ ] Credenciales MySQL de prod Hostinger (host, user, pass).
- [ ] Llaves SIIGO prod (sandbox usada hasta ahora).
- [ ] SMTP provider para correos transaccionales (SendGrid/Resend/Mailgun).
- [ ] Dominio final: `greatbaby.com.co` o `erp.greatbaby.com.co`.
- [ ] Certificado SSL (Hostinger auto-renueva Let's Encrypt).
- [ ] Access FTP/SSH para Yeniffer.

Hasta tener los 6 ítems → NO se puede ejecutar C-F10 ni para staging.
