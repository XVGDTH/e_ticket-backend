<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports_billets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_transport_id')->constrained();
            $table->date('ancienne_date');
            $table->string('ancienne_heure', 5);
            $table->date('nouvelle_date');
            $table->string('nouvelle_heure', 5);
            $table->timestamps();
            $table->index(['route_transport_id', 'ancienne_date', 'ancienne_heure'], 'reports_depart_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports_billets');
    }
};
