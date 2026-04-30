<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('subtotal', 10, 0);
            $table->decimal('shipping_cost', 10, 0)->default(0);
            $table->decimal('total_amount', 10, 0);
            $table->string('delivery_address');
            $table->string('delivery_phone');
            $table->enum('status', [
                'pending',           // commande créée
                'confirmed',         // admin a confirmé
                'preparing',         // commerçant prépare
                'assigned',          // livreur assigné
                'delivering',        // en cours de livraison
                'client_validated',  // client a validé
                'driver_validated',  // livreur a validé
                'delivered',         // livraison complète
                'cancelled',         // annulée
                'disputed'           // litige
            ])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
