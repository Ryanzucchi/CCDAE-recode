<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') throw new LogicException('Não alterar o PostgreSQL compartilhado por migrations do recode.');
        Schema::table('distritos', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('consumo_medio_kwh', 14, 2)->nullable();
            $table->string('populacao_fonte')->nullable();
            $table->timestamp('populacao_atualizada_em')->nullable();
        });
    }
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') throw new LogicException('Não alterar o PostgreSQL compartilhado.');
        Schema::table('distritos', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude', 'consumo_medio_kwh', 'populacao_fonte', 'populacao_atualizada_em']));
    }
};
