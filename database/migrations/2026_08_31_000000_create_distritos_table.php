<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            throw new LogicException('As migrations do recode nao podem alterar o PostgreSQL compartilhado do CCDAE.');
        }

        Schema::create('distritos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('cidade')->default('Caceres');
            $table->unsignedInteger('populacao')->default(0);
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->json('geojson');
            $table->timestamps();

            $table->unique(['cidade', 'nome']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            throw new LogicException('As migrations do recode nao podem alterar o PostgreSQL compartilhado do CCDAE.');
        }

        Schema::dropIfExists('distritos');
    }
};
