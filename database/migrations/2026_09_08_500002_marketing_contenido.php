<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ficha rich extendida al producto (copy comercial, keywords, USPs)
        Schema::table('productos', function (Blueprint $t) {
            $t->text('copy_comercial')->nullable()->after('descripcion');
            $t->text('specs_json')->nullable()->after('copy_comercial'); // JSON con specs {material:..., edad:..., etc}
            $t->string('keywords_seo', 500)->nullable()->after('specs_json');
            $t->text('beneficios')->nullable()->after('keywords_seo'); // USPs
        });

        // Parrilla de contenido / calendario de posts
        Schema::create('marketing_posts', function (Blueprint $t) {
            $t->id();
            $t->date('fecha_publicacion');
            $t->time('hora_publicacion')->nullable();
            $t->string('titulo', 200);
            $t->text('copy');
            $t->enum('canal', ['instagram', 'facebook', 'tiktok', 'whatsapp_status', 'email', 'web', 'otros']);
            $t->enum('tipo', ['post', 'reel', 'story', 'live', 'email', 'blog']);
            $t->enum('estado', ['idea', 'produccion', 'programado', 'publicado', 'cancelado'])->default('idea');
            $t->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->string('url_publicacion')->nullable();
            $t->text('notas')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['fecha_publicacion', 'canal']);
            $t->index(['estado']);
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->dropColumn(['copy_comercial', 'specs_json', 'keywords_seo', 'beneficios']);
        });
        Schema::dropIfExists('marketing_posts');
    }
};
