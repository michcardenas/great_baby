<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * F8 · persistir el kill-switch push_auto en la BD para que Aracely lo pueda
 * togglear desde el panel Vue sin tocar .env. Si es NULL, cae al valor de
 * FEATURE_SIIGO_PUSH_AUTO del .env (default false).
 *
 * Idempotente (Schema::hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('siigo_config', function ($table) {
            if (! Schema::hasColumn('siigo_config', 'push_auto')) {
                $table->boolean('push_auto')->nullable()->after('activo');
            }
            if (! Schema::hasColumn('siigo_config', 'push_auto_updated_at')) {
                $table->timestamp('push_auto_updated_at')->nullable();
            }
            if (! Schema::hasColumn('siigo_config', 'push_auto_updated_by')) {
                $table->foreignId('push_auto_updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('siigo_config', function ($table) {
            if (Schema::hasColumn('siigo_config', 'push_auto_updated_by')) $table->dropConstrainedForeignId('push_auto_updated_by');
            if (Schema::hasColumn('siigo_config', 'push_auto_updated_at')) $table->dropColumn('push_auto_updated_at');
            if (Schema::hasColumn('siigo_config', 'push_auto')) $table->dropColumn('push_auto');
        });
    }
};
