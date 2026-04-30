<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['banner', 'sponsored_product', 'featured_shop']);
            $table->string('image_url')->nullable();
            $table->string('target_url')->nullable(); // Lien vers produit ou boutique
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('amount_paid')->comment('Montant payé en FCFA');
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
