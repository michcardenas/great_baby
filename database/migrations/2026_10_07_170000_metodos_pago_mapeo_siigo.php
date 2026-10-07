<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada método de pago apunta a su tipo de pago en SIIGO.
 *
 * Hasta ahora el recibo de caja salía SIEMPRE con el mismo
 * `siigo.payment_type_recibo`, sin importar cómo había pagado el cliente: una
 * transferencia a Bancolombia y un pago en efectivo llegaban a SIIGO como el
 * mismo tipo. El medio real sólo quedaba como texto suelto en las
 * observaciones, así que la conciliación de bancos y caja en SIIGO no cuadra
 * con la realidad.
 *
 * Con esto, el método que se elige al registrar el pago decide a qué tipo de
 * SIIGO entra. Queda nullable: si no está mapeado se usa el global de siempre,
 * para no romper lo que ya funciona mientras se termina de configurar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metodos_pago', function (Blueprint $t) {
            if (! Schema::hasColumn('metodos_pago', 'siigo_payment_type_id')) {
                $t->unsignedInteger('siigo_payment_type_id')->nullable()->after('cuenta_puc');
            }
            if (! Schema::hasColumn('metodos_pago', 'siigo_payment_type_nombre')) {
                // Se guarda el nombre junto al id para que la pantalla muestre
                // a qué apunta sin tener que llamar a SIIGO en cada carga.
                $t->string('siigo_payment_type_nombre', 120)->nullable()->after('siigo_payment_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('metodos_pago', function (Blueprint $t) {
            foreach (['siigo_payment_type_id', 'siigo_payment_type_nombre'] as $c) {
                if (Schema::hasColumn('metodos_pago', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
