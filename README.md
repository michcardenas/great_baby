# GREAT BABY · ERP

Sistema de gestión empresarial (ERP) para **GREAT BABY S.A.S.** (Bucaramanga, Colombia).
Desarrollado por **MyTech Solutions** — Innovación y Tecnología para tu Empresa.

## Stack

- **Laravel 12** (PHP 8.2+)
- **Filament v5.7.8** (panel administrativo)
- **Livewire 3** (interactividad)
- **MariaDB / MySQL 8**
- **DomPDF · Endroid QR · Reverb · Spatie Permission · Laravel Auditing**

## Módulos incluidos

| Módulo | Estado |
|---|---|
| Dropi (cortes, wallet, sanciones, devoluciones) | ✅ |
| Cartera (facturas, pagos, cobranza WhatsApp, aging) | ✅ |
| Compras (OC, recepciones, importaciones, manifiestos DIAN) | ✅ |
| Inventario (traslados, toma física, alertas, kardex) | ✅ |
| Contabilidad (panel + 9 reportes con exports) | ✅ |
| Catálogo (productos, variantes, códigos de barras, QR, etiquetas) | ✅ |
| SIIGO API (sync + emisión electrónica DIAN + QR Anexo 1.9) | ✅ |
| Estación de Empaque (pistola + foto + voz TTS + confeti) | ✅ |
| Torre de Control (KPIs + Chart.js + mapa Leaflet) | ✅ |
| Modo TV Bodega | ✅ |
| Configuración Empresa + Portal público de factura | ✅ |

## Instalación rápida (dev local)

```bash
git clone https://github.com/yepacode/GREAT_BABY_ERP.git
cd GREAT_BABY_ERP
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# configurar DB en .env
php artisan migrate --seed
php artisan storage:link
php artisan serve --port=8090
```

## Producción

Ver [`docs/DEPLOY.md`](docs/DEPLOY.md) para la guía completa de despliegue en servidor Ubuntu + Nginx + PHP-FPM + MySQL + Reverb + queue workers.

## Credenciales de prueba

Ver `database/seeders/UsuariosDemoSeeder.php` — usuarios ejemplo por rol (Aracely, Alistador, Gerente, Contador, Vendedor).

## Contacto

- **MyTech Solutions**
- 📍 Bogotá, Colombia
- 📞 +57 333 724 6403
- ✉️ contacto@mytechsolutions.com
- 🌐 www.mytechsolutionsco.com

## Licencia

Software propietario. © 2026 GREAT BABY S.A.S. · Desarrollado por MyTech Solutions.
