<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('vehicle_type')->nullable(); // Moto, vélo, voiture
            $table->string('license_plate')->nullable();
            $table->string('id_card')->nullable(); // Carte d'identité
            $table->enum('status', ['pending', 'active', 'inactive', 'blocked'])->default('pending');
            $table->boolean('is_online')->default(false);
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('total_deliveries')->default(0);
            $table->decimal('total_earnings', 10, 0)->default(0);
            $table->decimal('current_balance', 10, 0)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
