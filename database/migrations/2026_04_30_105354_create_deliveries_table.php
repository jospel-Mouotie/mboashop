<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('pin', 6);
            $table->timestamp('pin_expires_at');
            $table->integer('pin_attempts')->default(0);

            // Validation client (client donne le code)
            $table->boolean('client_validated')->default(false);
            $table->timestamp('client_validated_at')->nullable();

            // Validation livreur (livreur entre le code)
            $table->boolean('driver_validated')->default(false);
            $table->timestamp('driver_validated_at')->nullable();

            // Preuve et localisation
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->string('proof_photo')->nullable();

            // Reminder (relance)
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('last_reminder_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
